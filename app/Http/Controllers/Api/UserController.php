<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'username', 'email', 'status', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['employee', 'roles']);
        $result = $this->indexQuery($request, $query, ['username', 'email'], ['status' => 'status']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $user->makeHidden(['password']);

        return $this->successResponse($user->load(['employee', 'roles']), 'User created successfully', 201);
    }

    public function show(string $user): JsonResponse
    {
        $model = User::with(['employee', 'roles.permissions'])->find($user);
        if (! $model) {
            return $this->notFoundResponse('User not found');
        }
        $model->makeHidden(['password']);

        return $this->successResponse($model);
    }

    public function update(UpdateUserRequest $request, string $user): JsonResponse
    {
        $model = User::find($user);
        if (! $model) {
            return $this->notFoundResponse('User not found');
        }
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $model->update($data);
        $model->makeHidden(['password']);

        return $this->successResponse($model->fresh(['employee', 'roles']), 'User updated successfully');
    }

    public function destroy(string $user): JsonResponse
    {
        $model = User::find($user);
        if (! $model) {
            return $this->notFoundResponse('User not found');
        }
        $model->delete();

        return $this->successResponse(null, 'User deleted successfully');
    }
}
