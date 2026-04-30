<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Companies", description="Quản lý công ty")
 */
class CompanyController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'status', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/companies",
     *     tags={"Companies"},
     *     summary="Danh sách công ty",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo status", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp (vd: name,-created_at)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], ['status' => 'status']);

        return $this->successResponse($result, 'api.common.ok');
    }

    /**
     * @OA\Post(
     *     path="/api/companies",
     *     tags={"Companies"},
     *     summary="Tạo công ty mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name"},
     *             @OA\Property(property="code", type="string", example="COMP001"),
     *             @OA\Property(property="name", type="string", example="Công ty ABC"),
     *             @OA\Property(property="address", type="string", example="123 Đường ABC"),
     *             @OA\Property(property="phone", type="string", example="0901234567"),
     *             @OA\Property(property="email", type="string", example="contact@abc.com"),
     *             @OA\Property(property="tax_code", type="string", example="1234567890"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return $this->successResponse($company, 'api.company.created', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/companies/{id}",
     *     tags={"Companies"},
     *     summary="Chi tiết công ty",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $company): JsonResponse
    {
        $model = Company::find($company);
        if (! $model) {
            return $this->notFoundResponse('api.company.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    /**
     * @OA\Put(
     *     path="/api/companies/{id}",
     *     tags={"Companies"},
     *     summary="Cập nhật công ty",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="tax_code", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateCompanyRequest $request, string $company): JsonResponse
    {
        $model = Company::find($company);
        if (! $model) {
            return $this->notFoundResponse('api.company.not_found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'api.company.updated');
    }

    /**
     * @OA\Delete(
     *     path="/api/companies/{id}",
     *     tags={"Companies"},
     *     summary="Xóa công ty",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $company): JsonResponse
    {
        $model = Company::find($company);
        if (! $model) {
            return $this->notFoundResponse('api.company.not_found');
        }

        return $this->errorResponse(
            'api.company.delete_not_allowed',
            422,
            [
                'code' => __('api.errors.code.operation_not_allowed'),
                'details' => [__('api.company.delete_not_allowed')],
            ],
        );
    }
}
