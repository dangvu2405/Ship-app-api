<?php

declare(strict_types=1);

namespace App\Services\Lark;

use App\Models\Employee;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LarkBaseSyncService
{
    public function __construct(private readonly LarkTokenService $tokenService) {}

    /**
     * @return array{fetched: int, updated: int, skipped: int, failed: int}
     */
    public function reverseSyncEmployees(int $limit = 200): array
    {
        $tableId = (string) config('lark.base.employees_table_id');

        return $this->reverseSync(
            $tableId,
            $limit,
            fn (array $fields): string => $this->applyEmployeeReverseSync($fields)
        );
    }

    /**
     * @return array{fetched: int, updated: int, skipped: int, failed: int}
     */
    public function reverseSyncTrips(int $limit = 200): array
    {
        $tableId = (string) config('lark.base.trips_table_id');

        return $this->reverseSync(
            $tableId,
            $limit,
            fn (array $fields): string => $this->applyTripReverseSync($fields)
        );
    }

    public function syncEmployee(Employee $employee): void
    {
        $this->upsertRecord(
            (string) config('lark.base.employees_table_id'),
            'EmployeeID',
            (string) $employee->id,
            [
                'EmployeeID' => (string) $employee->id,
                'Code' => (string) $employee->code,
                'Name' => (string) $employee->name,
                'Email' => (string) $employee->email,
                'Phone' => (string) $employee->phone,
                'Type' => (string) $employee->type,
                'Status' => (string) $employee->status,
            ]
        );
    }

    public function syncTrip(Trip $trip): void
    {
        $this->upsertRecord(
            (string) config('lark.base.trips_table_id'),
            'TripID',
            (string) $trip->id,
            [
                'TripID' => (string) $trip->id,
                'Code' => (string) $trip->code,
                'DriverID' => (string) $trip->driver_id,
                'VehicleID' => (string) $trip->vehicle_id,
                'StartPoint' => (string) $trip->start_point,
                'EndPoint' => (string) $trip->end_point,
                'DistanceKm' => (float) $trip->distance_km,
                'Price' => (float) $trip->price,
                'Status' => (string) $trip->status,
            ]
        );
    }

    public function reverseSyncAllowed(): bool
    {
        return (bool) config('lark.base.enable_reverse_sync', false);
    }

    /**
     * @param  callable(array<string, mixed>): string  $applyHandler
     * @return array{fetched: int, updated: int, skipped: int, failed: int}
     */
    private function reverseSync(string $tableId, int $limit, callable $applyHandler): array
    {
        $stats = [
            'fetched' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if ($limit < 1 || $tableId === '' || ! $this->reverseSyncAllowed()) {
            return $stats;
        }

        $records = $this->fetchRecords($tableId, $limit);
        $stats['fetched'] = count($records);

        foreach ($records as $record) {
            $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];

            try {
                $result = $applyHandler($fields);
                if (isset($stats[$result])) {
                    $stats[$result]++;

                    continue;
                }

                $stats['failed']++;
            } catch (Throwable $e) {
                $stats['failed']++;

                try {
                    Log::warning('Lark Base reverse sync item failed', [
                        'table_id' => $tableId,
                        'fields' => $fields,
                        'error' => $e->getMessage(),
                    ]);
                } catch (Throwable) {
                    // Ignore logging errors during reverse sync.
                }
            }
        }

        return $stats;
    }

    private function upsertRecord(string $tableId, string $keyField, string $keyValue, array $fields): void
    {
        $appToken = (string) config('lark.base.app_token');
        if ($tableId === '' || $appToken === '') {
            return;
        }

        $client = Http::timeout(10)
            ->retry(3, 250)
            ->withToken($this->tokenService->getTenantAccessToken());

        $baseUrl = sprintf('https://open.larksuite.com/open-apis/bitable/v1/apps/%s/tables/%s/records', $appToken, $tableId);
        $existingRecordId = $this->findRecordIdByKey($client, $baseUrl, $keyField, $keyValue);

        $response = $existingRecordId
            ? $client->put(sprintf('%s/%s', $baseUrl, $existingRecordId), ['fields' => $fields])
            : $client->post($baseUrl, ['fields' => $fields]);

        if (! $response->ok() || (int) $response->json('code', -1) !== 0) {
            try {
                Log::error('Lark Base sync failed', [
                    'table_id' => $tableId,
                    'key_field' => $keyField,
                    'key_value' => $keyValue,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            } catch (Throwable) {
                // Keep sync failure behavior deterministic even when log sink is unavailable.
            }

            throw new RuntimeException('Lark Base sync failed.');
        }
    }

    private function findRecordIdByKey($client, string $baseUrl, string $keyField, string $keyValue): ?string
    {
        $response = $client->post($baseUrl.'/search', [
            'filter' => [
                'conjunction' => 'and',
                'conditions' => [
                    [
                        'field_name' => $keyField,
                        'operator' => 'is',
                        'value' => [$keyValue],
                    ],
                ],
            ],
            'page_size' => 1,
        ]);

        if (! $response->ok() || (int) $response->json('code', -1) !== 0) {
            return null;
        }

        $items = $response->json('data.items', []);

        return is_array($items) && isset($items[0]['record_id']) ? (string) $items[0]['record_id'] : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecords(string $tableId, int $limit): array
    {
        $appToken = (string) config('lark.base.app_token');
        if ($tableId === '' || $appToken === '' || $limit < 1) {
            return [];
        }

        $client = Http::timeout(10)
            ->retry(3, 250)
            ->withToken($this->tokenService->getTenantAccessToken());

        $baseUrl = sprintf('https://open.larksuite.com/open-apis/bitable/v1/apps/%s/tables/%s/records', $appToken, $tableId);

        /** @var list<array<string, mixed>> $records */
        $records = [];
        $nextPageToken = '';
        $hasMore = true;

        while ($hasMore && count($records) < $limit) {
            $pageSize = min(100, $limit - count($records));
            if ($pageSize < 1) {
                break;
            }

            $query = ['page_size' => $pageSize];
            if ($nextPageToken !== '') {
                $query['page_token'] = $nextPageToken;
            }

            $response = $client->get($baseUrl, $query);
            if (! $response->ok() || (int) $response->json('code', -1) !== 0) {
                throw new RuntimeException('Failed to fetch Lark Base records.');
            }

            $items = $response->json('data.items', []);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $records[] = $item;
                    if (count($records) >= $limit) {
                        break;
                    }
                }
            }

            $hasMore = (bool) $response->json('data.has_more', false);
            $nextPageToken = (string) $response->json('data.page_token', '');
            if ($hasMore && $nextPageToken === '') {
                break;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return 'updated'|'skipped'|'failed'
     */
    private function applyEmployeeReverseSync(array $fields): string
    {
        $employeeId = $this->extractIntField($fields, 'EmployeeID');
        $code = $this->extractStringField($fields, 'Code');

        $employee = null;
        if ($employeeId !== null) {
            $employee = Employee::find($employeeId);
        }
        if (! $employee && $code !== '') {
            $employee = Employee::where('code', $code)->first();
        }
        if (! $employee) {
            return 'skipped';
        }

        $updates = [];

        if ($code !== '' && $employee->code !== $code) {
            $updates['code'] = $code;
        }

        $name = $this->extractStringField($fields, 'Name');
        if ($name !== '' && $employee->name !== $name) {
            $updates['name'] = $name;
        }

        $email = strtolower($this->extractStringField($fields, 'Email'));
        if ($email !== '' && $employee->email !== $email) {
            $updates['email'] = $email;
        }

        $phone = $this->extractStringField($fields, 'Phone');
        if ($phone !== '' && $employee->phone !== $phone) {
            $updates['phone'] = $phone;
        }

        $type = $this->mapEmployeeType($this->extractStringField($fields, 'Type'));
        if ($type !== null && $employee->type !== $type) {
            $updates['type'] = $type;
        }

        $status = $this->mapEmployeeStatus($this->extractStringField($fields, 'Status'));
        if ($status !== null && $employee->status !== $status) {
            $updates['status'] = $status;
        }

        if ($updates === []) {
            return 'skipped';
        }

        $updated = Employee::withoutEvents(function () use ($employee, $updates): bool {
            $employee->fill($updates);
            if (! $employee->isDirty()) {
                return false;
            }

            return $employee->save();
        });

        return $updated ? 'updated' : 'skipped';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return 'updated'|'skipped'|'failed'
     */
    private function applyTripReverseSync(array $fields): string
    {
        $tripId = $this->extractIntField($fields, 'TripID');
        $code = $this->extractStringField($fields, 'Code');

        $trip = null;
        if ($tripId !== null) {
            $trip = Trip::find($tripId);
        }
        if (! $trip && $code !== '') {
            $trip = Trip::where('code', $code)->first();
        }
        if (! $trip) {
            return 'skipped';
        }

        $updates = [];

        if ($code !== '' && $trip->code !== $code) {
            $updates['code'] = $code;
        }

        $startPoint = $this->extractStringField($fields, 'StartPoint');
        if ($startPoint !== '' && $trip->start_point !== $startPoint) {
            $updates['start_point'] = $startPoint;
        }

        $endPoint = $this->extractStringField($fields, 'EndPoint');
        if ($endPoint !== '' && $trip->end_point !== $endPoint) {
            $updates['end_point'] = $endPoint;
        }

        $distanceKm = $this->extractFloatField($fields, 'DistanceKm');
        if ($distanceKm !== null && (float) $trip->distance_km !== $distanceKm) {
            $updates['distance_km'] = $distanceKm;
        }

        $price = $this->extractFloatField($fields, 'Price');
        if ($price !== null && (float) $trip->price !== $price) {
            $updates['price'] = $price;
        }

        $status = $this->mapTripStatus($this->extractStringField($fields, 'Status'));
        if ($status !== null && $trip->status !== $status) {
            $updates['status'] = $status;
        }

        $driverId = $this->extractIntField($fields, 'DriverID');
        if ($driverId !== null && Employee::whereKey($driverId)->exists() && (int) $trip->driver_id !== $driverId) {
            $updates['driver_id'] = $driverId;
        }

        $vehicleId = $this->extractIntField($fields, 'VehicleID');
        if ($vehicleId !== null && Vehicle::whereKey($vehicleId)->exists() && (int) $trip->vehicle_id !== $vehicleId) {
            $updates['vehicle_id'] = $vehicleId;
        }

        if ($updates === []) {
            return 'skipped';
        }

        $updated = Trip::withoutEvents(function () use ($trip, $updates): bool {
            $trip->fill($updates);
            if (! $trip->isDirty()) {
                return false;
            }

            return $trip->save();
        });

        return $updated ? 'updated' : 'skipped';
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function extractStringField(array $fields, string $key): string
    {
        if (! array_key_exists($key, $fields)) {
            return '';
        }

        $value = $this->extractScalar($fields[$key]);
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function extractIntField(array $fields, string $key): ?int
    {
        if (! array_key_exists($key, $fields)) {
            return null;
        }

        $value = $this->extractScalar($fields[$key]);
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function extractFloatField(array $fields, string $key): ?float
    {
        if (! array_key_exists($key, $fields)) {
            return null;
        }

        $value = $this->extractScalar($fields[$key]);
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function extractScalar(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return null;
        }

        $first = $value[0] ?? null;
        if (is_scalar($first) || $first === null) {
            return $first;
        }

        if (is_array($first)) {
            foreach (['text', 'name', 'value'] as $candidateKey) {
                if (isset($first[$candidateKey]) && is_scalar($first[$candidateKey])) {
                    return $first[$candidateKey];
                }
            }
        }

        return null;
    }

    private function mapEmployeeType(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'office', 'driver' => $normalized,
            default => null,
        };
    }

    private function mapEmployeeStatus(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'active', 'inactive', 'resigned' => $normalized,
            default => null,
        };
    }

    private function mapTripStatus(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'pending', 'in_progress', 'completed', 'cancelled' => $normalized,
            default => null,
        };
    }
}
