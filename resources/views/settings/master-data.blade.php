@extends('layouts.app')
@section('title', 'Master Data')

@section('content')
<div x-data="{ activeTab: localStorage.getItem('masterDataTab') ?? 'payroll_items' }"
     x-init="$watch('activeTab', value => localStorage.setItem('masterDataTab', value))">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Master Data</h1>
            <p class="text-sm text-gray-500">จัดการข้อมูลหลัก: รายการเงินเดือน, แผนก, ตำแหน่ง, เกม, เรท FL, สิทธิ์ Workspace</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.rules') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">← กฎระบบ</a>
            <a href="{{ route('settings.bonus.index') }}" class="px-3 py-1.5 text-sm text-indigo-700 bg-indigo-100 rounded-lg hover:bg-indigo-200">Bonus</a>
            <a href="{{ route('settings.company') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">บริษัท</a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-semibold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-semibold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
        {{ session('error') }}
    </div>
    @endif

    <!-- Tab Navigation -->
    <div class="flex border-b border-gray-200 mb-6 gap-1">
        <button @click="activeTab = 'payroll_items'" :class="activeTab === 'payroll_items' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            รายการเงินเดือน
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ $payrollItemTypes->count() }}</span>
        </button>
        <button @click="activeTab = 'departments'" :class="activeTab === 'departments' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            แผนก
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ $departments->count() }}</span>
        </button>
        <button @click="activeTab = 'positions'" :class="activeTab === 'positions' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            ตำแหน่ง
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ $positions->count() }}</span>
        </button>
        <button @click="activeTab = 'job_stages'" :class="activeTab === 'job_stages' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            Job Stages
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ $jobStages->count() }}</span>
        </button>
        <button @click="activeTab = 'games'" :class="activeTab === 'games' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            เกม
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ $games->count() }}</span>
        </button>

        <button @click="activeTab = 'workspace_access'" :class="activeTab === 'workspace_access' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            คุมสิทธิ์ Workspace
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ $employees->count() }}</span>
        </button>

        @php $isAdmin = auth()->user()?->hasRole('admin'); @endphp
        @if($isAdmin)
        <button @click="activeTab = 'fl_layer_rate'" :class="activeTab === 'fl_layer_rate' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            FL Layer Rate
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-700">admin</span>
        </button>
        <button @click="activeTab = 'leave_policies'" :class="activeTab === 'leave_policies' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            🏖️ นโยบายวันลา
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-teal-100 text-teal-700">admin</span>
        </button>
        <button @click="activeTab = 'holiday_types'" :class="activeTab === 'holiday_types' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'" class="px-4 py-2.5 text-sm font-semibold border-b-2 rounded-t-lg transition-all">
            📅 ประเภทวันหยุด
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-purple-100 text-purple-700">{{ $holidayTypes->count() }}</span>
        </button>
        @endif
    </div>

    <!-- ===================== TAB: Payroll Item Types ===================== -->
    <div x-show="activeTab === 'payroll_items'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add Form -->
            <div class="bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มรายการใหม่
                </h3>
                <form action="{{ route('settings.master-data.payroll-item-types.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Code (ไม่ซ้ำ)</label>
                        <input type="text" name="code" required placeholder="เช่น bonus_monthly" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อภาษาไทย *</label>
                        <input type="text" name="label_th" required placeholder="เช่น โบนัสรายเดือน" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="label_en" placeholder="เช่น Monthly Bonus" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">หมวด *</label>
                            <select name="category" required class="w-full px-3 py-2 border rounded-lg text-sm">
                                <option value="income">เงินได้ (Income)</option>
                                <option value="deduction">เงินหัก (Deduction)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ลำดับ</label>
                            <input type="number" name="sort_order" value="99" min="0" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">เพิ่มรายการ</button>
                </form>
            </div>

            <!-- Income List -->
            <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-green-50 border-b border-green-100">
                    <h3 class="font-bold text-green-800 text-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
                        เงินได้ (Income)
                        <span class="text-xs bg-green-200 text-green-700 px-1.5 py-0.5 rounded-full font-bold">{{ $payrollItemTypes->where('category', 'income')->count() }}</span>
                    </h3>
                </div>
                <div class="divide-y">
                    @foreach($payrollItemTypes->where('category', 'income') as $item)
                    <div class="px-5 py-3 group hover:bg-gray-50 transition" x-data="{ editing: false }">
                        <div x-show="!editing" class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-gray-800">{{ $item->label_th }}</div>
                                <div class="text-[11px] text-gray-400 flex items-center gap-2">
                                    <code class="bg-gray-100 px-1 rounded">{{ $item->code }}</code>
                                    @if($item->label_en)
                                    <span>{{ $item->label_en }}</span>
                                    @endif
                                    <span>sort: {{ $item->sort_order }}</span>
                                    @if($item->is_system)
                                    <span class="px-1 py-0.5 bg-blue-100 text-blue-600 rounded text-[9px] font-bold uppercase">System</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                @if(!$item->is_system)
                                <form action="{{ route('settings.master-data.payroll-item-types.delete', $item->id) }}" method="POST" onsubmit="return confirm('ลบรายการ {{ $item->label_th }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                </form>
                                @endif
                            </div>
                        </div>
                        <form x-show="editing" x-cloak action="{{ route('settings.master-data.payroll-item-types.update', $item->id) }}" method="POST" class="space-y-2">
                            @csrf @method('PATCH')
                            <input type="text" name="label_th" value="{{ $item->label_th }}" class="w-full px-2 py-1.5 border rounded text-sm font-semibold" required>
                            <input type="text" name="label_en" value="{{ $item->label_en }}" placeholder="English name" class="w-full px-2 py-1.5 border rounded text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <select name="category" class="px-2 py-1.5 border rounded text-xs">
                                    <option value="income" @selected($item->category === 'income')>Income</option>
                                    <option value="deduction" @selected($item->category === 'deduction')>Deduction</option>
                                </select>
                                <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" class="px-2 py-1.5 border rounded text-xs">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200">ยกเลิก</button>
                                <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">บันทึก</button>
                            </div>
                        </form>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Deduction List -->
            <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-red-50 border-b border-red-100">
                    <h3 class="font-bold text-red-800 text-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                        เงินหัก (Deduction)
                        <span class="text-xs bg-red-200 text-red-700 px-1.5 py-0.5 rounded-full font-bold">{{ $payrollItemTypes->where('category', 'deduction')->count() }}</span>
                    </h3>
                </div>
                <div class="divide-y">
                    @foreach($payrollItemTypes->where('category', 'deduction') as $item)
                    <div class="px-5 py-3 group hover:bg-gray-50 transition" x-data="{ editing: false }">
                        <div x-show="!editing" class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-gray-800">{{ $item->label_th }}</div>
                                <div class="text-[11px] text-gray-400 flex items-center gap-2">
                                    <code class="bg-gray-100 px-1 rounded">{{ $item->code }}</code>
                                    @if($item->label_en)
                                    <span>{{ $item->label_en }}</span>
                                    @endif
                                    <span>sort: {{ $item->sort_order }}</span>
                                    @if($item->is_system)
                                    <span class="px-1 py-0.5 bg-blue-100 text-blue-600 rounded text-[9px] font-bold uppercase">System</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                @if(!$item->is_system)
                                <form action="{{ route('settings.master-data.payroll-item-types.delete', $item->id) }}" method="POST" onsubmit="return confirm('ลบรายการ {{ $item->label_th }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                </form>
                                @endif
                            </div>
                        </div>
                        <form x-show="editing" x-cloak action="{{ route('settings.master-data.payroll-item-types.update', $item->id) }}" method="POST" class="space-y-2">
                            @csrf @method('PATCH')
                            <input type="text" name="label_th" value="{{ $item->label_th }}" class="w-full px-2 py-1.5 border rounded text-sm font-semibold" required>
                            <input type="text" name="label_en" value="{{ $item->label_en }}" placeholder="English name" class="w-full px-2 py-1.5 border rounded text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <select name="category" class="px-2 py-1.5 border rounded text-xs">
                                    <option value="income" @selected($item->category === 'income')>Income</option>
                                    <option value="deduction" @selected($item->category === 'deduction')>Deduction</option>
                                </select>
                                <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" class="px-2 py-1.5 border rounded text-xs">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200">ยกเลิก</button>
                                <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">บันทึก</button>
                            </div>
                        </form>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Quick Reference -->
        <div class="mt-6 bg-gray-50 rounded-2xl border border-dashed border-gray-200 p-5">
            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3">Payroll Mode Reference</h4>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                @php
                    $modes = [
                        'monthly_staff' => ['label' => 'พนักงานรายเดือน', 'color' => 'blue'],
                        'freelance_layer' => ['label' => 'ฟรีแลนซ์', 'color' => 'green'],
                        'youtuber_salary' => ['label' => 'YouTuber เงินเดือน', 'color' => 'purple'],
                        'youtuber_settlement' => ['label' => 'YouTuber Settlement', 'color' => 'orange'],
                        'custom_hybrid' => ['label' => 'รูปแบบผสม', 'color' => 'pink'],
                    ];
                @endphp
                @foreach($modes as $code => $meta)
                <div class="rounded-xl border bg-white p-3">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-{{ $meta['color'] }}-100 text-{{ $meta['color'] }}-700 mb-1">{{ $code }}</span>
                    <div class="text-xs font-semibold text-gray-700">{{ $meta['label'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- ===================== TAB: Departments ===================== -->
    <div x-show="activeTab === 'departments'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add Department -->
            <div class="bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มแผนกใหม่
                </h3>
                <form action="{{ route('settings.master-data.departments.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อแผนก *</label>
                        <input type="text" name="name" required placeholder="เช่น ฝ่ายบัญชี" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Code *</label>
                        <input type="text" name="code" required placeholder="เช่น ACC" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 uppercase">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">เพิ่มแผนก</button>
                </form>
            </div>

            <!-- Department List -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-sky-50 border-b border-sky-100">
                    <h3 class="font-bold text-sky-800 text-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" /></svg>
                        แผนกทั้งหมด
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-gray-500">
                                <th class="text-left px-4 py-2.5 font-medium">ชื่อแผนก</th>
                                <th class="text-left px-4 py-2.5 font-medium">Code</th>
                                <th class="text-center px-4 py-2.5 font-medium">พนักงาน</th>
                                <th class="text-center px-4 py-2.5 font-medium">สถานะ</th>
                                <th class="text-right px-4 py-2.5 font-medium">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($departments as $dept)
                            <tr class="group hover:bg-gray-50" x-data="{ editing: false }">
                                <td class="px-4 py-3" colspan="5">
                                    <div x-show="!editing" class="flex items-center justify-between">
                                        <div class="flex items-center gap-8">
                                            <div class="min-w-[140px]">
                                                <div class="font-semibold text-gray-800">{{ $dept->name }}</div>
                                            </div>
                                            <div class="min-w-[80px]">
                                                <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $dept->code }}</code>
                                            </div>
                                            <div class="min-w-[80px] text-center">
                                                <span class="text-xs text-gray-500">{{ $dept->employees_count }} คน</span>
                                            </div>
                                            <div class="min-w-[80px] text-center">
                                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $dept->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                                    {{ $dept->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                            <form action="{{ route('settings.master-data.departments.delete', $dept->id) }}" method="POST" onsubmit="return confirm('ลบแผนก {{ $dept->name }}? (ต้องไม่มีพนักงานอยู่)')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                            </form>
                                        </div>
                                    </div>
                                    <form x-show="editing" x-cloak action="{{ route('settings.master-data.departments.update', $dept->id) }}" method="POST" class="flex items-center gap-3">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $dept->name }}" class="px-2 py-1.5 border rounded text-sm flex-grow" required>
                                        <input type="text" name="code" value="{{ $dept->code }}" class="px-2 py-1.5 border rounded text-sm w-24 uppercase" required>
                                        <label class="flex items-center gap-1 text-xs whitespace-nowrap">
                                            <input type="checkbox" name="is_active" value="1" {{ $dept->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                            Active
                                        </label>
                                        <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700 whitespace-nowrap">บันทึก</button>
                                        <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200 whitespace-nowrap">ยกเลิก</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">ยังไม่มีแผนก</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== TAB: Positions ===================== -->
    <div x-show="activeTab === 'positions'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add Position -->
            <div class="bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มตำแหน่งใหม่
                </h3>
                <form action="{{ route('settings.master-data.positions.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อตำแหน่ง *</label>
                        <input type="text" name="name" required placeholder="เช่น ตัดต่อจูเนียร์" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Code</label>
                        <input type="text" name="code" placeholder="เช่น JR_EDITOR" class="w-full px-3 py-2 border rounded-lg text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Workspace Panel</label>
                        <select name="workspace_panel" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="edit_jobs">งานตัดต่อ</option>
                            <option value="youtuber">YouTuber</option>
                            <option value="none">ไม่มี panel</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">สังกัดแผนก *</label>
                        <select name="department_id" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">-- เลือกแผนก --</option>
                            @foreach($departments->where('is_active', true) as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">เพิ่มตำแหน่ง</button>
                </form>
            </div>

            <!-- Position List -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-violet-50 border-b border-violet-100">
                    <h3 class="font-bold text-violet-800 text-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                        ตำแหน่งทั้งหมด
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-gray-500">
                                <th class="text-left px-4 py-2.5 font-medium">ตำแหน่ง</th>
                                <th class="text-left px-4 py-2.5 font-medium">Code</th>
                                <th class="text-left px-4 py-2.5 font-medium">แผนก</th>
                                <th class="text-left px-4 py-2.5 font-medium">Workspace Panel</th>
                                <th class="text-center px-4 py-2.5 font-medium">พนักงาน</th>
                                <th class="text-center px-4 py-2.5 font-medium">สถานะ</th>
                                <th class="text-right px-4 py-2.5 font-medium">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($positions as $pos)
                            <tr class="group hover:bg-gray-50" x-data="{ editing: false }">
                                <td class="px-4 py-3" colspan="6">
                                    <div x-show="!editing" class="flex items-center justify-between">
                                        <div class="flex items-center gap-6">
                                            <div class="min-w-[120px]">
                                                <div class="font-semibold text-gray-800">{{ $pos->name }}</div>
                                            </div>
                                            <div class="min-w-[80px]">
                                                <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $pos->code ?: '-' }}</code>
                                            </div>
                                            <div class="min-w-[100px]">
                                                <span class="text-xs text-gray-500">{{ $pos->department?->name ?? '-' }}</span>
                                            </div>
                                            <div class="min-w-[120px]">
                                                @php
                                                    $panelLabels = [
                                                        'recording_queue' => ['label' => 'ตารางถ่ายทำ', 'class' => 'bg-blue-50 text-blue-700'],
                                                        'edit_jobs'       => ['label' => 'งานตัดต่อ',  'class' => 'bg-indigo-50 text-indigo-700'],
                                                        'youtuber'        => ['label' => 'YouTuber',   'class' => 'bg-pink-50 text-pink-700'],
                                                        'none'            => ['label' => 'ไม่มี',       'class' => 'bg-gray-100 text-gray-500'],
                                                    ];
                                                    $pl = $panelLabels[$pos->workspace_panel] ?? $panelLabels['recording_queue'];
                                                @endphp
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $pl['class'] }}">{{ $pl['label'] }}</span>
                                            </div>
                                            <div class="min-w-[60px] text-center">
                                                <span class="text-xs text-gray-500">{{ $pos->employees_count }} คน</span>
                                            </div>
                                            <div class="min-w-[60px] text-center">
                                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $pos->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                                    {{ $pos->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                            <form action="{{ route('settings.master-data.positions.delete', $pos->id) }}" method="POST" onsubmit="return confirm('ลบตำแหน่ง {{ $pos->name }}? (ต้องไม่มีพนักงานอยู่)')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                            </form>
                                        </div>
                                    </div>
                                    <form x-show="editing" x-cloak action="{{ route('settings.master-data.positions.update', $pos->id) }}" method="POST" class="flex items-center gap-3 flex-wrap">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $pos->name }}" class="px-2 py-1.5 border rounded text-sm flex-grow" required>
                                        <input type="text" name="code" value="{{ $pos->code }}" placeholder="Code" class="px-2 py-1.5 border rounded text-sm w-28 uppercase">
                                        <select name="workspace_panel" class="px-2 py-1.5 border rounded text-sm">
                                            <option value="edit_jobs" @selected(($pos->workspace_panel ?? 'edit_jobs') === 'edit_jobs')>งานตัดต่อ</option>
                                            <option value="youtuber" @selected(($pos->workspace_panel ?? '') === 'youtuber')>YouTuber</option>
                                            <option value="none" @selected(($pos->workspace_panel ?? '') === 'none')>ไม่มี panel</option>
                                        </select>
                                        <select name="department_id" required class="px-2 py-1.5 border rounded text-sm">
                                            @foreach($departments->where('is_active', true) as $dept)
                                            <option value="{{ $dept->id }}" @selected($pos->department_id == $dept->id)>{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                        <label class="flex items-center gap-1 text-xs whitespace-nowrap">
                                            <input type="checkbox" name="is_active" value="1" {{ $pos->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                            Active
                                        </label>
                                        <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700 whitespace-nowrap">บันทึก</button>
                                        <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200 whitespace-nowrap">ยกเลิก</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">ยังไม่มีตำแหน่ง</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== TAB: Job Stages ===================== -->
    <div x-show="activeTab === 'job_stages'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มสถานะงานใหม่
                </h3>
                <form action="{{ route('settings.master-data.job-stages.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ประเภทงาน</label>
                        <select name="type" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="edit">Editing</option>
                            <option value="recording">Recording</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Code</label>
                        <input type="text" name="code" required class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น review_ready">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อสถานะ</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น รอตรวจงาน">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">สี</label>
                            <input type="text" name="color" value="slate" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Sort</label>
                            <input type="number" name="sort_order" value="99" min="0" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">เพิ่มสถานะงาน</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-sky-50 border-b border-sky-100">
                    <h3 class="font-bold text-sky-800 text-sm">รายการสถานะงานทั้งหมด</h3>
                </div>
                <div class="divide-y">
                    @forelse($jobStages as $stage)
                    <div class="px-5 py-3 group hover:bg-gray-50 transition" x-data="{ editing: false }">
                        <div x-show="!editing" class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ $stage->type === 'edit' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700' }}">{{ $stage->type }}</span>
                                <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $stage->code }}</code>
                                <span class="text-sm font-semibold text-gray-800">{{ $stage->name }}</span>
                                <span class="text-xs text-gray-400">สี: {{ $stage->color }}</span>
                                <span class="text-xs text-gray-400">sort: {{ $stage->sort_order }}</span>
                                @if($stage->is_core)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">CORE</span>
                                @endif
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $stage->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $stage->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                @if(!$stage->is_core)
                                <form action="{{ route('settings.master-data.job-stages.delete', $stage->id) }}" method="POST" onsubmit="return confirm('ลบสถานะ {{ $stage->name }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                </form>
                                @endif
                            </div>
                        </div>

                        <form x-show="editing" x-cloak action="{{ route('settings.master-data.job-stages.update', $stage->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-2 mt-1">
                            @csrf @method('PATCH')
                            @if(!$stage->is_core)
                            <input type="text" name="code" value="{{ $stage->code }}" class="px-2 py-1.5 border rounded text-sm" required>
                            @else
                            <div class="px-2 py-1.5 border rounded text-sm bg-gray-50 text-gray-500">{{ $stage->code }}</div>
                            @endif
                            <input type="text" name="name" value="{{ $stage->name }}" class="px-2 py-1.5 border rounded text-sm" required>
                            <input type="text" name="color" value="{{ $stage->color }}" class="px-2 py-1.5 border rounded text-sm" required>
                            <input type="number" name="sort_order" value="{{ $stage->sort_order }}" min="0" class="px-2 py-1.5 border rounded text-sm">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 px-2 py-1.5 border rounded">
                                <input type="checkbox" name="is_active" value="1" {{ $stage->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Active
                            </label>
                            <div class="flex items-center gap-2 justify-end">
                                <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200">ยกเลิก</button>
                                <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">บันทึก</button>
                            </div>
                        </form>
                    </div>
                    @empty
                    <div class="px-5 py-8 text-center text-gray-400 italic">ยังไม่มีสถานะงาน</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== TAB: Games ===================== -->
    <div x-show="activeTab === 'games'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add Game -->
            <div class="bg-white rounded-2xl shadow-sm border p-5 h-fit">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มเกมใหม่
                </h3>
                <form action="{{ route('settings.master-data.games.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อเกม *</label>
                        <input type="text" name="game_name" required placeholder="เช่น Elden Ring" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Slug (auto-generate ถ้าเว้นว่าง)</label>
                        <input type="text" name="game_slug" placeholder="elden-ring" class="w-full px-3 py-2 border rounded-lg text-sm lowercase">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">เพิ่มเกม</button>
                </form>
            </div>

            <!-- Game List -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-violet-50 border-b border-violet-100">
                    <h3 class="font-bold text-violet-800 text-sm flex items-center gap-2">
                        🎮 เกมทั้งหมด
                    </h3>
                </div>
                <div class="divide-y">
                    @forelse($games as $game)
                    <div class="px-5 py-3 group hover:bg-gray-50 transition" x-data="{ editing: false }">
                        <div x-show="!editing" class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">{{ $game->game_name }}</div>
                                    <code class="text-[11px] text-gray-400 bg-gray-50 px-1 rounded">{{ $game->game_slug }}</code>
                                </div>
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $game->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                    {{ $game->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @php $jobCount = $game->editingJobs()->where('is_deleted', false)->count(); @endphp
                                @if($jobCount > 0)
                                <span class="text-[10px] text-gray-400">{{ $jobCount }} งาน</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                @if($jobCount === 0)
                                <form action="{{ route('settings.master-data.games.delete', $game->id) }}" method="POST" onsubmit="return confirm('ลบเกม {{ $game->game_name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                </form>
                                @endif
                            </div>
                        </div>
                        <form x-show="editing" x-cloak action="{{ route('settings.master-data.games.update', $game->id) }}" method="POST" class="flex items-center gap-3 flex-wrap mt-2">
                            @csrf @method('PATCH')
                            <input type="text" name="game_name" value="{{ $game->game_name }}" class="px-2 py-1.5 border rounded text-sm flex-grow" required>
                            <input type="text" name="game_slug" value="{{ $game->game_slug }}" class="px-2 py-1.5 border rounded text-sm w-32 lowercase">
                            <label class="flex items-center gap-1 text-xs whitespace-nowrap">
                                <input type="checkbox" name="is_active" value="1" {{ $game->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Active
                            </label>
                            <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">บันทึก</button>
                            <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200">ยกเลิก</button>
                        </form>
                    </div>
                    @empty
                    <div class="px-5 py-8 text-center text-gray-400 italic">ยังไม่มีเกม — เพิ่มได้จากฟอร์มซ้ายมือ</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: FL Layer Rate (admin only) -->
    @if($isAdmin ?? false)
    <div x-show="activeTab === 'fl_layer_rate'" x-cloak x-data="{ selectedEmployeeId: '' }" class="space-y-6">

        {{-- Global Templates --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-1 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">T</span>
                    เพิ่ม Template ราคา FL Layer (ใช้ทั่วทั้งระบบ)
                </h3>
                <p class="text-[11px] text-gray-500 mb-3">ราคาเริ่มต้นต่อช่วง layer ใช้กับทุกคน (override ได้ด้วยราคาเฉพาะบุคคลด้านล่าง)</p>
                <form action="{{ route('settings.master-data.layer-rate-templates.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อ Template</label>
                        <input type="text" name="label" placeholder="เช่น L1-L3 มาตรฐาน" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Layer From *</label>
                            <input type="number" name="layer_from" min="1" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Layer To *</label>
                            <input type="number" name="layer_to" min="1" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Rate per minute *</label>
                        <input type="number" name="rate_per_minute" step="0.0001" min="0" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600">
                        เปิดใช้งาน
                    </label>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">เพิ่ม Template</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-indigo-50 border-b border-indigo-100">
                    <h3 class="font-bold text-indigo-800 text-sm">Template ราคาเลเยอร์ (Global)</h3>
                </div>
                <div class="overflow-x-auto max-h-[50vh]">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr class="text-gray-500">
                                <th class="text-left px-4 py-2.5 font-medium">ชื่อ</th>
                                <th class="text-left px-4 py-2.5 font-medium">ช่วงเลเยอร์</th>
                                <th class="text-left px-4 py-2.5 font-medium">เรท/นาที</th>
                                <th class="text-left px-4 py-2.5 font-medium">สถานะ</th>
                                <th class="text-right px-4 py-2.5 font-medium">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse(($layerRateTemplates ?? []) as $tpl)
                            <tr class="group hover:bg-gray-50" x-data="{ editing: false }">
                                <td colspan="5" class="px-4 py-3">
                                    <div x-show="!editing" class="flex items-center justify-between gap-4">
                                        <div class="min-w-[160px] font-semibold text-gray-800">{{ $tpl->label ?: '—' }}</div>
                                        <div class="min-w-[120px] text-sm text-gray-700">L{{ $tpl->layer_from }} - L{{ $tpl->layer_to }}</div>
                                        <div class="min-w-[140px] text-sm font-semibold text-gray-800">{{ number_format((float) $tpl->rate_per_minute, 4) }}/นาที</div>
                                        <div class="min-w-[80px]">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tpl->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">
                                                {{ $tpl->is_active ? 'ใช้งาน' : 'ปิด' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-auto">
                                            <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                            <form action="{{ route('settings.master-data.layer-rate-templates.delete', $tpl->id) }}" method="POST" onsubmit="return confirm('ลบ Template L{{ $tpl->layer_from }}-L{{ $tpl->layer_to }} ?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                            </form>
                                        </div>
                                    </div>
                                    <form x-show="editing" x-cloak action="{{ route('settings.master-data.layer-rate-templates.update', $tpl->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-2 mt-1">
                                        @csrf @method('PATCH')
                                        <input type="text" name="label" value="{{ $tpl->label }}" placeholder="ชื่อ" class="md:col-span-2 px-2 py-1.5 border rounded text-sm">
                                        <input type="number" name="layer_from" value="{{ $tpl->layer_from }}" min="1" required class="px-2 py-1.5 border rounded text-sm">
                                        <input type="number" name="layer_to" value="{{ $tpl->layer_to }}" min="1" required class="px-2 py-1.5 border rounded text-sm">
                                        <input type="number" name="rate_per_minute" value="{{ (float) $tpl->rate_per_minute }}" step="0.0001" min="0" required class="px-2 py-1.5 border rounded text-sm">
                                        <div class="md:col-span-6 flex items-center justify-between">
                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                                <input type="checkbox" name="is_active" value="1" {{ $tpl->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                                เปิดใช้งาน
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200">ยกเลิก</button>
                                                <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">บันทึก</button>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">ยังไม่มี Template</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Per-employee overrides --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มเทมเพลตราคา FL Layer รายคน
                </h3>
                <form action="{{ route('settings.master-data.layer-rate-rules.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">พนักงาน (FL Layer) *</label>
                        <select name="employee_id" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="">-- เลือกพนักงาน --</option>
                            @foreach($freelanceLayerEmployees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Layer From *</label>
                            <input type="number" name="layer_from" min="1" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Layer To *</label>
                            <input type="number" name="layer_to" min="1" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Rate per minute *</label>
                        <input type="number" name="rate_per_minute" step="0.0001" min="0" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Effective Date *</label>
                        <input type="date" name="effective_date" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-emerald-600">
                        เปิดใช้งาน
                    </label>
                    <button type="submit" class="w-full bg-emerald-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-emerald-700 transition">เพิ่มเทมเพลต</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 bg-emerald-50 border-b border-emerald-100">
                    <h3 class="font-bold text-emerald-800 text-sm">รายการเทมเพลตราคาเลเยอร์รายคน (FL Layer)</h3>
                </div>
                <div class="px-5 py-3 border-b border-gray-100 bg-white">
                    <div class="max-w-sm">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ฟิลเตอร์เฉพาะพนักงาน</label>
                        <select x-model="selectedEmployeeId" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="">-- แสดงทุกคน --</option>
                            @foreach($freelanceLayerEmployees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto max-h-[65vh]">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr class="text-gray-500">
                                <th class="text-left px-4 py-2.5 font-medium">พนักงาน</th>
                                <th class="text-left px-4 py-2.5 font-medium">ช่วงเลเยอร์</th>
                                <th class="text-left px-4 py-2.5 font-medium">เรท/นาที</th>
                                <th class="text-left px-4 py-2.5 font-medium">Effective</th>
                                <th class="text-left px-4 py-2.5 font-medium">สถานะ</th>
                                <th class="text-right px-4 py-2.5 font-medium">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($layerRateRules as $rule)
                            <tr class="group hover:bg-gray-50" x-data="{ editing: false }" x-show="selectedEmployeeId === '' || selectedEmployeeId === '{{ $rule->employee_id }}'">
                                <td class="px-4 py-3" colspan="6">
                                    <div x-show="!editing" class="flex items-center justify-between gap-4">
                                        <div class="min-w-[180px]">
                                            <div class="font-semibold text-gray-800">{{ $rule->employee?->full_name ?? '-' }}</div>
                                            <div class="text-xs text-gray-400">{{ $rule->employee?->employee_code ?? '-' }}</div>
                                        </div>
                                        <div class="min-w-[120px] text-sm text-gray-700">L{{ $rule->layer_from }} - L{{ $rule->layer_to }}</div>
                                        <div class="min-w-[120px] text-sm font-semibold text-gray-800">{{ number_format((float) $rule->rate_per_minute, 4) }}/นาที</div>
                                        <div class="min-w-[120px] text-xs text-gray-500">{{ optional($rule->effective_date)->format('d/m/Y') }}</div>
                                        <div class="min-w-[80px]">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $rule->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">
                                                {{ $rule->is_active ? 'ใช้งาน' : 'ปิด' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-auto">
                                            <button @click="editing = true" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                                            <form action="{{ route('settings.master-data.layer-rate-rules.delete', $rule->id) }}" method="POST" onsubmit="return confirm('ลบเทมเพลตราคา L{{ $rule->layer_from }}-L{{ $rule->layer_to }} ของ {{ $rule->employee?->full_name }} ?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-600 bg-red-50 rounded border border-red-200 hover:bg-red-100">ลบ</button>
                                            </form>
                                        </div>
                                    </div>

                                    <form x-show="editing" x-cloak action="{{ route('settings.master-data.layer-rate-rules.update', $rule->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-2 mt-1">
                                        @csrf @method('PATCH')
                                        <div class="md:col-span-2 text-sm px-2 py-1.5 border rounded bg-gray-50 text-gray-700">
                                            {{ $rule->employee?->full_name ?? '-' }}
                                        </div>
                                        <input type="number" name="layer_from" value="{{ $rule->layer_from }}" min="1" required class="px-2 py-1.5 border rounded text-sm">
                                        <input type="number" name="layer_to" value="{{ $rule->layer_to }}" min="1" required class="px-2 py-1.5 border rounded text-sm">
                                        <input type="number" name="rate_per_minute" value="{{ (float) $rule->rate_per_minute }}" step="0.0001" min="0" required class="px-2 py-1.5 border rounded text-sm">
                                        <input type="date" name="effective_date" value="{{ optional($rule->effective_date)->toDateString() }}" required class="px-2 py-1.5 border rounded text-sm">
                                        <div class="md:col-span-6 flex items-center justify-between">
                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                                <input type="checkbox" name="is_active" value="1" {{ $rule->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600">
                                                เปิดใช้งาน
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 rounded hover:bg-gray-200">ยกเลิก</button>
                                                <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">บันทึก</button>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">ยังไม่มีเทมเพลตราคา FL Layer รายคน</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ===================== TAB: Workspace Access ===================== -->
    <div x-show="activeTab === 'workspace_access'" x-cloak>
        <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 bg-slate-50 border-b border-slate-200">
                <h3 class="font-bold text-slate-800 text-sm">ควบคุมสิทธิ์แก้ไข Workspace</h3>
                <p class="text-xs text-slate-500 mt-1">เปิด/ปิดสิทธิ์แก้ไขหน้า Workspace แยกรายพนักงาน (ทุกแผนก)</p>
            </div>
            <div class="overflow-x-auto max-h-[65vh]">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr class="text-gray-500">
                            <th class="text-left px-4 py-2.5 font-medium">พนักงาน</th>
                            <th class="text-left px-4 py-2.5 font-medium">แผนก</th>
                            <th class="text-left px-4 py-2.5 font-medium">ตำแหน่ง</th>
                            <th class="text-left px-4 py-2.5 font-medium">สถานะสิทธิ์</th>
                            <th class="text-right px-4 py-2.5 font-medium">จัดการ</th>
                        </tr>
                    </thead>
                    @foreach($employees as $emp)
                        @php
                            $toggle = $emp->moduleToggles->firstWhere('module_name', 'workspace_editing');
                            $isEnabled = $toggle ? (bool) $toggle->is_enabled : true;
                        @endphp
                        <tbody class="divide-y" x-data="{ showEdit: false, enabled: '{{ $isEnabled ? '1' : '0' }}' }">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-800">{{ $emp->full_name }}</div>
                                    <div class="text-xs text-gray-400">{{ $emp->employee_code }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $emp->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $emp->position?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $isEnabled ? 'เปิดแก้ไข' : 'ปิดแก้ไข' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" @click="showEdit = !showEdit" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">ปรับสิทธิ์</button>
                                </td>
                            </tr>
                            <tr x-show="showEdit" x-cloak class="bg-slate-50/50">
                                <td colspan="5" class="px-4 py-3">
                                    <form action="{{ route('settings.master-data.workspace-access.update', $emp) }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="is_enabled" x-model="enabled" class="border rounded-lg px-3 py-1.5 text-xs">
                                            <option value="1">เปิดแก้ไข</option>
                                            <option value="0">ปิดแก้ไข</option>
                                        </select>
                                        <button type="submit" class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">อัปเดต</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== TAB: Leave Policies ===================== -->
    @if($isAdmin)
    <div x-show="activeTab === 'leave_policies'" x-cloak>
        <div x-data="{ showAdd: false, editing: null }">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-gray-800">นโยบายวันลา</h3>
                    <p class="text-xs text-gray-500 mt-0.5">ตั้งค่ามาตรฐานสำหรับโควต้าลาพักร้อน/ป่วย/กิจ + กฎการยกยอด/แลกเงิน</p>
                </div>
                <button @click="showAdd = true; editing = null" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors shadow-sm">
                    + เพิ่มนโยบาย
                </button>
            </div>

            {{-- Policy List --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($leavePolicies as $p)
                <div class="bg-white rounded-xl border border-gray-200 p-4 {{ $p->is_default ? 'ring-2 ring-amber-300 ring-offset-1' : '' }}">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            @if($p->is_default)<span class="text-amber-500" title="Default">⭐</span>@endif
                            <h4 class="font-bold text-gray-800">{{ $p->name }}</h4>
                            @unless($p->is_active)<span class="px-1.5 py-0.5 bg-gray-100 text-gray-500 text-[10px] rounded">ปิดใช้งาน</span>@endunless
                        </div>
                        <span class="text-[11px] text-gray-400">👥 {{ $p->employees_count }} คน</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center mb-3">
                        <div class="p-2 bg-teal-50 rounded">
                            <div class="text-[9px] font-bold text-teal-700 uppercase">🏖️ พักร้อน</div>
                            <div class="text-lg font-extrabold text-teal-700">{{ $p->vacation_days }}</div>
                        </div>
                        <div class="p-2 bg-blue-50 rounded">
                            <div class="text-[9px] font-bold text-blue-700 uppercase">🤒 ป่วย</div>
                            <div class="text-lg font-extrabold text-blue-700">{{ $p->sick_days }}</div>
                        </div>
                        <div class="p-2 bg-amber-50 rounded">
                            <div class="text-[9px] font-bold text-amber-700 uppercase">👤 กิจ</div>
                            <div class="text-lg font-extrabold text-amber-700">{{ $p->personal_days }}</div>
                        </div>
                    </div>
                    <div class="space-y-1 text-[11px] text-gray-600">
                        <div>📥 ยกยอด:
                            @if($p->allow_carryover)
                                <span class="text-emerald-600 font-bold">✓</span>
                                @if($p->max_carryover_days !== null)<span class="text-gray-500">สูงสุด {{ $p->max_carryover_days }} วัน</span>@endif
                                @if($p->carryover_expires_months !== null)<span class="text-gray-400">/ หมดใน {{ $p->carryover_expires_months }} เดือน</span>@endif
                            @else
                                <span class="text-rose-500">ห้าม</span>
                            @endif
                        </div>
                        <div>💵 แลกเงิน:
                            @if($p->allow_encashment)
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span class="text-gray-500">{{ \App\Models\LeavePolicy::ENCASH_FORMULAS[$p->encash_rate_formula] ?? $p->encash_rate_formula }}</span>
                                @if($p->max_encash_days_per_year !== null)<span class="text-gray-400">/ สูงสุด {{ $p->max_encash_days_per_year }} วัน/ปี</span>@endif
                            @else
                                <span class="text-rose-500">ห้าม</span>
                            @endif
                        </div>
                        <div>🚫 ระหว่างทดลอง:
                            <span class="{{ $p->sick_during_probation ? 'text-emerald-600' : 'text-rose-500' }}">ป่วย {{ $p->sick_during_probation ? '✓' : '✗' }}</span>
                            <span class="{{ $p->personal_during_probation ? 'text-emerald-600' : 'text-rose-500' }}">· กิจ {{ $p->personal_during_probation ? '✓' : '✗' }}</span>
                            <span class="{{ $p->vacation_during_probation ? 'text-emerald-600' : 'text-rose-500' }}">· พักร้อน {{ $p->vacation_during_probation ? '✓' : '✗' }}</span>
                        </div>
                        <div>📅 พักร้อนใช้ได้เมื่อทำงานครบ:
                            <span class="text-gray-700 font-bold">{{ $p->vacation_eligibility_months ?? 12 }} เดือน</span>
                            <span class="text-gray-400 text-[10px]">(ม.30 พรบ.คุ้มครองแรงงาน)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-1 mt-3 pt-3 border-t border-gray-100">
                        <button @click="editing = {{ Js::from($p->toArray()) }}; showAdd = true" class="px-2.5 py-1 text-[11px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">แก้ไข</button>
                        @unless($p->is_default)
                        <form action="{{ route('settings.master-data.leave-policies.delete', $p->id) }}" method="POST" onsubmit="return confirm('ลบ {{ $p->name }}? พนักงานที่ผูกอยู่จะย้ายไป Default')" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-2.5 py-1 text-[11px] font-bold text-rose-600 bg-rose-50 rounded border border-rose-200 hover:bg-rose-100">ลบ</button>
                        </form>
                        @endunless
                    </div>
                </div>
                @endforeach

                @if($leavePolicies->isEmpty())
                <div class="col-span-full p-8 text-center bg-gray-50 rounded-xl text-gray-400 text-sm">
                    ยังไม่มีนโยบาย — กด "+ เพิ่มนโยบาย" เพื่อเริ่มต้น
                </div>
                @endif
            </div>

            {{-- Add/Edit Modal --}}
            <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto" @click.self="showAdd = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl my-6">
                    <form :action="editing ? '{{ url('settings/master-data/leave-policies') }}/' + editing.id : '{{ route('settings.master-data.leave-policies.store') }}'" method="POST" class="p-6">
                        @csrf
                        <template x-if="editing">
                            <input type="hidden" name="_method" value="PATCH">
                        </template>
                        <h3 class="text-lg font-bold mb-4" x-text="editing ? 'แก้ไขนโยบายวันลา' : 'เพิ่มนโยบายวันลา'"></h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อนโยบาย *</label>
                                    <input type="text" name="name" required maxlength="100"
                                           :value="editing?.name ?? ''"
                                           placeholder="เช่น Default / Manager / Senior 3-5 ปี"
                                           class="w-full px-3 py-2 border rounded-lg text-sm">
                                </div>
                                <div class="flex items-end gap-3">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" name="is_default" value="1" :checked="editing?.is_default ?? false">
                                        <span class="text-xs">⭐ Default</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" name="is_active" value="1" :checked="editing ? editing.is_active : true">
                                        <span class="text-xs">เปิดใช้</span>
                                    </label>
                                </div>
                            </div>

                            <fieldset class="border border-gray-200 rounded-lg p-3">
                                <legend class="px-2 text-xs font-bold text-gray-600">โควต้าวันลา/ปี</legend>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">🏖️ พักร้อน</label>
                                        <input type="number" name="vacation_days" min="0" max="365" required
                                               :value="editing?.vacation_days ?? 6"
                                               class="w-full px-3 py-2 border rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">🤒 ป่วย</label>
                                        <input type="number" name="sick_days" min="0" max="365" required
                                               :value="editing?.sick_days ?? 30"
                                               class="w-full px-3 py-2 border rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">👤 กิจ</label>
                                        <input type="number" name="personal_days" min="0" max="365" required
                                               :value="editing?.personal_days ?? 3"
                                               class="w-full px-3 py-2 border rounded-lg text-sm">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-3">
                                <legend class="px-2 text-xs font-bold text-gray-600">📥 ยกยอด (เฉพาะลาพักร้อน)</legend>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="allow_carryover" value="1" :checked="editing ? editing.allow_carryover : true">
                                        <span class="text-sm">อนุญาตยกยอดข้ามปี</span>
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-600 mb-1">เพดานสูงสุด (วัน) — เว้นว่าง = ไม่จำกัด</label>
                                            <input type="number" name="max_carryover_days" min="0" max="365"
                                                   :value="editing?.max_carryover_days ?? 5"
                                                   class="w-full px-3 py-2 border rounded-lg text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-600 mb-1">หมดอายุภายใน (เดือน) — เว้นว่าง = ไม่หมด</label>
                                            <input type="number" name="carryover_expires_months" min="1" max="24"
                                                   :value="editing?.carryover_expires_months ?? 6"
                                                   class="w-full px-3 py-2 border rounded-lg text-sm">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-3">
                                <legend class="px-2 text-xs font-bold text-gray-600">💵 แลกเป็นเงิน (เฉพาะลาพักร้อน)</legend>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="allow_encashment" value="1" :checked="editing ? editing.allow_encashment : true">
                                        <span class="text-sm">อนุญาตแลกวันลาเป็นเงิน</span>
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-600 mb-1">สูตรอัตรา/วัน *</label>
                                            <select name="encash_rate_formula" required class="w-full px-3 py-2 border rounded-lg text-sm">
                                                @foreach(\App\Models\LeavePolicy::ENCASH_FORMULAS as $key => $label)
                                                    <option value="{{ $key }}" :selected="(editing?.encash_rate_formula ?? 'salary_div_30') === '{{ $key }}'">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-600 mb-1">สูงสุด/ปี (วัน) — เว้นว่าง = ไม่จำกัด</label>
                                            <input type="number" name="max_encash_days_per_year" min="0" max="365"
                                                   :value="editing?.max_encash_days_per_year ?? ''"
                                                   class="w-full px-3 py-2 border rounded-lg text-sm">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-3">
                                <legend class="px-2 text-xs font-bold text-gray-600">🚫 สิทธิระหว่างทดลองงาน</legend>
                                <p class="text-[10px] text-gray-500 mb-2">⚖️ กฎหมายไทย: ป่วย (ม.32) + กิจ (ม.34) ลาได้ตั้งแต่วันแรก · พักร้อน (ม.30) เมื่อทำงานครบ 1 ปี</p>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <label class="flex items-center gap-1.5 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="hidden" name="sick_during_probation" value="0">
                                        <input type="checkbox" name="sick_during_probation" value="1" :checked="editing?.sick_during_probation ?? true">
                                        <span>🤒 ลาป่วย</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="hidden" name="personal_during_probation" value="0">
                                        <input type="checkbox" name="personal_during_probation" value="1" :checked="editing?.personal_during_probation ?? true">
                                        <span>👤 ลากิจ</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="hidden" name="vacation_during_probation" value="0">
                                        <input type="checkbox" name="vacation_during_probation" value="1" :checked="editing?.vacation_during_probation ?? false">
                                        <span>🏖️ พักร้อน</span>
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-3">
                                <legend class="px-2 text-xs font-bold text-gray-600">📅 สิทธิพักร้อน — เริ่มใช้ได้</legend>
                                <label class="block text-xs text-gray-600 mb-1">ทำงานครบกี่เดือน จึงจะลาพักร้อนได้</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="vacation_eligibility_months" min="0" max="60"
                                           :value="editing?.vacation_eligibility_months ?? 12"
                                           class="w-24 px-3 py-2 border rounded-lg text-sm">
                                    <span class="text-sm text-gray-500">เดือน (default 12 ตาม ม.30)</span>
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-3">
                                <legend class="px-2 text-xs font-bold text-gray-600">⚙️ เพิ่มเติม</legend>
                                <label class="block text-xs text-gray-600 mb-1">หมายเหตุ</label>
                                <textarea name="note" maxlength="500" rows="2" :value="editing?.note ?? ''"
                                          class="w-full px-3 py-2 border rounded-lg text-sm"
                                          placeholder="คำอธิบายนโยบายนี้..."></textarea>
                            </fieldset>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 mt-4 border-t border-gray-100">
                            <button type="button" @click="showAdd = false; editing = null" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                            <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700" x-text="editing ? 'บันทึกการแก้ไข' : 'เพิ่มนโยบาย'"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== TAB: Holiday Types ===================== --}}
    <div x-show="activeTab === 'holiday_types'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Add Form --}}
            <div class="bg-white rounded-2xl shadow-sm border p-5"
                 x-data="{ color: 'purple' }">
                <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-xs">+</span>
                    เพิ่มประเภทวันหยุด
                </h3>
                <form action="{{ route('settings.master-data.holiday-types.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Code (ไม่ซ้ำ, a-z, _, -)</label>
                        <input type="text" name="code" required pattern="[A-Za-z0-9_\-]+" placeholder="เช่น religious" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชื่อแสดงผล</label>
                        <input type="text" name="name" required placeholder="เช่น วันหยุดศาสนา" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ไอคอน (emoji)</label>
                        <input type="text" name="icon" maxlength="10" placeholder="🎉" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">สี Default</label>
                        <input type="hidden" name="default_color" :value="color">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($colorPresets as $key => $label)
                                <button type="button"
                                        @click="color = '{{ $key }}'"
                                        :class="color === '{{ $key }}' ? 'ring-2 ring-offset-2 ring-gray-700' : 'hover:scale-110'"
                                        class="w-6 h-6 rounded-full bg-{{ $key }}-400 transition-transform"
                                        title="{{ $label }}"></button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ลำดับ</label>
                        <input type="number" name="sort_order" min="0" value="99" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    </div>
                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-purple-700 transition">+ เพิ่มประเภท</button>
                </form>
            </div>

            {{-- List --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4">ประเภทวันหยุดทั้งหมด ({{ $holidayTypes->count() }})</h3>
                <div class="space-y-2">
                    @forelse($holidayTypes as $type)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100"
                         x-data="{
                             editing: false,
                             form: {
                                 name: @js($type->name),
                                 icon: @js($type->icon),
                                 default_color: @js($type->default_color),
                                 sort_order: @js($type->sort_order),
                                 is_active: {{ $type->is_active ? 'true' : 'false' }},
                             }
                         }">
                        <div x-show="!editing" class="flex items-center gap-3 flex-grow">
                            <div class="w-10 h-10 rounded-xl bg-{{ $type->default_color }}-100 text-{{ $type->default_color }}-700 flex items-center justify-center text-lg">
                                {{ $type->icon ?: '📅' }}
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm font-bold text-gray-800">
                                    {{ $type->name }}
                                    @if($type->is_system)
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] bg-gray-200 text-gray-600 font-bold">SYSTEM</span>
                                    @endif
                                    @if(!$type->is_active)
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] bg-red-100 text-red-700 font-bold">ปิด</span>
                                    @endif
                                </p>
                                <p class="text-[10px] text-gray-400">
                                    <code class="font-mono">{{ $type->code }}</code> · สี: {{ $colorPresets[$type->default_color] ?? $type->default_color }} · ใช้งาน {{ $type->company_holidays_count }} รายการ
                                </p>
                            </div>
                            <button type="button" @click="editing = true" class="px-3 py-1 text-xs text-purple-600 hover:bg-purple-50 rounded-lg">แก้ไข</button>
                            @if(!$type->is_system)
                                <form action="{{ route('settings.master-data.holiday-types.delete', $type->id) }}" method="POST" onsubmit="return confirm('ลบประเภท \'{{ $type->name }}\'?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1 text-xs text-red-600 hover:bg-red-50 rounded-lg">ลบ</button>
                                </form>
                            @endif
                        </div>

                        {{-- Edit form --}}
                        <form x-show="editing" action="{{ route('settings.master-data.holiday-types.update', $type->id) }}" method="POST" class="w-full space-y-2">
                            @csrf @method('PATCH')
                            <input type="hidden" name="default_color" x-model="form.default_color">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                                <div class="md:col-span-4">
                                    <label class="block text-[9px] font-bold text-gray-400 uppercase">ชื่อ</label>
                                    <input type="text" name="name" x-model="form.name" required class="w-full px-2 py-1.5 border rounded text-sm">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[9px] font-bold text-gray-400 uppercase">ไอคอน</label>
                                    <input type="text" name="icon" x-model="form.icon" maxlength="10" class="w-full px-2 py-1.5 border rounded text-sm">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[9px] font-bold text-gray-400 uppercase">ลำดับ</label>
                                    <input type="number" name="sort_order" x-model="form.sort_order" min="0" class="w-full px-2 py-1.5 border rounded text-sm">
                                </div>
                                <div class="md:col-span-2 flex items-center gap-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-gray-300 text-purple-600">
                                    <span class="text-xs text-gray-600">เปิดใช้</span>
                                </div>
                                <div class="md:col-span-2 flex gap-1">
                                    <button type="submit" class="flex-1 px-2 py-1.5 bg-purple-600 text-white rounded text-xs font-bold">บันทึก</button>
                                    <button type="button" @click="editing = false" class="px-2 py-1.5 bg-gray-100 text-gray-600 rounded text-xs">ยกเลิก</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[9px] font-bold text-gray-400 uppercase mb-1">สี Default</label>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($colorPresets as $key => $label)
                                        <button type="button"
                                                @click="form.default_color = '{{ $key }}'"
                                                :class="form.default_color === '{{ $key }}' ? 'ring-2 ring-offset-1 ring-gray-700' : 'hover:scale-110'"
                                                class="w-5 h-5 rounded-full bg-{{ $key }}-400 transition-transform"
                                                title="{{ $label }}"></button>
                                    @endforeach
                                </div>
                            </div>
                        </form>
                    </div>
                    @empty
                    <div class="py-8 text-center text-sm text-gray-400">ยังไม่มีประเภทวันหยุด</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
