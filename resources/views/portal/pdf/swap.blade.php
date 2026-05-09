@extends('portal.pdf._layout', ['title' => 'ใบขอสลับวันทำงาน (Day Swap Request Form)'])

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
            <td>วันที่จะมาทำงาน</td>
            <td>{{ optional($doc->work_date)->format('d/m/Y') }} ({{ optional($doc->work_date)->locale('th')->isoFormat('dddd') }})</td>
        </tr>
        <tr>
            <td>วันที่จะหยุดทดแทน</td>
            <td>{{ optional($doc->off_date)->format('d/m/Y') }} ({{ optional($doc->off_date)->locale('th')->isoFormat('dddd') }})</td>
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
