<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class TableSort
{
    /**
     * Sort the full filtered query, then paginate.
     *
     * @param  array<string, array{column?: string, dir?: string, query?: callable, value?: callable}>  $columns
     */
    public static function paginate(Builder $query, array $columns, string $default, int $perPage = 20): LengthAwarePaginator
    {
        $requested = request()->query('sort');
        $requestedDir = request()->query('dir');
        $active = is_string($requested) && isset($columns[$requested]);
        $key = $active ? $requested : $default;
        $dir = $active && in_array($requestedDir, ['asc', 'desc'], true)
            ? $requestedDir
            : ($columns[$key]['dir'] ?? 'asc');

        $spec = $columns[$key];

        if (isset($spec['value'])) {
            $sorted = $query->get()
                ->sortBy($spec['value'], SORT_REGULAR, $dir === 'desc')
                ->values();

            return self::paginateCollection($sorted, $perPage);
        }

        if (isset($spec['query'])) {
            $query->orderBy(($spec['query'])(), $dir);
        } else {
            $query->orderBy($spec['column'], $dir);
        }

        $query->orderBy($query->getModel()->getQualifiedKeyName(), $dir);

        return $query->paginate($perPage)->withQueryString();
    }

    protected static function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();
        $slice = $items->forPage($page, $perPage)->values();

        return new Paginator($slice, $items->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
