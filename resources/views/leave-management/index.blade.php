@extends('layouts.app')
@section('title', 'จัดการวันลา')

@section('content')
<div class="max-w-7xl mx-auto p-4">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🏖️ จัดการวันลาทั้งบริษัท</h1>
            <p class="text-sm text-gray-500 mt-0.5">ภาพรวมสิทธิวันลาของพนักงานทุกคน • ปี {{ $year + 543 }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-xs text-gray-500">ปี:</label>
                <select name="year" onchange="this.form.submit()" class="px-3 py-1.5 border rounded-lg text-sm">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y + 543 }}</option>
                    @endfor
                </select>
            </form>
            <a href="{{ route('settings.master-data') }}" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 rounded-lg text-xs font-bold hover:bg-gray-50">⚙️ จัดการนโยบาย</a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">พนักงาน</div>
            <div class="text-2xl font-extrabold text-gray-800 mt-1">{{ $stats['total_employees'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">นโยบายที่ใช้</div>
            <div class="text-2xl font-extrabold text-indigo-700 mt-1">{{ $stats['total_policies'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">ยอดยกข้ามปี</div>
            <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ rtrim(rtrim(number_format($stats['total_carryover_days'], 1), '0'), '.') }}</div>
            <div class="text-[10px] text-gray-400">วัน</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">แลกเป็นเงิน</div>
            <div class="text-2xl font-extrabold text-rose-700 mt-1">{{ rtrim(rtrim(number_format($stats['total_encash_days'], 1), '0'), '.') }}</div>
            <div class="text-[10px] text-gray-400">วัน</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">มูลค่าแลก</div>
            <div class="text-xl font-extrabold text-amber-600 mt-1">฿{{ number_format($stats['total_encash_amount'], 0) }}</div>
        </div>
    </div>

    {{-- Batch action bar --}}
    <div x-data="{
        selected: [],
        showBatchCarry: false,
        toggleAll(e) {
            if (e.target.checked) {
                this.selected = Array.from(document.querySelectorAll('.row-cb')).map(cb => cb.value);
            } else {
                this.selected = [];
            }
        }
    }">
        <div class="flex items-center justify-between mb-3 p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
            <div class="text-sm">
                <span class="font-bold text-indigo-700">เลือก: <span x-text="selected.length"></span> คน</span>
                <span class="text-gray-500 ml-2">(เลือกหลายคนเพื่อ batch action)</span>
            </div>
            <div class="flex gap-2">
                <button @click="showBatchCarry = true" :disabled="selected.length === 0"
                        :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
                        class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold transition-colors">
                    📥 ยกยอดให้คนที่เลือก
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">
                                <input type="checkbox" @change="toggleAll($event)" class="rounded border-gray-300">
                            </th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">พนักงาน</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">นโยบาย</th>
                            <th class="px-3 py-2 text-center text-[10px] font-bold text-teal-600 uppercase">🏖️ พักร้อน</th>
                            <th class="px-3 py-2 text-center text-[10px] font-bold text-blue-600 uppercase">🤒 ป่วย</th>
                            <th class="px-3 py-2 text-center text-[10px] font-bold text-amber-600 uppercase">👤 กิจ</th>
                            <th class="px-3 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($rows as $row)
                        @php $emp = $row['employee']; $b = $row['balances']; $policy = $row['policy']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <input type="checkbox" class="row-cb rounded border-gray-300" value="{{ $emp->id }}" x-model="selected">
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-bold text-gray-800">{{ $emp->display_name }}</div>
                                <div class="text-[10px] text-gray-400">{{ $emp->employee_code }} • {{ $emp->department?->name }}</div>
                            </td>
                            <td class="px-3 py-2">
                                @if($policy)
                                    <span class="text-xs">
                                        @if($policy->is_default)⭐@endif {{ $policy->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-rose-500">— ไม่มี —</span>
                                @endif
                            </td>
                            @foreach(['vacation_leave','sick_leave','personal_leave'] as $type)
                            @php $bb = $b[$type]; @endphp
                            <td class="px-3 py-2 text-center">
                                <div class="text-sm font-bold {{ $bb['remaining'] <= 0 ? 'text-rose-600' : 'text-gray-800' }}">
                                    {{ rtrim(rtrim(number_format($bb['remaining'], 1), '0'), '.') }}
                                    <span class="text-[10px] text-gray-400 font-normal">/ {{ rtrim(rtrim(number_format($bb['total_available'], 1), '0'), '.') }}</span>
                                </div>
                                @if($bb['carryover'] > 0)
                                <div class="text-[9px] text-emerald-600 font-bold">+{{ rtrim(rtrim(number_format($bb['carryover'], 1), '0'), '.') }} ยก</div>
                                @endif
                                @if($bb['encashed'] > 0)
                                <div class="text-[9px] text-rose-600 font-bold">−{{ rtrim(rtrim(number_format($bb['encashed'], 1), '0'), '.') }} แลก</div>
                                @endif
                            </td>
                            @endforeach
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('workspace.show', ['employee' => $emp->id, 'month' => now()->month, 'year' => now()->year]) }}"
                                   class="text-[10px] text-indigo-600 hover:underline">เปิด Workspace ↗</a>
                                <a href="{{ route('employees.edit', $emp->id) }}"
                                   class="ml-2 text-[10px] text-gray-500 hover:underline">แก้ไข</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-3 py-12 text-center text-gray-400">ไม่มีพนักงาน</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Batch Carryover Modal --}}
        <div x-show="showBatchCarry" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showBatchCarry = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-4">📥 ยกยอดวันลาแบบ Batch</h3>
                <form method="POST" action="{{ route('leave-management.batch-carryover') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="leave_type" value="vacation_leave">
                    <template x-for="empId in selected" :key="empId">
                        <input type="hidden" name="employee_ids[]" :value="empId">
                    </template>
                    <p class="text-sm text-gray-700">จะยกยอดให้ <span class="font-bold text-indigo-700" x-text="selected.length"></span> คน</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">ปีต้นทาง</label>
                            <input type="number" name="source_year" required value="{{ $year - 1 }}"
                                   class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">ปีปลายทาง</label>
                            <input type="number" name="target_year" required value="{{ $year }}"
                                   class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 p-2 bg-amber-50 border border-amber-100 rounded-lg">
                        <input type="checkbox" name="cap_to_max" value="1" checked>
                        <span class="text-xs">ตัดให้ไม่เกินเพดาน max_carryover_days ของแต่ละนโยบาย</span>
                    </label>
                    <p class="text-[11px] text-gray-500">ระบบจะคำนวณยอดที่ยกได้ของแต่ละคนจากยอดคงเหลือปีต้นทาง — คนที่เหลือ 0 จะข้าม</p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showBatchCarry = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                        <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">ยกยอด</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
