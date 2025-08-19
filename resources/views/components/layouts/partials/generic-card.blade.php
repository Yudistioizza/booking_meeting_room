@props(['title', 'stats' => []])

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="relative h-full flex-1 overflow-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">{{ $title }}</h2>
                @if(!empty($stats))
                    @include('components.layouts.partials.stats-box', ['stats' => $stats])
                @endif
            </div>

            {{ $slot }}
        </div>
    </div>
</div>