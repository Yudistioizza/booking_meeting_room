<div class="relative" wire:poll.45s>
    <button class="icon-btn relative" wire:click="toggle" aria-label="Notifikasi" aria-expanded="{{ $open ? 'true' : 'false' }}">
        <flux:icon.bell class="size-5" />
        @if ($unread > 0)<span class="absolute top-1 right-1 min-w-4 h-4 px-1 grid place-items-center rounded-full bg-busy-500 text-white text-[10px] font-bold tnum">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </button>
    @if ($open)
        <div class="fixed inset-0 z-40" wire:click="toggle"></div>
        <div class="absolute right-0 top-full mt-2 w-[22rem] max-w-[calc(100vw-2rem)] card shadow-xl z-50 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
                <p class="font-bold">Notifikasi</p>
                @if ($unread > 0)<button wire:click="readAll" class="text-xs font-semibold text-brand-700 hover:underline">Tandai semua dibaca</button>@endif
            </div>
            <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                @forelse ($items as $n)
                    <button wire:click="read({{ $n->id }})" class="w-full text-left px-4 py-3 flex gap-3 hover:bg-slate-50 {{ $n->is_read ? '' : 'bg-brand-50/50' }}">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $n->is_read ? 'bg-transparent' : 'bg-brand-600' }}"></span>
                        <span class="min-w-0"><span class="block text-sm text-slate-800">{{ $n->message }}</span><span class="block text-xs text-slate-400 mt-0.5">{{ $n->created_at->diffForHumans() }}</span></span>
                    </button>
                @empty
                    <p class="px-4 py-10 text-center text-sm text-slate-400">Belum ada notifikasi.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
