@props(['name', 'size' => 'w-8 h-8'])

<div {{ $attributes->merge(['class' => "rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-medium text-sm $size"]) }}>
    {{ strtoupper(substr($name, 0, 1)) }}
</div>