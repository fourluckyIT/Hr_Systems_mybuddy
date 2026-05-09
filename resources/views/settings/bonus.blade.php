@extends('layouts.app')

@section('title', 'Bonus Manager')

@php
    $statusConfig = [
        'draft'       => ['label' => 'ฉบับร่าง',     'color' => 'bg-gray-100 text-gray-700 border-gray-200'],
        'calculating' => ['label' => 'กำลังคำนวณ',   'color' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'calculated'  => ['label' => 'คำนวณแล้ว',    'color' => 'bg-blue-50 text-blue-700 border-blue-200'],
        'reviewed'    => ['label' => 'ตรวจแล้ว',     'color' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'approved'    => ['label' => 'อนุมัติแล้ว',  'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'paid'        => ['label' => 'จ่ายแล้ว',     'color' => 'bg-emerald-600 text-white border-emerald-700'],
        'closed'      => ['label' => 'ปิดรอบ',       'color' => 'bg-gray-700 text-white border-gray-800'],
        'rejected'    => ['label' => 'ปฏิเสธ',       'color' => 'bg-red-50 text-red-700 border-red-200'],
    ];
    $currentStatus = $selectedCycle?->status ?? 'draft';
    $statusInfo = $statusConfig[$currentStatus] ?? $statusConfig['draft'];

    // เปลี่ยน penalty ที่เก็บเป็น ratio (-0.01) → % บวก (1.00) สำหรับโชว์
    $absentPenaltyPct = $selectedCycle ? abs((float) $selectedCycle->absent_penalty_per_day) * 100 : 1.0;
    $latePenaltyPct = $selectedCycle ? abs((float) $selectedCycle->late_penalty_per_occurrence) * 100 : 0.2;
    $leavePenaltyPct = $selectedCycle ? (float) $selectedCycle->leave_penalty_rate * 100 : 1.0;
    $maxAllocationPct = $selectedCycle ? (float) $selectedCycle->max_allocation * 100 : 40;
    $juneMaxRatioPct = $selectedCycle ? (float) $selectedCycle->june_max_ratio * 100 : 40;
@endphp

@section('content')
<div class="space-y-6"
     x-data="bonusManager({
         cycleId: {{ $selectedCycle?->id ?? 'null' }},
         hasSelectedMonths: {{ $hasSelectedMonths ? 'true' : 'false' }},
         routes: {
             preview: '{{ route('settings.bonus.preview') }}',
             metrics: '{{ $selectedCycle ? route('settings.bonus.metrics', ['cycle' => $selectedCycle->id, 'employee' => 0]) : '' }}',
             previewPayslipPost: '{{ $selectedCycle ? route('settings.bonus.cycles.preview-payslip-post', ['cycle' => $selectedCycle->id]) : '' }}',
         },
         csrf: '{{ csrf_token() }}',
     })">

    {{-- ───── Header ───── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Bonus Manager</h1>
                @if($selectedCycle)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full border {{ $statusInfo['color'] }}">
                        {{ $statusInfo['label'] }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">บริหารรอบโบนัส คำนวณ และอนุมัติการจ่าย</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="newCycleOpen = true"
                    class="px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                + สร้างรอบใหม่
            </button>
            <button type="button" @click="tierListModal = true"
                    class="px-3 py-1.5 text-sm font-semibold text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 border border-indigo-200">
                จัดการ Tiers
            </button>
            <a href="{{ route('settings.rules') }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">กลับ Rules</a>
        </div>
    </div>

    {{-- ───── ไม่มีรอบเลย ───── --}}
    @if(!$selectedCycle)
        <div class="bg-white rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
            <div class="text-gray-400 text-5xl mb-3">📅</div>
            <h3 class="text-lg font-semibold text-gray-700">ยังไม่มีรอบโบนัส</h3>
            <p class="text-sm text-gray-500 mt-1 mb-4">เริ่มต้นด้วยการสร้างรอบโบนัสรอบแรก เช่น <code class="bg-gray-100 px-1 rounded">2026-JUN</code></p>
            <button type="button" @click="newCycleOpen = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700">
                + สร้างรอบโบนัสแรก
            </button>
        </div>
    @else

    {{-- ───── Cycle picker + Quick stats ───── --}}
    <div class="bg-white rounded-2xl border shadow-sm p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('settings.bonus.index') }}" class="flex items-center gap-2 flex-1 min-w-[260px]">
                <label class="text-xs font-semibold text-gray-500 uppercase">รอบโบนัส</label>
                <select name="cycle_id" onchange="this.form.submit()"
                        class="flex-1 px-3 py-2 border rounded-lg text-sm font-semibold">
                    @foreach($cycles as $cycle)
                        <option value="{{ $cycle->id }}" {{ $selectedCycle->id === $cycle->id ? 'selected' : '' }}>
                            {{ $cycle->cycle_code }} — {{ $statusConfig[$cycle->status]['label'] ?? $cycle->status }} ({{ optional($cycle->payment_date)->format('d M Y') }})
                        </option>
                    @endforeach
                </select>
            </form>

            {{-- Cycle-level destructive actions (compact) --}}
            <div class="flex items-center gap-2">
                @if(in_array($currentStatus, ['draft','calculating','calculated','reviewed']))
                    <form x-ref="destroyCycleForm" action="{{ route('settings.bonus.cycles.destroy', $selectedCycle) }}" method="POST" style="display:none">
                        @csrf
                        @method('DELETE')
                    </form>
                    <button type="button"
                            @click.prevent.stop="if(window.confirm('ยืนยันการลบรอบโบนัสนี้? (ข้อมูลคำนวณทั้งหมดจะถูกลบ)')) { $refs.destroyCycleForm.submit() }"
                            class="px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200">
                        🗑 ลบรอบนี้
                    </button>
                @endif
            </div>
        </div>

        {{-- Quick stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-4 border-t">
            <div class="bg-gray-50 rounded-xl p-3">
                <div class="text-[10px] font-bold text-gray-400 uppercase">พนักงานคำนวณแล้ว</div>
                <div class="text-2xl font-bold text-gray-900">{{ $cycleSummary['total_employees'] }}</div>
            </div>
            <div class="bg-blue-50 rounded-xl p-3">
                <div class="text-[10px] font-bold text-blue-400 uppercase">รอตรวจ</div>
                <div class="text-2xl font-bold text-blue-700">{{ $cycleSummary['pending_count'] }}</div>
            </div>
            <div class="bg-emerald-50 rounded-xl p-3">
                <div class="text-[10px] font-bold text-emerald-400 uppercase">อนุมัติแล้ว</div>
                <div class="text-2xl font-bold text-emerald-700">{{ $cycleSummary['approved_count'] }}</div>
            </div>
            <div class="bg-indigo-50 rounded-xl p-3">
                <div class="text-[10px] font-bold text-indigo-400 uppercase">รวมจ่าย</div>
                <div class="text-2xl font-bold text-indigo-700">฿{{ number_format((float) $cycleSummary['total_payment'], 0) }}</div>
            </div>
        </div>
    </div>

    {{-- ───── Workflow Stepper + Next Action ───── --}}
    @php
        $hasCalculations = $calculations->count() > 0;
        $stepStatus = function (string $stepKey) use ($currentStatus, $hasSelectedMonths, $hasCalculations) {
            // Returns: 'done' | 'current' | 'pending'
            $finalStatuses = ['paid', 'closed'];
            return match ($stepKey) {
                'config'    => 'done',
                'months'    => $hasSelectedMonths ? 'done' : (in_array($currentStatus, ['draft','calculating']) ? 'current' : 'pending'),
                'calculate' => $hasCalculations ? 'done' : ($hasSelectedMonths && in_array($currentStatus, ['draft','calculating']) ? 'current' : 'pending'),
                'approve'   => in_array($currentStatus, ['approved','paid','closed']) ? 'done' : (in_array($currentStatus, ['calculated','reviewed']) ? 'current' : 'pending'),
                'pay'       => in_array($currentStatus, $finalStatuses) ? 'done' : ($currentStatus === 'approved' ? 'current' : 'pending'),
                'close'     => $currentStatus === 'closed' ? 'done' : ($currentStatus === 'paid' ? 'current' : 'pending'),
                default     => 'pending',
            };
        };

        $steps = [
            ['key'=>'config',    'num'=>'1', 'label'=>'ตั้งค่ารอบ',  'anchor'=>'#step-1'],
            ['key'=>'months',    'num'=>'2', 'label'=>'เลือกเดือน',  'anchor'=>'#step-2'],
            ['key'=>'calculate', 'num'=>'3', 'label'=>'คำนวณ',       'anchor'=>'#step-3'],
            ['key'=>'approve',   'num'=>'4', 'label'=>'อนุมัติ',     'anchor'=>'#step-4'],
            ['key'=>'pay',       'num'=>'5', 'label'=>'จ่าย',         'anchor'=>null],
            ['key'=>'close',     'num'=>'6', 'label'=>'ปิดรอบ',       'anchor'=>null],
        ];

        // Next-action prompt
        $nextAction = (function () use ($currentStatus, $hasSelectedMonths, $hasCalculations) {
            if ($currentStatus === 'closed')   return ['icon'=>'🎉', 'text'=>'รอบนี้สิ้นสุดแล้ว — ดูประวัติได้ที่ section ด้านล่าง',          'cta'=>null];
            if ($currentStatus === 'paid')     return ['icon'=>'📦', 'text'=>'จ่ายเงินเสร็จแล้ว — กดปิดรอบเพื่อ archive',                     'cta'=>'close'];
            if ($currentStatus === 'approved') return ['icon'=>'💸', 'text'=>'พร้อมจ่ายโบนัส — กดทำเครื่องหมายจ่ายเพื่อบันทึกเข้า payslip',  'cta'=>'mark_paid'];
            if ($currentStatus === 'rejected') return ['icon'=>'↺',  'text'=>'รอบถูกปฏิเสธ — กดเปิดรอบใหม่เพื่อแก้ไข',                       'cta'=>'reopen'];
            if (in_array($currentStatus, ['calculated','reviewed'])) return ['icon'=>'✅', 'text'=>'คำนวณครบแล้ว — เลื่อนลงไปตรวจและอนุมัติที่ section 4', 'cta'=>'jump:#step-4'];
            if (!$hasSelectedMonths)           return ['icon'=>'📅', 'text'=>'ขั้นต่อไป — เลือกเดือนที่จะนำข้อมูลมาคำนวณที่ section 2',         'cta'=>'jump:#step-2'];
            if (!$hasCalculations)             return ['icon'=>'🧮', 'text'=>'ขั้นต่อไป — คำนวณโบนัสรายคนหรือรายกลุ่มที่ section 3',         'cta'=>'jump:#step-3'];
            return ['icon'=>'⏳', 'text'=>'ระบบกำลังคำนวณ...', 'cta'=>null];
        })();
    @endphp

    <div class="bg-white rounded-2xl border shadow-sm p-5">
        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-500 uppercase mb-4">
            <span>📈 Workflow</span>
            <span class="text-gray-300">·</span>
            <span class="text-gray-400 normal-case font-normal">ดำเนินการตามลำดับ {{ $statusInfo['label'] }}</span>
        </div>

        {{-- Stepper --}}
        <div class="relative">
            <div class="flex items-center justify-between gap-1">
                @foreach($steps as $i => $s)
                    @php $st = $stepStatus($s['key']); @endphp
                    <div class="flex flex-col items-center flex-1 relative z-10">
                        @if($s['anchor'])
                            <a href="{{ $s['anchor'] }}" class="block">
                        @else
                            <div>
                        @endif
                        <div class="w-9 h-9 rounded-full grid place-items-center text-sm font-bold border-2 transition
                            {{ $st === 'done' ? 'bg-emerald-500 text-white border-emerald-500' : '' }}
                            {{ $st === 'current' ? 'bg-indigo-600 text-white border-indigo-600 ring-4 ring-indigo-200 animate-pulse' : '' }}
                            {{ $st === 'pending' ? 'bg-white text-gray-400 border-gray-200' : '' }}">
                            {{ $st === 'done' ? '✓' : $s['num'] }}
                        </div>
                        @if($s['anchor'])
                            </a>
                        @else
                            </div>
                        @endif
                        <div class="text-[10px] font-semibold mt-1.5 text-center
                            {{ $st === 'done' ? 'text-emerald-700' : '' }}
                            {{ $st === 'current' ? 'text-indigo-700' : '' }}
                            {{ $st === 'pending' ? 'text-gray-400' : '' }}">
                            {{ $s['label'] }}
                        </div>
                    </div>
                    @if($i < count($steps) - 1)
                        @php
                            $nextStatus = $stepStatus($steps[$i+1]['key']);
                            $lineClass = $st === 'done' ? 'bg-emerald-400' : 'bg-gray-200';
                        @endphp
                        <div class="flex-1 h-0.5 -mx-2 {{ $lineClass }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Next-action prompt --}}
        <div class="mt-5 p-4 rounded-xl border-2
            {{ $currentStatus === 'closed' ? 'bg-gray-50 border-gray-200' : '' }}
            {{ in_array($currentStatus, ['paid','approved']) ? 'bg-emerald-50 border-emerald-200' : '' }}
            {{ in_array($currentStatus, ['draft','calculating','calculated','reviewed']) ? 'bg-indigo-50 border-indigo-200' : '' }}
            {{ $currentStatus === 'rejected' ? 'bg-red-50 border-red-200' : '' }}">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3 flex-1 min-w-[260px]">
                    <div class="text-3xl">{{ $nextAction['icon'] }}</div>
                    <div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase mb-0.5">ขั้นต่อไป</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $nextAction['text'] }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($nextAction['cta'] === 'mark_paid')
                        <form x-ref="markPaidForm" action="{{ route('settings.bonus.cycles.transition', $selectedCycle) }}" method="POST" style="display:none">
                            @csrf
                            <input type="hidden" name="transition_action" value="mark_paid">
                        </form>
                        <button type="button" @click="openMarkPaidPreview()"
                                class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 shadow-sm">
                            ✓ ทำเครื่องหมายจ่ายแล้ว →
                        </button>
                    @elseif($nextAction['cta'] === 'close')
                        <form x-ref="closeCycleForm" action="{{ route('settings.bonus.cycles.transition', $selectedCycle) }}" method="POST" style="display:none">
                            @csrf
                            <input type="hidden" name="transition_action" value="close">
                        </form>
                        <button type="button" @click.prevent.stop="$refs.closeCycleForm.submit()"
                                class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm font-bold hover:bg-gray-800">
                            ปิดรอบ →
                        </button>
                    @elseif($nextAction['cta'] === 'reopen')
                        <form x-ref="reopenCycleForm" action="{{ route('settings.bonus.cycles.transition', $selectedCycle) }}" method="POST" style="display:none">
                            @csrf
                            <input type="hidden" name="transition_action" value="reopen">
                        </form>
                        <button type="button" @click.prevent.stop="$refs.reopenCycleForm.submit()"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700">
                            ↺ เปิดรอบใหม่
                        </button>
                    @elseif(str_starts_with((string) $nextAction['cta'], 'jump:'))
                        @php $anchor = substr($nextAction['cta'], 5); @endphp
                        <a href="{{ $anchor }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700">
                            ไปยังขั้นนี้ →
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ───── 1. Cycle Settings (collapsible) ───── --}}
    <div id="step-1" class="bg-white rounded-2xl border shadow-sm scroll-mt-4" x-data="{ open: false }">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between p-5">
            <div class="flex items-center gap-3 text-left">
                <div class="w-8 h-8 rounded-full bg-emerald-500 text-white grid place-items-center font-bold">✓</div>
                <div>
                    <div class="text-sm font-bold text-gray-700">เงื่อนไขรอบโบนัส</div>
                    <div class="text-xs text-gray-500">ตั้งวันจ่าย, อัตราหักลด, สเกลปลดล็อก ตามอายุงาน</div>
                </div>
            </div>
            <span class="text-gray-400" x-text="open ? '▾' : '▸'"></span>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-5 border-t pt-4">
            <form action="{{ route('settings.bonus.cycles.update', $selectedCycle) }}" method="POST" class="space-y-5"
                  x-data="cycleSettingsForm({
                      absentPct: {{ round($absentPenaltyPct, 4) }},
                      latePct: {{ round($latePenaltyPct, 4) }},
                      leavePct: {{ round($leavePenaltyPct, 4) }},
                      maxAllocPct: {{ round($maxAllocationPct, 2) }},
                      juneMaxPct: {{ round($juneMaxRatioPct, 2) }},
                      leaveFreeDays: {{ (int) $selectedCycle->leave_free_days }},
                  })">
                @csrf
                @method('PATCH')

                {{-- Hidden raw values that get submitted --}}
                <input type="hidden" name="absent_penalty_per_day" :value="(-absentPct / 100).toFixed(4)">
                <input type="hidden" name="late_penalty_per_occurrence" :value="(-latePct / 100).toFixed(4)">
                <input type="hidden" name="leave_penalty_rate" :value="(leavePct / 100).toFixed(4)">
                <input type="hidden" name="max_allocation" :value="(maxAllocPct / 100).toFixed(4)">
                <input type="hidden" name="june_max_ratio" :value="(juneMaxPct / 100).toFixed(4)">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">วันจ่ายโบนัส</label>
                        <input type="date" name="payment_date" value="{{ optional($selectedCycle->payment_date)->toDateString() }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">เพดานการจ่าย (Max Allocation)</label>
                        <div class="relative">
                            <input type="number" step="0.1" min="0" max="100" x-model.number="maxAllocPct" required class="w-full px-3 py-2 border rounded-lg text-sm pr-8">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5">เพดานสูงสุดที่จ่ายได้ของรอบนี้</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">June ปลดล็อกสูงสุด</label>
                        <div class="relative">
                            <input type="number" step="0.1" min="0" max="100" x-model.number="juneMaxPct" required class="w-full px-3 py-2 border rounded-lg text-sm pr-8">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5">รอบ June ปลดล็อกได้สูงสุด (ที่เหลือไปออก December)</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">สเกลเดือนรอบ June</label>
                        <input type="number" name="june_scale_months" min="1" max="24" value="{{ $selectedCycle->june_scale_months }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        <p class="text-[10px] text-gray-400 mt-0.5">พนง. ผ่าน probation ครบจำนวนเดือนนี้ → ปลดล็อก June เต็มจำนวน</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">สเกลเดือนรอบ December</label>
                        <input type="number" name="full_scale_months" min="1" max="36" value="{{ $selectedCycle->full_scale_months }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        <p class="text-[10px] text-gray-400 mt-0.5">พนง. ผ่าน probation ครบจำนวนเดือนนี้ → ปลดล็อกเต็ม 100%</p>
                    </div>
                </div>

                {{-- Penalties: humanized --}}
                <div class="bg-rose-50/50 border border-rose-100 rounded-xl p-4 space-y-3">
                    <div class="text-xs font-bold text-rose-600 uppercase">หักโบนัสจากการเข้างาน</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">หักวันขาดงาน</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" x-model.number="absentPct" class="w-full px-3 py-2 border rounded-lg text-sm pr-12">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">% / วัน</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">หักการมาสาย</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" x-model.number="latePct" class="w-full px-3 py-2 border rounded-lg text-sm pr-12">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">% / ครั้ง</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">วันลาฟรี</label>
                            <div class="relative">
                                <input type="number" name="leave_free_days" min="0" max="30" x-model.number="leaveFreeDays" required class="w-full px-3 py-2 border rounded-lg text-sm pr-10">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">วัน</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">หักลาเกิน</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" x-model.number="leavePct" class="w-full px-3 py-2 border rounded-lg text-sm pr-12">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">% / วัน</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-[11px] text-gray-500">
                        ตัวอย่าง: ขาด 2 วัน + สาย 3 ครั้ง + ลา 7 วัน (ฟรี <span x-text="leaveFreeDays"></span> วัน) →
                        หัก
                        <span class="font-bold text-rose-600" x-text="(absentPct * 2 + latePct * 3 + leavePct * Math.max(7 - leaveFreeDays, 0)).toFixed(2) + '%'"></span>
                        จาก base
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-semibold text-sm hover:bg-emerald-700">
                        บันทึกเงื่อนไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ───── 2. Visual Month Picker ───── --}}
    <div id="step-2" class="bg-white rounded-2xl border shadow-sm p-5 transition-all duration-500 scroll-mt-4"
         :class="!hasSelectedMonths ? 'border-amber-400 ring-2 ring-amber-200' : ''">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-full grid place-items-center font-bold"
                 :class="hasSelectedMonths ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700 animate-pulse'">2</div>
            <div class="flex-1">
                <div class="text-sm font-bold text-gray-700">เดือนที่ใช้คำนวณ</div>
                <div class="text-xs text-gray-500">เลือกเดือนที่จะนำข้อมูลมาคำนวณ — เห็นได้ทันทีว่าเดือนไหนมี payslip ปิดแล้ว</div>
            </div>
            <template x-if="!hasSelectedMonths">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 border border-amber-300 animate-pulse">
                    ⚠ ยังไม่ได้เลือกเดือน
                </span>
            </template>
        </div>

        <form action="{{ route('settings.bonus.cycles.months.update', $selectedCycle) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                @foreach($candidateMonths as $m)
                    <label class="relative cursor-pointer group">
                        <input type="checkbox" name="months[]" value="{{ $m['month_key'] }}" {{ $m['already_selected'] ? 'checked' : '' }} class="sr-only peer" {{ $m['is_future'] ? 'disabled' : '' }}>
                        <div class="rounded-xl border-2 p-3 transition
                                    {{ $m['is_future'] ? 'border-gray-100 bg-gray-50 opacity-50 cursor-not-allowed' : 'border-gray-200 hover:border-indigo-300' }}
                                    peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:ring-2 peer-checked:ring-indigo-200">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-bold text-gray-900">{{ $m['month_label'] }}</div>
                                @if($m['finalized_payslips'] > 0)
                                    <span class="text-emerald-600 text-xs" title="Payslip ปิดแล้ว">✓</span>
                                @elseif($m['is_future'])
                                    <span class="text-gray-400 text-xs">⏳</span>
                                @else
                                    <span class="text-amber-500 text-xs" title="ยังไม่ปิด payslip">⚠</span>
                                @endif
                            </div>
                            <div class="space-y-0.5 text-[11px] text-gray-600">
                                <div>👥 {{ $m['employees_count'] }} คน</div>
                                <div>🎬 {{ number_format($m['total_clip_minutes']) }} นาที</div>
                                <div class="text-gray-400">📄 {{ $m['finalized_payslips'] }} payslip ปิด</div>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                    บันทึกเดือนที่เลือก
                </button>
            </div>
        </form>
    </div>

    {{-- ───── 3. Calculate (single + batch in tabs) ───── --}}
    <div id="step-3" class="bg-white rounded-2xl border shadow-sm p-5 scroll-mt-4 {{ $hasSelectedMonths && !$hasCalculations && in_array($currentStatus, ['draft','calculating']) ? 'ring-2 ring-indigo-300 border-indigo-300' : '' }}"
         x-data="{ tab: 'single' }">
        <div class="flex items-center gap-3 mb-4">
            @if($hasCalculations)
                <div class="w-8 h-8 rounded-full bg-emerald-500 text-white grid place-items-center font-bold">✓</div>
            @else
                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 grid place-items-center font-bold {{ $hasSelectedMonths && in_array($currentStatus, ['draft','calculating']) ? 'animate-pulse ring-4 ring-indigo-200' : '' }}">3</div>
            @endif
            <div class="flex-1">
                <div class="text-sm font-bold text-gray-700">คำนวณโบนัส</div>
                <div class="text-xs text-gray-500">ระบบดึงข้อมูลจริงให้อัตโนมัติ — กรอกแก้ก็ได้</div>
            </div>
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button type="button" @click="tab = 'single'" :class="tab === 'single' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-semibold rounded-md">รายบุคคล</button>
                <button type="button" @click="tab = 'batch'" :class="tab === 'batch' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-semibold rounded-md">รายกลุ่ม</button>
            </div>
        </div>

        {{-- Month guard warning --}}
        <template x-if="!hasSelectedMonths">
            <div class="mb-4 flex items-start gap-3 bg-amber-50 border-2 border-amber-300 rounded-xl p-4">
                <div class="text-2xl flex-shrink-0">🚫</div>
                <div>
                    <div class="text-sm font-bold text-amber-800">ยังไม่ได้เลือกเดือนที่ใช้คำนวณ</div>
                    <div class="text-xs text-amber-700 mt-1">
                        ต้องเลือกเดือนใน <span class="font-bold">ส่วนที่ 2</span> ก่อน — ระบบถึงจะดึงข้อมูลจริง (นาทีคลิป, ขาด/สาย/ลา) ได้
                        ถ้าคำนวณโดยไม่เลือกเดือน ตัวเลขจะเป็น 0 ทั้งหมด → โบนัสผิดพลาด
                    </div>
                    <a href="#" @click.prevent="document.querySelector('[x-data] .rounded-2xl:nth-child(4)')?.scrollIntoView({ behavior: 'smooth' })"
                       class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-amber-800 hover:text-amber-900 underline">
                        ↑ ไปเลือกเดือนด้านบน
                    </a>
                </div>
            </div>
        </template>

        {{-- ---- SINGLE ---- --}}
        <div x-show="tab === 'single'" class="grid grid-cols-1 lg:grid-cols-5 gap-5">
            <form action="{{ route('settings.bonus.calculate') }}" method="POST" class="lg:col-span-3 space-y-3">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $selectedCycle->id }}">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">พนักงาน</label>
                    <div class="flex gap-2">
                        <select name="employee_id" x-model="single.employee_id" @change="loadMetrics()" required class="flex-1 px-3 py-2 border rounded-lg text-sm">
                            <option value="">-- เลือกพนักงาน --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" data-salary="{{ $emp->salaryProfile?->base_salary ?? 0 }}">
                                    {{ $emp->full_name }} ({{ $emp->employee_code }}) — ฿{{ number_format((float) ($emp->salaryProfile?->base_salary ?? 0), 0) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" @click="loadMetrics()" :disabled="!single.employee_id || single.loading"
                                class="px-3 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 border border-indigo-200 disabled:opacity-50 whitespace-nowrap">
                            <span x-show="!single.loading">🔄 โหลดจากข้อมูลจริง</span>
                            <span x-show="single.loading">กำลังโหลด...</span>
                        </button>
                    </div>
                    <div x-show="single.metricsNote" class="mt-1 text-[11px] text-amber-600" x-text="single.metricsNote"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ฐานเงินเดือนคำนวณ</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">฿</span>
                            <input type="number" name="base_reference" step="0.01" min="0" x-model.number="single.base_reference" @input="schedulePreview()" required class="w-full pl-7 pr-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tier <span class="text-gray-400 font-normal">(เว้นว่าง = อัตโนมัติ)</span></label>
                        <select name="tier_code" x-model="single.tier_code" @change="schedulePreview()" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">— ระบบเลือกอัตโนมัติ —</option>
                            @foreach($tiers as $tier)
                                <option value="{{ $tier->tier_code }}">{{ $tier->tier_code }} — {{ $tier->tier_name }} (×{{ 1 + (float) $tier->multiplier }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-blue-600 mb-1">นาทีคลิป/เดือน (ถัวเฉลี่ย)</label>
                        <input type="number" name="clip_duration_minutes_per_month" min="0" x-model.number="single.clip_duration_minutes_per_month" @input="schedulePreview()" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm" placeholder="ใช้สำหรับ auto tier">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-blue-600 mb-1">เดือนผ่านเกณฑ์</label>
                        <input type="number" name="qualified_months" min="0" x-model.number="single.qualified_months" @input="schedulePreview()" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">ขาดงาน (วัน)</label>
                        <input type="number" name="absent_days" min="0" x-model.number="single.absent_days" @input="schedulePreview()" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">มาสาย (ครั้ง)</label>
                        <input type="number" name="late_count" min="0" x-model.number="single.late_count" @input="schedulePreview()" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">ลา (วัน)</label>
                        <input type="number" name="leave_days" min="0" x-model.number="single.leave_days" @input="schedulePreview()" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>

                <button type="submit"
                        :disabled="!hasSelectedMonths"
                        :class="!hasSelectedMonths ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
                        class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm"
                        :title="!hasSelectedMonths ? 'ต้องเลือกเดือนที่ใช้คำนวณก่อน (ส่วนที่ 2)' : ''">
                    <span x-show="hasSelectedMonths">คำนวณและบันทึก</span>
                    <span x-show="!hasSelectedMonths" class="flex items-center justify-center gap-2">
                        🔒 เลือกเดือนก่อน → ถึงจะคำนวณได้
                    </span>
                </button>
            </form>

            {{-- Live preview panel --}}
            <div class="lg:col-span-2 bg-gradient-to-br from-indigo-50 to-blue-50 rounded-xl p-4 border border-indigo-100 self-start sticky top-4">
                <div class="text-xs font-bold text-indigo-700 uppercase mb-3">📊 พรีวิวสด</div>
                <template x-if="!preview.loaded && !preview.error">
                    <div class="text-xs text-gray-500 italic py-8 text-center">เลือกพนักงานและกรอกข้อมูลเพื่อดูผลคำนวณทันที</div>
                </template>
                <template x-if="preview.error">
                    <div class="text-xs text-red-600 bg-red-50 p-3 rounded-lg" x-text="preview.error"></div>
                </template>
                <template x-if="preview.loaded && !preview.error">
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between"><span class="text-gray-500">ฐาน</span><span class="font-semibold" x-text="'฿' + fmt(preview.base_reference)"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Tier</span><span class="font-semibold" x-text="preview.tier_code + ' (×' + (1 + parseFloat(preview.tier_multiplier)).toFixed(2) + ')'"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">หลังคูณ tier</span><span class="font-semibold" x-text="'฿' + fmt(preview.tier_adjusted_bonus)"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">หักเข้างาน</span><span class="font-semibold" :class="preview.attendance_adjustment < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="(preview.attendance_adjustment * 100).toFixed(2) + '%'"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">หลังหัก</span><span class="font-semibold" x-text="'฿' + fmt(preview.final_bonus_net)"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">เดือนหลัง probation</span><span class="font-semibold" x-text="preview.months_after_probation + ' เดือน'"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">ปลดล็อก</span><span class="font-semibold" x-text="(preview.unlock_percentage * 100).toFixed(2) + '%'"></span></div>
                        <div class="border-t border-indigo-200 pt-2 mt-2 flex justify-between items-center">
                            <span class="text-sm font-bold text-indigo-900">ที่จะจ่ายจริง</span>
                            <span class="text-2xl font-bold text-indigo-700" x-text="'฿' + fmt(preview.actual_payment)"></span>
                        </div>
                        <template x-if="preview.warnings && preview.warnings.length > 0">
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-2 text-amber-700 mt-2">
                                <template x-for="w in preview.warnings"><div x-text="'⚠ ' + w"></div></template>
                            </div>
                        </template>
                        <template x-if="!preview.is_active_on_payment">
                            <div class="bg-gray-100 rounded-lg p-2 text-gray-600 mt-2">ⓘ ไม่ active ณ วันจ่าย — payment = 0</div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- ---- BATCH ---- --}}
        <div x-show="tab === 'batch'" x-cloak>
            <form action="{{ route('settings.bonus.batch-calculate') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $selectedCycle->id }}">

                <label class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                    <input type="hidden" name="auto_fill" value="0">
                    <input type="checkbox" name="auto_fill" value="1" checked class="rounded text-emerald-600">
                    <span class="text-sm font-semibold text-emerald-700">ใช้ข้อมูลจริงรายบุคคลอัตโนมัติ</span>
                    <span class="text-xs text-gray-500">— ระบบจะดึง absent / late / leave / clip ของแต่ละคนจาก attendance + worklog</span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tier (เว้นว่าง = ระบบเลือกตาม clip ของแต่ละคน)</label>
                    <select name="tier_code" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">— ระบบเลือกอัตโนมัติรายคน —</option>
                        @foreach($tiers as $tier)
                            <option value="{{ $tier->tier_code }}">{{ $tier->tier_code }} ({{ $tier->tier_name }} ×{{ 1 + (float) $tier->multiplier }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-data="{ search: '', selectAll: false }">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-gray-700">เลือกพนักงาน</label>
                        <input x-model="search" placeholder="ค้น..." class="px-3 py-1 border rounded-lg text-xs w-48">
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border rounded-t-lg">
                        <input type="checkbox" x-model="selectAll" @change="$root.querySelectorAll('input[name=\'employee_ids[]\']:not([disabled])').forEach(cb => { if (!cb.closest('label').classList.contains('hidden')) cb.checked = selectAll })">
                        <span class="text-xs font-semibold">เลือกทั้งหมด (ที่กรอง)</span>
                    </div>
                    <div class="max-h-72 overflow-y-auto border-x border-b rounded-b-lg p-2 space-y-0.5">
                        @foreach($employees as $emp)
                            <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded hover:bg-gray-50 text-xs"
                                   :class="search && !'{{ strtolower($emp->full_name . ' ' . $emp->employee_code) }}'.includes(search.toLowerCase()) ? 'hidden' : ''">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" class="rounded">
                                    <span class="font-semibold">{{ $emp->full_name }}</span>
                                    <span class="text-gray-400">{{ $emp->employee_code }}</span>
                                </div>
                                <span class="text-gray-500">฿{{ number_format((float) ($emp->salaryProfile?->base_salary ?? 0), 0) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit"
                        :disabled="!hasSelectedMonths"
                        :class="!hasSelectedMonths ? 'opacity-40 cursor-not-allowed' : 'hover:bg-violet-700'"
                        class="w-full bg-violet-600 text-white py-2.5 rounded-xl font-bold text-sm"
                        :title="!hasSelectedMonths ? 'ต้องเลือกเดือนที่ใช้คำนวณก่อน (ส่วนที่ 2)' : ''">
                    <span x-show="hasSelectedMonths">คำนวณรายกลุ่ม</span>
                    <span x-show="!hasSelectedMonths" class="flex items-center justify-center gap-2">
                        🔒 เลือกเดือนก่อน → ถึงจะคำนวณได้
                    </span>
                </button>
            </form>
        </div>
    </div>

    {{-- ───── 4. Results table ───── --}}
    <div id="step-4" class="bg-white rounded-2xl border shadow-sm p-5 scroll-mt-4 {{ in_array($currentStatus, ['calculated','reviewed']) ? 'ring-2 ring-indigo-300 border-indigo-300' : '' }}"
         x-data="{ search: '', filter: 'all', breakdownId: null }">
        <div class="flex items-center gap-3 mb-4">
            @if(in_array($currentStatus, ['approved','paid','closed']))
                <div class="w-8 h-8 rounded-full bg-emerald-500 text-white grid place-items-center font-bold">✓</div>
            @else
                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 grid place-items-center font-bold {{ in_array($currentStatus, ['calculated','reviewed']) ? 'animate-pulse ring-4 ring-indigo-200' : '' }}">4</div>
            @endif
            <div class="flex-1">
                <div class="text-sm font-bold text-gray-700">ตรวจและอนุมัติ</div>
                <div class="text-xs text-gray-500">ดู breakdown ราย row, แก้ raycycle, อนุมัติทีเดียว</div>
            </div>
            <div class="flex gap-2 items-center">
                <input x-model="search" placeholder="ค้นชื่อ/รหัส..." class="px-3 py-1.5 border rounded-lg text-xs w-40">
                <select x-model="filter" class="px-3 py-1.5 border rounded-lg text-xs">
                    <option value="all">ทุกสถานะ</option>
                    <option value="calculated">รอตรวจ</option>
                    <option value="approved">อนุมัติแล้ว</option>
                </select>
            </div>
        </div>

        @if($calculations->count() === 0)
            <div class="text-center py-12 text-gray-400 italic">ยังไม่มีข้อมูลคำนวณในรอบนี้ — เลื่อนขึ้นไปข้างบนเพื่อคำนวณ</div>
        @else
            <form action="{{ route('settings.bonus.approve') }}" method="POST">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $selectedCycle->id }}">
                <div class="overflow-x-auto border rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-[11px] uppercase text-gray-500">
                            <tr>
                                <th class="text-left px-3 py-2 w-8"><input type="checkbox" onclick="this.closest('table').querySelectorAll('input[name=\'calculation_ids[]\']').forEach(cb => cb.checked = this.checked)"></th>
                                <th class="text-left px-3 py-2">พนักงาน</th>
                                <th class="text-left px-3 py-2">Tier</th>
                                <th class="text-right px-3 py-2">ฐาน</th>
                                <th class="text-right px-3 py-2">หลังหัก</th>
                                <th class="text-right px-3 py-2">ปลดล็อก</th>
                                <th class="text-right px-3 py-2">จ่ายจริง</th>
                                <th class="text-center px-3 py-2">สถานะ</th>
                                <th class="text-right px-3 py-2 w-24">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($calculations as $calc)
                                @php
                                    $isLocked = in_array($calc->status, ['approved', 'paid']);
                                    $rowStatusInfo = $statusConfig[$calc->status] ?? ['label' => $calc->status, 'color' => 'bg-gray-100 text-gray-700 border-gray-200'];
                                    $searchKey = strtolower(($calc->employee?->full_name ?? '') . ' ' . ($calc->employee?->employee_code ?? ''));
                                @endphp
                                <tr :class="(filter !== 'all' && filter !== '{{ $calc->status }}') || (search && !'{{ $searchKey }}'.includes(search.toLowerCase())) ? 'hidden' : ''">
                                    <td class="px-3 py-2">
                                        @if(!$isLocked)
                                            <input type="checkbox" name="calculation_ids[]" value="{{ $calc->id }}">
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-gray-900">{{ $calc->employee?->full_name ?? '-' }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $calc->employee?->employee_code }} · {{ $calc->months_after_probation }} ด./probation</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded bg-violet-50 text-violet-700">{{ $calc->tier?->tier_code ?? '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-600">{{ number_format((float) $calc->base_reference, 0) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600">{{ number_format((float) $calc->final_bonus_net, 0) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="text-xs font-semibold {{ (float) $calc->unlock_percentage > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                                            {{ number_format((float) $calc->unlock_percentage * 100, 1) }}%
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" @click="breakdownId = (breakdownId === {{ $calc->id }} ? null : {{ $calc->id }})"
                                                class="font-bold text-indigo-700 hover:underline">
                                            ฿{{ number_format((float) $calc->actual_payment, 0) }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full border {{ $rowStatusInfo['color'] }}">
                                            {{ $rowStatusInfo['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if(!$isLocked)
                                            <div class="inline-flex gap-1">
                                                <form action="{{ route('settings.bonus.calculations.recalculate', $calc) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" title="คำนวณซ้ำจากข้อมูลจริง" class="text-blue-600 hover:bg-blue-50 px-2 py-1 rounded">🔄</button>
                                                </form>
                                                <form action="{{ route('settings.bonus.calculations.destroy', $calc) }}" method="POST" class="inline" onsubmit="return confirm('ลบรายการ {{ $calc->employee?->full_name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="ลบ" class="text-red-600 hover:bg-red-50 px-2 py-1 rounded">🗑</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">🔒</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr x-show="breakdownId === {{ $calc->id }}" x-cloak>
                                    <td colspan="9" class="px-6 py-4 bg-indigo-50/50">
                                        <div class="text-xs font-bold text-indigo-700 uppercase mb-2">รายละเอียดการคำนวณ</div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                            <div><span class="text-gray-500">ฐาน:</span> ฿{{ number_format((float) $calc->base_reference, 2) }}</div>
                                            <div><span class="text-gray-500">Tier ×:</span> {{ number_format(1 + (float) $calc->tier_multiplier, 3) }}</div>
                                            <div><span class="text-gray-500">หลังคูณ tier:</span> ฿{{ number_format((float) $calc->tier_adjusted_bonus, 2) }}</div>
                                            <div><span class="text-gray-500">หักเข้างาน:</span> {{ number_format((float) $calc->attendance_adjustment * 100, 2) }}%</div>
                                            <div><span class="text-gray-500">หลังหัก:</span> ฿{{ number_format((float) $calc->final_bonus_net, 2) }}</div>
                                            <div><span class="text-gray-500">ปลดล็อก:</span> {{ number_format((float) $calc->unlock_percentage * 100, 2) }}%</div>
                                            <div><span class="text-gray-500">เดือนพ้น probation:</span> {{ $calc->months_after_probation }}</div>
                                            <div class="font-bold text-indigo-700"><span class="text-gray-500 font-normal">→ จ่ายจริง:</span> ฿{{ number_format((float) $calc->actual_payment, 2) }}</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($cycleSummary['pending_count'] > 0)
                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-xs text-gray-500">เลือกแถวที่ต้องการ แล้วกดอนุมัติ</div>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">
                            ✓ อนุมัติรายการที่เลือก
                        </button>
                    </div>
                @endif
            </form>
        @endif
    </div>

    @endif

    {{-- ───── New cycle modal ───── --}}
    <div x-show="newCycleOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40" @click.self="newCycleOpen = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">สร้างรอบโบนัสใหม่</h3>
                <button @click="newCycleOpen = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form action="{{ route('settings.bonus.cycles.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">รหัสรอบ</label>
                    <input type="text" name="cycle_code" required placeholder="เช่น 2026-JUN" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ปี</label>
                        <input type="number" name="cycle_year" required value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">รอบ</label>
                        <select name="cycle_period" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="june">June (ครึ่งปี)</option>
                            <option value="december">December (ปลายปี)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">วันจ่าย</label>
                    <input type="date" name="payment_date" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">เพดานการจ่าย</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0" max="1" name="max_allocation" value="0.40" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <p class="text-[10px] text-gray-400 mt-0.5">0.40 = 40% — แก้ภายหลังได้</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="newCycleOpen = false" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">สร้าง</button>
                </div>
            </form>
        </div>
    </div>

    @include('settings.partials.tiers-modal')

    {{-- ───── Mark-Paid Preview Modal ───── --}}
    <div x-show="markPaidModal" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40" @click.self="markPaidModal = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">ตรวจก่อนทำเครื่องหมายจ่าย</h3>
                    <p class="text-xs text-gray-500" x-show="markPaidPreview">โบนัสจะถูกบันทึกเข้า payslip <span class="font-semibold text-indigo-700" x-text="markPaidPreview?.target_label"></span></p>
                </div>
                <button @click="markPaidModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <template x-if="markPaidLoading">
                <div class="py-8 text-center text-sm text-gray-500">กำลังตรวจสอบ payslip...</div>
            </template>

            <template x-if="!markPaidLoading && markPaidPreview">
                <div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="bg-emerald-50 rounded-lg p-3 text-center">
                            <div class="text-[10px] font-bold text-emerald-600 uppercase">พร้อมจ่าย</div>
                            <div class="text-2xl font-bold text-emerald-700" x-text="markPaidPreview.eligible_count"></div>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-3 text-center">
                            <div class="text-[10px] font-bold text-amber-600 uppercase">ซ้ำ (ข้าม)</div>
                            <div class="text-2xl font-bold text-amber-700" x-text="markPaidPreview.duplicate_count"></div>
                        </div>
                        <div class="bg-red-50 rounded-lg p-3 text-center">
                            <div class="text-[10px] font-bold text-red-600 uppercase">ติดล็อก</div>
                            <div class="text-2xl font-bold text-red-700" x-text="markPaidPreview.blocked_count"></div>
                        </div>
                    </div>

                    <div class="border rounded-xl overflow-hidden max-h-72 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="text-left px-3 py-2">พนักงาน</th>
                                    <th class="text-right px-3 py-2">ยอด</th>
                                    <th class="text-left px-3 py-2">payslip</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <template x-for="r in markPaidPreview.rows">
                                    <tr :class="r.blocked ? 'bg-red-50' : (r.already_posted ? 'bg-amber-50' : '')">
                                        <td class="px-3 py-2">
                                            <span class="font-semibold" x-text="r.employee_name"></span>
                                            <span class="text-gray-400 ml-1" x-text="r.employee_code"></span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="'฿' + Number(r.amount).toLocaleString()"></td>
                                        <td class="px-3 py-2">
                                            <template x-if="r.blocked">
                                                <span class="text-red-700 font-semibold">❌ ปิดแล้ว</span>
                                            </template>
                                            <template x-if="r.already_posted && !r.blocked">
                                                <span class="text-amber-700">⚠ ซ้ำ — ข้าม</span>
                                            </template>
                                            <template x-if="!r.blocked && !r.already_posted">
                                                <span class="text-emerald-700">✓ <span x-text="r.payslip_status === 'not_generated' ? 'ยังไม่ generate' : r.payslip_status"></span></span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <template x-if="markPaidPreview.blocked_count > 0">
                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                            ⚠️ มี payslip ที่ปิดแล้ว — ต้องเปิด payslip เดือน <span class="font-semibold" x-text="markPaidPreview.target_label"></span> ก่อน แล้วค่อยกลับมาทำเครื่องหมายจ่าย
                        </div>
                    </template>

                    <div class="flex justify-between items-center mt-4">
                        <div class="text-sm">
                            รวมที่จะ post: <span class="font-bold text-indigo-700" x-text="'฿' + Number(markPaidPreview.total_amount).toLocaleString()"></span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="markPaidModal = false" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                            <button :disabled="markPaidPreview.blocked_count > 0 || markPaidPreview.eligible_count === 0"
                                    @click="markPaidModal = false; $refs.markPaidForm.submit()"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                                ✓ ยืนยันทำเครื่องหมายจ่าย
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function bonusManager(cfg) {
    return {
        cycleId: cfg.cycleId,
        hasSelectedMonths: cfg.hasSelectedMonths ?? false,
        routes: cfg.routes,
        csrf: cfg.csrf,
        newCycleOpen: false,
        tierListModal: false,
        editTier: null,
        editModal: false,

        single: {
            employee_id: '',
            base_reference: 0,
            tier_code: '',
            absent_days: 0,
            late_count: 0,
            leave_days: 0,
            clip_duration_minutes_per_month: null,
            qualified_months: null,
            loading: false,
            metricsNote: '',
        },

        preview: { loaded: false, error: null, warnings: [] },
        previewTimer: null,
        markPaidModal: false,
        markPaidLoading: false,
        markPaidPreview: null,

        async openMarkPaidPreview() {
            this.markPaidModal = true;
            this.markPaidLoading = true;
            this.markPaidPreview = null;
            try {
                const r = await fetch(this.routes.previewPayslipPost, { headers: { 'Accept': 'application/json' }});
                this.markPaidPreview = await r.json();
            } catch (e) {
                alert('โหลดพรีวิวไม่สำเร็จ: ' + e.message);
                this.markPaidModal = false;
            } finally {
                this.markPaidLoading = false;
            }
        },

        openEdit(tier) { this.editTier = tier; this.editModal = true; },

        fmt(n) { return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

        async loadMetrics() {
            if (!this.single.employee_id || !this.cycleId) return;
            this.single.loading = true;
            this.single.metricsNote = '';
            try {
                const url = this.routes.metrics.replace('/employees/0/', '/employees/' + this.single.employee_id + '/');
                const r = await fetch(url, { headers: { 'Accept': 'application/json' }});
                if (!r.ok) throw new Error('โหลดข้อมูลไม่สำเร็จ');
                const m = await r.json();
                this.single.base_reference = m.base_reference;
                this.single.absent_days = m.absent_days;
                this.single.late_count = m.late_count;
                this.single.leave_days = m.leave_days;
                this.single.clip_duration_minutes_per_month = m.clip_duration_minutes_per_month;
                this.single.qualified_months = m.qualified_months;
                if (!m.has_data) {
                    this.single.metricsNote = m.note || '';
                } else {
                    this.single.metricsNote = `โหลดจาก ${m.selected_months_count} เดือนที่เลือก`;
                }
                this.schedulePreview();
            } catch (e) {
                this.single.metricsNote = 'ไม่สามารถโหลดข้อมูล: ' + e.message;
            } finally {
                this.single.loading = false;
            }
        },

        schedulePreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => this.runPreview(), 400);
        },

        async runPreview() {
            if (!this.single.employee_id || !this.cycleId || !this.single.base_reference) {
                this.preview = { loaded: false, error: null, warnings: [] };
                return;
            }
            try {
                const fd = new FormData();
                fd.append('cycle_id', this.cycleId);
                fd.append('employee_id', this.single.employee_id);
                fd.append('base_reference', this.single.base_reference);
                if (this.single.tier_code) fd.append('tier_code', this.single.tier_code);
                ['absent_days','late_count','leave_days','clip_duration_minutes_per_month','qualified_months'].forEach(k => {
                    if (this.single[k] !== null && this.single[k] !== '') fd.append(k, this.single[k]);
                });
                const r = await fetch(this.routes.preview, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await r.json();
                if (!r.ok) {
                    this.preview = { loaded: false, error: data.error || data.message || 'พรีวิวไม่สำเร็จ', warnings: [] };
                    return;
                }
                this.preview = { ...data.result, warnings: data.warnings || [], loaded: true, error: null };
            } catch (e) {
                this.preview = { loaded: false, error: e.message, warnings: [] };
            }
        },
    };
}

function cycleSettingsForm(init) {
    return {
        absentPct: init.absentPct,
        latePct: init.latePct,
        leavePct: init.leavePct,
        maxAllocPct: init.maxAllocPct,
        juneMaxPct: init.juneMaxPct,
        leaveFreeDays: init.leaveFreeDays,
    };
}
</script>
@endsection
