@extends('layouts.app')

@section('title', 'OT Inbox')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🔔 OT Inbox</h1>
            <p class="text-sm text-gray-600 mt-1">คำขอ OT รออนุมัติทั้งบริษัท</p>
        </div>
        <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-bold">{{ $pending->count() }} รายการ</span>
    </div>

    @if(session('success'))
        <div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-semibold">✓ {{ session('success') }}</div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        @if($pending->isEmpty())
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-2">✓</div>
                <div class="text-sm">ไม่มีคำขอ OT รออนุมัติ</div>
            </div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">วันที่</th>
                    <th class="px-4 py-2 text-left">พนักงาน</th>
                    <th class="px-4 py-2 text-right">นาที OT</th>
                    <th class="px-4 py-2 text-left">เหตุผล</th>
                    <th class="px-4 py-2 text-left">อ้างอิงงาน</th>
                    <th class="px-4 py-2 text-right">การดำเนินการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($pending as $r)
                <tr x-data="{ rejectOpen: false }" class="hover:bg-indigo-50/40">
                    <td class="px-4 py-2 font-mono text-gray-700">{{ $r->log_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">
                        <span class="font-semibold text-gray-800">{{ $r->employee?->display_name ?? '—' }}</span>
                        <span class="text-xs text-gray-400 ml-1">{{ $r->employee?->employee_code }}</span>
                    </td>
                    <td class="px-4 py-2 text-right font-bold text-indigo-700">
                        {{ $r->requested_minutes }} นาที
                        <span class="text-[10px] font-normal text-gray-400 block">
                            ({{ floor($r->requested_minutes / 60) }}ชม. {{ $r->requested_minutes % 60 }}น.)
                        </span>
                    </td>
                    <td class="px-4 py-2 text-gray-700 max-w-xs truncate" title="{{ $r->reason }}">{{ $r->reason }}</td>
                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $r->job_reference ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('workspace.show', [$r->employee_id, $r->log_date->month, $r->log_date->year]) }}"
                               class="text-xs text-gray-400 hover:text-indigo-600 hover:underline">Workspace ↗</a>

                            {{-- Approve --}}
                            <form method="POST" action="{{ route('ot.request.approve', $r) }}" class="inline">
                                @csrf
                                <button class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded transition">
                                    ✓ อนุมัติ
                                </button>
                            </form>

                            {{-- Reject toggle --}}
                            <button @click="rejectOpen = !rejectOpen"
                                    class="px-3 py-1 text-xs font-bold rounded border transition"
                                    :class="rejectOpen ? 'bg-red-600 text-white border-red-600' : 'border-red-300 text-red-600 hover:bg-red-50'">
                                ✕ ปฏิเสธ
                            </button>
                        </div>

                        {{-- Reject inline form --}}
                        <div x-show="rejectOpen" x-cloak x-transition
                             class="mt-2 p-2 bg-red-50 border border-red-200 rounded-lg text-left">
                            <form method="POST" action="{{ route('ot.request.reject', $r) }}" class="space-y-1.5">
                                @csrf
                                <textarea name="review_note" rows="2" maxlength="300"
                                          placeholder="หมายเหตุการปฏิเสธ (optional)"
                                          class="w-full px-2 py-1 text-xs border border-red-200 rounded bg-white focus:ring-1 focus:ring-red-400 resize-none"></textarea>
                                <div class="flex gap-2">
                                    <button type="submit"
                                            class="flex-1 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded transition">
                                        ยืนยันปฏิเสธ
                                    </button>
                                    <button type="button" @click="rejectOpen = false"
                                            class="px-3 py-1 text-xs text-gray-500 hover:text-gray-700">ยกเลิก</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
