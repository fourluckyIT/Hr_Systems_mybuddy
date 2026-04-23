@extends('layouts.app')
@section('title', 'คำขอลา / สลับวันทำงาน')

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4 sm:px-6"
     x-data="{
        tab: 'leave',
        leaveModal: false,
        swapModal: false,
     }">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    {{-- ═══════════════════════════════════════════ --}}
    {{-- HEADER                                     --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isAdmin ? 'จัดการคำขอลา / สลับวัน' : 'คำขอลาของฉัน' }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $isAdmin ? 'ตรวจสอบและอนุมัติคำขอจากพนักงานทุกคน' : 'ส่งคำขอและติดตามสถานะ' }}</p>
        </div>
        <div class="flex gap-2">
            <button @click="leaveModal = true"
                    class="px-4 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                ขอลา
            </button>
            <button @click="swapModal = true"
                    class="px-4 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold hover:bg-amber-600 transition-colors shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                สลับวัน
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════ --}}
    {{-- STAT CARDS                                 --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-amber-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">รอตรวจสอบ</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['leave_pending'] + $stats['swap_pending'] }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">ลา {{ $stats['leave_pending'] }} · สลับ {{ $stats['swap_pending'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-green-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-green-500 uppercase tracking-wider">อนุมัติแล้ว</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['leave_approved'] + $stats['swap_approved'] }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">ลา {{ $stats['leave_approved'] }} · สลับ {{ $stats['swap_approved'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-red-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">ปฏิเสธ</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $stats['leave_rejected'] }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">คำขอลาที่ถูกปฏิเสธ</p>
        </div>
        <div class="bg-white rounded-xl border border-indigo-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider">ทั้งหมด</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $stats['leave_total'] + $stats['swap_total'] }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">ลา {{ $stats['leave_total'] }} · สลับ {{ $stats['swap_total'] }}</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════ --}}
    {{-- TABS                                       --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        {{-- Tab Headers --}}
        <div class="flex border-b border-gray-100">
            <button @click="tab = 'leave'" :class="tab === 'leave' ? 'border-b-2 border-indigo-600 text-indigo-700 bg-indigo-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="flex-1 px-5 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                📋 คำขอลา
                @if($stats['leave_pending'] > 0)
                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full">{{ $stats['leave_pending'] }}</span>
                @endif
            </button>
            <button @click="tab = 'swap'" :class="tab === 'swap' ? 'border-b-2 border-amber-500 text-amber-700 bg-amber-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="flex-1 px-5 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                ⇄ สลับวันทำงาน
                @if($stats['swap_pending'] > 0)
                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full">{{ $stats['swap_pending'] }}</span>
                @endif
            </button>
        </div>

        {{-- Tab: Leave Requests --}}
        <div x-show="tab === 'leave'" x-transition>
            @if($leaveRequests->isEmpty())
            <div class="p-12 text-center">
                <div class="text-4xl mb-3">📭</div>
                <p class="text-sm font-semibold text-gray-600">ยังไม่มีคำขอลา</p>
                <p class="text-xs text-gray-400 mt-1">กดปุ่ม "ขอลา" เพื่อส่งคำขอ</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50/80 text-[11px] text-gray-500 uppercase tracking-wider">
                        <tr>
                            @if($isAdmin)<th class="px-4 py-2.5 text-left">พนักงาน</th>@endif
                            <th class="px-4 py-2.5 text-left">วันที่ลา</th>
                            <th class="px-4 py-2.5 text-left">ประเภท</th>
                            <th class="px-4 py-2.5 text-left">เหตุผล</th>
                            <th class="px-4 py-2.5 text-center">สถานะ</th>
                            <th class="px-4 py-2.5 text-center">หมายเหตุ</th>
                            <th class="px-4 py-2.5 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($leaveRequests as $lr)
                        <tr class="hover:bg-indigo-50/30 transition-colors {{ $lr->status === 'pending' ? 'bg-amber-50/20' : '' }}">
                            @if($isAdmin)
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">{{ $lr->employee->nickname ?? $lr->employee->first_name }}</div>
                                <div class="text-[10px] text-gray-400">{{ $lr->employee->employee_code ?? '' }}</div>
                            </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $lr->leave_date->format('d/m/Y') }}
                                <div class="text-[10px] text-gray-400">{{ $lr->leave_date->translatedFormat('l') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @php $typeColors = ['sick_leave'=>'bg-blue-100 text-blue-700','personal_leave'=>'bg-yellow-100 text-yellow-700','vacation_leave'=>'bg-teal-100 text-teal-700','lwop'=>'bg-red-100 text-red-700']; @endphp
                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $typeColors[$lr->leave_type] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $leaveTypes[$lr->leave_type] ?? $lr->leave_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 max-w-[200px] truncate text-xs" title="{{ $lr->reason }}">{{ $lr->reason ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @include('leave.partials.status-badge', ['status' => $lr->status])
                            </td>
                            <td class="px-4 py-3 text-[10px] text-gray-400 text-center">{{ $lr->review_note ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($isAdmin && $lr->status === 'pending')
                                <div class="flex gap-1 justify-center" x-data="{ confirmReject: false }">
                                    <form method="POST" action="{{ route('leave.review', $lr) }}">@csrf @method('PATCH')
                                        <input type="hidden" name="action" value="approved">
                                        <button class="px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-[11px] font-semibold hover:bg-emerald-200 transition-colors">✓ อนุมัติ</button>
                                    </form>
                                    <button @click="confirmReject = !confirmReject" class="px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-[11px] font-semibold hover:bg-red-200 transition-colors">✗ ปฏิเสธ</button>
                                    <form x-show="confirmReject" x-cloak x-transition method="POST" action="{{ route('leave.review', $lr) }}" class="flex items-center gap-1">@csrf @method('PATCH')
                                        <input type="hidden" name="action" value="rejected">
                                        <input name="review_note" placeholder="เหตุผล..." class="w-24 border border-red-200 rounded px-1.5 py-0.5 text-[10px]">
                                        <button class="px-2 py-0.5 bg-red-600 text-white rounded text-[10px] font-bold">ยืนยัน</button>
                                    </form>
                                </div>
                                @elseif($lr->status === 'pending')
                                <form method="POST" action="{{ route('leave.cancel', $lr) }}">@csrf
                                    <button class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-[11px] font-medium hover:bg-gray-200 transition-colors">ยกเลิก</button>
                                </form>
                                @else
                                <span class="text-[10px] text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Tab: Swap Requests --}}
        <div x-show="tab === 'swap'" x-cloak x-transition>
            @if($swapRequests->isEmpty())
            <div class="p-12 text-center">
                <div class="text-4xl mb-3">⇄</div>
                <p class="text-sm font-semibold text-gray-600">ยังไม่มีคำขอสลับวัน</p>
                <p class="text-xs text-gray-400 mt-1">กดปุ่ม "สลับวัน" เพื่อส่งคำขอ</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50/80 text-[11px] text-gray-500 uppercase tracking-wider">
                        <tr>
                            @if($isAdmin)<th class="px-4 py-2.5 text-left">พนักงาน</th>@endif
                            <th class="px-4 py-2.5 text-left">มาทำงานแทน</th>
                            <th class="px-4 py-2.5 text-left">หยุดแทน</th>
                            <th class="px-4 py-2.5 text-left">เหตุผล</th>
                            <th class="px-4 py-2.5 text-center">สถานะ</th>
                            <th class="px-4 py-2.5 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($swapRequests as $sr)
                        <tr class="hover:bg-amber-50/30 transition-colors {{ $sr->status === 'pending' ? 'bg-amber-50/20' : '' }}">
                            @if($isAdmin)
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">{{ $sr->employee->nickname ?? $sr->employee->first_name }}</div>
                                <div class="text-[10px] text-gray-400">{{ $sr->employee->employee_code ?? '' }}</div>
                            </td>
                            @endif
                            <td class="px-4 py-3">
                                <div class="font-medium text-green-700">{{ $sr->work_date->translatedFormat('D d/m/Y') }}</div>
                                <div class="text-[10px] text-green-500">▶ มาทำงาน</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-red-600">{{ $sr->off_date->translatedFormat('D d/m/Y') }}</div>
                                <div class="text-[10px] text-red-400">◼ หยุดแทน</div>
                            </td>
                            <td class="px-4 py-3 text-gray-500 max-w-[180px] truncate text-xs" title="{{ $sr->reason }}">{{ $sr->reason ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @include('leave.partials.status-badge', ['status' => $sr->status])
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($isAdmin && $sr->status === 'pending')
                                <div class="flex gap-1 justify-center">
                                    <form method="POST" action="{{ route('leave.swap.review', $sr) }}">@csrf @method('PATCH')
                                        <input type="hidden" name="action" value="approved">
                                        <button class="px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-[11px] font-semibold hover:bg-emerald-200 transition-colors">✓ อนุมัติ</button>
                                    </form>
                                    <form method="POST" action="{{ route('leave.swap.review', $sr) }}">@csrf @method('PATCH')
                                        <input type="hidden" name="action" value="rejected">
                                        <button class="px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-[11px] font-semibold hover:bg-red-200 transition-colors">✗ ปฏิเสธ</button>
                                    </form>
                                </div>
                                @elseif($sr->status === 'pending')
                                <form method="POST" action="{{ route('leave.swap.cancel', $sr) }}">@csrf
                                    <button class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-[11px] font-medium hover:bg-gray-200 transition-colors">ยกเลิก</button>
                                </form>
                                @else
                                <span class="text-[10px] text-gray-300">—</span>
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
    {{-- ═══════════════════════════════════════════ --}}
    {{-- MODALS (Alpine.js)                         --}}
    {{-- ═══════════════════════════════════════════ --}}
    @include('leave.partials.modal-leave')
    @include('leave.partials.modal-swap')
</div>

@if(request('open') === 'swap')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.querySelector('[x-data]');
    if (el && el.__x) el.__x.$data.swapModal = true;
    else if (el && el._x_dataStack) el._x_dataStack[0].swapModal = true;
});
</script>
@endif
@endsection
