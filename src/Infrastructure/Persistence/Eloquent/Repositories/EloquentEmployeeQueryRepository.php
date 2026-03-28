<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Application\Employee\DTOs\ListEmployeesCriteria;
use Src\Application\Shared\DTOs\PaginatedResult;
use Src\Application\Shared\DTOs\PaginationMeta;
use Src\Domain\Employee\Repositories\EmployeeQueryRepositoryInterface;
use Src\Infrastructure\Persistence\Eloquent\Models\EmployeeModel;

final class EloquentEmployeeQueryRepository implements EmployeeQueryRepositoryInterface
{
    private const ALLOWED_SORT_COLUMNS = [
        'id',
        'code',
        'name',
        'email',
        'type',
        'status',
        'office_id',
        'join_date',
        'created_at',
    ];

    private const SEARCHABLE_COLUMNS = [
        'code',
        'name',
        'email',
    ];

    public function findAllPaginated(ListEmployeesCriteria $criteria): PaginatedResult
    {
        $query = EmployeeModel::query()
            ->with(['office', 'department', 'position', 'driver']);

        // Apply filters
        if ($criteria->officeId !== null) {
            $query->where('office_id', $criteria->officeId);
        }

        if ($criteria->departmentId !== null) {
            $query->where('department_id', $criteria->departmentId);
        }

        if ($criteria->type !== null) {
            $query->where('type', $criteria->type);
        }

        if ($criteria->status !== null) {
            $query->where('status', $criteria->status);
        }

        // Apply search
        if ($criteria->keyword !== null && $criteria->keyword !== '') {
            $keyword = $criteria->keyword;
            $query->where(function ($q) use ($keyword) {
                foreach (self::SEARCHABLE_COLUMNS as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'like', '%'.$keyword.'%');
                }
            });
        }

        // Apply sorting
        $sortBy = in_array($criteria->sortBy, self::ALLOWED_SORT_COLUMNS, true)
            ? $criteria->sortBy
            : 'id';
        $query->orderBy($sortBy, $criteria->sortOrder);

        // Paginate
        $paginator = $query->paginate($criteria->perPage, ['*'], 'page', $criteria->page);

        $items = collect($paginator->items())->map(function (EmployeeModel $model) {
            return [
                'id' => $model->id,
                'code' => $model->code,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'dob' => $model->dob?->format('Y-m-d'),
                'gender' => $model->gender,
                'address' => $model->address,
                'type' => $model->type,
                'status' => $model->status,
                'join_date' => $model->join_date->format('Y-m-d'),
                'resign_date' => $model->resign_date?->format('Y-m-d'),
                'office_id' => $model->office_id,
                'department_id' => $model->department_id,
                'position_id' => $model->position_id,
                'created_at' => $model->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $model->updated_at->format('Y-m-d H:i:s'),
                'office' => $model->office ? [
                    'id' => $model->office->id,
                    'name' => $model->office->name,
                ] : null,
                'department' => $model->department ? [
                    'id' => $model->department->id,
                    'name' => $model->department->name,
                ] : null,
                'position' => $model->position ? [
                    'id' => $model->position->id,
                    'name' => $model->position->name,
                ] : null,
            ];
        })->toArray();

        $meta = new PaginationMeta(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            from: $paginator->firstItem() ?? 0,
            to: $paginator->lastItem() ?? 0
        );

        return new PaginatedResult($items, $meta);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByOfficeId(int $officeId): array
    {
        return EmployeeModel::query()
            ->where('office_id', $officeId)
            ->where('status', 'active')
            ->orderBy('id')
            ->lazy()
            ->map(fn (EmployeeModel $model) => [
                'id' => $model->id,
                'code' => $model->code,
                'name' => $model->name,
                'type' => $model->type,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDriversByCompanyId(int $companyId): array
    {
        return EmployeeModel::query()
            ->where('type', 'driver')
            ->where('status', 'active')
            ->whereHas('office', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->orderBy('id')
            ->lazy()
            ->map(fn (EmployeeModel $model) => [
                'id' => $model->id,
                'code' => $model->code,
                'name' => $model->name,
            ])
            ->all();
    }
}
