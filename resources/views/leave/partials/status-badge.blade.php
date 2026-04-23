@php
    $badgeMap = [
        'approved'  => ['bg-emerald-100 text-emerald-700', '✓ อนุมัติ'],
        'rejected'  => ['bg-red-100 text-red-700', '✗ ปฏิเสธ'],
        'cancelled' => ['bg-gray-100 text-gray-500', 'ยกเลิก'],
        'pending'   => ['bg-amber-100 text-amber-700', '⏳ รอตรวจสอบ'],
    ];
    $badge = $badgeMap[$status] ?? $badgeMap['pending'];
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold {{ $badge[0] }}">
    {{ $badge[1] }}
</span>
