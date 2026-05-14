@extends('layouts.app')

@section('title', $doc->document_number)

@section('content')
@php
    $statusMeta = [
        'pending'   => ['label' => 'รออนุมัติ',   'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'approved'  => ['label' => 'อนุมัติแล้ว', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'rejected'  => ['label' => 'ไม่อนุมัติ',   'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว',   'class' => 'bg-gray-100 text-gray-600 border-gray-300'],
    ];
    $st = $statusMeta[$doc->status] ?? ['label' => $doc->status, 'class' => 'bg-gray-50 text-gray-700 border-gray-200'];

    $fields = match($type) {
        'leave' => [
            ['label' => 'ประเภทการลา', 'value' => \App\Models\Employee::LEAVE_TYPE_LABELS[$doc->leave_type] ?? $doc->leave_type],
            ['label' => 'วันที่ลา', 'value' => optional($doc->leave_date)->format('d/m/Y') . ' (' . optional($doc->leave_date)->locale('th')->isoFormat('dddd') . ')'],
            ['label' => 'เหตุผล',   'value' => $doc->reason ?: '—'],
        ],
        'ot' => [
            ['label' => 'วันที่ทำ OT',  'value' => optional($doc->log_date)->format('d/m/Y') . ' (' . optional($doc->log_date)->locale('th')->isoFormat('dddd') . ')'],
            ['label' => 'จำนวนชั่วโมง', 'value' => round($doc->requested_minutes / 60, 2) . ' ชม. (' . $doc->requested_minutes . ' นาที)'],
            ['label' => 'งานอ้างอิง',   'value' => $doc->job_reference ?: '—'],
            ['label' => 'เหตุผล',       'value' => $doc->reason ?: '—'],
        ],
        'swap' => [
            ['label' => 'วันที่จะมาทำงาน',     'value' => optional($doc->work_date)->format('d/m/Y') . ' (' . optional($doc->work_date)->locale('th')->isoFormat('dddd') . ')'],
            ['label' => 'วันที่จะหยุดทดแทน',  'value' => optional($doc->off_date)->format('d/m/Y') . ' (' . optional($doc->off_date)->locale('th')->isoFormat('dddd') . ')'],
            ['label' => 'เหตุผล', 'value' => $doc->reason ?: '—'],
        ],
        'expense' => [
            ['label' => 'ประเภทการเบิก', 'value' => $doc->type === 'advance' ? 'เบิกเงินล่วงหน้า' : 'เบิกค่าใช้จ่าย'],
            ['label' => 'วันที่ขอเบิก',  'value' => optional($doc->claim_date)->format('d/m/Y')],
            ['label' => 'รายการ',         'value' => $doc->description],
            ['label' => 'จำนวนเงิน',     'value' => number_format((float) $doc->amount, 2) . ' บาท'],
        ],
        'carryover' => [
            ['label' => 'ประเภทวันลา', 'value' => match($doc->leave_type) {
                'vacation_leave' => 'ลาพักร้อน',
                default => $doc->leave_type,
            }],
            ['label' => 'จำนวนวัน',    'value' => rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.') . ' วัน'],
            ['label' => 'จากปี',        'value' => $doc->source_year ?? '—'],
            ['label' => 'ไปปี',          'value' => $doc->year],
            ['label' => 'หมายเหตุ',     'value' => $doc->note ?: '—'],
        ],
        'encash' => [
            ['label' => 'ประเภทวันลา', 'value' => match($doc->leave_type) {
                'vacation_leave' => 'ลาพักร้อน',
                default => $doc->leave_type,
            }],
            ['label' => 'จำนวนวัน',    'value' => rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.') . ' วัน'],
            ['label' => 'อัตรา/วัน',   'value' => number_format((float) $doc->rate_per_day, 2) . ' บาท'],
            ['label' => 'ยอดที่จะได้รับ', 'value' => number_format((float) $doc->amount, 2) . ' บาท'],
            ['label' => 'จ่ายในเดือน', 'value' => sprintf('%02d/%d', $doc->payout_month, $doc->payout_year)],
            ['label' => 'หมายเหตุ',     'value' => $doc->note ?: '—'],
        ],
        default => [],
    };
@endphp

<div class="max-w-4xl mx-auto space-y-4">

    <a href="{{ route('portal.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← กลับศูนย์เอกสาร
    </a>

    @if(session('success'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs text-gray-500">{{ $meta['label'] }}</p>
                <h1 class="text-xl font-bold text-gray-900 font-mono">{{ $doc->document_number }}</h1>
                <p class="text-xs text-gray-400 mt-1">สร้างเมื่อ {{ optional($doc->created_at)->format('d/m/Y H:i') }}</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $st['class'] }}">
                {{ $st['label'] }}
            </span>
        </div>
    </div>

    {{-- Detail card --}}
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">รายละเอียด</h2>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-5 py-2.5 w-48 text-xs font-semibold text-gray-500 bg-gray-50/50">พนักงาน</td>
                    <td class="px-5 py-2.5 text-gray-800">
                        {{ $doc->employee->first_name }} {{ $doc->employee->last_name }}
                        <span class="text-xs text-gray-400">
                            · {{ $doc->employee->employee_code }}
                            @if($doc->employee->position) · {{ $doc->employee->position->name }}@endif
                        </span>
                    </td>
                </tr>
                @foreach($fields as $f)
                <tr>
                    <td class="px-5 py-2.5 text-xs font-semibold text-gray-500 bg-gray-50/50">{{ $f['label'] }}</td>
                    <td class="px-5 py-2.5 text-gray-800">{{ $f['value'] }}</td>
                </tr>
                @endforeach
                @if(in_array($type, ['leave', 'ot', 'swap']) && $doc->reviewed_at)
                <tr>
                    <td class="px-5 py-2.5 text-xs font-semibold text-gray-500 bg-gray-50/50">ผู้อนุมัติ</td>
                    <td class="px-5 py-2.5 text-gray-800">
                        {{ $doc->reviewedBy->name ?? '—' }}
                        <span class="text-xs text-gray-400">· {{ optional($doc->reviewed_at)->format('d/m/Y H:i') }}</span>
                        @if($doc->review_note)
                            <p class="text-sm text-gray-600 mt-1">{{ $doc->review_note }}</p>
                        @endif
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('portal.print', [$type, $doc->id]) }}" target="_blank"
           class="inline-flex items-center gap-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900">
            พิมพ์ PDF
        </a>

        @if($isAdmin && $doc->status === 'pending')
            <form action="{{ route('portal.approve', [$type, $doc->id]) }}" method="POST" class="inline-flex items-center gap-2">
                @csrf
                <input type="text" name="review_note" placeholder="หมายเหตุ (ไม่บังคับ)"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-56">
                <button type="submit"
                        onclick="return confirm('อนุมัติเอกสารนี้?')"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">
                    อนุมัติ
                </button>
            </form>
            <form action="{{ route('portal.reject', [$type, $doc->id]) }}" method="POST" class="inline-flex">
                @csrf
                <button type="submit"
                        onclick="return confirm('ปฏิเสธเอกสารนี้?')"
                        class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-semibold hover:bg-rose-700">
                    ไม่อนุมัติ
                </button>
            </form>
        @endif

        @php
            $canCancelLeave = $type === 'leave'
                && !in_array($doc->status, ['cancelled', 'rejected'])
                && ($isAdmin || ($doc->status === 'pending' && (int) $doc->requested_by === (int) auth()->id()));
            $canCancelSwap = $type === 'swap'
                && !in_array($doc->status, ['cancelled', 'rejected'])
                && ($isAdmin || ($doc->status === 'pending' && (int) $doc->requested_by === (int) auth()->id()));
        @endphp
        @if($canCancelLeave)
            <form action="{{ route('leave.cancel', $doc->id) }}" method="POST" class="inline-flex">
                @csrf
                <button type="submit"
                        onclick="return confirm('ยกเลิกคำขอลานี้? หากอนุมัติแล้วระบบจะคืนสิทธิให้พนักงาน')"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm font-semibold hover:bg-gray-300">
                    ยกเลิกการลา
                </button>
            </form>
        @elseif($canCancelSwap)
            <form action="{{ route('leave.swap.cancel', $doc->id) }}" method="POST" class="inline-flex">
                @csrf
                <button type="submit"
                        onclick="return confirm('ยกเลิกคำขอสลับวันนี้?')"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm font-semibold hover:bg-gray-300">
                    ยกเลิกการสลับวัน
                </button>
            </form>
        @endif

        @if($type === 'leave' && $doc->status === 'cancelled')
            <span class="px-3 py-1.5 text-xs text-gray-500 bg-gray-100 rounded-lg">เอกสารนี้ถูกยกเลิกแล้ว — สามารถพิมพ์ใบยกเลิกได้</span>
        @endif
    </div>

    {{-- Attachments --}}
    @if($meta['allow_attachments'] ?? false)
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">ไฟล์แนบ <span class="text-xs text-gray-400 font-normal">({{ $doc->attachments->count() }})</span></h2>
            </div>
            <div class="p-5 space-y-2">
                @forelse($doc->attachments as $att)
                    <div class="flex items-center gap-3 p-2.5 border border-gray-100 rounded-lg">
                        <div class="flex-grow min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $att->original_filename }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $att->size_kb }} KB
                                · อัปโหลด {{ $att->created_at->diffForHumans() }}
                                @if($att->uploader) · โดย {{ $att->uploader->name }}@endif
                                @if($att->note) · {{ $att->note }}@endif
                            </p>
                        </div>
                        <a href="{{ $att->url }}" target="_blank" class="px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 rounded">เปิด</a>
                        @if($isAdmin || $att->uploaded_by === auth()->id())
                            <form action="{{ route('portal.attachments.delete', [$type, $doc->id, $att->id]) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1 text-xs text-rose-600 hover:bg-rose-50 rounded"
                                        onclick="return confirm('ลบไฟล์นี้?')">ลบ</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic">ยังไม่มีไฟล์แนบ</p>
                @endforelse

                <form action="{{ route('portal.attachments.store', [$type, $doc->id]) }}" method="POST" enctype="multipart/form-data"
                      class="border-t border-gray-100 pt-3 mt-3 grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                    @csrf
                    <div class="md:col-span-6">
                        <label class="block text-xs text-gray-500 mb-1">เพิ่มไฟล์</label>
                        <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                               class="w-full text-xs">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs text-gray-500 mb-1">หมายเหตุ</label>
                        <input type="text" name="note" maxlength="255" placeholder="เช่น ใบรับรองแพทย์"
                               class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="w-full px-4 py-1.5 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900">อัปโหลด</button>
                    </div>
                </form>
                <p class="text-xs text-gray-400">PDF, รูปภาพ, Word — ไม่เกิน 5 MB</p>
            </div>
        </div>
    @endif
</div>
@endsection
