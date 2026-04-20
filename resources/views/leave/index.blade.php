@extends('layouts.app')

@section('title', 'คำขอลา / สลับวันทำงาน')

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4">

    {{-- Flash --}}
    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">คำขอลา / สลับวันทำงาน</h1>
        <div class="flex gap-2">
            <button onclick="document.getElementById('modal-leave').classList.remove('hidden')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                + ขอลา
            </button>
            <button onclick="document.getElementById('modal-swap').classList.remove('hidden')"
                    class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600">
                ⇄ ขอสลับวัน
            </button>
        </div>
    </div>

    {{-- Leave Requests --}}
    <div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
        <div class="px-4 py-3 bg-blue-600 text-white font-semibold text-sm">คำขอลา</div>
        @if($leaveRequests->isEmpty())
            <div class="p-6 text-center text-gray-400 text-sm">ยังไม่มีคำขอลา</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        @if($isAdmin)<th class="px-4 py-2 text-left">พนักงาน</th>@endif
                        <th class="px-4 py-2 text-left">วันที่ลา</th>
                        <th class="px-4 py-2 text-left">ประเภท</th>
                        <th class="px-4 py-2 text-left">เหตุผล</th>
                        <th class="px-4 py-2 text-center">สถานะ</th>
                        <th class="px-4 py-2 text-center">หมายเหตุ</th>
                        <th class="px-4 py-2 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaveRequests as $lr)
                    <tr class="border-t hover:bg-gray-50">
                        @if($isAdmin)<td class="px-4 py-2">{{ $lr->employee->full_name }}</td>@endif
                        <td class="px-4 py-2 font-medium">{{ $lr->leave_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $leaveTypes[$lr->leave_type] ?? $lr->leave_type }}</td>
                        <td class="px-4 py-2 text-gray-500 max-w-[200px] truncate">{{ $lr->reason ?? '-' }}</td>
                        <td class="px-4 py-2 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                @if($lr->status === 'approved') bg-green-100 text-green-700
                                @elseif($lr->status === 'rejected') bg-red-100 text-red-700
                                @elseif($lr->status === 'cancelled') bg-gray-100 text-gray-500
                                @else bg-amber-100 text-amber-700
                                @endif">
                                @switch($lr->status)
                                    @case('approved') ✓ อนุมัติ @break
                                    @case('rejected') ✗ ปฏิเสธ @break
                                    @case('cancelled') ยกเลิก @break
                                    @default รอตรวจสอบ
                                @endswitch
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-400 text-center">{{ $lr->review_note ?? '-' }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($isAdmin && $lr->status === 'pending')
                            <div class="flex gap-1 justify-center">
                                <form method="POST" action="{{ route('leave.review', $lr) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="approved">
                                    <button class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">อนุมัติ</button>
                                </form>
                                <form method="POST" action="{{ route('leave.review', $lr) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="rejected">
                                    <button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">ปฏิเสธ</button>
                                </form>
                            </div>
                            @elseif($lr->status === 'pending')
                            <form method="POST" action="{{ route('leave.cancel', $lr) }}">
                                @csrf
                                <button class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs hover:bg-gray-200">ยกเลิก</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Day Swap Requests --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 bg-amber-500 text-white font-semibold text-sm">คำขอสลับวันทำงาน</div>
        @if($swapRequests->isEmpty())
            <div class="p-6 text-center text-gray-400 text-sm">ยังไม่มีคำขอสลับวัน</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        @if($isAdmin)<th class="px-4 py-2 text-left">พนักงาน</th>@endif
                        <th class="px-4 py-2 text-left">มาแทน (work_date)</th>
                        <th class="px-4 py-2 text-left">หยุดแทน (off_date)</th>
                        <th class="px-4 py-2 text-left">เหตุผล</th>
                        <th class="px-4 py-2 text-center">สถานะ</th>
                        <th class="px-4 py-2 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($swapRequests as $sr)
                    <tr class="border-t hover:bg-gray-50">
                        @if($isAdmin)<td class="px-4 py-2">{{ $sr->employee->full_name }}</td>@endif
                        <td class="px-4 py-2 font-medium">{{ $sr->work_date->format('D, d/m/Y') }}</td>
                        <td class="px-4 py-2 font-medium text-red-600">{{ $sr->off_date->format('D, d/m/Y') }}</td>
                        <td class="px-4 py-2 text-gray-500 max-w-[180px] truncate">{{ $sr->reason ?? '-' }}</td>
                        <td class="px-4 py-2 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                @if($sr->status === 'approved') bg-green-100 text-green-700
                                @elseif($sr->status === 'rejected') bg-red-100 text-red-700
                                @elseif($sr->status === 'cancelled') bg-gray-100 text-gray-500
                                @else bg-amber-100 text-amber-700
                                @endif">
                                @switch($sr->status)
                                    @case('approved') ✓ อนุมัติ @break
                                    @case('rejected') ✗ ปฏิเสธ @break
                                    @case('cancelled') ยกเลิก @break
                                    @default รอตรวจสอบ
                                @endswitch
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if($isAdmin && $sr->status === 'pending')
                            <div class="flex gap-1 justify-center">
                                <form method="POST" action="{{ route('leave.swap.review', $sr) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="approved">
                                    <button class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">อนุมัติ</button>
                                </form>
                                <form method="POST" action="{{ route('leave.swap.review', $sr) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="rejected">
                                    <button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">ปฏิเสธ</button>
                                </form>
                            </div>
                            @elseif($sr->status === 'pending')
                            <form method="POST" action="{{ route('leave.swap.cancel', $sr) }}">
                                @csrf
                                <button class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs hover:bg-gray-200">ยกเลิก</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Modal: ขอลา --}}
