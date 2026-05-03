<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Customers", description="Quản lý khách hàng")
 */
class CustomerController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'type', 'tax_code', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/customers",
     *     tags={"Customers"},
     *     summary="Danh sách khách hàng",
     *
     *     @OA\Parameter(name="search", in="query", description="Tìm theo name, tax_code, email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="type", in="query", description="Lọc theo loại khách hàng", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();
        $result = $this->indexQuery($request, $query, ['name', 'tax_code', 'email'], ['type' => 'type']);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/customers",
     *     tags={"Customers"},
     *     summary="Tạo khách hàng mới",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", example="Công ty XYZ"),
     *             @OA\Property(property="type", type="string", enum={"company","individual"}, example="company"),
     *             @OA\Property(property="tax_code", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="contact_person", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return $this->successResponse($customer, 'Customer created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     summary="Chi tiết khách hàng",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $customer): JsonResponse
    {
        $model = Customer::find($customer);
        if (! $model) {
            return $this->notFoundResponse('Customer not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     summary="Cập nhật khách hàng",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="tax_code", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="contact_person", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateCustomerRequest $request, string $customer): JsonResponse
    {
        $model = Customer::find($customer);
        if (! $model) {
            return $this->notFoundResponse('Customer not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Customer updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     summary="Xóa khách hàng",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $customer): JsonResponse
    {
        $model = Customer::find($customer);
        if (! $model) {
            return $this->notFoundResponse('Customer not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Customer deleted successfully');
    }
}
