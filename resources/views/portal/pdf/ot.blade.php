@extends('portal.pdf._layout', ['title' => 'ใบขออนุมัติทำงานล่วงเวลา (Overtime Request Form)'])

@php
    $hours = $doc->requested_minutes > 0 ? round($doc->requested_minutes / 60, 2) : 0;
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
            <td>วันที่ทำ OT</td>
            <td>{{ optional($doc->log_date)->format('d/m/Y') }} ({{ optional($doc->log_date)->locale('th')->isoFormat('dddd') }})</td>
        </tr>
        <tr>
            <td>จำนวนชั่วโมงที่ขอ</td>
            <td>{{ $hours }} ชั่วโมง ({{ $doc->requested_minutes }} นาที)</td>
        </tr>
        <tr>
            <td>งานที่จะทำ / อ้างอิง</td>
            <td>{{ $doc->job_reference ?: '-' }}</td>
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
@endsection
