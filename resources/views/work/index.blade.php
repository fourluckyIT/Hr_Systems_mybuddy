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
        jobModal: false,
        isEditing: false,
        selectedGameId: '',
        pricingMode: 'template',
        directFinalizeModal: false,
        dfJobId: null,
        dfJobName: '',
        dfJobLayer: 1,
        dfJobAssigneeFixedRate: 0,
        dfJobPayrollMode: '',
        editorsMap: {
            @foreach($editors as $ed)
            '{{ $ed->id }}': '{{ $ed->payroll_mode }}',
            @endforeach
        },
        jobForm: {
            id: '', job_name: '', game_id: '', youtuber_id: '', game_link: '', deadline_days: 7, deadline_date: '', assigned_to: '',
            layer_count: '', video_duration_minutes: '', video_duration_seconds: '', notes: ''
        },
        openAdd() {
            this.isEditing = false;
            this.jobForm = { id: '', job_name: '', game_id: '', youtuber_id: '', game_link: '', deadline_days: 7, deadline_date: '', assigned_to: '', layer_count: '', video_duration_minutes: '', video_duration_seconds: '', notes: '' };
            this.selectedGameId = '';
            this.jobModal = true;
        },
        openEdit(job) {
            this.isEditing = true;
            this.jobForm = {
                id: job.id,
                job_name: job.job_name,
                game_id: job.game_id ? String(job.game_id) : '',
                youtuber_id: job.youtuber_id ? String(job.youtuber_id) : '',
                game_link: job.game_link || '',
                deadline_days: job.deadline_days || 7,
                deadline_date: job.deadline_date ? job.deadline_date.split('T')[0] : '',
                assigned_to: job.assigned_to ? String(job.assigned_to) : '',
                layer_count: job.layer_count || '',
                video_duration_minutes: job.video_duration_minutes || '',
                video_duration_seconds: job.video_duration_seconds || '',
                notes: job.notes || ''
            };
            this.selectedGameId = this.jobForm.game_id;
            this.jobModal = true;
        },
        openDirectFinalize(job) {
            this.dfJobId = job.id;
            this.dfJobName = job.job_name;
            this.dfJobLayer = job.layer_count || 1;
            this.dfJobAssigneeFixedRate = job.assignee ? (job.assignee.fixed_rate_per_clip || 0) : 0;
            this.dfJobPayrollMode = job.assignee ? (job.assignee.payroll_mode || '') : '';
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

            <a href="{{ route('work.recording-sessions.index') }}"
               class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                📹 งานถ่าย
            </a>
            <button @click="openAdd()"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                + มอบหมายงานใหม่
            </button>
        </div>
    </div>

    {{-- Summary Cards & Filters --}}
    <div class="space-y-6">
        <form action="{{ route('work.index') }}" method="GET" class="bg-white shadow-sm border border-gray-100 p-5 rounded-2xl flex flex-wrap items-end gap-5">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">
            
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> กรองตาม YouTuber</label>
                <select name="youtuber_id" class="w-full border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow" onchange="this.form.submit()">
                    <option value="">-- แสดงทั้งหมด --</option>
                    @foreach($youtubers as $ytb)
                        <option value="{{ $ytb->id }}" {{ $youtuberFilter == $ytb->id ? 'selected' : '' }}>{{ $ytb->first_name }} {{ $ytb->last_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/></svg> กรองตาม Editor</label>
                <select name="editor_id" class="w-full border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow" onchange="this.form.submit()">
                    <option value="">-- แสดงทั้งหมด --</option>
                    @foreach($editors as $ed)
                        <option value="{{ $ed->id }}" {{ $editorFilter == $ed->id ? 'selected' : '' }}>{{ $ed->first_name }} {{ $ed->last_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('work.index', ['month' => $month, 'year' => $year]) }}" class="px-5 py-2.5 text-sm text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-xl font-bold transition-colors">ล้างค่า</a>
                <button type="submit" class="px-5 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-bold shadow-sm hover:bg-gray-800 focus:ring-2 focus:ring-gray-900 focus:ring-offset-1 transition-all">ค้นหา</button>
            </div>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-2xl shadow-lg border border-gray-700 text-white relative overflow-hidden">
                <div class="relative z-10">
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">งานที่กำลังดำเนินการ</div>
                    <div class="text-4xl font-extrabold">{{ $summary['editing_active'] }} <span class="text-base font-semibold opacity-70 ml-1">งาน</span></div>
                </div>
                <svg class="absolute right-0 bottom-0 text-white opacity-5 w-28 h-28 transform translate-x-4 translate-y-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 rounded-2xl shadow-lg border border-emerald-400 text-white relative overflow-hidden">
                <div class="relative z-10">
                    <div class="text-[10px] font-bold text-emerald-100 uppercase tracking-wider mb-2 opacity-90">งานที่เสร็จสมบูรณ์</div>
                    <div class="text-4xl font-extrabold">{{ $summary['editing_final'] }} <span class="text-base font-semibold opacity-70 ml-1">งาน</span></div>
                </div>
                <svg class="absolute right-0 bottom-0 text-white opacity-10 w-28 h-28 transform translate-x-4 translate-y-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            
            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-6 rounded-2xl shadow-lg border border-indigo-400 text-white relative overflow-hidden">
                <div class="relative z-10">
                    <div class="text-[10px] font-bold text-indigo-100 uppercase tracking-wider mb-2 opacity-90">ระยะเวลารวม (Final)</div>
                    <div class="text-4xl font-extrabold">{{ $summary['total_duration_hms'] }}</div>
                </div>
                <svg class="absolute right-0 bottom-0 text-white opacity-10 w-28 h-28 transform translate-x-4 translate-y-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- Editing Jobs --}}
    <div class="space-y-4">
        <h2 class="text-lg font-bold text-gray-800 ml-1">งานทั้งหมดในระบบ</h2>

        <div class="bg-white shadow-sm rounded-2xl overflow-hidden border border-gray-200">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left font-bold text-gray-500 text-[10px] uppercase tracking-widest">ชื่องาน</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-500 text-[10px] uppercase tracking-widest">หมวดหมู่</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-500 text-[10px] uppercase tracking-widest">ผู้รับผิดชอบ</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-500 text-[10px] uppercase tracking-widest">สถานะ</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-500 text-[10px] uppercase tracking-widest">กำหนดส่ง / ข้อมูล</th>
                        <th class="px-6 py-4 text-center font-bold text-gray-500 text-[10px] uppercase tracking-widest">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($editingJobs as $job)
                    <tr class="hover:bg-indigo-50/30 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $job->job_name }}</div>
                            @if($job->status === 'final')
                                <div class="text-[10px] text-gray-400">ปิดงาน: {{ $job->finalized_at?->format('d/m/Y H:i') ?? '-' }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ $job->game?->game_name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $job->assignee?->first_name ?? '-' }}</div>
                            @if($job->youtuber)
                                <div class="text-[10px] text-indigo-500 font-semibold">YTB: {{ $job->youtuber->first_name }}</div>
                            @endif
                        </td>
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
                                    <button @click="openEdit({{ \Illuminate\Support\Js::from($job) }})" class="text-gray-400 hover:text-indigo-600" title="แก้ไขรายละเอียดงาน">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                @endif

                                @if($job->status === 'assigned')
                                    <form action="{{ route('work.editing-job.start', $job) }}" method="POST">@csrf
                                        <button class="bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white px-3 py-1 rounded-lg font-bold text-xs transition-colors shadow-sm">เริ่มงาน</button>
                                    </form>
                                @elseif($job->status === 'in_progress')
                                    <form action="{{ route('work.editing-job.mark-ready', $job) }}" method="POST">@csrf
                                        <button class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1 rounded-lg font-bold text-xs transition-colors shadow-sm">ส่งงาน</button>
                                    </form>
                                @elseif($job->status === 'review_ready')
                                    <form action="{{ route('work.editing-job.finalize', $job) }}" method="POST" class="flex flex-col gap-1 items-center justify-center" x-data="{ mode: '{{ $job->assignee?->fixed_rate_per_clip > 0 ? 'custom' : 'layer' }}' }">
                                        @csrf
                                        <div class="flex items-center gap-1.5">
                                            @if($job->assignee?->payroll_mode === 'freelance_layer')
                                                <select name="pricing_mode" x-model="mode" class="border border-gray-200 rounded px-1 py-1 text-[9px] bg-gray-50 focus:ring-0 text-gray-600">
                                                    <option value="layer">ตาม Layer</option>
                                                    <option value="custom">เหมาคลิป (Fix)</option>
                                                    <option value="custom_rate_per_min">เรท/นาที (อิสระ)</option>
                                                </select>
                                                <div x-show="mode === 'layer'" class="flex items-center gap-1 bg-white border border-gray-200 rounded px-1 py-0.5">
                                                    <span class="text-[9px] text-gray-400 font-bold">L</span>
                                                    <input type="number" name="layer_count" min="1" value="{{ $job->layer_count ?? 1 }}" class="w-8 border-0 bg-transparent p-0 text-center text-[10px] font-bold text-indigo-600 focus:ring-0">
                                                </div>
                                                <div x-show="mode === 'custom'" x-cloak class="flex items-center gap-1 bg-white border border-gray-200 rounded px-1 py-0.5">
                                                    <span class="text-[9px] text-gray-400 font-bold">฿</span>
                                                    <input type="number" name="fix_amount" step="0.01" min="0" value="{{ $job->assignee?->fixed_rate_per_clip ?? 0 }}" class="w-12 border-0 bg-transparent p-0 text-right text-[10px] font-bold text-orange-600 focus:ring-0" placeholder="เหมา">
                                                </div>
                                                <div x-show="mode === 'custom_rate_per_min'" x-cloak class="flex items-center gap-1 bg-white border border-gray-200 rounded px-1 py-0.5">
                                                    <span class="text-[9px] text-gray-400 font-bold">เรท</span>
                                                    <input type="number" name="custom_rate" step="0.0001" min="0" class="w-12 border-0 bg-transparent p-0 text-right text-[10px] font-bold text-teal-600 focus:ring-0" placeholder="/น.">
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded px-1 py-0.5">
                                                <input type="number" name="video_duration_hours" class="w-8 border-0 bg-transparent p-0 text-center text-[10px] focus:ring-0" placeholder="ชม.">
                                                <span class="text-[9px] text-gray-400">:</span>
                                                <input type="number" name="video_duration_minutes" class="w-8 border-0 bg-transparent p-0 text-center text-[10px] focus:ring-0" placeholder="น.">
                                                <span class="text-[9px] text-gray-400">:</span>
                                                <input type="number" name="video_duration_seconds" class="w-8 border-0 bg-transparent p-0 text-center text-[10px] focus:ring-0" placeholder="ว." min="0" max="59">
                                            </div>
                                            <input type="date" name="finalized_at" value="{{ date('Y-m-d') }}" class="w-24 border border-gray-300 rounded px-1 py-1 text-[10px]" required>
                                            <button class="bg-emerald-600 text-white hover:bg-emerald-700 px-2 py-1 rounded font-bold whitespace-nowrap text-[10px] shadow-sm">
                                                ปิดงาน
                                            </button>
                                        </div>
                                    </form>
                                @endif
                                
                                @if(auth()->user()?->hasRole('admin'))
                                    @if($job->status !== 'final')
                                        <button @click="openDirectFinalize({{ \Illuminate\Support\Js::from($job) }})" class="text-xs text-indigo-700 border border-indigo-200 bg-indigo-50 px-3 py-1 rounded-lg hover:bg-indigo-100 font-bold whitespace-nowrap shadow-sm transition-colors">
                                            ปิดงานด่วน
                                        </button>
                                    @endif
                                    <form action="{{ route('work.editing-job.delete', $job) }}" method="POST">@csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition-colors" title="ลบงาน (ลบทันที)">
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

    {{-- Unified Add / Edit Job Modal --}}
    <div x-show="jobModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="jobModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="jobModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-50 to-white px-6 py-4 border-b border-indigo-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-indigo-900" x-text="isEditing ? 'แก้ไขรายละเอียดงาน' : 'มอบหมายงานใหม่'"></h3>
                    <button type="button" @click="jobModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form
                    :action="isEditing ? ('{{ url('work/job') }}/' + jobForm.id) : '{{ route('work.editing-job.store') }}'"
                    method="POST">
                    @csrf
                    <input type="hidden" name="_method" :value="isEditing ? 'PATCH' : 'POST'">
                    <div class="p-6 space-y-4">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อโปรเจกต์ <span class="text-red-500">*</span></label>
                            <input type="text" name="job_name" x-model="jobForm.job_name"
                                   class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">หมวดหมู่เกม <span class="text-red-500">*</span></label>
                                <select name="game_id" x-model="jobForm.game_id"
                                        class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm" required>
                                    <option value="">-- เลือกหมวดหมู่ --</option>
                                    @foreach($games as $game)
                                        <option value="{{ $game->id }}">{{ $game->game_name }}</option>
                                    @endforeach
                                    <option value="other">อื่นๆ (ระบุชื่อ)...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">เจ้าของช่อง (YTB)</label>
                                <select name="youtuber_id" x-model="jobForm.youtuber_id"
                                        class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm">
                                    <option value="">-- ไม่ระบุ --</option>
                                    @foreach($youtubers as $ytb)
                                        <option value="{{ $ytb->id }}">{{ $ytb->first_name }} {{ $ytb->last_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Editor <span class="text-red-500">*</span></label>
                            <select name="assigned_to" x-model="jobForm.assigned_to"
                                    class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm" required>
                                <option value="">-- เลือก Editor --</option>
                                @foreach($editors as $ed)
                                    <option value="{{ $ed->id }}">{{ $ed->first_name }} {{ $ed->last_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="jobForm.game_id === 'other'" x-cloak class="p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                            <label class="block text-xs font-bold text-indigo-700 uppercase mb-1">ระบุชื่อหมวดหมู่ใหม่ <span class="text-red-500">*</span></label>
                            <input type="text" name="new_game_name"
                                   class="block w-full border border-indigo-300 rounded-md px-3 py-2 sm:text-sm"
                                   :required="jobForm.game_id === 'other'">
                        </div>

                        {{-- Pricing section removed as per user request - manage in Workspace instead --}}


                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ลิงก์เกม <span class="text-xs text-gray-400">(optional)</span></label>
                            <input type="url" name="game_link" x-model="jobForm.game_link"
                                   class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">กำหนดส่งภายใน (วัน)</label>
                                <input type="number" name="deadline_days" x-model="jobForm.deadline_days" min="1"
                                       class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm">
                            </div>
                            <div x-show="isEditing">
                                <label class="block text-sm font-medium text-gray-700 mb-1">หรือกำหนด Deadline วันที่</label>
                                <input type="date" name="deadline_date" x-model="jobForm.deadline_date"
                                       class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm">
                            </div>
                        </div>

                        {{-- Edit-only: ข้อมูลการตัดต่อ --}}
                        <div x-show="isEditing" class="p-3 bg-gray-50 border border-gray-200 rounded-lg space-y-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">ข้อมูลการตัดต่อ (แก้ไขย้อนหลังได้)</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Layer Count</label>
                                    <input type="number" name="layer_count" x-model="jobForm.layer_count"
                                           class="block w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Duration (นาที)</label>
                                    <input type="number" name="video_duration_minutes" x-model="jobForm.video_duration_minutes"
                                           class="block w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Duration (วินาที)</label>
                                    <input type="number" name="video_duration_seconds" x-model="jobForm.video_duration_seconds"
                                           min="0" max="59" class="block w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">โน้ต <span class="text-xs text-gray-400">(optional)</span></label>
                            <textarea name="notes" x-model="jobForm.notes" rows="2"
                                      class="block w-full border border-gray-300 rounded-md px-3 py-2 sm:text-sm"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 rounded-b-2xl border-t border-gray-100 flex justify-end gap-3">
                        <button type="button" @click="jobModal = false"
                                class="px-5 py-2.5 border border-gray-300 text-gray-700 font-bold rounded-xl text-sm hover:bg-gray-100 transition-colors">ยกเลิก</button>
                        <button type="submit"
                                class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl shadow-sm text-sm hover:bg-indigo-700 transition-colors"
                                x-text="isEditing ? 'บันทึกการแก้ไข' : 'มอบหมายงาน'"></button>
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
                                <input type="number" name="video_duration_hours" min="0" class="block w-20 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="ชม.">
                                <span class="text-gray-400 font-bold">:</span>
                                <input type="number" name="video_duration_minutes" min="0" class="block w-20 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="น." required>
                                <span class="text-gray-400 font-bold">:</span>
                                <input type="number" name="video_duration_seconds" min="0" max="59" class="block w-20 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="ว." required>
                            </div>
                        </div>

                        <div x-show="dfJobPayrollMode === 'freelance_layer'" x-data="{ mode: dfJobAssigneeFixedRate > 0 ? 'custom' : 'layer' }" class="p-3 bg-gray-50 border border-gray-200 rounded-lg space-y-3">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">รูปแบบการคิดเงิน</h4>
                            <div>
                                <select name="pricing_mode" x-model="mode" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500">
                                    <option value="layer">ตาม Layer</option>
                                    <option value="custom">เหมาคลิป (Fix Rate)</option>
                                    <option value="custom_rate_per_min">เรทต่อนาที (กำหนดเองแยกเฉพาะงาน)</option>
                                </select>
                            </div>

                            <div x-show="mode === 'layer'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนเลเยอร์ (ถ้ามีชาร์จเพิ่ม)</label>
                                <input type="number" name="layer_count" min="1" x-bind:value="dfJobLayer" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="ไม่บังคับ">
                            </div>

                            <div x-show="mode === 'custom'" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ยอดเงินเหมาจ่ายสำหรับคลิปนี้ (บาท)</label>
                                <input type="number" name="fix_amount" step="0.01" min="0" x-bind:value="dfJobAssigneeFixedRate" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="ยอดเงิน">
                            </div>

                            <div x-show="mode === 'custom_rate_per_min'" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">เรทต่อนาที (บาท/นาที)</label>
                                <input type="number" name="custom_rate" step="0.0001" min="0" class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500" placeholder="เรทเงิน">
                            </div>
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
