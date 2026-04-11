@extends('layouts.app')
@section('title', 'Employee Board')

@section('content')
<div x-data="{
    search: '{{ request('search', '') }}',
    showAddModal: false,
    payrollMode: '{{ request('payroll_mode', '') }}',
    departmentId: '{{ request('department_id', '') }}'
}">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Employee Board</h1>
        <button @click="showAddModal = true" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + เพิ่มพนักงาน
        </button>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('employees.index') }}" class="flex flex-wrap items-center gap-3 mb-6">
        <input type="text" name="search" x-model="search" placeholder="ค้นหาชื่อ / รหัส..."
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 w-64">
        <select name="payroll_mode" x-model="payrollMode" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">ทุก Payroll Mode</option>
            <option value="monthly_staff">พนักงานรายเดือน</option>
            <option value="freelance_layer">ฟรีแลนซ์เรทเลเยอร์</option>
            <option value="freelance_fixed">ฟรีแลนซ์ฟิกเรท</option>
            <option value="youtuber_salary">YouTuber เงินเดือน</option>
            <option value="youtuber_settlement">YouTuber Settlement</option>
        </select>
        <select name="department_id" x-model="departmentId" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">ทุกแผนก</option>
            @foreach($departments as $dept)
            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" name="show_inactive" value="1" {{ $showInactive ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600" onchange="this.form.submit()">
            แสดงพนักงานที่ระงับ
        </label>
        <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 ml-auto">ค้นหา</button>
    </form>

    @php
        $modeMeta = [
            'monthly_staff' => ['label' => 'พนักงานรายเดือน', 'badge' => 'bg-blue-100 text-blue-700'],
            'freelance_layer' => ['label' => 'ฟรีแลนซ์เรทเลเยอร์', 'badge' => 'bg-green-100 text-green-700'],
            'freelance_fixed' => ['label' => 'ฟรีแลนซ์ฟิกเรท', 'badge' => 'bg-emerald-100 text-emerald-700'],
            'youtuber_salary' => ['label' => 'YouTuber เงินเดือน', 'badge' => 'bg-purple-100 text-purple-700'],
            'youtuber_settlement' => ['label' => 'YouTuber Settlement', 'badge' => 'bg-orange-100 text-orange-700'],
            'custom_hybrid' => ['label' => 'รูปแบบผสม', 'badge' => 'bg-pink-100 text-pink-700'],
        ];

        $modeOrder = array_keys($modeMeta);
        $groupedEmployees = $employees->groupBy('payroll_mode')->sortBy(function ($_, $mode) use ($modeOrder) {
            $position = array_search($mode, $modeOrder, true);
            return $position === false ? 999 : $position;
        });
    @endphp

    @forelse($groupedEmployees as $mode => $employeesInMode)
    <section class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900">{{ $modeMeta[$mode]['label'] ?? $mode }}</h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                {{ $employeesInMode->count() }} คน
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @foreach($employeesInMode as $emp)
            <div class="relative group">
                <a href="{{ route('workspace.show', ['employee' => $emp->id, 'month' => now()->month, 'year' => now()->year]) }}"
                class="block h-full bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-indigo-300 transition {{ !$emp->is_active ? 'grayscale opacity-60 bg-gray-50' : '' }}">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full {{ $emp->is_active ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-200 text-gray-500' }} font-bold text-lg mb-3 mx-auto">
                        {{ mb_substr($emp->display_name, 0, 1) }}
                    </div>
                    <h3 class="text-center font-semibold {{ $emp->is_active ? 'text-gray-900' : 'text-gray-500' }} text-sm truncate">{{ $emp->display_name }}</h3>
                    <p class="text-center text-xs text-gray-500 mt-1">{{ $emp->position?->name ?? '-' }}</p>
                    <div class="mt-2 text-center">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $modeMeta[$emp->payroll_mode]['badge'] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $modeMeta[$emp->payroll_mode]['label'] ?? $emp->payroll_mode }}
                        </span>
                    </div>

                    @if(in_array($emp->payroll_mode, ['freelance_layer', 'freelance_fixed', 'youtuber_salary']))
                    <div class="mt-2 text-center">
                        @php $avgMin = $emp->average_minutes_last_3_months; @endphp
                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold 
                            {{ $avgMin < 100 ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-gray-100 text-gray-600' }}">
                            Avg: {{ number_format($avgMin, 1) }}m
                            @if($avgMin < 100) ⚠️ @endif
                        </span>
                    </div>
                    @endif

                    @if($emp->salaryProfile && $emp->salaryProfile->base_salary > 0)
                    <p class="text-center text-sm font-semibold text-gray-700 mt-2">
                        {{ number_format($emp->salaryProfile->base_salary, 0) }} ฿
                    </p>
                    @endif
                </a>

                <a href="{{ route('employees.edit', $emp->id) }}"
                   class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity px-2 py-1 rounded-md bg-white shadow-sm border border-gray-200 text-[11px] font-medium text-indigo-600 hover:bg-indigo-50"
                   title="แก้ไขข้อมูลพนักงาน"
                   onclick="event.stopPropagation()">
                    EDIT
                </a>

                <!-- Toggle Status Button -->
                <form action="{{ route('employees.toggle-status', $emp->id) }}" method="POST" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="p-1 rounded-full bg-white shadow-sm border border-gray-200 hover:bg-gray-50 {{ $emp->is_active ? 'text-red-500' : 'text-green-500' }}" title="{{ $emp->is_active ? 'ระงับพนักงาน' : 'เปิดใช้งานพนักงาน' }}">
                        @if($emp->is_active)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        @endif
                    </button>
                </form>
            </div>
            @endforeach

            @if($loop->first)
            <!-- Add Card -->
            <button @click="showAddModal = true"
                class="bg-white rounded-xl shadow-sm border-2 border-dashed border-gray-300 p-4 flex flex-col items-center justify-center min-h-[160px] hover:border-indigo-400 hover:bg-indigo-50 transition cursor-pointer">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-2xl mb-2">+</div>
                <span class="text-sm text-gray-500">เพิ่มพนักงาน</span>
            </button>
            @endif
        </div>
    </section>
    @empty
    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-500">
        ไม่พบพนักงานตามเงื่อนไขที่ค้นหา
    </div>
    @endforelse

    <!-- Add Employee Modal -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showAddModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <form method="POST" action="{{ route('employees.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-bold mb-4">เพิ่มพนักงานใหม่</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อ *</label>
                        <input type="text" name="first_name" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">นามสกุล *</label>
                        <input type="text" name="last_name" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อเล่น</label>
                        <input type="text" name="nickname" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">รหัสพนักงาน</label>
                        <input type="text" name="employee_code" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Payroll Mode *</label>
                        <select name="payroll_mode" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="monthly_staff">พนักงานรายเดือน</option>
                            <option value="freelance_layer">ฟรีแลนซ์เรทเลเยอร์</option>
                            <option value="freelance_fixed">ฟรีแลนซ์ฟิกเรท</option>
                            <option value="youtuber_salary">YouTuber เงินเดือน</option>
                            <option value="youtuber_settlement">YouTuber Settlement</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">แผนก</label>
                        <select name="department_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">-</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เงินเดือน</label>
                        <input type="number" name="base_salary" step="0.01" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">วันเริ่มงาน</label>
                        <input type="date" name="start_date" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ธนาคาร</label>
                        <input type="text" name="bank_name" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เลขบัญชี</label>
                        <input type="text" name="account_number" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
