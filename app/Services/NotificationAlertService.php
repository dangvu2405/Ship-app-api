<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CostApprovalRequest;
use App\Models\DriverDocument;
use App\Models\User;
use App\Models\VehicleDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class NotificationAlertService
{
    public function dispatchDueAlerts(int $withinDays = 7): int
    {
        $created = 0;
        $threshold = now()->addDays($withinDays)->endOfDay();

        $driverDocs = DriverDocument::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $threshold->toDateString())
            ->get();
        foreach ($driverDocs as $document) {
            foreach ($this->recipientUsers((int) $document->company_id) as $user) {
                $this->pushNotification($user, [
                    'type' => 'driver_document_expiry',
                    'driver_document_id' => $document->id,
                    'driver_id' => $document->driver_id,
                    'expiry_date' => optional($document->expiry_date)->toDateString(),
                ]);
                $created++;
            }
        }

        $vehicleDocs = VehicleDocument::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $threshold->toDateString())
            ->get();
        foreach ($vehicleDocs as $document) {
            foreach ($this->recipientUsers((int) $document->company_id) as $user) {
                $this->pushNotification($user, [
                    'type' => 'vehicle_document_expiry',
                    'vehicle_document_id' => $document->id,
                    'vehicle_id' => $document->vehicle_id,
                    'expiry_date' => optional($document->expiry_date)->toDateString(),
                ]);
                $created++;
            }
        }

        $approvalRequests = CostApprovalRequest::query()
            ->where('status', 'pending')
            ->get();
        foreach ($approvalRequests as $approvalRequest) {
            foreach ($this->recipientUsers((int) $approvalRequest->company_id) as $user) {
                $this->pushNotification($user, [
                    'type' => 'cost_approval_required',
                    'approval_request_id' => $approvalRequest->id,
                    'trip_id' => $approvalRequest->trip_id,
                    'total_amount' => (float) $approvalRequest->total_amount,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return Collection<int, User> */
    private function recipientUsers(int $companyId): Collection
    {
        $query = User::query()->where('status', 'active');
        if ($companyId > 0 && Schema::hasColumn('users', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $users = $query->get();

        return $users->filter(function (User $user): bool {
            $role = strtolower((string) ($user->role ?? ''));
            if (in_array($role, ['admin', 'dispatcher'], true)) {
                return true;
            }

            if (method_exists($user, 'roles')) {
                return $user->roles()->whereIn('name', ['admin', 'dispatcher'])->exists();
            }

            return false;
        })->values();
    }

    /** @param array<string, mixed> $payload */
    private function pushNotification(User $user, array $payload): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'data' => $payload,
        ]);
    }
}
