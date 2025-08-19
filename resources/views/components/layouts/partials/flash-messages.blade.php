@props(['types' => ['success', 'error', 'info']])

@foreach($types as $type)
    @if(session()->has($type))
        <div class="flash-message px-4 py-3 border rounded-md mb-4
            {{ $type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : '' }}
            {{ $type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : '' }}
            {{ $type === 'info' ? 'bg-blue-100 border-blue-400 text-blue-700' : '' }}"
            data-flash>
            <div class="flex items-center">
                <flux:icon name="{{ $type === 'success' ? 'check-circle' : ($type === 'error' ? 'x-circle' : 'information-circle') }}" class="w-5 h-5 mr-2" />
                {{ session($type) }}
            </div>
        </div>
    @endif
@endforeach

<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.querySelectorAll('[data-flash]').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    });
</script>