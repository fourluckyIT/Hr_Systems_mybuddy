@php
    $baseSalary = $employee->salaryProfile?->base_salary ?? 0;
    $ceilingPercent = $employee->advance_ceiling_percent ?? 0;
    $ceilingAmount = ($baseSalary * $ceilingPercent) / 100;
    $totalAdvances = $claims->where('type', 'advance')->sum('amount');
    $isOverCeiling = $ceilingPercent > 0 && $totalAdvances > $ceilingAmount;
    $remainingQuota = $ceilingPercent > 0 ? ($ceilingAmount - $totalAdvances) : null;
    $canManageAdjustments = auth()->user()?->hasRole('admin') ?? false;

    $extraIncomes = $extraIncomes ?? \App\Models\ExtraIncomeEntry::where('employee_id', $employee->id)
        ->where('month', $month)->where('year', $year)->orderByDesc('id')->get();
    $extraTotal = $extraIncomes->sum('amount');
    $totalRows = $extraIncomes->count() + $claims->count();
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
     x-data="{
        tab: 'all',
        openForm: false,
        formType: 'extra',
        showCeiling: false,
        money(v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        ceilingPercentDraft: {{ (float) $ceilingPercent }},
        baseSalary: {{ (float) $baseSalary }},
        quotaAmount() { return (this.baseSalary * this.ceilingPercentDraft) / 100; }
     }">

    <!-- Header -->
    <div class="px-4 py-3 bg-indigo-600 text-white flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-semibold text-sm">รายรับพิเศษและเบิกเงิน</span>
        </div>
        <div class="flex items-center gap-1 bg-white/15 p-0.5 rounded-md">
            <button @click="tab = 'all'" :class="tab === 'all' ? 'bg-white text-indigo-700' : 'text-white/70 hover:text-white'"
                    class="px-2.5 py-1 rounded text-[11px] font-semibold transition-all">ทั้งหมด</button>
            <button @click="tab = 'income'" :class="tab === 'income' ? 'bg-white text-green-700' : 'text-white/70 hover:text-white'"
                    class="px-2.5 py-1 rounded text-[11px] font-semibold transition-all">รายรับ</button>
            <button @click="tab = 'deduction'" :class="tab === 'deduction' ? 'bg-white text-rose-700' : 'text-white/70 hover:text-white'"
                    class="px-2.5 py-1 rounded text-[11px] font-semibold transition-all">รายหัก</button>
        </div>
    </div>

    <!-- Compact stats strip -->
    <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100 bg-gray-50/50">
        <div class="px-4 py-3">
            <div class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">รายรับพิเศษ</div>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-lg font-bold text-gray-800">฿{{ number_format($extraTotal, 2) }}</span>
                <span class="text-[10px] text-gray-400">{{ $extraIncomes->count() }} รายการ</span>
            </div>
        </div>
        <div class="px-4 py-3">
            <div class="text-[10px] text-gray-500 font-medium uppercase tracking-wide flex items-center gap-1">
                เบิกสะสม
                @if($isOverCeiling)
                    <span class="text-rose-500">•เกินเพดาน</span>
                @endif
            </div>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-lg font-bold {{ $isOverCeiling ? 'text-rose-600' : 'text-gray-800' }}">฿{{ number_format($totalAdvances, 2) }}</span>
                <span class="text-[10px] text-gray-400">{{ $ceilingPercent > 0 ? $ceilingPercent.'%' : 'ไม่จำกัด' }}</span>
            </div>
        </div>
        <div class="px-4 py-3">
            <div class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">โควต้าคงเหลือ</div>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-lg font-bold text-gray-800" x-text="ceilingPercentDraft > 0 ? '฿' + money({{ (float) $remainingQuota }}) : 'ไม่จำกัด'"></span>
                @if($ceilingPercent > 0)
                    <span class="text-[10px] text-gray-400">สูงสุด {{ number_format($ceilingAmount, 2) }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Action row -->
    <div class="px-4 py-2.5 flex items-center justify-between border-b border-gray-100">
        <span class="text-xs text-gray-500">
            {{ $totalRows }} รายการ
        </span>
        <div class="flex items-center gap-1.5">
            @if($canManageAdjustments)
            <button @click="showCeiling = !showCeiling"
                    class="text-[11px] text-gray-500 hover:text-gray-700 px-2 py-1 rounded hover:bg-gray-100 transition-colors">
                ⚙ ตั้งเพดานเบิก
            </button>
            @endif
            <button @click="openForm = !openForm"
                    :class="openForm ? 'bg-gray-200 text-gray-600' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                    class="px-3 py-1.5 rounded-md text-xs font-semibold transition-colors flex items-center gap-1">
                <svg x-show="!openForm" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                <svg x-show="openForm" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                <span x-text="openForm ? 'ยกเลิก' : 'เพิ่มรายการ'"></span>
            </button>
        </div>
    </div>

    <!-- Inline Add Form -->
    <div x-show="openForm" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="p-4 bg-gray-50 border-b border-gray-100">

        <div class="flex items-center gap-2 mb-3">
            <button @click="formType = 'extra'"
                    :class="formType === 'extra' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                    class="px-3 py-1 rounded-md text-[11px] font-semibold transition-colors">รายรับพิเศษ</button>
            <button @click="formType = 'claim'"
                    :class="formType === 'claim' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                    class="px-3 py-1 rounded-md text-[11px] font-semibold transition-colors">เบิก / ล่วงหน้า</button>
        </div>

        <!-- Extra Income -->
        <form x-show="formType === 'extra'" method="POST" action="{{ route('workspace.extra-income.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2">
            @csrf
            <div class="md:col-span-3">
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">รายการ</label>
                <input type="text" name="label" required maxlength="200" placeholder="เช่น โบนัส YouTube, Tip"
                       class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">หมวดหมู่</label>
                <input type="text" name="category" maxlength="80" placeholder="ไม่บังคับ"
                       class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">จำนวน (฿)</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                       class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm font-semibold text-green-700 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="md:col-span-6 flex items-center justify-between pt-2">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" name="include_in_payslip" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-[11px] text-gray-600">รวมเข้ารอบเงินเดือน (มีผลต่อ Net Pay)</span>
                </label>
                <button type="submit" class="px-4 py-1.5 bg-green-600 text-white rounded-md text-xs font-semibold hover:bg-green-700 transition-colors">
                    + เพิ่มรายรับ
                </button>
            </div>
        </form>

        <!-- Claim / Advance -->
        <form x-show="formType === 'claim'" method="POST" action="{{ route('workspace.claims.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2">
            @csrf
            <div class="md:col-span-3">
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">รายละเอียด</label>
                <input type="text" name="description" required maxlength="255" placeholder="เช่น เบิกเงินล่วงหน้า, ค่ารักษา"
                       class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">ประเภท</label>
                <select name="type" class="w-full border border-gray-200 rounded-md px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="advance">เบิกล่วงหน้า (หัก)</option>
                    <option value="reimbursement">เบิกคืน (รายรับ)</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">จำนวน (฿)</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                       class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm font-semibold text-rose-600 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">วันที่</label>
                <input type="date" name="claim_date" value="{{ date('Y-m-d') }}" required
                       class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="md:col-span-6 flex justify-end pt-2">
                <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-semibold hover:bg-indigo-700 transition-colors">
                    + เพิ่มรายการ
                </button>
            </div>
        </form>
    </div>

    <!-- Advance Ceiling (collapsible) -->
    @if($canManageAdjustments)
    <div x-show="showCeiling" x-cloak x-transition class="p-4 bg-amber-50/50 border-b border-amber-100">
        <form action="{{ route('workspace.updateAdvanceCeiling', $employee->id) }}" method="POST" class="flex flex-col md:flex-row md:items-end gap-3">
            @csrf @method('PATCH')
            <div class="flex-grow">
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-[11px] font-medium text-gray-600">เพดานเบิกล่วงหน้า (% ของเงินเดือน)</label>
                    <span class="text-xs font-bold text-indigo-700" x-text="ceilingPercentDraft + '%'"></span>
                </div>
                <div class="flex items-center gap-3">
                    <input type="range" min="0" max="100" step="1" x-model.number="ceilingPercentDraft" class="flex-grow accent-indigo-600">
                    <input type="number" name="advance_ceiling_percent" x-model.number="ceilingPercentDraft" min="0" max="100" step="0.5"
                           class="w-20 border border-gray-200 rounded-md px-2 py-1 text-sm font-semibold text-indigo-700 text-center">
                    <span class="text-[11px] text-gray-500 whitespace-nowrap">= ฿<span x-text="money(quotaAmount())"></span></span>
                </div>
            </div>
            <button type="submit" class="px-4 py-1.5 bg-indigo-700 text-white rounded-md text-xs font-semibold hover:bg-indigo-800 transition-colors">
                บันทึกเพดาน
            </button>
        </form>
    </div>
    @endif

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">
                    <th class="px-4 py-2 text-left">รายละเอียด</th>
                    <th class="px-4 py-2 text-left">ประเภท</th>
                    <th class="px-4 py-2 text-right">จำนวน</th>
                    <th class="px-4 py-2 text-center">สถานะ</th>
                    @if($canManageAdjustments)
                    <th class="px-4 py-2 text-right w-24">จัดการ</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($extraIncomes as $e)
                <tr x-show="tab === 'all' || tab === 'income'" class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-gray-800 text-sm">{{ $e->label }}</div>
                        <div class="text-[10px] text-gray-400">{{ $e->category ?? 'ทั่วไป' }}</div>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 rounded text-[10px] font-semibold border border-green-100">
                            <span class="w-1 h-1 rounded-full bg-green-500"></span>
                            รายรับพิเศษ
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <div class="font-semibold text-green-700">+{{ number_format($e->amount, 2) }}</div>
                        @if(!$e->include_in_payslip)
                            <div class="text-[9px] text-amber-600 font-medium">ไม่รวมในสลิป</div>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <span class="text-[10px] text-green-600 font-semibold">✓ บันทึก</span>
                    </td>
                    @if($canManageAdjustments)
                    <td class="px-4 py-2.5 text-right">
                        <form method="POST" action="{{ route('workspace.extra-income.delete', $e) }}" class="inline" onsubmit="return confirm('ลบรายการนี้?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1 text-gray-300 hover:text-rose-500 rounded transition-colors" title="ลบ">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </form>
                    </td>
                    @endif
                </tr>
                @endforeach

                @foreach($claims as $claim)
                @php
                    $isDeduction = $claim->type === 'advance';
                    $rowTab = $isDeduction ? 'deduction' : 'income';
                @endphp
                <tr x-show="tab === 'all' || tab === '{{ $rowTab }}'" class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-gray-800 text-sm">{{ $claim->description }}</div>
                        <div class="text-[10px] text-gray-400">{{ $claim->claim_date?->format('d/m/Y') }}</div>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 {{ $isDeduction ? 'bg-rose-50 text-rose-700 border-rose-100' : 'bg-sky-50 text-sky-700 border-sky-100' }} rounded text-[10px] font-semibold border">
                            <span class="w-1 h-1 rounded-full {{ $isDeduction ? 'bg-rose-500' : 'bg-sky-500' }}"></span>
                            {{ $isDeduction ? 'เบิกล่วงหน้า' : 'เบิกคืน' }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <div class="font-semibold {{ $isDeduction ? 'text-rose-700' : 'text-sky-700' }}">
                            {{ $isDeduction ? '-' : '+' }}{{ number_format($claim->amount, 2) }}
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        @if($claim->status === 'approved')
                            <span class="text-[10px] text-indigo-600 font-semibold">✓ อนุมัติ</span>
                        @else
                            <span class="text-[10px] text-amber-600 font-semibold">รออนุมัติ</span>
                        @endif
                    </td>
                    @if($canManageAdjustments)
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">
                            @if($claim->status === 'pending')
                            <form action="{{ route('workspace.claims.approve', $claim->id) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-[10px] font-semibold hover:bg-indigo-100 transition-colors">อนุมัติ</button>
                            </form>
                            @endif
                            <form action="{{ route('workspace.claims.delete', $claim->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-gray-300 hover:text-rose-500 rounded transition-colors" title="ลบ">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @endforeach

                @if($extraIncomes->isEmpty() && $claims->isEmpty())
                <tr>
                    <td colspan="{{ $canManageAdjustments ? 5 : 4 }}" class="px-4 py-10 text-center">
                        <div class="text-gray-400 text-sm">ยังไม่มีรายการปรับปรุงหรือโบนัสในเดือนนี้</div>
                        <div class="text-[11px] text-gray-400 mt-1">คลิก "เพิ่มรายการ" เพื่อเริ่มต้น</div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
