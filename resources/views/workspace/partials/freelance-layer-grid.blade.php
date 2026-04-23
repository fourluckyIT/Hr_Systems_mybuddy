@php
    $isAdmin = auth()->user()?->hasRole('admin') ?? false;
    $rates = ($layerRates ?? collect())->sortBy('layer_from')->values();
    $rateCount = $rates->count();
@endphp

<div x-data="flLayerRates({
        initial: {{ $rates->map(fn($r) => [
            'id' => $r->id,
            'layer_from' => $r->layer_from,
            'layer_to' => $r->layer_to,
            'rate_per_minute' => (float) $r->rate_per_minute,
        ])->toJson() }},
     })">

{{-- Work logs grid --}}
<div class="bg-white rounded-xl shadow-sm border overflow-hidden" x-data="{ editingRowId: null }">
    <div class="px-4 py-3 border-b flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            <h3 class="text-sm font-bold text-gray-800">Freelance Layer — {{ $employee->display_name }}</h3>
            <span class="text-[10px] px-2 py-0.5 bg-gray-100 text-gray-600 rounded font-semibold">{{ $workLogs->count() }} งาน</span>
        </div>
        <div class="flex items-center gap-2">
            @if($isAdmin)
            <button type="button" @click="$dispatch('open-price-modal')"
                    class="inline-flex items-center gap-1.5 text-[11px] px-3 py-1.5 border border-gray-200 hover:bg-gray-50 rounded-lg font-semibold text-gray-700 transition">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Price / min
                <span class="text-[9px] px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded font-bold">{{ $rateCount }}</span>
            </button>
            @endif
        </div>
    </div>

    @php
        $activeJobCount = ($assignedEditJobs ?? collect())->whereNotIn('status', ['final'])->count();
    @endphp

    @if($activeJobCount > 0)
        <div class="px-4 py-2 bg-yellow-50 border-b border-yellow-100 text-[11px] text-yellow-700">
            ⏳ มี {{ $activeJobCount }} งานกำลังดำเนินการ — รายได้จะขึ้นเมื่อปิดงาน (Final)
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-[11px] text-gray-500 uppercase">
                <tr>
                    <th class="px-3 py-2 text-center w-8"></th>
                    <th class="px-3 py-2 text-left">ชื่องาน</th>
                    <th class="px-3 py-2 text-center w-24">เวลา</th>
                    <th class="px-3 py-2 text-right w-24">เรท/นาที</th>
                    <th class="px-3 py-2 text-right w-28">รายได้</th>
                    @if($isAdmin)<th class="px-3 py-2 w-10"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($workLogs as $i => $log)
                @php
                    $durationMinutes = ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
                    $durationHms = \App\Support\DurationInput::formatMinutesAsHms($durationMinutes);
                    $amount = (float) $log->amount;
                    $rate = (float) ($log->custom_rate ?? $log->rate ?? 0);
                @endphp
                <tr class="border-t hover:bg-gray-50 group {{ $log->is_disabled ? 'opacity-40' : '' }}"
                    :class="editingRowId === {{ $log->id }} ? 'bg-indigo-50/50' : ''">
                    <td class="px-3 py-2 text-center">
                        <form method="POST" action="{{ route('workspace.toggleWorkLog', $log->id) }}" class="inline">
                            @csrf
                            <input type="checkbox" onchange="this.form.submit()" {{ $log->is_disabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-red-600 focus:ring-red-500 w-3.5 h-3.5 cursor-pointer" title="ข้าม">
                        </form>
                    </td>
                    <td class="px-3 py-2 text-sm font-medium text-gray-800">
                        {{ $log->notes ? str_replace('Auto: ', '', $log->notes) : ($log->work_type ?? '-') }}
                        @if($log->source_flag === 'auto')
                            <span class="inline-block ml-1 text-[9px] bg-blue-50 text-blue-600 px-1 rounded font-semibold">AUTO</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center font-mono text-[11px] text-gray-500">{{ $durationHms }}</td>

                    @if($isAdmin)
                        {{-- Rate Column --}}
                        <td class="px-3 py-2 text-right text-xs text-gray-600" x-show="editingRowId !== {{ $log->id }}">
                            @if(($log->pricing_mode ?? 'layer') === 'custom')
                                <span class="inline-block px-1.5 py-0.5 rounded bg-orange-100 text-orange-700 text-[9px] font-bold mr-1" title="Fix Rate">FIX</span>
                            @elseif(($log->pricing_mode ?? 'layer') === 'custom_rate_per_min')
                                <span class="inline-block px-1.5 py-0.5 rounded bg-teal-100 text-teal-700 text-[9px] font-bold mr-1" title="Custom Rate/Min">CUSTOM</span>
                            @endif
                            <span class="tabular-nums">{{ number_format($rate, 2) }}</span>
                        </td>
                        <td class="px-3 py-2 text-right" x-show="editingRowId === {{ $log->id }}" x-cloak>
                            <div class="flex flex-col items-end gap-1" x-data="{ mode: '{{ $log->pricing_mode ?? 'layer' }}' }">
                                <select name="pricing_mode" x-model="mode" form="edit-form-{{ $log->id }}" class="w-24 px-1 py-1 border border-indigo-300 rounded text-[10px] bg-indigo-50 text-indigo-700 font-semibold focus:ring-0">
                                    <option value="layer">Layer (เรท/นาที)</option>
                                    <option value="custom">Fix Rate (เหมา)</option>
                                    <option value="custom_rate_per_min">เรท/นาที (อิสระ)</option>
                                </select>
                                <select name="layer" x-show="mode === 'layer'" form="edit-form-{{ $log->id }}" class="w-24 px-1 py-1 border border-indigo-300 rounded text-[10px] bg-white text-gray-700 focus:ring-0">
                                    @foreach($rates as $r)
                                        <option value="{{ $r->layer_from }}" {{ $log->layer >= $r->layer_from && $log->layer <= $r->layer_to ? 'selected' : '' }}>
                                            L{{ $r->layer_from }}-{{ $r->layer_to }} ({{ (float)$r->rate_per_minute }})
                                        </option>
                                    @endforeach
                                    @if($rates->isEmpty())
                                        <option value="{{ $log->layer }}">L{{ $log->layer }}</option>
                                    @endif
                                </select>
                                <input type="number" name="rate" x-show="mode === 'custom' || mode === 'custom_rate_per_min'" form="edit-form-{{ $log->id }}" step="0.0001" min="0" value="{{ $rate }}"
                                       class="w-24 px-2 py-1 border border-indigo-300 rounded text-xs text-right bg-white" placeholder="Rate/Fix">
                            </div>
                        </td>

                        {{-- Amount Column --}}
                        <td class="px-3 py-2 text-right font-semibold text-gray-900 tabular-nums" x-show="editingRowId !== {{ $log->id }}">
                            {{ number_format($amount, 2) }}
                        </td>
                        <td class="px-3 py-2 text-right" x-show="editingRowId === {{ $log->id }}" x-cloak>
                            <input type="number" name="amount" form="edit-form-{{ $log->id }}" step="0.01" min="0" value="{{ $amount }}"
                                   class="w-28 px-2 py-1 border border-indigo-300 rounded text-xs text-right font-semibold bg-white" placeholder="Amount">
                        </td>

                        {{-- Action Column --}}
                        <td class="px-3 py-2 text-center">
                            <div x-show="editingRowId !== {{ $log->id }}">
                                <button type="button" @click="editingRowId = {{ $log->id }}"
                                        class="text-gray-300 hover:text-indigo-600 transition" title="แก้ไข">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                            </div>
                            <div x-show="editingRowId === {{ $log->id }}" x-cloak class="flex items-center gap-1 justify-center">
                                <form id="edit-form-{{ $log->id }}" method="POST" action="{{ route('workspace.work-log.rate.update', $log->id) }}" class="hidden">
                                    @csrf @method('PATCH')
                                </form>
                                <button type="submit" form="edit-form-{{ $log->id }}" class="w-6 h-6 rounded bg-indigo-600 text-white hover:bg-indigo-700 flex items-center justify-center" title="บันทึก">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="editingRowId = null" class="w-6 h-6 rounded bg-gray-100 text-gray-500 hover:bg-gray-200 flex items-center justify-center" title="ยกเลิก">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </td>
                    @else
                        <td class="px-3 py-2 text-right text-xs text-gray-600 tabular-nums">{{ number_format($rate, 2) }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900 tabular-nums">{{ number_format($amount, 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr class="border-t">
                    <td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-3 py-10 text-center">
                        <div class="text-sm text-gray-500">ยังไม่มีรายการ</div>
                        <div class="mt-1 text-[11px] text-gray-400">งานจะขึ้นอัตโนมัติเมื่อปิดงานใน WORK Center</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Assigned Edit Jobs (merged in) --}}
<div class="mt-4">
    @include('workspace.partials.performance-records')
</div>

{{-- Price / min Modal --}}
@if($isAdmin)
<div x-show="modalOpen" x-cloak
     @open-price-modal.window="openModal()"
     @keydown.escape.window="modalOpen = false"
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     x-transition.opacity>
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="modalOpen = false"></div>

    <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-800">Price / min</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">ตั้งอัตราต่อช่วง Layer ของ {{ $employee->display_name }}</p>
            </div>
            <button type="button" @click="modalOpen = false" class="p-1.5 hover:bg-gray-100 rounded-lg text-gray-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('workspace.fl-layer-rates.save', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
            @csrf
            <div class="max-h-[60vh] overflow-y-auto divide-y">
                <template x-for="(row, idx) in rows" :key="idx">
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <input type="hidden" :name="`rates[${idx}][id]`" :value="row.id ?? ''">
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-gray-400 font-bold">L</span>
                            <input type="number" min="1" :name="`rates[${idx}][layer_from]`" x-model.number="row.layer_from"
                                   class="w-14 px-2 py-1 border border-gray-200 rounded text-sm text-center" required>
                            <span class="text-gray-300">–</span>
                            <span class="text-[10px] text-gray-400 font-bold">L</span>
                            <input type="number" min="1" :name="`rates[${idx}][layer_to]`" x-model.number="row.layer_to"
                                   class="w-14 px-2 py-1 border border-gray-200 rounded text-sm text-center" required>
                        </div>
                        <div class="flex-1 flex items-center gap-2 justify-end">
                            <input type="number" step="0.01" min="0"
                                   :name="`rates[${idx}][rate_per_minute]`" x-model.number="row.rate_per_minute"
                                   class="w-28 px-3 py-1 border border-gray-200 rounded text-sm text-right font-semibold" required>
                            <span class="text-[10px] text-gray-400 w-10">/นาที</span>
                            <button type="button" @click="removeRow(idx)"
                                    class="text-gray-300 hover:text-red-500 transition" title="ลบ">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="rows.length === 0" class="px-5 py-10 text-center text-xs text-gray-400 italic">
                    ยังไม่มีช่วงราคา — กด "+ เพิ่มช่วง" ด้านล่าง
                </div>
            </div>

            <div class="px-5 py-3 bg-gray-50 border-t flex items-center justify-between gap-2">
                <button type="button" @click="addRow()"
                        class="text-[11px] px-3 py-1.5 bg-white border border-gray-200 hover:border-indigo-300 text-indigo-600 rounded-lg font-semibold">
                    + เพิ่มช่วง
                </button>
                <div class="flex gap-2">
                    <button type="button" @click="modalOpen = false" class="text-[11px] px-3 py-1.5 text-gray-500 hover:bg-gray-100 rounded-lg font-semibold">ยกเลิก</button>
                    <button type="submit" class="text-[11px] px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow">
                        บันทึก & คำนวณใหม่
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

</div>

@push('scripts')
<script>
function flLayerRates({ initial }) {
    return {
        modalOpen: false,
        rows: JSON.parse(JSON.stringify(initial ?? [])),
        _backup: JSON.parse(JSON.stringify(initial ?? [])),
        openModal() {
            this.rows = JSON.parse(JSON.stringify(this._backup));
            this.modalOpen = true;
        },
        addRow() {
            const last = this.rows[this.rows.length - 1];
            const nextFrom = last ? (Number(last.layer_to) + 1) : 1;
            this.rows.push({ id: null, layer_from: nextFrom, layer_to: nextFrom + 2, rate_per_minute: 0 });
        },
        removeRow(idx) { this.rows.splice(idx, 1); },
    };
}
</script>
@endpush
