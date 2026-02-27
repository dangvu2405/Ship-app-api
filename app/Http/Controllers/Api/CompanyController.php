<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'status', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Company::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], ['status' => 'status']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return $this->successResponse($company, 'Company created successfully', 201);
    }

    public function show(string $company): JsonResponse
    {
        $model = Company::find($company);
        if (! $model) {
            return $this->notFoundResponse('Company not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateCompanyRequest $request, string $company): JsonResponse
    {
        $model = Company::find($company);
        if (! $model) {
            return $this->notFoundResponse('Company not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Company updated successfully');
    }

    public function destroy(string $company): JsonResponse
    {
        $model = Company::find($company);
        if (! $model) {
            return $this->notFoundResponse('Company not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Company deleted successfully');
    }
}
