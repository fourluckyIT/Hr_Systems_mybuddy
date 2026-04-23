@extends('layouts.app')

@section('title', 'สรุปการเงินบริษัท')

@section('content')
<div x-data="{ showRevenueForm: false, showExpenseForm: false, showSubForm: false }" class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">สรุปการเงินบริษัท (Company P&L)</h1>
            <p class="text-sm text-gray-500">ปี {{ $year }}</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('company.finance', ['year' => $year - 1]) }}" class="px-3 py-1.5 border rounded text-sm">← {{ $year - 1 }}</a>
            <span class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm font-bold">{{ $year }}</span>
            <a href="{{ route('company.finance', ['year' => $year + 1]) }}" class="px-3 py-1.5 border rounded text-sm">{{ $year + 1 }} →</a>
        </div>
    </div>

    {{-- Year Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500">รายรับรวม</p>
            <p class="text-lg font-bold text-green-600">฿{{ number_format($yearTotals['revenue'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500">ค่าใช้จ่ายทั่วไป</p>
            <p class="text-lg font-bold text-red-600">฿{{ number_format($yearTotals['expense'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500">Subscription</p>
            <p class="text-lg font-bold text-orange-600">฿{{ number_format($yearTotals['subscription'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500">ค่าจ้าง/เงินเดือน</p>
            <p class="text-lg font-bold text-blue-600">฿{{ number_format($yearTotals['payroll'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500">กำไร/ขาดทุนรวม</p>
            <p class="text-lg font-bold {{ $yearTotals['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                {{ $yearTotals['net'] >= 0 ? '+' : '' }}฿{{ number_format($yearTotals['net'], 2) }}
            </p>
        </div>
    </div>

    {{-- Monthly P&L Table --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 bg-indigo-600 text-white font-semibold text-sm">
            ตาราง P&L รายเดือน — ปี {{ $year }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">เดือน</th>
                        <th class="px-3 py-2 text-right text-green-700">รายรับ</th>
                        <th class="px-3 py-2 text-right text-red-700">ค่าใช้จ่าย</th>
                        <th class="px-3 py-2 text-right text-orange-700">Subscription</th>
                        <th class="px-3 py-2 text-right text-blue-700">เงินเดือน</th>
                        <th class="px-3 py-2 text-right">รวมค่าใช้จ่าย</th>
                        <th class="px-3 py-2 text-right font-bold">กำไร/ขาดทุน</th>
                        <th class="px-3 py-2 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @php $cumulative = 0; @endphp
                    @foreach($monthlyData as $m => $data)
                    @php $cumulative += $data['net']; @endphp
                    <tr class="border-t hover:bg-gray-50 {{ $month == $m ? 'bg-indigo-50' : '' }}">
                        <td class="px-3 py-2 font-medium">{{ $data['month_name'] }}</td>
                        <td class="px-3 py-2 text-right text-green-700">฿{{ number_format($data['revenue'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-red-600">฿{{ number_format($data['expense'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-orange-600">฿{{ number_format($data['subscription'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-blue-600">฿{{ number_format($data['payroll'], 2) }}</td>
                        <td class="px-3 py-2 text-right">฿{{ number_format($data['total_expense'], 2) }}</td>
                        <td class="px-3 py-2 text-right font-bold {{ $data['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $data['net'] >= 0 ? '+' : '' }}฿{{ number_format($data['net'], 2) }}
                            <span class="text-[10px] text-gray-400 block">สะสม: ฿{{ number_format($cumulative, 2) }}</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('company.finance', ['year' => $year, 'month' => $m]) }}"
                               class="text-indigo-600 hover:underline text-xs">ดูรายละเอียด</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-bold text-xs">
                    <tr>
                        <td class="px-3 py-2">รวมทั้งปี</td>
                        <td class="px-3 py-2 text-right text-green-700">{{ number_format($yearTotals['revenue'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-red-700">{{ number_format($yearTotals['expense'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-orange-700">{{ number_format($yearTotals['subscription'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-blue-700">{{ number_format($yearTotals['payroll'], 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($yearTotals['expense'] + $yearTotals['subscription'] + $yearTotals['payroll'], 2) }}</td>
                        <td class="px-3 py-2 text-right {{ $yearTotals['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($yearTotals['net'], 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Monthly Detail (when month selected) --}}
    @if($month)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Revenues --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 bg-green-600 text-white font-semibold text-sm flex justify-between">
                <span>รายรับ — {{ $monthNames[$month] }} {{ $year }}</span>
                <button @click="showRevenueForm = !showRevenueForm" class="text-xs bg-green-500 hover:bg-green-400 px-2 py-0.5 rounded">+ เพิ่ม</button>
            </div>
            <div x-show="showRevenueForm" x-cloak class="p-3 bg-green-50 border-b">
                <form method="POST" action="{{ route('company.revenue.store') }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="text" name="source" placeholder="แหล่งรายได้" required class="w-full px-2 py-1 border rounded text-xs">
                    <input type="text" name="description" placeholder="รายละเอียด" class="w-full px-2 py-1 border rounded text-xs">
                    <input type="number" step="0.01" name="amount" placeholder="จำนวนเงิน" required class="w-full px-2 py-1 border rounded text-xs">
                    <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs">บันทึก</button>
                </form>
            </div>
            <div class="divide-y">
                @forelse($revenues as $r)
                <div class="px-3 py-2 flex justify-between items-center text-xs">
                    <div>
                        <p class="font-medium">{{ $r->source }}</p>
                        @if($r->description)<p class="text-gray-400">{{ $r->description }}</p>@endif
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-green-700">{{ number_format($r->amount, 2) }}</span>
                        <form method="POST" action="{{ route('company.revenue.delete', $r) }}" onsubmit="return confirm('ลบรายรับนี้?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600">&times;</button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="px-3 py-4 text-center text-gray-400 text-xs">ไม่มีรายรับ</div>
                @endforelse
            </div>
        </div>

        {{-- Expenses --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 bg-red-600 text-white font-semibold text-sm flex justify-between">
                <span>ค่าใช้จ่าย — {{ $monthNames[$month] }} {{ $year }}</span>
                <button @click="showExpenseForm = !showExpenseForm" class="text-xs bg-red-500 hover:bg-red-400 px-2 py-0.5 rounded">+ เพิ่ม</button>
            </div>
            <div x-show="showExpenseForm" x-cloak class="p-3 bg-red-50 border-b">
                <form method="POST" action="{{ route('company.expense.store') }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="text" name="category" placeholder="หมวดหมู่" required class="w-full px-2 py-1 border rounded text-xs">
                    <input type="text" name="description" placeholder="รายละเอียด" class="w-full px-2 py-1 border rounded text-xs">
                    <input type="number" step="0.01" name="amount" placeholder="จำนวนเงิน" required class="w-full px-2 py-1 border rounded text-xs">
                    <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-xs">บันทึก</button>
                </form>
            </div>
            <div class="divide-y">
                @forelse($expenses as $e)
                <div class="px-3 py-2 flex justify-between items-center text-xs">
                    <div>
                        <p class="font-medium">{{ $e->category }}</p>
                        @if($e->description)<p class="text-gray-400">{{ $e->description }}</p>@endif
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-red-700">{{ number_format($e->amount, 2) }}</span>
                        <form method="POST" action="{{ route('company.expense.delete', $e) }}" onsubmit="return confirm('ลบรายการนี้?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600">&times;</button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="px-3 py-4 text-center text-gray-400 text-xs">ไม่มีค่าใช้จ่าย</div>
                @endforelse
            </div>
        </div>

        {{-- Subscriptions --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 bg-orange-600 text-white font-semibold text-sm flex justify-between">
                <span>Subscription — {{ $monthNames[$month] }} {{ $year }}</span>
                <button @click="showSubForm = !showSubForm" class="text-xs bg-orange-500 hover:bg-orange-400 px-2 py-0.5 rounded">+ เพิ่ม</button>
            </div>
            <div x-show="showSubForm" x-cloak class="p-3 bg-orange-50 border-b">
                <form method="POST" action="{{ route('company.subscription.store') }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="text" name="name" placeholder="ชื่อบริการ" required class="w-full px-2 py-1 border rounded text-xs">
                    <input type="number" step="0.01" name="amount" placeholder="จำนวนเงิน" required class="w-full px-2 py-1 border rounded text-xs">
                    <label class="flex items-center text-xs"><input type="checkbox" name="is_recurring" value="1" class="mr-1"> Recurring</label>
                    <button type="submit" class="bg-orange-600 text-white px-3 py-1 rounded text-xs">บันทึก</button>
                </form>
            </div>
            <div class="divide-y">
                @forelse($subscriptions as $s)
                <div class="px-3 py-2 flex justify-between items-center text-xs">
                    <div>
                        <p class="font-medium">{{ $s->name }}</p>
                        @if($s->is_recurring)<span class="text-[10px] bg-orange-100 text-orange-700 px-1 rounded">Recurring</span>@endif
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-orange-700">{{ number_format($s->amount, 2) }}</span>
                        <form method="POST" action="{{ route('company.subscription.delete', $s) }}" onsubmit="return confirm('ลบรายการนี้?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600">&times;</button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="px-3 py-4 text-center text-gray-400 text-xs">ไม่มี Subscription</div>
                @endforelse
            </div>
        </div>

    </div>
    @endif
</div>
@endsection
