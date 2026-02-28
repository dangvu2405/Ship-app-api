<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Departments", description="Quản lý phòng ban")
 */
class DepartmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'office_id', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/departments",
     *     tags={"Departments"},
     *     summary="Danh sách phòng ban",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="office_id", in="query", description="Lọc theo văn phòng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()->with('office');
        $result = $this->indexQuery($request, $query, ['code', 'name'], ['office_id' => 'office_id']);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/departments",
     *     tags={"Departments"},
     *     summary="Tạo phòng ban mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name","office_id"},
     *             @OA\Property(property="code", type="string", example="DEP001"),
     *             @OA\Property(property="name", type="string", example="Phòng Nhân sự"),
     *             @OA\Property(property="office_id", type="integer", example=1),
     *             @OA\Property(property="parent_id", type="integer"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return $this->successResponse($department->load('office'), 'Department created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/departments/{id}",
     *     tags={"Departments"},
     *     summary="Chi tiết phòng ban",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $department): JsonResponse
    {
        $model = Department::with('office', 'parent')->find($department);
        if (! $model) {
            return $this->notFoundResponse('Department not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/departments/{id}",
     *     tags={"Departments"},
     *     summary="Cập nhật phòng ban",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="office_id", type="integer"),
     *             @OA\Property(property="parent_id", type="integer"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateDepartmentRequest $request, string $department): JsonResponse
    {
        $model = Department::find($department);
        if (! $model) {
            return $this->notFoundResponse('Department not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['office', 'parent']), 'Department updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/departments/{id}",
     *     tags={"Departments"},
     *     summary="Xóa phòng ban",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $department): JsonResponse
    {
        $model = Department::find($department);
        if (! $model) {
            return $this->notFoundResponse('Department not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Department deleted successfully');
    }
}
