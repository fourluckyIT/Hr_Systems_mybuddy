@extends('layouts.app')

@section('title', 'ขอ OT')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">ขอ OT</h1>
        <p class="text-sm text-gray-600 mt-1">กดขอ OT ได้เลย ไม่ต้องรอ attendance ของเดือนถูกกรอก — HR/Admin จะตรวจและอนุมัติที่ฝั่งเค้า</p>
    </div>

    <!-- Form -->
    <form method="POST" action="{{ route('ot.request.store') }}" class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        @csrf
        <div class="grid md:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">วันที่ทำ OT</label>
                <input type="date" name="log_date" value="{{ old('log_date', $todayIso) }}" required
                       class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">จำนวนนาที OT <span class="text-red-500">*</span></label>
                <input type="number" name="requested_minutes" min="15" max="720" step="15" value="{{ old('requested_minutes', 60) }}" required
                       class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                <p class="text-[10px] text-gray-400 mt-0.5">ขั้นต่ำ 15 นาที · สูงสุด 720 นาที (12 ชม.) · ทีละ 15 นาที</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">อ้างอิงงาน (optional)</label>
                <input type="text" name="job_reference" value="{{ old('job_reference') }}" maxlength="120" placeholder="เช่น ช่อง A · EP 12"
                       class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
            </div>
            <div class="md:col-span-4">
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">เหตุผล <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="2" required maxlength="500" placeholder="สั้นๆ ก็พอ เช่น เก็บงาน w02 / exports ล้มต้อง render ใหม่"
                          class="w-full px-3 py-2 border border-gray-300 rounded text-sm">{{ old('reason') }}</textarea>
            </div>
        </div>
        <div class="flex justify-end mt-3">
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">
                📝 ส่งคำขอ OT
            </button>
        </div>
    </form>

    <!-- History -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-bold text-sm text-gray-800">คำขอของฉัน</h2>
            <span class="text-xs text-gray-500">{{ $requests->count() }} รายการ</span>
        </div>
        @if($requests->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">ยังไม่เคยขอ OT</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">วันที่</th>
                    <th class="px-4 py-2 text-right">นาที</th>
                    <th class="px-4 py-2 text-left">เหตุผล</th>
                    <th class="px-4 py-2 text-left">อ้างอิง</th>
                    <th class="px-4 py-2 text-center">สถานะ</th>
                    <th class="px-4 py-2 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($requests as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-mono">{{ $r->log_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 text-right font-semibold">{{ $r->requested_minutes }}</td>
                    <td class="px-4 py-2 text-gray-700">{{ $r->reason }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $r->job_reference ?? '—' }}</td>
                    <td class="px-4 py-2 text-center">
                        @if($r->status === 'pending')
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">⏳ รออนุมัติ</span>
                        @elseif($r->status === 'approved')
                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold">✓ อนุมัติแล้ว</span>
                        @elseif($r->status === 'rejected')
                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-bold" title="{{ $r->review_note ?? '' }}">✕ ไม่อนุมัติ</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold">ยกเลิก</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        @if($r->status === 'pending')
                            <form method="POST" action="{{ route('ot.request.cancel', $r) }}" class="inline" onsubmit="return confirm('ยกเลิกคำขอนี้?')">
                                @csrf
                                <button class="text-xs text-red-600 hover:underline">ยกเลิก</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
