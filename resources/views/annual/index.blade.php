@extends('layouts.app')

@section('title', 'สรุปรายปี ' . $year)

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">สรุปรายปีรายบุคคล (Annual Summary)</h1>
            <p class="text-sm text-gray-500">ปี {{ $year }} — พนักงาน {{ count($employeeData) }} คน</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('annual.index', ['year' => $year - 1]) }}" class="px-3 py-1.5 border rounded text-sm">← {{ $year - 1 }}</a>
            <span class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm font-bold">{{ $year }}</span>
            <a href="{{ route('annual.index', ['year' => $year + 1]) }}" class="px-3 py-1.5 border rounded text-sm">{{ $year + 1 }} →</a>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 text-left sticky left-0 bg-gray-50 z-10 min-w-[160px]">พนักงาน</th>
                        <th class="px-2 py-2 text-left sticky left-[160px] bg-gray-50 z-10 min-w-[100px]">Payroll Mode</th>
                        @for($m = 1; $m <= 12; $m++)
                        <th class="px-2 py-2 text-right min-w-[90px]">{{ $monthNames[$m] }}</th>
                        @endfor
                        <th class="px-2 py-2 text-right min-w-[100px] bg-indigo-50">รวมรับ</th>
                        <th class="px-2 py-2 text-right min-w-[100px] bg-red-50">รวมหัก</th>
                        <th class="px-2 py-2 text-right min-w-[100px] bg-green-50 font-bold">สุทธิรวม</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employeeData as $row)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-2 py-2 sticky left-0 bg-white z-10">
                            <a href="{{ route('workspace.show', [$row['employee'], now()->month, $year]) }}" class="text-indigo-600 hover:underline font-medium">
                                {{ $row['employee']->first_name }} {{ $row['employee']->last_name }}
                            </a>
                        </td>
                        <td class="px-2 py-2 sticky left-[160px] bg-white z-10">
                            <span class="px-1.5 py-0.5 rounded text-[10px]
                                @switch($row['employee']->payroll_mode)
                                    @case('monthly_staff') bg-blue-100 text-blue-700 @break
                                    @case('freelance_layer') bg-purple-100 text-purple-700 @break
                                    @case('freelance_fixed') bg-orange-100 text-orange-700 @break
                                    @case('youtuber_salary') bg-pink-100 text-pink-700 @break
                                    @case('youtuber_settlement') bg-yellow-100 text-yellow-700 @break
                                    @default bg-gray-100 text-gray-700
                                @endswitch
                            ">{{ $row['employee']->payroll_mode }}</span>
                        </td>
                        @for($m = 1; $m <= 12; $m++)
                        @php $md = $row['monthly'][$m]; @endphp
                        <td class="px-2 py-2 text-right {{ $md['net'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">
                            @if($md['net'] > 0)
                                {{ number_format($md['net'], 0) }}
                                @if($md['finalized'])
                                <span class="text-green-500 text-[9px]">✓</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        @endfor
                        <td class="px-2 py-2 text-right bg-indigo-50/50 text-indigo-700 font-medium">{{ number_format($row['total_income'], 0) }}</td>
                        <td class="px-2 py-2 text-right bg-red-50/50 text-red-600 font-medium">{{ number_format($row['total_deduction'], 0) }}</td>
                        <td class="px-2 py-2 text-right bg-green-50/50 font-bold {{ $row['total_net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($row['total_net'], 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                @if(count($employeeData) > 0)
                <tfoot class="bg-gray-100 font-bold text-xs">
                    <tr>
                        <td class="px-2 py-2 sticky left-0 bg-gray-100 z-10" colspan="2">รวมทั้งหมด</td>
                        @for($m = 1; $m <= 12; $m++)
                        <td class="px-2 py-2 text-right">
                            {{ number_format(collect($employeeData)->sum(fn($r) => $r['monthly'][$m]['net']), 0) }}
                        </td>
                        @endfor
                        <td class="px-2 py-2 text-right bg-indigo-50 text-indigo-700">{{ number_format(collect($employeeData)->sum('total_income'), 0) }}</td>
                        <td class="px-2 py-2 text-right bg-red-50 text-red-600">{{ number_format(collect($employeeData)->sum('total_deduction'), 0) }}</td>
                        <td class="px-2 py-2 text-right bg-green-50 text-green-700">{{ number_format(collect($employeeData)->sum('total_net'), 0) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
