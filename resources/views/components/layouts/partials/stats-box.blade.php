@props(['stats'])

<div class="flex gap-4 text-sm">
    @foreach($stats as $label => $value)
        <div class="text-center">
            <div class="font-semibold text-blue-600">{{ $value }}</div>
            <div class="text-gray-500">{{ $label }}</div>
        </div>
    @endforeach
</div>