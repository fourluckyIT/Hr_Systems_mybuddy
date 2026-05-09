@php
    $note = trim((string) ($item['notes'] ?? $item['note'] ?? ''));
    $hasNote = $note !== '';
    // Split on newlines (the deliberate separator from the calculator). Falls back to `; ` for legacy data.
    $parts = $hasNote ? array_values(array_filter(array_map('trim', preg_split('/\r?\n|\s*;\s+/', $note)))) : [];
@endphp
<div class="flex justify-between text-sm py-1 group/row relative">
    <span class="text-gray-600 inline-flex items-center gap-1 relative">
        <span class="inline-flex items-center gap-1 {{ $hasNote ? 'cursor-help border-b border-dashed border-gray-300 group/tip' : '' }}">
            <span>{{ $item['label'] }}</span>
            @if($isManual && $canManageWorkspace)
                <span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>
            @endif
            @if($hasNote)
                {{-- Custom tooltip: bullet list, vertical (offset clearly below the label so it doesn't kiss the row) --}}
                <div class="pointer-events-none absolute z-[60] left-0 top-full mt-3 hidden group-hover/tip:block w-max max-w-[320px] p-2.5 bg-gray-900 text-white text-[11px] leading-relaxed rounded-lg shadow-xl">
                    @if(count($parts) > 1)
                        <ul class="space-y-1">
                            @foreach($parts as $p)
                                <li class="flex gap-1.5"><span class="text-gray-400">•</span><span>{{ $p }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <div>{{ $note }}</div>
                    @endif
                    {{-- Arrow pointing up to the label --}}
                    <div class="absolute -top-1.5 left-3 w-3 h-3 bg-gray-900 rotate-45"></div>
                </div>
            @endif
        </span>
    </span>
    <span class="font-medium {{ $item['amount'] > 0 ? '' : 'text-gray-400' }}">{{ number_format($item['amount'], 2) }}</span>
</div>
