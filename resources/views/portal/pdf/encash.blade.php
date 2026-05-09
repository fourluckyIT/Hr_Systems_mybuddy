@extends('portal.pdf._layout', ['title' => 'ใบขอแลกวันลาเป็นเงิน (Leave Encashment Request)'])

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
            <td>ปีของสิทธิ</td>
            <td>{{ $doc->year }} (พ.ศ. {{ $doc->year + 543 }})</td>
        </tr>
        <tr>
            <td>จำนวนวันที่แลก</td>
            <td>{{ rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.') }} วัน</td>
        </tr>
        <tr>
            <td>อัตราต่อวัน</td>
            <td>{{ number_format((float) $doc->rate_per_day, 2) }} บาท</td>
        </tr>
        <tr>
            <td>ยอดที่จะได้รับ</td>
            <td><b>{{ number_format((float) $doc->amount, 2) }} บาท</b></td>
        </tr>
        <tr>
            <td>เดือนที่จ่าย</td>
            <td>{{ sprintf('%02d/%d', $doc->payout_month, $doc->payout_year) }}</td>
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
