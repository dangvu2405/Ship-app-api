<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        $users = User::query()->paginate(15); // Rely on tenant.context scoping/policies

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'username' => 'nullable|string|max:255|unique:users',
            'status' => 'nullable|string|in:active,inactive',
            'employee_id' => 'nullable|integer',
            'role_ids' => 'nullable|array',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:500',
            'avatar_url' => 'nullable|string',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'username' => $validated['username'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'employee_id' => $validated['employee_id'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'residential_address' => $validated['residential_address'] ?? null,
                'avatar_url' => $validated['avatar_url'] ?? null,
            ]);
            if (!empty($validated['role_ids'])) {
                $user->roles()->sync($validated['role_ids']);
            }
            return $user;
        });

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): JsonResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(Request $request, User $user): JsonResource
    {
        $this->authorize('update', $user);

        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8',
            'username' => 'sometimes|nullable|string|max:255|unique:users,username,' . $user->id,
            'status' => 'sometimes|required|string|in:active,inactive',
            'employee_id' => 'nullable|integer',
            'role_ids' => 'nullable|array',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:500',
            'avatar_url' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $user) {
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }
            if (isset($validated['role_ids'])) {
                $user->roles()->sync($validated['role_ids']);
                unset($validated['role_ids']);
            }
            $user->update($validated);
        });

        return new UserResource($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, User $user): JsonResource
    {
        $validated = $request->validate(['status' => 'required|string|in:active,inactive']);
        $user->update(['status' => $validated['status']]);
        return new UserResource($user);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $user->update(['password' => Hash::make('password')]);
        return response()->json(['success' => true, 'message' => 'Password reset to default']);
    }

    public function permissions(Request $request, User $user): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'modules' => [['key' => 'trips', 'label' => 'Chuyến'], ['key' => 'customers', 'label' => 'Khách hàng'], ['key' => 'vehicles', 'label' => 'Phương tiện'], ['key' => 'drivers', 'label' => 'Tài xế'], ['key' => 'invoices', 'label' => 'Hóa đơn'], ['key' => 'reports', 'label' => 'Báo cáo'], ['key' => 'settings', 'label' => 'Cấu hình']],
                    'actions' => [['key' => 'view', 'label' => 'Xem'], ['key' => 'create', 'label' => 'Tạo'], ['key' => 'update', 'label' => 'Sửa'], ['key' => 'delete', 'label' => 'Xóa'], ['key' => 'approve', 'label' => 'Duyệt'], ['key' => 'export', 'label' => 'Xuất']],
                    'granted' => $user->user_permissions ?? new \stdClass()
                ]
            ]);
        }
        $validated = $request->validate(['permissions' => 'required|array']);
        $user->update(['user_permissions' => $validated['permissions']]);
        return response()->json(['success' => true, 'message' => 'Permissions updated']);
    }
}
