@extends('layouts.app')

@section('title', 'Expense Tracker')

@php
    $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $colorMap = [
        'red' => 'bg-red-100 text-red-700', 'green' => 'bg-green-100 text-green-700',
        'purple' => 'bg-purple-100 text-purple-700', 'indigo' => 'bg-indigo-100 text-indigo-700',
        'amber' => 'bg-amber-100 text-amber-700', 'pink' => 'bg-pink-100 text-pink-700',
        'teal' => 'bg-teal-100 text-teal-700', 'orange' => 'bg-orange-100 text-orange-700',
        'gray' => 'bg-gray-100 text-gray-700',
    ];
    $incomeCategories = $categories->where('type', 'income');
    $expenseCategories = $categories->where('type', 'expense');
    $net = $totalIncome - $totalExpense;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{ view: '{{ $viewMode }}', showForm: false }">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">💰 รายรับ / ค่าใช้จ่าย</h1>
            <p class="text-sm text-gray-600 mt-1">วิเคราะห์กระแสเงินเข้า-ออกบริษัท · รวมทุกอย่างที่ไม่ใช่เงินเดือน</p>
        </div>
        <button @click="showForm = !showForm" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">
            + เพิ่มรายการ
        </button>
    </div>

    <!-- Summary -->
    <div class="grid md:grid-cols-3 gap-3">
        <div class="bg-white border border-green-200 rounded-xl p-4">
            <div class="text-xs font-bold text-green-600 uppercase">รายรับ</div>
            <div class="text-2xl font-bold text-green-700 mt-1">+{{ number_format($totalIncome, 2) }} ฿</div>
        </div>
        <div class="bg-white border border-red-200 rounded-xl p-4">
            <div class="text-xs font-bold text-red-600 uppercase">รายจ่าย</div>
            <div class="text-2xl font-bold text-red-700 mt-1">-{{ number_format($totalExpense, 2) }} ฿</div>
        </div>
        <div class="bg-white border {{ $net >= 0 ? 'border-indigo-200' : 'border-red-300' }} rounded-xl p-4">
            <div class="text-xs font-bold uppercase {{ $net >= 0 ? 'text-indigo-600' : 'text-red-600' }}">กำไรสุทธิ</div>
            <div class="text-2xl font-bold mt-1 {{ $net >= 0 ? 'text-indigo-700' : 'text-red-700' }}">
                {{ $net >= 0 ? '+' : '' }}{{ number_format($net, 2) }} ฿
            </div>
        </div>
    </div>

    <!-- Add form (collapsible) -->
    <form x-show="showForm" x-cloak x-transition method="POST" action="{{ route('expense-tracker.entry.store') }}"
          class="bg-white border-2 border-indigo-200 rounded-xl p-5">
        @csrf
        <div class="grid md:grid-cols-6 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">ประเภท</label>
                <select name="type" required class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                    <option value="expense">รายจ่าย</option>
                    <option value="income">รายรับ</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">หมวด</label>
                <select name="expense_category_id" required class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                    <optgroup label="รายจ่าย">
                        @foreach($expenseCategories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="รายรับ">
                        @foreach($incomeCategories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">วันที่</label>
                <input type="date" name="entry_date" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">จำนวน (฿)</label>
                <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Recurring</label>
                <label class="flex items-center h-10 gap-2">
                    <input type="checkbox" name="is_recurring" value="1" class="accent-indigo-600">
                    <span class="text-sm text-gray-600">รายเดือน</span>
                </label>
            </div>
            <div class="md:col-span-6">
                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">รายการ</label>
                <input type="text" name="description" required maxlength="500" placeholder="เช่น Adobe CC · 12 licenses"
                       class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
            </div>
        </div>
        <div class="flex justify-end mt-3 gap-2">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">ยกเลิก</button>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">บันทึก</button>
        </div>
    </form>

    <!-- Filters + View Switcher -->
    <div class="bg-white border border-gray-200 rounded-xl p-3 flex flex-wrap items-center gap-3">
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <select name="month" class="px-3 py-1.5 border border-gray-300 rounded text-sm" onchange="this.form.submit()">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $monthNames[$m] }}</option>
                @endfor
            </select>
            <select name="year" class="px-3 py-1.5 border border-gray-300 rounded text-sm" onchange="this.form.submit()">
                @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <select name="type" class="px-3 py-1.5 border border-gray-300 rounded text-sm" onchange="this.form.submit()">
                <option value="">ทุกประเภท</option>
                <option value="income" {{ $typeFilter === 'income' ? 'selected' : '' }}>เฉพาะรายรับ</option>
                <option value="expense" {{ $typeFilter === 'expense' ? 'selected' : '' }}>เฉพาะรายจ่าย</option>
            </select>
            <select name="category_id" class="px-3 py-1.5 border border-gray-300 rounded text-sm" onchange="this.form.submit()">
                <option value="">ทุกหมวด</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ $catFilter == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="view" :value="view">
        </form>

        <div class="ml-auto inline-flex items-center rounded-lg border border-gray-300 overflow-hidden text-xs">
            <button type="button" @click="view='table'" :class="view==='table' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1.5 font-semibold">☰ ตาราง</button>
            <button type="button" @click="view='card'" :class="view==='card' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1.5 font-semibold">▦ การ์ด</button>
        </div>
    </div>

    <!-- Table view -->
    <div x-show="view==='table'" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-800 text-white px-4 py-2 text-sm font-semibold">
            ค่าใช้จ่าย & รายรับ — {{ $monthNames[$month] }} {{ $year }}
        </div>
        @if($entries->isEmpty())
            <div class="p-10 text-center text-gray-400 text-sm">ไม่มีรายการในเดือนนี้</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">วันที่</th>
                    <th class="px-3 py-2 text-left">ประเภท</th>
                    <th class="px-3 py-2 text-left">หมวด</th>
                    <th class="px-3 py-2 text-left">รายการ</th>
                    <th class="px-3 py-2 text-right">จำนวน</th>
                    <th class="px-3 py-2 text-center">Recurring</th>
                    <th class="px-3 py-2 text-center">สถานะ</th>
                    <th class="px-3 py-2 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($entries as $e)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($e['date'])->format('d M') }}</td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $e['type']==='income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $e['type']==='income' ? 'รายรับ' : 'รายจ่าย' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-gray-600">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $colorMap[$e['category_color']] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $e['category'] }}
                        </span>
                    </td>
                    <td class="px-3 py-2">{{ $e['description'] }}</td>
                    <td class="px-3 py-2 text-right font-bold {{ $e['type']==='income' ? 'text-green-700' : 'text-red-700' }}">
                        {{ $e['type']==='income' ? '+' : '-' }}{{ number_format($e['amount'], 2) }}
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if($e['is_recurring'])
                            <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-bold">รายเดือน</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        <span class="text-[10px] text-gray-500">{{ $e['status'] }}</span>
                    </td>
                    <td class="px-3 py-2 text-right">
                        <form method="POST" action="{{ route('expense-tracker.entry.delete', [$e['model'], $e['id']]) }}"
                              class="inline" onsubmit="return confirm('ลบรายการนี้?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">ลบ</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="4" class="px-3 py-2 text-right font-semibold text-gray-600">รวม</td>
                    <td class="px-3 py-2 text-right">
                        <span class="text-green-700 font-bold">+{{ number_format($totalIncome, 2) }}</span>
                        <span class="text-gray-400 mx-1">/</span>
                        <span class="text-red-700 font-bold">-{{ number_format($totalExpense, 2) }}</span>
                    </td>
                    <td colspan="3" class="px-3 py-2 text-right font-bold {{ $net >= 0 ? 'text-indigo-700' : 'text-red-700' }}">
                        สุทธิ: {{ $net >= 0 ? '+' : '' }}{{ number_format($net, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

    <!-- Card view -->
    <div x-show="view==='card'" class="grid md:grid-cols-2 gap-4">
        <!-- Income cards -->
        <div class="space-y-3">
            <div class="flex items-center gap-2 text-green-700 font-bold">
                <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                รายรับ ({{ $groupedIncome->count() }} หมวด)
            </div>
            @forelse($groupedIncome as $catName => $rows)
                @php $sum = $rows->sum('amount'); @endphp
                <div class="bg-white border border-green-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-green-800">{{ $catName }}</div>
                        <div class="text-lg font-bold text-green-700">+{{ number_format($sum, 2) }}</div>
                    </div>
                    <div class="space-y-1">
                        @foreach($rows as $r)
                            <div class="flex justify-between text-xs text-gray-600 py-1 border-t border-gray-100">
                                <span class="truncate max-w-xs">{{ $r->description ?? $r->source }}</span>
                                <span class="font-mono text-gray-700">+{{ number_format($r->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-400 italic">ไม่มีรายรับในเดือนนี้</div>
            @endforelse
        </div>

        <!-- Expense cards -->
        <div class="space-y-3">
            <div class="flex items-center gap-2 text-red-700 font-bold">
                <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                รายจ่าย ({{ $groupedExpense->count() }} หมวด)
            </div>
            @forelse($groupedExpense as $catName => $rows)
                @php $sum = $rows->sum('amount'); @endphp
                <div class="bg-white border border-red-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-red-800">{{ $catName }}</div>
                        <div class="text-lg font-bold text-red-700">-{{ number_format($sum, 2) }}</div>
                    </div>
                    <div class="space-y-1">
                        @foreach($rows as $r)
                            <div class="flex justify-between text-xs text-gray-600 py-1 border-t border-gray-100">
                                <span class="truncate max-w-xs">
                                    {{ $r->description ?? $r->category }}
                                    @if($r->is_recurring)<span class="text-[9px] px-1 bg-indigo-50 text-indigo-600 rounded">M</span>@endif
                                </span>
                                <span class="font-mono text-gray-700">-{{ number_format($r->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-400 italic">ไม่มีรายจ่ายในเดือนนี้</div>
            @endforelse
        </div>
    </div>

    <!-- Category manager -->
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-sm text-gray-800">หมวดหมู่</h2>
        </div>
        <form method="POST" action="{{ route('expense-tracker.categories.store') }}" class="flex gap-2 flex-wrap mb-3">
            @csrf
            <input type="text" name="name" required maxlength="120" placeholder="ชื่อหมวดใหม่" class="px-3 py-1.5 border border-gray-300 rounded text-sm flex-1 min-w-[160px]">
            <select name="type" class="px-3 py-1.5 border border-gray-300 rounded text-sm">
                <option value="expense">รายจ่าย</option>
                <option value="income">รายรับ</option>
            </select>
            <select name="color" class="px-3 py-1.5 border border-gray-300 rounded text-sm">
                @foreach(['red','green','purple','indigo','amber','pink','teal','orange','gray'] as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <button class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm font-semibold">+ เพิ่ม</button>
        </form>
        <div class="flex flex-wrap gap-2">
            @foreach($categories as $c)
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold {{ $colorMap[$c->color] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $c->name }}
                    <span class="text-[9px] opacity-60">({{ $c->type }})</span>
                    <form method="POST" action="{{ route('expense-tracker.categories.delete', $c) }}" class="inline">
                        @csrf @method('DELETE')
                        <button class="ml-1 opacity-60 hover:opacity-100" title="ซ่อน">×</button>
                    </form>
                </span>
            @endforeach
        </div>
    </div>
</div>
@endsection
