<?php

namespace App\Services;

class FilterService
{
    public function applyFilters($query, array $filters): mixed
    {
        // 1. Keyword search
        if (!empty($filters['search'])) {
            $keyword = strtolower($filters['search']);
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$keyword}%"]);
            });
        }

        // 2. Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            match ($filters['status']) {
                'active'   => $query->where('is_active', true)->whereNull('deleted_at'),
                'inactive' => $query->where('is_active', false)->whereNull('deleted_at'),
                'deleted'  => $query->onlyTrashed(),
                default    => null,
            };
        }

        // 3. Role filter (opsional – hanya untuk User)
        if (!empty($filters['role']) && $filters['role'] !== 'all') {
            $query->where('role_id', $filters['role']);
        }

        return $query;
    }

    public function applySorting($query, string $column, string $direction): mixed
    {
        // whitelist kolom agar tidak SQL-injection
        $allowed = ['id', 'name', 'email', 'created_at', 'updated_at', 'deleted_at'];
        if (!in_array($column, $allowed)) {
            $column = 'name';
        }
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction);
    }
}