<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasIndexQuery
{
    /**
     * Apply pagination, filter, search, sort to query and return paginated result.
     * Response format per spec: data: { data: [...], meta: { current_page, last_page, per_page, total } }
     *
     * @param  Request  $request
     * @param  Builder  $query
     * @param  array  $searchable  Column names for keyword/q search (e.g. ['code', 'name'])
     * @param  array  $filterable  Request param => column name (e.g. ['office_id' => 'office_id'])
     * @return array{data: \Illuminate\Support\Collection, meta: array}
     */
    protected function indexQuery(Request $request, Builder $query, array $searchable = [], array $filterable = []): array
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $perPage = $perPage > 0 ? $perPage : 15;
        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Filter
        foreach ($filterable as $param => $column) {
            if ($request->has($param) && $request->input($param) !== '' && $request->input($param) !== null) {
                $query->where($column, $request->input($param));
            }
        }

        // Search (keyword or q)
        $keyword = $request->input('keyword', $request->input('q'));
        if ($keyword && ! empty($searchable)) {
            $query->where(function ($q) use ($keyword, $searchable) {
                foreach ($searchable as $i => $column) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'like', '%' . $keyword . '%');
                }
            });
        }

        // Sort (whitelist allowed columns to avoid SQL injection)
        $allowedSortColumns = $this->allowedSortColumns ?? ['id'];
        if (in_array($sortBy, $allowedSortColumns, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', $sortOrder);
        }

        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
