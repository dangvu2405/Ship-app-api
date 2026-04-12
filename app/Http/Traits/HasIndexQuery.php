<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasIndexQuery
{
    /**
     * Apply pagination, filter, search, sort to query and return paginated result.
     * Response format per spec: data: { data: [...], meta: { current_page, last_page, per_page, total } }
     *
     * @param  array  $searchable  Column names for keyword/q/search search (e.g. ['code', 'name'])
     * @param  array  $filterable  Request param => column name (e.g. ['office_id' => 'office_id'])
     * @param  Closure(Builder, string): void|null  $keywordFilter  Optional; when set, replaces default LIKE on $searchable (e.g. driver + employee name).
     * @return array{data: \Illuminate\Support\Collection, meta: array}
     */
    protected function indexQuery(Request $request, Builder $query, array $searchable = [], array $filterable = [], ?Closure $keywordFilter = null): array
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

        // Search: keyword, q, or search (FE dataProvider — FRONTEND_PAYLOAD_BY_SCREEN)
        $keyword = $request->input('keyword', $request->input('q', $request->input('search')));
        if ($keyword !== null && $keyword !== '' && is_string($keyword)) {
            if ($keywordFilter instanceof Closure) {
                $keywordFilter($query, $keyword);
            } elseif (! empty($searchable)) {
                $query->where(function ($q) use ($keyword, $searchable): void {
                    foreach ($searchable as $i => $column) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->{$method}($column, 'like', '%'.$keyword.'%');
                    }
                });
            }
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
