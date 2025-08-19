@props(['headers', 'items', 'emptyMessage' => 'Belum ada data'])

<div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
    <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                @foreach($headers as $header)
                    <th class="px-4 py-3 text-left font-medium text-gray-700">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @if($items->isEmpty())
                <tr>
                    <td colspan="{{ count($headers) }}" class="px-4 py-8 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="inbox" class="w-12 h-12 text-gray-400" />
                            <div class="text-gray-500">{{ $emptyMessage }}</div>
                        </div>
                    </td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>