@php
    $baseSalary = $employee->salaryProfile?->base_salary ?? 0;
    $ceilingPercent = $employee->advance_ceiling_percent ?? 0;
    $ceilingAmount = ($baseSalary * $ceilingPercent) / 100;
    $totalAdvances = $claims->where('type', 'advance')->sum('amount');
    $isOverCeiling = $ceilingPercent > 0 && $totalAdvances > $ceilingAmount;
    $remainingQuota = $ceilingPercent > 0 ? ($ceilingAmount - $totalAdvances) : null;
@endphp

<!-- Cash Advance & Expense Claims Module -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden" x-data="{
    openManage: false,
    detailText: '',
    baseSalary: {{ (float) $baseSalary }},
    totalAdvances: {{ (float) $totalAdvances }},
    ceilingPercentDraft: {{ (float) $ceilingPercent }},
    money(v) {
        return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    quotaAmount() {
        return (this.baseSalary * this.ceilingPercentDraft) / 100;
    },
    quotaRemaining() {
        return this.quotaAmount() - this.totalAdvances;
    }
}">
    <div class="px-4 py-3 bg-gray-800 text-white font-semibold text-sm flex justify-between items-center">
        <span>ประวัติการเบิกเงิน</span>
        <span class="text-xs opacity-70">บันทึกยอดหัก หรือยอดบวกเพิ่ม</span>
    </div>

    <div class="p-4">
        <div class="mb-4 grid grid-cols-1 gap-3">
            <div class="rounded-xl border bg-gray-50 px-4 py-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">ยอดเบิกสะสม</div>
                <div class="mt-1 text-2xl font-bold {{ $isOverCeiling ? 'text-red-600' : 'text-gray-800' }}">{{ number_format($totalAdvances, 2) }} ฿</div>
                <div class="text-xs text-gray-400">เพดาน <span x-text="ceilingPercentDraft > 0 ? money(quotaAmount()) + ' ฿' : 'ไม่จำกัด'"></span></div>
            </div>
            <div class="rounded-xl border bg-gray-50 px-4 py-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Quota ปัจจุบัน</div>
                <div class="mt-1 text-2xl font-bold" :class="quotaRemaining() < 0 ? 'text-red-600' : 'text-gray-800'" x-text="ceilingPercentDraft > 0 ? money(quotaRemaining()) + ' ฿' : 'ไม่จำกัด'"></div>
                <div class="text-xs text-gray-400">วงเงินรวม <span x-text="ceilingPercentDraft > 0 ? money(quotaAmount()) + ' ฿' : 'ไม่จำกัด'"></span></div>
            </div>
            <button type="button" @click="openManage = !openManage" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-left transition hover:bg-indigo-100">
                <div class="text-[10px] font-bold uppercase tracking-wide text-indigo-400">จัดการ</div>
                <div class="mt-1 text-lg font-bold text-indigo-700">{{ 'เปิดส่วนตั้งค่าและเพิ่มรายการ' }}</div>
                <div class="text-xs text-indigo-500" x-text="openManage ? 'คลิกเพื่อซ่อน' : 'คลิกเพื่อเปิด'"></div>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr class="text-gray-500">
                        <th class="px-3 py-2 text-left">วันที่</th>
                        <th class="px-3 py-2 text-left">ประเภท</th>
                        <th class="px-3 py-2 text-right">จำนวนเงิน</th>
                        <th class="px-3 py-2 text-center">สถานะ</th>
                        <th class="px-3 py-2 text-center">รายละเอียด</th>
                        <th class="px-3 py-2 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($claims as $claim)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-3 py-2 text-gray-500">{{ $claim->claim_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase
                                {{ $claim->type === 'advance' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' }}">
                                {{ $claim->type === 'advance' ? 'Advance' : 'Reimburse' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-bold {{ $claim->type === 'advance' ? 'text-red-700' : 'text-green-700' }}">
                            {{ $claim->type === 'advance' ? '-' : '' }}{{ number_format($claim->amount, 2) }} ฿
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($claim->status === 'approved')
                            <span class="text-green-600 flex items-center justify-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-bold">อนุมัติแล้ว</span>
                            </span>
                            @else
                            <span class="text-gray-400 italic">รออนุมัติ</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            <button type="button" @click="detailText = @js($claim->description);" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition" title="ดูรายละเอียด">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2 text-[10px] font-bold">
                                @if($claim->status === 'pending')
                                <form action="{{ route('workspace.claims.approve', $claim->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-indigo-600 hover:text-indigo-800 uppercase">Approve</button>
                                </form>
                                @endif
                                <form action="{{ route('workspace.claims.delete', $claim->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 uppercase">ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-gray-400 italic">ไม่มีรายการเบิกเงินในเดือนนี้</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="openManage" x-cloak class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-4">
            <div class="grid grid-cols-1 gap-4">
                <form action="{{ route('workspace.updateAdvanceCeiling', $employee->id) }}" method="POST" class="rounded-xl border bg-white p-3 flex items-center gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="flex-grow">
                        <label class="block text-[10px] uppercase font-bold text-indigo-400 mb-1">ตั้งเพดานการเบิก (%)</label>
                        <div class="space-y-2">
                            <input type="range" min="0" max="100" step="0.5" x-model.number="ceilingPercentDraft" class="w-full accent-indigo-600">
                            <div class="flex items-center gap-2">
                                <input type="number" name="advance_ceiling_percent" x-model.number="ceilingPercentDraft" min="0" max="100" step="0.5" class="w-full px-2 py-1.5 border rounded text-sm font-bold text-indigo-700 bg-white">
                                <span class="text-xs text-gray-400 font-semibold">%</span>
                            </div>
                            <div class="text-xs text-indigo-600 font-semibold">
                                เพดานปัจจุบัน <span x-text="money(quotaAmount())"></span> ฿
                            </div>
                        </div>
                    </div>
                    <div class="self-end">
                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-[10px] font-bold uppercase hover:bg-indigo-700 transition">Update</button>
                    </div>
                </form>

                @if($errors->has('amount'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-3 text-red-700 text-xs font-bold">
                    {{ $errors->first('amount') }}
                </div>
                @else
                <div class="rounded-xl border bg-white px-3 py-3 text-xs text-gray-500 flex items-center">
                    ใช้ส่วนนี้เมื่ออยากตั้งเพดานหรือเพิ่มรายการเบิกใหม่ โดยประวัติจะอัปเดตในตารางด้านบนทันที
                </div>
                @endif
            </div>

            <form action="{{ route('workspace.claims.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" method="POST" class="grid grid-cols-1 gap-3 rounded-xl border bg-white p-3">
                @csrf
                <div>
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">ประเภท</label>
                    <select name="type" class="w-full px-2 py-1.5 border rounded text-xs">
                        <option value="advance">เบิกเงินล่วงหน้า (Deduction)</option>
                        <option value="reimbursement">เบิกค่าใช้จ่าย (Income)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">วันที่</label>
                    <input type="date" name="claim_date" value="{{ date('Y-m-d') }}" required class="w-full px-2 py-1.5 border rounded text-xs">
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">จำนวนเงิน</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="w-full px-2 py-1.5 border rounded text-xs font-bold">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 text-white py-1.5 rounded text-xs font-bold hover:bg-indigo-700 transition">+ เพิ่มรายการ</button>
                </div>
                <div class="col-span-1">
                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">รายละเอียด</label>
                    <input type="text" name="description" placeholder="ระบุเหตุผล..." required class="w-full px-2 py-1.5 border rounded text-xs">
                </div>
            </form>
        </div>

        <div x-show="detailText !== ''" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="detailText = ''">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-800">รายละเอียดรายการเบิกเงิน</h4>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="detailText = ''">ปิด</button>
                </div>
                <div class="text-sm text-gray-600 whitespace-pre-wrap" x-text="detailText"></div>
            </div>
        </div>
    </div>
</div>
