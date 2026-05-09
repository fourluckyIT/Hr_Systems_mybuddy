@php
    $baseSalary = $employee->salaryProfile?->base_salary ?? 0;
    $ceilingPercent = $employee->advance_ceiling_percent ?? 0;
    $ceilingAmount = ($baseSalary * $ceilingPercent) / 100;
    $totalAdvances = $claims->where('type', 'advance')->sum('amount');
    $isOverCeiling = $ceilingPercent > 0 && $totalAdvances > $ceilingAmount;
    $remainingQuota = $ceilingPercent > 0 ? max(0, $ceilingAmount - $totalAdvances) : null;
    $canManageAdjustments = auth()->user()?->hasRole('admin') ?? false;

    $extraIncomes = $extraIncomes ?? \App\Models\ExtraIncomeEntry::where('employee_id', $employee->id)
        ->where('month', $month)->where('year', $year)->orderByDesc('id')->get();
    $extraTotal = $extraIncomes->sum('amount');
    $totalRows = $extraIncomes->count() + $claims->count();
@endphp

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden"
     x-data="{
        tab: 'all',
        openForm: false,
        formType: '{{ $canManageAdjustments ? 'extra' : 'claim' }}',
        showCeiling: false,
        money(v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        ceilingPercentDraft: {{ (float) $ceilingPercent }},
        baseSalary: {{ (float) $baseSalary }},
        quotaAmount() { return (this.baseSalary * this.ceilingPercentDraft) / 100; }
     }">

    {{-- Header: title + filter pills + add button on one row --}}
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">💸</div>
            <div>
                <div class="font-bold text-sm text-gray-900 leading-tight">
                    {{ $canManageAdjustments ? 'รายรับพิเศษ & เบิกเงิน' : 'เบิกเงิน / ขอเบิกล่วงหน้า' }}
                </div>
                <div class="text-[10px] text-gray-400">{{ $totalRows }} รายการในเดือนนี้</div>
            </div>
        </div>
        <div class="flex items-center gap-1.5">
            <div class="flex items-center bg-gray-100 p-0.5 rounded-lg">
                <button @click="tab = 'all'"       :class="tab === 'all'       ? 'bg-white shadow text-gray-800' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all">ทั้งหมด</button>
                <button @click="tab = 'income'"    :class="tab === 'income'    ? 'bg-white shadow text-emerald-700' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all">+ รายรับ</button>
                <button @click="tab = 'deduction'" :class="tab === 'deduction' ? 'bg-white shadow text-rose-700' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all">− รายหัก</button>
            </div>
            <button @click="openForm = !openForm"
                    :class="openForm ? 'bg-gray-200 text-gray-700' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors flex items-center gap-1">
                <span x-text="openForm ? '✕ ยกเลิก' : '{{ $canManageAdjustments ? '+ เพิ่ม' : '+ ขอเบิกเงิน' }}'"></span>
            </button>
        </div>
    </div>

    {{-- KPI pills row — flex-wrap so narrow widths break neatly instead of clipping --}}
    <div class="px-4 py-3 flex flex-wrap gap-2">
        <div class="flex-1 min-w-[140px] px-3 py-2 bg-emerald-50/60 border border-emerald-100 rounded-xl">
            <div class="text-[10px] text-emerald-700 font-bold uppercase tracking-wide">รายรับพิเศษ</div>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-base font-extrabold text-emerald-700">฿{{ number_format($extraTotal, 2) }}</span>
                <span class="text-[10px] text-emerald-500">{{ $extraIncomes->count() }} รายการ</span>
            </div>
        </div>
        <div class="flex-1 min-w-[140px] px-3 py-2 {{ $isOverCeiling ? 'bg-rose-50/60 border-rose-200' : 'bg-rose-50/40 border-rose-100' }} border rounded-xl">
            <div class="text-[10px] text-rose-700 font-bold uppercase tracking-wide flex items-center gap-1">
                เบิกสะสม
                @if($isOverCeiling)<span class="text-rose-600 font-black">⚠</span>@endif
            </div>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-base font-extrabold {{ $isOverCeiling ? 'text-rose-600' : 'text-rose-700' }}">฿{{ number_format($totalAdvances, 2) }}</span>
                <span class="text-[10px] text-rose-500">{{ $ceilingPercent > 0 ? 'เพดาน '.$ceilingPercent.'%' : 'ไม่จำกัด' }}</span>
            </div>
        </div>
        <div class="flex-1 min-w-[140px] px-3 py-2 bg-indigo-50/60 border border-indigo-100 rounded-xl">
            <div class="text-[10px] text-indigo-700 font-bold uppercase tracking-wide">โควต้าคงเหลือ</div>
            <div class="flex items-baseline gap-1.5 mt-0.5 whitespace-nowrap">
                @if($ceilingPercent > 0)
                    <span class="text-base font-extrabold text-indigo-700">฿{{ number_format($remainingQuota, 2) }}</span>
                    <span class="text-[10px] text-indigo-500">/ {{ number_format($ceilingAmount, 0) }}</span>
                @else
                    <span class="text-base font-extrabold text-indigo-700">ไม่จำกัด</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Sub-action row: ceiling toggle (admin) --}}
    @if($canManageAdjustments)
    <div class="px-4 pb-3 -mt-1">
        <button @click="showCeiling = !showCeiling"
                :class="showCeiling ? 'bg-amber-100 text-amber-700 border-amber-300' : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 hover:text-gray-700'"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[11px] font-semibold transition-all">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            ตั้งเพดานเบิกล่วงหน้า
        </button>
    </div>
    @endif

    {{-- Inline Add Form --}}
    <div x-show="openForm" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mx-4 mb-3 p-3 bg-gray-50 border border-gray-100 rounded-xl">

        @if($canManageAdjustments)
        <div class="flex items-center gap-1.5 mb-3">
            <button @click="formType = 'extra'"
                    :class="formType === 'extra' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                    class="px-3 py-1 rounded-md text-[11px] font-bold transition-colors">+ รายรับพิเศษ</button>
            <button @click="formType = 'claim'"
                    :class="formType === 'claim' ? 'bg-rose-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                    class="px-3 py-1 rounded-md text-[11px] font-bold transition-colors">− เบิก / ล่วงหน้า</button>
        </div>
        @else
        <div class="mb-3 px-3 py-2 bg-rose-50 border border-rose-100 rounded-lg text-[11px] text-rose-700">
            <span class="font-bold">ขอเบิกเงิน</span> — กรอกรายละเอียดด้านล่าง คำขอจะอยู่ในสถานะรออนุมัติจนกว่า HR/Admin จะตรวจสอบ
        </div>
        @endif

        {{-- Extra Income (Admin only) --}}
        @if($canManageAdjustments)
        <form x-show="formType === 'extra'" method="POST" action="{{ route('workspace.extra-income.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="grid grid-cols-1 md:grid-cols-12 gap-2">
            @csrf
            <div class="md:col-span-5">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">รายการ</label>
                <input type="text" name="label" required maxlength="200" placeholder="เช่น โบนัส YouTube, Tip"
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div class="md:col-span-5">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">หมวดหมู่</label>
                <input type="text" name="category" maxlength="80" placeholder="ไม่บังคับ"
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">จำนวน</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-bold text-emerald-700 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div class="md:col-span-12 flex items-center justify-between pt-1">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" name="include_in_payslip" value="1" checked class="rounded border-gray-300 text-emerald-600">
                    <span class="text-[11px] text-gray-600">รวมเข้ารอบเงินเดือน (มีผลต่อ Net Pay)</span>
                </label>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors shadow-sm">
                    บันทึกรายรับ
                </button>
            </div>
        </form>
        @endif

        {{-- Claim / Advance --}}
        @if($canManageAdjustments)
        {{-- Admin layout: 6 columns with type selector --}}
        <form x-show="formType === 'claim'" method="POST" action="{{ route('workspace.claims.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="grid grid-cols-1 md:grid-cols-12 gap-2">
            @csrf
            <div class="md:col-span-5">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">รายละเอียด</label>
                <input type="text" name="description" required maxlength="255" placeholder="เช่น เบิกเงินล่วงหน้า, ค่ารักษา"
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
            </div>
            <div class="md:col-span-3">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">ประเภท</label>
                <select name="type" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                    <option value="advance">เบิกล่วงหน้า (หัก)</option>
                    <option value="reimbursement">เบิกคืน (รายรับ)</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">จำนวน</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-bold text-rose-600 focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">วันที่</label>
                <input type="date" name="claim_date" value="{{ date('Y-m-d') }}" required
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
            </div>
            <div class="md:col-span-12 flex justify-end pt-1">
                <button type="submit" class="px-4 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors shadow-sm">
                    บันทึกการเบิก
                </button>
            </div>
        </form>
        @else
        {{-- Owner layout: simpler — type is locked to advance, no selector needed --}}
        <form x-show="formType === 'claim'" method="POST" action="{{ route('workspace.claims.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="space-y-2">
            @csrf
            <input type="hidden" name="type" value="advance">
            <div>
                <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">รายละเอียด</label>
                <input type="text" name="description" required maxlength="255" placeholder="เช่น เบิกเงินล่วงหน้า, ค่ารักษา"
                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">จำนวน (บาท)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                           class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-bold text-rose-600 focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-0.5 uppercase">วันที่ขอเบิก</label>
                    <input type="date" name="claim_date" value="{{ date('Y-m-d') }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                </div>
            </div>
            <div class="flex justify-end pt-1">
                <button type="submit" class="px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors shadow-sm">
                    บันทึกการเบิก
                </button>
            </div>
        </form>
        @endif
    </div>

    {{-- Advance Ceiling (collapsible) --}}
    @if($canManageAdjustments)
    <div x-show="showCeiling" x-cloak x-transition class="mx-4 mb-3 p-3 bg-amber-50/50 border border-amber-200 rounded-xl">
        <form action="{{ route('workspace.updateAdvanceCeiling', $employee->id) }}" method="POST">
            @csrf @method('PATCH')
            {{-- Row 1: label + live % --}}
            <div class="flex justify-between items-center mb-2">
                <label class="text-[11px] font-bold text-amber-800 uppercase">เพดานเบิกล่วงหน้า (% ของเงินเดือน)</label>
                <span class="text-xs font-black text-amber-700" x-text="ceilingPercentDraft + '%'"></span>
            </div>
            {{-- Row 2: slider stretches, controls + button on the right, all in one row with no overflow --}}
            <div class="flex items-center gap-2">
                <input type="range" min="0" max="100" step="1" x-model.number="ceilingPercentDraft"
                       class="flex-1 min-w-0 accent-amber-600">
                <input type="number" name="advance_ceiling_percent" x-model.number="ceilingPercentDraft"
                       min="0" max="100" step="0.5"
                       class="w-14 shrink-0 border border-amber-200 rounded-md px-1.5 py-1 text-sm font-bold text-amber-700 text-center bg-white">
                <span class="shrink-0 text-[11px] text-amber-700 whitespace-nowrap font-semibold">= ฿<span x-text="money(quotaAmount())"></span></span>
                <button type="submit"
                        class="shrink-0 px-3 py-1.5 bg-amber-600 text-white rounded-lg text-[11px] font-bold hover:bg-amber-700 transition-colors shadow-sm whitespace-nowrap">
                    บันทึก
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Card list (replaces wide table — works on any width) --}}
    <div class="px-4 pb-4 space-y-2">
        @foreach($extraIncomes as $e)
        <div x-show="tab === 'all' || tab === 'income'"
             class="flex items-center gap-3 p-3 bg-emerald-50/30 border border-emerald-100 rounded-xl hover:bg-emerald-50/50 transition-colors group">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-black shrink-0">+</div>
            <div class="flex-grow min-w-0">
                <div class="font-bold text-sm text-gray-900 truncate">{{ $e->label }}</div>
                <div class="flex items-center gap-2 text-[10px] text-gray-500 mt-0.5">
                    <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded font-bold">รายรับพิเศษ</span>
                    <span>{{ $e->category ?? 'ทั่วไป' }}</span>
                    @if(!$e->include_in_payslip)<span class="text-amber-600 font-bold">• ไม่รวมในสลิป</span>@endif
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="font-extrabold text-emerald-700 tabular-nums">+{{ number_format($e->amount, 2) }}</div>
                <div class="text-[9px] text-emerald-600 font-bold">✓ บันทึกแล้ว</div>
            </div>
            @if($canManageAdjustments)
            <form method="POST" action="{{ route('workspace.extra-income.delete', $e) }}" class="shrink-0" onsubmit="return confirm('ลบรายการนี้?')">
                @csrf @method('DELETE')
                <button type="submit" class="p-1.5 text-gray-300 hover:text-rose-500 rounded transition-colors opacity-0 group-hover:opacity-100" title="ลบ">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
            @endif
        </div>
        @endforeach

        @foreach($claims as $claim)
        @php
            $isDeduction = $claim->type === 'advance';
            $rowTab = $isDeduction ? 'deduction' : 'income';
            $rowClass    = $isDeduction ? 'bg-rose-50/30 border-rose-100 hover:bg-rose-50/50' : 'bg-sky-50/30 border-sky-100 hover:bg-sky-50/50';
            $iconClass   = $isDeduction ? 'bg-rose-100 text-rose-600'   : 'bg-sky-100 text-sky-600';
            $badgeClass  = $isDeduction ? 'bg-rose-100 text-rose-700'   : 'bg-sky-100 text-sky-700';
            $amountClass = $isDeduction ? 'text-rose-700'               : 'text-sky-700';
        @endphp
        <div x-show="tab === 'all' || tab === '{{ $rowTab }}'"
             class="flex items-center gap-3 p-3 border rounded-xl transition-colors group {{ $rowClass }}">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-black shrink-0 {{ $iconClass }}">{{ $isDeduction ? '−' : '+' }}</div>
            <div class="flex-grow min-w-0">
                <div class="font-bold text-sm text-gray-900 truncate">{{ $claim->description }}</div>
                <div class="flex items-center gap-2 text-[10px] text-gray-500 mt-0.5">
                    <span class="px-1.5 py-0.5 rounded font-bold whitespace-nowrap {{ $badgeClass }}">{{ $isDeduction ? 'เบิกล่วงหน้า' : 'เบิกคืน' }}</span>
                    <span class="whitespace-nowrap">{{ $claim->claim_date?->format('d/m/Y') }}</span>
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="font-extrabold tabular-nums {{ $amountClass }}">{{ $isDeduction ? '-' : '+' }}{{ number_format($claim->amount, 2) }}</div>
                @if($claim->status === 'approved')
                    <div class="text-[9px] text-indigo-600 font-bold">✓ อนุมัติแล้ว</div>
                @else
                    <div class="text-[9px] text-amber-600 font-bold">⏳ รออนุมัติ</div>
                @endif
            </div>
            @if($canManageAdjustments)
            <div class="flex items-center gap-1 shrink-0">
                @if($claim->status === 'pending')
                <form action="{{ route('workspace.claims.approve', $claim->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="px-2.5 py-1 bg-indigo-600 text-white rounded-lg text-[11px] font-bold hover:bg-indigo-700 transition-colors whitespace-nowrap shadow-sm">อนุมัติ</button>
                </form>
                @endif
                <a href="{{ route('workspace.claims.print', $claim->id) }}" target="_blank" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded transition-colors opacity-0 group-hover:opacity-100" title="พิมพ์ใบสำคัญจ่าย / ใบเบิก">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                </a>
                <form action="{{ route('workspace.claims.delete', $claim->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-1.5 text-gray-300 hover:text-rose-500 rounded transition-colors opacity-0 group-hover:opacity-100" title="ลบ">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
            @endif
        </div>
        @endforeach

        @if($extraIncomes->isEmpty() && $claims->isEmpty())
        <div class="py-10 text-center bg-gray-50/50 border-2 border-dashed border-gray-200 rounded-xl">
            <div class="text-4xl mb-2 opacity-30">📋</div>
            <div class="text-gray-500 text-sm font-semibold">ยังไม่มีรายการในเดือนนี้</div>
            <div class="text-[11px] text-gray-400 mt-1">คลิกปุ่ม "{{ $canManageAdjustments ? '+ เพิ่ม' : '+ ขอเบิกเงิน' }}" ด้านบนเพื่อเริ่มต้น</div>
        </div>
        @endif
    </div>
</div>
