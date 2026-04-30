<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Users", description="Quản lý người dùng")
 */
class UserController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'username', 'email', 'status', 'created_at'];

    public function __construct(private readonly UserService $userService) {}

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
        $query = User::query()->with(['roles']);
        $result = $this->indexQuery($request, $query, ['username', 'email'], ['status' => 'status']);

        return $this->successResponse($result, 'api.common.ok');
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
        $user = $this->userService->create($request->validated());
        $user->makeHidden(['password']);

        return $this->successResponse($user, 'api.user.created', 201);
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
        $model = User::with(['roles.permissions'])->find($user);
        if (! $model) {
            return $this->notFoundResponse('api.user.not_found');
        }
        $model->makeHidden(['password']);

        return $this->successResponse($model, 'api.common.ok');
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
            return $this->notFoundResponse('api.user.not_found');
        }
        $user = $this->userService->update($model, $request->validated());
        $user->makeHidden(['password']);

        return $this->successResponse($user, 'api.user.updated');
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
            return $this->notFoundResponse('api.user.not_found');
        }

        $model->delete();

        return $this->successResponse(null, 'api.user.deleted');
    }
}
