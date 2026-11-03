<div class="max-w-xl">
    <div class="card card-pad">
        @if (! empty($heading))<h2 class="text-base font-bold">{{ $heading }}</h2>@endif
        @if (! empty($subheading))<p class="text-sm text-slate-500 mt-0.5">{{ $subheading }}</p>@endif
        <div class="mt-5">{{ $slot }}</div>
    </div>
</div>
