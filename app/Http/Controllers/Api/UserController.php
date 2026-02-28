<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(name="Users", description="Quản lý người dùng")
 */
class UserController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'username', 'email', 'status', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Danh sách người dùng",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo username, email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['employee', 'roles']);
        $result = $this->indexQuery($request, $query, ['username', 'email'], ['status' => 'status']);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Tạo người dùng mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","email","password"},
     *             @OA\Property(property="username", type="string", example="user001"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $user->makeHidden(['password']);

        return $this->successResponse($user->load(['employee', 'roles']), 'User created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Chi tiết người dùng",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $user): JsonResponse
    {
        $model = User::with(['employee', 'roles.permissions'])->find($user);
        if (! $model) {
            return $this->notFoundResponse('User not found');
        }
        $model->makeHidden(['password']);

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Cập nhật người dùng",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Xóa người dùng",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
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
