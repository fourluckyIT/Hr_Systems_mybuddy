@extends('layouts.app')
@section('title', 'Work Pipeline')

@section('content')
@php
    $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $prevMonth = $month == 1 ? 12 : $month - 1;
    $prevYear = $month == 1 ? $year - 1 : $year;
    $nextMonth = $month == 12 ? 1 : $month + 1;
    $nextYear = $month == 12 ? $year + 1 : $year;
@endphp
<div x-data="{ 
        showAddModal: false, 
        selectedGameId: '',
        addAssigneeId: '',
        addPricingMode: 'template',
        directFinalizeModal: false,
        dfJobId: null,
        dfJobName: '',
        editorsMap: {
            @foreach($editors as $ed)
            '{{ $ed->id }}': '{{ $ed->payroll_mode }}',
            @endforeach
        },
        showEditModal: false,
        editForm: {
            id: '', job_name: '', game_id: '', game_link: '', deadline_days: 7, deadline_date: '', assigned_to: '', 
            layer_count: '', video_duration_minutes: '', video_duration_seconds: '', notes: '',
            pricing_mode: '', layer: '', custom_rate: ''
        },
        openEdit(job) {
            this.editForm = {
                id: job.id,
                job_name: job.job_name,
                game_id: job.game_id,
                game_link: job.game_link || '',
                deadline_days: job.deadline_days,
                deadline_date: job.deadline_date ? job.deadline_date.split('T')[0] : '',
                assigned_to: job.assigned_to,
                layer_count: job.layer_count || '',
                video_duration_minutes: job.video_duration_minutes || '',
                video_duration_seconds: job.video_duration_seconds || '',
                notes: job.notes || '',
                pricing_mode: job.pricing_mode || 'template',
                layer: job.layer || '',
                custom_rate: job.custom_rate || ''
            };
            this.showEditModal = true;
        },
        openDirectFinalize(job) {
            this.dfJobId = job.id;
            this.dfJobName = job.job_name;
            this.directFinalizeModal = true;
        }
    }" class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Work Pipeline</h1>
            <p class="text-sm text-gray-500">ระบบติดตามงานและวัตถุดิบ (Consolidated)</p>
        </div>

        <div class="flex items-center gap-4">
            {{-- Month Selector --}}
            <div class="flex items-center gap-2 relative" x-data="{
                open: false,
                pickerYear: {{ $year }},
                currentMonth: {{ $month }},
                currentYear: {{ $year }},
                currentMonthReal: {{ now()->month }},
                currentYearReal: {{ now()->year }},
                thaiMonths: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
                goTo(m, y) {
                    window.location.href = '{{ route('work.index', ['month' => '__M__', 'year' => '__Y__']) }}'.replace('__M__', m).replace('__Y__', y);
                }
            }">
                <a href="{{ route('work.index', ['month' => $prevMonth, 'year' => $prevYear]) }}"
                   class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&larr;</a>

                <button @click="open = !open" type="button"
                        class="px-4 py-1 bg-indigo-50 text-indigo-700 rounded-lg font-semibold text-sm hover:bg-indigo-100 transition-colors relative cursor-pointer flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $monthNames[$month] }} {{ $year + 543 }}
                    <svg class="w-3 h-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                {{-- Month Picker Dropdown --}}
                <div x-show="open" x-cloak x-transition @click.away="open = false"
                     class="absolute z-50 mt-2 top-full right-0 bg-white border border-gray-200 rounded-xl shadow-xl p-4 w-[280px]">
                    <div class="flex items-center justify-between mb-3">
                        <button @click="pickerYear--" type="button" class="p-1 rounded hover:bg-gray-100 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span class="text-sm font-bold text-gray-800" x-text="'พ.ศ. ' + (pickerYear + 543)"></span>
                        <button @click="pickerYear++" type="button" class="p-1 rounded hover:bg-gray-100 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-1.5">
                        <template x-for="(name, idx) in thaiMonths" :key="idx">
                            <button
                                @click="goTo(idx + 1, pickerYear); open = false;"
                                type="button"
                                :class="{
                                    'bg-indigo-600 text-white font-bold shadow-sm': (idx + 1) === currentMonth && pickerYear === currentYear,
                                    'bg-gray-50 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700': !((idx + 1) === currentMonth && pickerYear === currentYear)
                                }"
                                class="px-2 py-2 rounded-lg text-xs font-medium transition-all text-center"
                                x-text="name">
                            </button>
                        </template>
                    </div>
                </div>

                <a href="{{ route('work.index', ['month' => $nextMonth, 'year' => $nextYear]) }}"
                   class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&rarr;</a>
            </div>

            <button @click="showAddModal = true; selectedGameId = ''"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                + มอบหมายงานใหม่
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">งานที่กำลังดำเนินการ</div>
            <div class="text-2xl font-bold text-gray-900">{{ $summary['editing_active'] }} <span class="text-sm font-normal text-gray-500">งาน</span></div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <div class="text-xs font-semibold text-green-500 uppercase tracking-wider mb-1">งานที่เสร็จสมบูรณ์</div>
            <div class="text-2xl font-bold text-green-600">{{ $summary['editing_final'] }} <span class="text-sm font-normal text-green-400">งาน</span></div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <div class="text-xs font-semibold text-indigo-500 uppercase tracking-wider mb-1">ระยะเวลารวม (Final)</div>
            <div class="text-2xl font-bold text-indigo-600">{{ $summary['total_duration_hms'] }}</div>
        </div>
    </div>

    {{-- Editing Jobs --}}
    <div class="space-y-4">
        <h2 class="text-lg font-semibold">งานทั้งหมด</h2>

        <div class="bg-white shadow rounded-lg overflow-hidden border">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">ชื่องาน</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">หมวดหมู่</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">ผู้รับผิดชอบ</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">กำหนดส่ง / ข้อมูล</th>
                        <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($editingJobs as $job)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $job->job_name }}</div>
                            @if($job->status === 'final')
                                <div class="text-[10px] text-gray-400">ปิดงาน: {{ $job->finalized_at?->format('d/m/Y H:i') ?? '-' }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ $job->game?->game_name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $job->assignee?->first_name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'assigned'     => 'bg-blue-100 text-blue-700',
                                    'in_progress'  => 'bg-yellow-100 text-yellow-700',
                                    'review_ready' => 'bg-purple-100 text-purple-700',
                                    'final'        => 'bg-emerald-100 text-emerald-700',
                                ];
                                $statusLabels = [
                                    'assigned'     => 'ได้รับมอบหมาย',
                                    'in_progress'  => 'กำลังตัดต่อ',
                                    'review_ready' => 'รอตรวจ',
                                    'final'        => 'ปิดงานแล้ว',
                                ];
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$job->status] ?? 'bg-gray-100' }}">
                                {{ $statusLabels[$job->status] ?? $job->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            @if($job->status === 'final')
                                <div class="flex flex-col gap-0.5">
                                    <div class="text-xs font-bold text-gray-700">ความยาว: {{ $job->video_duration_minutes ?? 0 }}:{{ str_pad($job->video_duration_seconds ?? 0, 2, '0', STR_PAD_LEFT) }}</div>
                                    @if($job->layer_count)
                                        <div class="text-[10px]">เลเยอร์: {{ $job->layer_count }}</div>
                                    @endif
                                </div>
                            @else
                                {{ $job->deadline_date ? $job->deadline_date->format('d/m/Y') : '-' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-3">
                                @if(auth()->user()?->hasRole('admin'))
                                    <button @click="openEdit({{ $job->toJson() }})" class="text-gray-400 hover:text-indigo-600" title="แก้ไข">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                @endif

                                @if($job->status === 'assigned')
                                    <form action="{{ route('work.editing-job.start', $job) }}" method="POST">@csrf
                                        <button class="text-indigo-600 hover:text-indigo-900 font-medium text-xs">เริ่มงาน</button>
                                    </form>
                                @elseif($job->status === 'in_progress')
                                    <form action="{{ route('work.editing-job.mark-ready', $job) }}" method="POST">@csrf
                                        <button class="text-blue-600 hover:text-blue-900 font-medium text-xs">ส่งงาน</button>
                                    </form>
                                @elseif($job->status === 'review_ready')
                                    <form action="{{ route('work.editing-job.finalize', $job) }}" method="POST" class="flex flex-col gap-1 items-end">
                                        @csrf
                                        <div class="flex items-center gap-1">
                                            <input type="number" name="video_duration_minutes" class="w-14 border border-gray-300 rounded px-1.5 py-1 text-xs" placeholder="นาที" title="ความยาววิดีโอ (นาที)">
                                            <span class="text-xs text-gray-500">:</span>
                                            <input type="number" name="video_duration_seconds" class="w-14 border border-gray-300 rounded px-1.5 py-1 text-xs" placeholder="วินาที" min="0" max="59" title="ความยาววิดีโอ (วินาที)">
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="date" name="finalized_at" value="{{ date('Y-m-d') }}" class="border border-gray-300 rounded px-2 py-1 text-xs" required title="วันที่ปิดงาน (Final)">
                                            <button class="text-green-600 hover:text-green-900 font-bold whitespace-nowrap text-[10px]">อนุมัติ / ปิดงาน</button>
                                        </div>
                                    </form>
                                @endif
                                
                                @if(auth()->user()?->hasRole('admin'))
                                    @if($job->status !== 'final')
                                        <button @click="openDirectFinalize({{ $job->toJson() }})" class="text-xs text-indigo-600 border border-indigo-200 bg-indigo-50 px-2 py-1 rounded hover:bg-indigo-100 font-medium whitespace-nowrap">
                                            ปิดงานด่วน
                                        </button>
                                    @endif
                                    <form action="{{ route('work.editing-job.delete', $job) }}" method="POST" onsubmit="return confirm('ยืนยันลบงานนี้?')">@csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-400 italic">ยังไม่มีงานที่ Active</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Job Modal --}}
    <div x-show="showAddModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showAddModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/50" @click="showAddModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">
                <form action="{{ route('work.editing-job.store') }}" method="POST">
                    @csrf
                    <div class="px-6 pt-6 pb-4 space-y-3">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-bold text-gray-900">มอบหมายงานใหม่</h3>
                            <button type="button" @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อโปรเจกต์ <span class="text-red-500">*</span></label>
                            <input type="text" name="job_name" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">หมวดหมู่เกม <span class="text-red-500">*</span></label>
                                <select name="game_id" x-model="selectedGameId" class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm" required>
                                    <option value="">-- เลือกหมวดหมู่ --</option>
                                    @foreach($games as $game)
                                        <option value="{{ $game->id }}">{{ $game->game_name }}</option>
                                    @endforeach
                                    <option value="other">อื่นๆ (ระบุชื่อ)...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Editor <span class="text-red-500">*</span></label>
                                <select name="assigned_to" x-model="addAssigneeId" class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm" required>
                                    <option value="">-- เลือก Editor --</option>
                                    @foreach($editors as $ed)
                                        <option value="{{ $ed->id }}">{{ $ed->first_name }} {{ $ed->last_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div x-show="selectedGameId === 'other'" x-cloak class="p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                            <label class="block text-xs font-bold text-indigo-700 uppercase mb-1">ระบุชื่อหมวดหมู่ใหม่ <span class="text-red-500">*</span></label>
                            <input type="text" name="new_game_name" class="block w-full border border-indigo-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :required="selectedGameId === 'other'">
                        </div>

                        <!-- Pricing For Freelance Layer -->
                        <div x-show="editorsMap[addAssigneeId] === 'freelance_layer'" x-cloak class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                            <h4 class="text-sm font-semibold text-gray-800">เรทราคาสำหรับฟรีแลนซ์ Layer</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">รูปแบบ <span class="text-red-500">*</span></label>
                                    <select name="pricing_mode" x-model="addPricingMode" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" :required="editorsMap[addAssigneeId] === 'freelance_layer'">
                                        <option value="template">ตามกลุ่มเลเยอร์ (Template)</option>
                                        <option value="custom">ราคาพิเศษ (Isolated)</option>
                                    </select>
                                </div>
                                <div x-show="addPricingMode === 'template'">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Layer <span class="text-red-500">*</span></label>
                                    <input type="number" name="layer" min="1" value="1" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" :required="editorsMap[addAssigneeId] === 'freelance_layer' && addPricingMode === 'template'">
                                </div>
                                <div x-show="addPricingMode === 'custom'">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">ราคา/นาที (บาท) <span class="text-red-500">*</span></label>
                                    <input type="number" name="custom_rate" min="0" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" :required="editorsMap[addAssigneeId] === 'freelance_layer' && addPricingMode === 'custom'">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ลิงก์เกม (ถ้ามี)</label>
                            <input type="url" name="game_link" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">กำหนดส่งภายใน (วัน)</label>
                            <input type="number" name="deadline_days" min="1" value="7" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">โน้ต</label>
                            <textarea name="notes" rows="2" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end gap-3">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm hover:bg-gray-100">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">สร้างงาน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Job Modal --}}
    <div x-show="showEditModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showEditModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">
                <form :action="'{{ url('work/job') }}/' + editForm.id" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="px-6 pt-6 pb-4 space-y-3">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-bold text-gray-900">แก้ไขรายละเอียดงาน</h3>
                            <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อโปรเจกต์ <span class="text-red-500">*</span></label>
                            <input type="text" name="job_name" x-model="editForm.job_name" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">หมวดหมู่เกม <span class="text-red-500">*</span></label>
                                <select name="game_id" x-model="editForm.game_id" class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm" required>
                                    <option value="">-- เลือกหมวดหมู่ --</option>
                                    @foreach($games as $game)
                                        <option value="{{ $game->id }}">{{ $game->game_name }}</option>
                                    @endforeach
                                    <option value="other">อื่นๆ (ระบุชื่อ)...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Editor <span class="text-red-500">*</span></label>
                                <select name="assigned_to" x-model="editForm.assigned_to" class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm" required>
                                    <option value="">-- เลือก Editor --</option>
                                    @foreach($editors as $ed)
                                        <option value="{{ $ed->id }}">{{ $ed->first_name }} {{ $ed->last_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div x-show="editForm.game_id === 'other'" x-cloak class="p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                            <label class="block text-xs font-bold text-indigo-700 uppercase mb-1">ระบุชื่อหมวดหมู่ใหม่ <span class="text-red-500">*</span></label>
                            <input type="text" name="new_game_name" class="block w-full border border-indigo-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :required="editForm.game_id === 'other'">
                        </div>

                        <!-- Pricing For Freelance Layer -->
                        <div x-show="editorsMap[editForm.assigned_to] === 'freelance_layer'" x-cloak class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                            <h4 class="text-sm font-semibold text-gray-800">เรทราคาสำหรับฟรีแลนซ์ Layer</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">รูปแบบ <span class="text-red-500">*</span></label>
                                    <select name="pricing_mode" x-model="editForm.pricing_mode" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" :required="editorsMap[editForm.assigned_to] === 'freelance_layer'">
                                        <option value="template">ตามกลุ่มเลเยอร์ (Template)</option>
                                        <option value="custom">ราคาพิเศษ (Isolated)</option>
                                    </select>
                                </div>
                                <div x-show="editForm.pricing_mode === 'template'">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Layer <span class="text-red-500">*</span></label>
                                    <input type="number" name="layer" min="1" x-model="editForm.layer" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" :required="editorsMap[editForm.assigned_to] === 'freelance_layer' && editForm.pricing_mode === 'template'">
                                </div>
                                <div x-show="editForm.pricing_mode === 'custom'">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">ราคา/นาที (บาท) <span class="text-red-500">*</span></label>
                                    <input type="number" name="custom_rate" min="0" step="0.01" x-model="editForm.custom_rate" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" :required="editorsMap[editForm.assigned_to] === 'freelance_layer' && editForm.pricing_mode === 'custom'">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ลิงก์เกม (ถ้ามี)</label>
                            <input type="url" name="game_link" x-model="editForm.game_link" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">กำหนดส่ง (วัน)</label>
                                <input type="number" name="deadline_days" x-model="editForm.deadline_days" min="1" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">หรือระบุวันที่ Deadline</label>
                                <input type="date" name="deadline_date" x-model="editForm.deadline_date" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>
                        
                        {{-- Final Details (Only if job is final) --}}
                        <div class="p-3 bg-gray-50 border rounded-lg space-y-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">ข้อมูลการตัดต่อ (เผื่อแก้ไข)</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-500">Layer Count</label>
                                    <input type="number" name="layer_count" x-model="editForm.layer_count" class="block w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-500">Duration (นาที)</label>
                                    <input type="number" name="video_duration_minutes" x-model="editForm.video_duration_minutes" class="block w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-500">Duration (วินาที)</label>
                                    <input type="number" name="video_duration_seconds" x-model="editForm.video_duration_seconds" min="0" max="59" class="block w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">โน้ต</label>
                            <textarea name="notes" x-model="editForm.notes" rows="2" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end gap-3">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm hover:bg-gray-100">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Direct Finalize Modal (Fast Track) --}}
    <div x-show="directFinalizeModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="directFinalizeModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/50" @click="directFinalizeModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10">
                <form :action="'{{ url('work/job') }}/' + dfJobId + '/direct-finalize'" method="POST">
                    @csrf
                    <div class="px-6 pt-6 pb-4 space-y-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-bold text-indigo-700">ปิดงานด่วน (Fast-Track)</h3>
                            <button type="button" @click="directFinalizeModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        
                        <div class="bg-indigo-50 text-indigo-800 p-3 rounded border border-indigo-100 text-sm mb-4">
                            <strong>งาน:</strong> <span x-text="dfJobName"></span>
                            <br><span class="text-xs text-indigo-600 opacity-80 mt-1 block">* สำหรับตรวจงานและกดปิดลงบัญชีข้ามขั้นตอน</span>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ชิ้นงานเสร็จสมบูรณ์จริง <span class="text-red-500">*</span></label>
                            <input type="date" name="review_ready_at" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" required title="ใช้วัดผล Performance ส่งตรงเวลาหรือไม่">
                            <p class="text-[10px] text-gray-500 mt-0.5">วันที่พนักงานส่งไฟล์ให้ตรวจ (ใช้คำนวณการส่งตรงเวลา)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ปิดลงบัญชี (Finalized) <span class="text-red-500">*</span></label>
                            <input type="date" name="finalized_at" value="{{ date('Y-m-d') }}" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ความยาววิดีโอ <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="video_duration_minutes" min="0" class="block w-24 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="นาที" required>
                                <span class="text-gray-500 font-bold">:</span>
                                <input type="number" name="video_duration_seconds" min="0" max="59" class="block w-24 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="วิ" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนเลเยอร์ (ถ้ามีชาร์จเพิ่ม)</label>
                            <input type="number" name="layer_count" min="1" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="ไม่บังคับ">
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end gap-3">
                        <button type="button" @click="directFinalizeModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm hover:bg-gray-100">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">ยืนยันปิดงานด่วน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
