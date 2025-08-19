@props(['role'])

@php
    $colors = [
        'Admin'   => 'bg-red-100 text-red-800',
        'Manager' => 'bg-blue-100 text-blue-800',
        'User'    => 'bg-green-100 text-green-800',
        'default' => 'bg-gray-100 text-gray-800',
    ];
    $color = $colors[$role] ?? $colors['default'];
@endphp

<span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $color }}">
    {{ $role }}
</span>