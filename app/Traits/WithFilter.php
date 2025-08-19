<?php

namespace App\Traits;

trait WithFilter
{
    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $this->sortDirection = $this->sortBy === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        // reset semua filter yang digunakan di UserManager
        $this->reset([
            'search',
            'sortBy',
            'sortDirection',
            'filterStatus',
            'filterRole',
            'perPage',
        ]);

        // kembalikan nilai default
        $this->filterStatus = 'all';
        $this->filterRole   = 'all';
        $this->perPage      = 10;

        $this->resetPage();
        $this->flashInfo('Filter telah direset.');
    }
}
