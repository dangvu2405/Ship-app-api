<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Throwable;

/**
 * Laravel `notifications` table — danh sách / đếm chưa đọc / đánh dấu đọc (tương thích FE ActivityLog).
 */
class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
            $page = max(1, (int) $request->query('page', 1));

            $query = $request->user()->notifications()->orderByDesc('created_at');

            if ($request->query('read') === '0' || $request->query('read') === 'false') {
                $query->whereNull('read_at');
            } elseif ($request->query('read') === '1' || $request->query('read') === 'true') {
                $query->whereNotNull('read_at');
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $items = $paginator->getCollection()->map(fn (DatabaseNotification $n) => $this->toActivityPayload($n))->values()->all();

            return $this->successResponse([
                'data' => $items,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ], 'api.common.ok');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = $request->user()->unreadNotifications()->count();

            return $this->successResponse(['count' => $count], 'api.common.ok');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        try {
            /** @var DatabaseNotification|null $notification */
            $notification = $request->user()->notifications()->where('id', $id)->first();
            if ($notification === null) {
                return $this->notFoundResponse('api.notification.not_found');
            }

            if ($notification->read_at === null) {
                $notification->markAsRead();
                $notification->refresh();
            }

            return $this->successResponse($this->toActivityPayload($notification), 'api.notification.marked_read');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $request->user()->unreadNotifications->markAsRead();

            return $this->successResponse(null, 'api.notification.all_marked_read');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toActivityPayload(DatabaseNotification $n): array
    {
        $raw = $n->data;
        $data = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        $laravelType = (string) $n->type;
        $allowed = ['create', 'update', 'delete', 'system', 'user'];
        $fromData = isset($data['type']) && is_string($data['type']) ? $data['type'] : null;
        $uiType = ($fromData !== null && in_array($fromData, $allowed, true))
            ? $fromData
            : $this->inferUiType($laravelType);

        $description = (string) ($data['body'] ?? $data['message'] ?? $data['title'] ?? $data['description'] ?? __('Notification'));

        return [
            'id' => $n->id,
            'type' => $uiType,
            'resource' => (string) ($data['resource'] ?? 'user'),
            'resource_id' => isset($data['resource_id']) ? (int) $data['resource_id'] : null,
            'action' => (string) ($data['action'] ?? 'updated'),
            'description' => $description,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'user_name' => isset($data['user_name']) ? (string) $data['user_name'] : null,
            'created_at' => $n->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'read' => $n->read_at !== null,
        ];
    }

    private function inferUiType(string $laravelType): string
    {
        if (str_contains($laravelType, 'SystemNotification')) {
            return 'system';
        }

        if (str_contains($laravelType, 'User') || str_contains($laravelType, 'Mention')) {
            return 'user';
        }

        return 'system';
    }
}