<div id="modal-leave" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-base font-bold">ขอลา</h2>
            <button onclick="document.getElementById('modal-leave').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" action="{{ route('leave.store') }}" class="space-y-3">
            @csrf
            @if($isAdmin)
            <div>
                <label class="text-xs font-medium text-gray-600">พนักงาน</label>
                <select name="employee_id" required class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ (int) request('employee_id') === (int) $emp->id ? 'selected' : '' }}>{{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->user()->employee?->id }}">
            @endif
            <div>
                <label class="text-xs font-medium text-gray-600">วันที่ลา</label>
                <input type="date" name="leave_date" required class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">ประเภทการลา</label>
                <select name="leave_type" required class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($leaveTypes as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">เหตุผล (ถ้ามี)</label>
                <textarea name="reason" rows="2" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg font-medium text-sm hover:bg-blue-700">ส่งคำขอ</button>
        </form>
    </div>
</div>

{{-- Modal: สลับวัน --}}
<div id="modal-swap" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-base font-bold">ขอสลับวันทำงาน</h2>
            <button onclick="document.getElementById('modal-swap').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" action="{{ route('leave.swap.store') }}" class="space-y-3">
            @csrf
            @if($isAdmin)
            <div>
                <label class="text-xs font-medium text-gray-600">พนักงาน</label>
                <select name="employee_id" required class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->full_name }}</option>@endforeach
                </select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->user()->employee?->id }}">
            @endif
            <div>
                <label class="text-xs font-medium text-gray-600">วันที่จะ <span class="text-green-600 font-bold">มาทำงาน</span> (แทนวันหยุด)</label>
                <input type="date" name="work_date" required class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">วันที่จะ <span class="text-red-600 font-bold">หยุด</span> (แทนวันทำงาน)</label>
                <input type="date" name="off_date" required class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">เหตุผล (ถ้ามี)</label>
                <textarea name="reason" rows="2" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="w-full py-2 bg-amber-500 text-white rounded-lg font-medium text-sm hover:bg-amber-600">ส่งคำขอ</button>
        </form>
    </div>
</div>
@if(request('open') === 'swap')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-swap');
    if (modal) {
        modal.classList.remove('hidden');
    }
});
</script>
@endif
@endsection
