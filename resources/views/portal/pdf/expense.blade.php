@extends('portal.pdf._layout', [
    'title' => $doc->type === 'advance' ? 'ใบเบิกเงินล่วงหน้า (Cash Advance Form)' : 'ใบเบิกค่าใช้จ่าย (Expense Reimbursement Form)'
])

@section('body')
<table class="details">
    <thead>
        <tr>
            <th style="width: 10%;">ลำดับ<br>(No.)</th>
            <th style="width: 60%;">รายการ<br>(Description)</th>
            <th style="width: 30%;">จำนวนเงิน / บาท<br>(Amount / THB)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">1</td>
            <td>{{ $doc->description }}</td>
            <td class="text-right">{{ number_format((float) $doc->amount, 2) }}</td>
        </tr>
        <tr><td style="height: 30px;"></td><td></td><td></td></tr>
        <tr><td style="height: 30px;"></td><td></td><td></td></tr>
        <tr>
            <td colspan="2" class="text-right" style="font-weight: bold;">รวมเงินทั้งสิ้น (Grand Total)</td>
            <td class="text-right" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format((float) $doc->amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<table class="doc-info" style="margin-top: -10px;">
    <tr>
        <td class="label">ประเภทการเบิก:</td>
        <td>
            @if($doc->type === 'advance')
                [ &#10003; ] เบิกเงินล่วงหน้า (Cash Advance) &nbsp;&nbsp; [ &nbsp;&nbsp; ] เบิกค่าใช้จ่าย (Reimbursement)
            @else
                [ &nbsp;&nbsp; ] เบิกเงินล่วงหน้า (Cash Advance) &nbsp;&nbsp; [ &#10003; ] เบิกค่าใช้จ่าย (Reimbursement)
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">วันที่ขอเบิก:</td>
        <td>{{ optional($doc->claim_date)->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">สถานะ:</td>
        <td>
            @switch($doc->status)
                @case('approved') อนุมัติแล้ว (Approved) — {{ optional($doc->approved_at)->format('d/m/Y') }} @break
                @case('rejected') ไม่อนุมัติ (Rejected) @break
                @default รออนุมัติ (Pending)
            @endswitch
        </td>
    </tr>
</table>
@endsection
