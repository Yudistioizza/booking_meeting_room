@props(['items'])

@if($items->hasPages())
    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-500">
            Menampilkan {{ $items->firstItem() }} sampai {{ $items->lastItem() }} dari {{ $items->total() }} hasil
        </div>
        <div>
            {{ $items->links() }}
        </div>
    </div>
@endif