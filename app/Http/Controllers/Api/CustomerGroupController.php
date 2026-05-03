<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Traits\HasIndexQuery;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Customer Groups", description="Quản lý nhóm khách hàng")
 */
final class CustomerGroupController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/customer-groups",
     *     tags={"Customer Groups"},
     *     summary="Danh sách nhóm khách hàng",
     *
     *     @OA\Parameter(name="search", in="query", description="Tìm theo name, description", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->indexQuery($request, CustomerGroup::query(), ['name', 'description'], []);

        return $this->successResponse($result, 'OK');
    }
}
