@extends('portal.pdf._layout', ['title' => 'ใบลา (Leave Request Form)'])

@php
    $typeLabels = [
        'sick_leave'     => 'ลาป่วย (Sick Leave)',
        'personal_leave' => 'ลากิจ (Personal Leave)',
        'vacation_leave' => 'ลาพักร้อน (Vacation)',
        'lwop'           => 'ลาไม่รับค่าจ้าง (Leave Without Pay)',
    ];
@endphp

@section('body')
<table class="details">
    <thead>
        <tr>
            <th style="width: 30%;">รายการ (Item)</th>
            <th style="width: 70%;">รายละเอียด (Detail)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>ประเภทการลา</td>
            <td>{{ $typeLabels[$doc->leave_type] ?? $doc->leave_type }}</td>
        </tr>
        <tr>
            <td>วันที่ลา</td>
            <td>{{ optional($doc->leave_date)->format('d/m/Y') }} ({{ optional($doc->leave_date)->locale('th')->isoFormat('dddd') }})</td>
        </tr>
        <tr>
            <td>เหตุผล</td>
            <td>{{ $doc->reason ?: '-' }}</td>
        </tr>
        <tr>
            <td>สถานะคำขอ</td>
            <td>
                @switch($doc->status)
                    @case('approved') อนุมัติแล้ว (Approved) @break
                    @case('rejected') ไม่อนุมัติ (Rejected) @break
                    @default รออนุมัติ (Pending)
                @endswitch
            </td>
        </tr>
        @if($doc->reviewed_at)
        <tr>
            <td>หมายเหตุผู้อนุมัติ</td>
            <td>{{ $doc->review_note ?: '-' }}</td>
        </tr>
        @endif
    </tbody>
</table>

@if($doc->attachments && $doc->attachments->isNotEmpty())
    <p style="margin-top: -10px; font-size: 14px;">
        📎 มีไฟล์แนบ {{ $doc->attachments->count() }} ไฟล์ (ตรวจสอบในระบบ)
    </p>
@endif
@endsection
