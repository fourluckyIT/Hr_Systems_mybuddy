@php
    $extraIncomes = $extraIncomes ?? \App\Models\ExtraIncomeEntry::where('employee_id', $employee->id)
        ->where('month', $month)->where('year', $year)->orderByDesc('id')->get();
    $extraTotal = $extraIncomes->sum('amount');
@endphp

<div class="bg-white rounded-xl shadow-sm overflow-hidden mt-6 border border-green-100">
    <div class="px-4 py-3 bg-green-600 text-white flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-sm font-semibold">💸 รายรับพิเศษ</span>
            <span class="text-[11px] text-green-100">YouTube bonus · Brand deal · Tip · อื่นๆ</span>
        </div>
        <span class="text-xs bg-green-700/40 px-2 py-0.5 rounded-full">{{ $extraIncomes->count() }} รายการ · {{ number_format($extraTotal, 2) }} ฿</span>
    </div>

    <form method="POST" action="{{ route('workspace.extra-income.store', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}"
          class="px-4 py-3 bg-green-50/50 border-b border-green-100 grid md:grid-cols-5 gap-2">
        @csrf
        <input type="text" name="label" required maxlength="200" placeholder="หัวข้อ เช่น ยอดวิวโบนัส ช่อง A"
               class="px-2 py-1 border border-gray-300 rounded text-xs md:col-span-2">
        <input type="text" name="category" maxlength="80" placeholder="หมวด (optional)" class="px-2 py-1 border border-gray-300 rounded text-xs">
        <input type="number" step="0.01" min="0.01" name="amount" required placeholder="จำนวน"
               class="px-2 py-1 border border-gray-300 rounded text-xs text-right">
        <div class="flex gap-2">
            <label class="inline-flex items-center gap-1 text-[11px] text-gray-600" title="ติ๊กเพื่อรวมรายการนี้ในสลิปเงินเดือน (มีผลต่อยอด net pay)">
                <input type="checkbox" name="include_in_payslip" value="1" checked class="accent-indigo-600">
                รวมในสลิป
            </label>
            <button class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded flex-1">+ เพิ่ม</button>
        </div>
    </form>

    @if($extraIncomes->isEmpty())
        <div class="px-4 py-6 text-center text-xs text-gray-400">ยังไม่มีรายรับพิเศษในเดือนนี้</div>
    @else
    <table class="w-full text-xs">
        <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase">
            <tr>
                <th class="px-3 py-1.5 text-left">หัวข้อ</th>
                <th class="px-3 py-1.5 text-left">หมวด</th>
                <th class="px-3 py-1.5 text-right">จำนวน</th>
                <th class="px-3 py-1.5 text-center" title="รวมในสลิปเงินเดือน">รวมในสลิป</th>
                <th class="px-3 py-1.5 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($extraIncomes as $e)
            <tr class="hover:bg-green-50/30">
                <td class="px-3 py-1.5 font-semibold text-gray-800">{{ $e->label }}</td>
                <td class="px-3 py-1.5 text-gray-500">{{ $e->category ?? '—' }}</td>
                <td class="px-3 py-1.5 text-right font-mono font-bold text-green-700">+{{ number_format($e->amount, 2) }}</td>
                <td class="px-3 py-1.5 text-center">
                    @if($e->include_in_payslip)
                        <span class="text-green-600 font-bold">✓</span>
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-3 py-1.5 text-right">
                    <form method="POST" action="{{ route('workspace.extra-income.delete', $e) }}" class="inline" onsubmit="return confirm('ลบรายการนี้?')">
                        @csrf @method('DELETE')
                        <button class="text-[11px] text-red-500 hover:underline">ลบ</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-gray-50">
            <tr>
                <td colspan="2" class="px-3 py-1.5 text-right font-semibold text-gray-500">รวม</td>
                <td class="px-3 py-1.5 text-right font-mono font-bold text-green-700">+{{ number_format($extraTotal, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    @endif
</div>
