<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Búsquedas insensibles a mayúsculas/minúsculas compatibles con MySQL/MariaDB y PostgreSQL.
 *
 * - PostgreSQL: usa ILIKE (nativo).
 * - MySQL/MariaDB/SQLite: usa LOWER(columna) LIKE ? (no existe ILIKE en MySQL).
 */
class InsensitiveSearch
{
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    protected static function usePgsqlIlike(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Contiene texto (usuario), sin distinguir mayúsculas.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function whereInsensitiveLike(EloquentBuilder|QueryBuilder $query, string $column, string $search, string $boolean = 'and'): void
    {
        if (self::usePgsqlIlike()) {
            $pattern = '%'.self::escapeLike($search).'%';
            $sql = $column.' ILIKE ?';
        } else {
            $pattern = '%'.mb_strtolower(self::escapeLike($search)).'%';
            $sql = 'LOWER('.$column.') LIKE ?';
        }

        if ($boolean === 'or') {
            $query->orWhereRaw($sql, [$pattern]);
        } else {
            $query->whereRaw($sql, [$pattern]);
        }
    }

    /**
     * Patrón fijo ya con % (ej. '%venta%', '%compra%').
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function whereInsensitiveLikePattern(EloquentBuilder|QueryBuilder $query, string $column, string $pattern, string $boolean = 'and'): void
    {
        if (self::usePgsqlIlike()) {
            $sql = $column.' ILIKE ?';
            $bound = $pattern;
        } else {
            $sql = 'LOWER('.$column.') LIKE ?';
            $bound = mb_strtolower($pattern);
        }

        if ($boolean === 'or') {
            $query->orWhereRaw($sql, [$bound]);
        } else {
            $query->whereRaw($sql, [$bound]);
        }
    }
}
