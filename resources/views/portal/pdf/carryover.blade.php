@extends('portal.pdf._layout', ['title' => 'ใบขอยกยอดวันลาข้ามปี (Leave Carryover Request)'])

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
            <td>ประเภทวันลา</td>
            <td>
                @switch($doc->leave_type)
                    @case('vacation_leave') ลาพักร้อน (Vacation) @break
                    @default {{ $doc->leave_type }}
                @endswitch
            </td>
        </tr>
        <tr>
            <td>จำนวนวันที่ยกยอด</td>
            <td>{{ rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.') }} วัน</td>
        </tr>
        <tr>
            <td>ยกยอดจากปี</td>
            <td>{{ $doc->source_year ?? '-' }} (พ.ศ. {{ $doc->source_year ? $doc->source_year + 543 : '-' }})</td>
        </tr>
        <tr>
            <td>ไปใช้ในปี</td>
            <td>{{ $doc->year }} (พ.ศ. {{ $doc->year + 543 }})</td>
        </tr>
        <tr>
            <td>หมายเหตุ</td>
            <td>{{ $doc->note ?: '-' }}</td>
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
        @if($doc->rejection_reason)
        <tr>
            <td>เหตุผลที่ไม่อนุมัติ</td>
            <td>{{ $doc->rejection_reason }}</td>
        </tr>
        @endif
    </tbody>
</table>
@endsection
