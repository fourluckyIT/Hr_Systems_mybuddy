<!-- YouTuber Settlement Grid -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 bg-orange-600 text-white font-semibold text-sm flex justify-between items-center">
        <span>YouTuber Settlement — รายรับ-รายจ่าย</span>
        <span class="text-xs opacity-80">คำนวณกำไรสุทธิ / รายเดือน</span>
    </div>

    <form method="POST" action="{{ route('workspace.saveWorkLogs', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
        @csrf
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 text-left">รายการ (Revenue / Expense)</th>
                        <th class="px-2 py-2 text-center w-32">ประเภท</th>
                        <th class="px-2 py-2 text-right w-40">จำนวนเงิน</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody id="settlement-grid-body">
                    @forelse($workLogs as $index => $log)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-2 py-1">
                            <input type="text" name="worklogs[{{ $index }}][work_type]" value="{{ $log->work_type }}" placeholder="เช่น รายได้จากการโฆษณา, ค่ากองถ่าย..." class="w-full px-1 py-0.5 border rounded text-xs">
                        </td>
                        <td class="px-2 py-1 text-center">
                            <select name="worklogs[{{ $index }}][entry_type]" class="w-full px-1 py-0.5 border rounded text-xs">
                                <option value="income" {{ ($log->entry_type ?? 'income') == 'income' ? 'selected' : '' }}>รายรับ (Income)</option>
                                <option value="deduction" {{ ($log->entry_type ?? '') == 'deduction' ? 'selected' : '' }}>รายจ่าย (Expense)</option>
                            </select>
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" step="0.01" name="worklogs[{{ $index }}][amount]" value="{{ $log->amount }}" class="w-full px-1 py-0.5 border rounded text-xs text-right font-medium">
                        </td>
                        <td class="px-2 py-1 text-center text-red-500 hover:text-red-700 cursor-pointer" onclick="this.closest('tr').remove()">
                            &times;
                        </td>
                    </tr>
                    @empty
                    <tr class="border-t h-20">
                        <td colspan="4" class="text-center text-gray-400 italic">กด + เพิ่มรายการ เพื่อเริ่มกรอกข้อมูล</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 bg-gray-50 flex justify-between items-center border-t border-gray-100">
            <button type="button" onclick="addRow()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                + เพิ่มรายการ (Add Item)
            </button>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-1 px-4 rounded text-sm shadow-sm transition">
                บันทึก & คำนวณยอดสุทธิ
            </button>
        </div>
    </form>
</div>

<script>
function addRow() {
    const tbody = document.getElementById('settlement-grid-body');
    const index = tbody.querySelectorAll('tr').length;
    
    // Clear empty row if exists
    if (tbody.querySelector('.italic')) {
        tbody.innerHTML = '';
    }

    const html = `
        <tr class="border-t hover:bg-gray-50">
            <td class="px-2 py-1">
                <input type="text" name="worklogs[${index}][work_type]" placeholder="ระบุรายการ..." class="w-full px-1 py-0.5 border rounded text-xs">
            </td>
            <td class="px-2 py-1 text-center">
                <select name="worklogs[${index}][entry_type]" class="w-full px-1 py-0.5 border rounded text-xs">
                    <option value="income">รายรับ (Income)</option>
                    <option value="deduction">รายจ่าย (Expense)</option>
                </select>
            </td>
            <td class="px-2 py-1">
                <input type="number" step="0.01" name="worklogs[${index}][amount]" class="w-full px-1 py-0.5 border rounded text-xs text-right font-medium">
            </td>
            <td class="px-2 py-1 text-center text-red-500 hover:text-red-700 cursor-pointer" onclick="this.closest('tr').remove()">
                &times;
            </td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', html);
}
</script>
