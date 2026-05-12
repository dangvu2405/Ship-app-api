<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CompanyController extends BaseController
{
    public function __construct()
    {
        $this->authorizeResource(Company::class, 'company');
    }

    public function index(Request $request): JsonResource
    {
        $query = Company::query()
            ->where('id', auth()->user()->company_id); // Scope to the authenticated user's company

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'name'); // Default sort by name
        $sortOrder = $request->input('sort_order', 'asc'); // Default sort order asc

        // Validate sort_by to prevent SQL injection or unexpected column names
        $allowedSortColumns = ['name', 'code', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'name'; // Fallback to default if invalid
        }

        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $perPage = (int) $request->input('per_page', 15);
        $companies = $query->paginate($perPage);

        return CompanyResource::collection($companies);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:companies',
            'tax_code' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $company = DB::transaction(function () use ($validated) {
            $company = Company::create($validated);
            return $company;
        });

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): JsonResource
    {
        return new CompanyResource($company);
    }

    public function update(Request $request, Company $company): JsonResource
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:companies,code,' . $company->id,
            'tax_code' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|string|email|max:255',
            'status' => 'sometimes|nullable|string|in:active,inactive',
        ]);

        DB::transaction(function () use ($validated, $company) {
            $company->update($validated);
        });

        return new CompanyResource($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        DB::transaction(function () use ($company) {
            $company->delete();
        });

        return response()->json(null, 204);
    }
}
