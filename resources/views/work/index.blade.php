@extends('layouts.app')

@section('title', 'Work Center')

@section('content')
@php
    $formatDurationMinutes = fn ($minutes) => $minutes !== null
        ? \App\Support\DurationInput::formatMinutesAsHms($minutes)
        : '-';
    $recPercent = $summary['recording_total'] > 0 ? round($summary['recording_active'] / $summary['recording_total'] * 100) : 0;
    $resPercent = $summary['resource_total'] > 0 ? round($summary['resource_ready'] / $summary['resource_total'] * 100) : 0;
    $editPercent = $summary['edit_total'] > 0 ? round($summary['edit_active'] / $summary['edit_total'] * 100) : 0;
@endphp
<div x-data="{
        tab: '{{ request()->query('tab') }}' || localStorage.getItem('workCenterTab') || 'recording',
        showRecForm: false, showResForm: false, showEditForm: false,
        recSearch: '', resSearch: '', editSearch: ''
     }" 
     x-init="$watch('tab', value => localStorage.setItem('workCenterTab', value))" class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Work Center</h1>
            <p class="text-sm text-gray-500">Pipeline ควบคุมงานถ่าย, Resource, ตัดต่อ</p>
        </div>
    </div>

    {{-- Summary Cards — KPI Style --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Recording Card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 cursor-pointer hover:border-indigo-300 transition-colors group" @click="tab='recording'">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">คิวถ่าย</span>
                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-base group-hover:bg-indigo-100 transition-colors">🎬</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-gray-900">{{ $summary['recording_active'] }}</span>
                <span class="text-sm text-gray-400">active</span>
            </div>
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                    <span>{{ $summary['recording_active'] }} / {{ $summary['recording_total'] }}</span>
                    <span>{{ $recPercent }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-indigo-500 h-1.5 rounded-full transition-all" style="width: {{ $recPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Resources Card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 cursor-pointer hover:border-indigo-300 transition-colors group" @click="tab='resource'">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Resources</span>
                <span class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center text-base group-hover:bg-gray-100 transition-colors">📦</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-gray-900">{{ $summary['resource_ready'] }}</span>
                <span class="text-sm text-gray-400">ready</span>
            </div>
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                    <span>{{ $summary['resource_ready'] }} / {{ $summary['resource_total'] }}</span>
                    <span>{{ $resPercent }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-gray-400 h-1.5 rounded-full transition-all" style="width: {{ $resPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Edit Jobs Card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 cursor-pointer hover:border-indigo-300 transition-colors group" @click="tab='editing'">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">งานตัดต่อ</span>
                <span class="w-8 h-8 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center text-base group-hover:bg-slate-100 transition-colors">✂️</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-gray-900">{{ $summary['edit_active'] }}</span>
                <span class="text-sm text-gray-400">active</span>
            </div>
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                    <span>{{ $summary['edit_active'] }} / {{ $summary['edit_total'] }}</span>
                    <span>{{ $editPercent }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-slate-500 h-1.5 rounded-full transition-all" style="width: {{ $editPercent }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Navigation — Underline Style --}}
    <div class="border-b border-gray-200">
        <nav class="flex space-x-8" aria-label="Tabs">
            <button @click="tab='recording'"
                :class="tab==='recording' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors flex items-center gap-2">
                🎬 คิวถ่าย
                <span :class="tab==='recording' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors">{{ $summary['recording_total'] }}</span>
            </button>
            <button @click="tab='resource'"
                :class="tab==='resource' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors flex items-center gap-2">
                📦 Resources
                <span :class="tab==='resource' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors">{{ $summary['resource_total'] }}</span>
            </button>
            <button @click="tab='editing'"
                :class="tab==='editing' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors flex items-center gap-2">
                ✂️ งานตัดต่อ
                <span :class="tab==='editing' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors">{{ $summary['edit_total'] }}</span>
            </button>
        </nav>
    </div>

    {{-- ====== Recording Tab ====== --}}
    <div x-show="tab==='recording'" x-cloak class="space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold">คิวถ่าย / Recording Plan</h2>
            <button @click="showRecForm=!showRecForm" class="inline-flex items-center gap-1.5 bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                สร้างคิวถ่าย
            </button>
        </div>

        {{-- Search/Filter Bar --}}
        <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-4 py-2.5">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="recSearch" placeholder="ค้นหาตามชื่อคิว, เกม, ทีม..." class="w-full text-sm border-0 focus:ring-0 placeholder-gray-400 p-0">
            <button x-show="recSearch" @click="recSearch=''" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Form --}}
        <div x-show="showRecForm" x-cloak class="bg-white rounded-xl shadow-sm border p-4">
            <form method="POST" action="{{ route('work.recording.store') }}" class="space-y-3"
                  x-data="{ gameType: '' }">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <label class="text-xs text-gray-500">ชื่อคิว *</label>
                        <input type="text" name="title" required class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Game Type</label>
                        <select name="game_type" x-model="gameType" class="w-full border rounded px-2 py-1.5 text-sm">
                            <option value="">-- เลือกประเภท --</option>
                            <option value="ผี">เกมผี</option>
                            <option value="FUNNY">FUNNY</option>
                            <option value="SIMULATOR">SIMULATOR</option>
                            <option value="MOBA">MOBA</option>
                            <option value="RPG">RPG</option>
                            <option value="FPS">FPS</option>
                            <option value="PUZZLE">PUZZLE</option>
                            <option value="__custom__">(เพิ่มประเภท)</option>
                        </select>
                    </div>
                    <div x-show="gameType === '__custom__'" x-cloak>
                        <label class="text-xs text-gray-500">ประเภทเกม (กำหนดเอง)</label>
                        <input type="text" name="game_type_custom" placeholder="พิมพ์ประเภทเกม"
                               class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Game</label>
                        <input type="text" name="game" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Map</label>
                        <input type="text" name="map" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">วันที่กำหนดถ่าย</label>
                        <input type="date" name="scheduled_date" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">เวลาถ่าย</label>
                        <input type="time" name="scheduled_time" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">ระยะเวลา</label>
                        <input type="time" step="60" name="planned_duration_hms" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Priority</label>
                        <select name="priority" class="w-full border rounded px-2 py-1.5 text-sm">
                            <option value="normal">Normal</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">หมายเหตุ</label>
                        <input type="text" name="notes" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">ชื่อ Resource (ไม่บังคับ)</label>
                        <input type="text" name="resource_title" placeholder="ถ้าไม่ใส่ ระบบตั้งชื่อให้อัตโนมัติ" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                </div>

                {{-- Assignee Section --}}
                @php
                    $youtuberOptions = $youtubers->map(fn($emp) => [
                        'id' => (string) $emp->id,
                        'name' => trim($emp->first_name . ' ' . $emp->last_name),
                    ])->values();
                    $otherOptions = $employees
                        ->whereNotIn('id', $youtubers->pluck('id'))
                        ->map(fn($emp) => [
                            'id' => (string) $emp->id,
                            'name' => trim($emp->first_name . ' ' . $emp->last_name),
                        ])->values();
                @endphp
                <div class="border-t pt-3" x-data="{
                    youtuberOptions: @js($youtuberOptions),
                    otherOptions: @js($otherOptions),
                    assignees: [],
                    manualEmployeeId: '',
                    showManualPicker: false,
                    isSelected(id) {
                        return this.assignees.some(a => a.employee_id === String(id));
                    },
                    addAssignee(emp, role = 'youtuber') {
                        const employeeId = String(emp.id);
                        if (this.isSelected(employeeId)) return;
                        this.assignees.push({ employee_id: employeeId, role: role, name: emp.name });
                    },
                    toggleYoutuber(emp) {
                        const employeeId = String(emp.id);
                        if (this.isSelected(employeeId)) {
                            this.assignees = this.assignees.filter(a => a.employee_id !== employeeId);
                            return;
                        }
                        this.addAssignee(emp, 'youtuber');
                    },
                    addManualById() {
                        const employeeId = String(this.manualEmployeeId || '');
                        if (!employeeId) return;
                        const match = this.otherOptions.find(emp => String(emp.id) === employeeId);
                        if (!match) return;
                        this.addAssignee(match, 'crew');
                        this.manualEmployeeId = '';
                    },
                    removeAt(index) {
                        this.assignees.splice(index, 1);
                    }
                }">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-700">🎬 ทีมที่ถ่ายคิวนี้</span>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg p-3 space-y-3 shadow-sm">
                        {{-- Youtuber Quick Picks (Pills) --}}
                        <div class="flex flex-wrap gap-2">
                            <template x-for="emp in youtuberOptions" :key="emp.id">
                                <button type="button" @click="toggleYoutuber(emp)"
                                        :class="isSelected(emp.id)
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm ring-1 ring-indigo-600'
                                            : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700'"
                                        class="h-7 px-3 py-1 rounded-full border text-[11px] font-medium transition-colors select-none flex items-center justify-center">
                                    <span x-text="emp.name"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Manual Picker --}}
                        <div class="flex gap-2 items-center border-t border-gray-100 pt-3">
                            <select x-model="manualEmployeeId" class="border border-gray-300 rounded-md px-2 py-1.5 text-xs flex-1 bg-gray-50 focus:bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                <option value="">-- เลือกพนักงานคนอื่นเพิ่ม --</option>
                                <template x-for="emp in otherOptions" :key="emp.id">
                                    <option :value="emp.id" x-text="emp.name"></option>
                                </template>
                            </select>
                            <button type="button" @click="addManualById()"
                                    class="h-7 px-3 bg-slate-800 border border-transparent text-white rounded text-xs font-medium hover:bg-slate-700 shadow-sm transition-colors whitespace-nowrap">
                                + เพิ่มเข้าคิว
                            </button>
                        </div>
                    </div>

                    {{-- Selected Assignees --}}
                    <div class="mt-3 bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-600">รายชื่อในคิวปัจจุบัน</span>
                            <span class="text-[10px] text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded font-medium" x-text="assignees.length + ' คน'"></span>
                        </div>

                        <template x-if="assignees.length === 0">
                            <div class="px-3 py-4 text-xs text-center text-gray-400">ยังไม่มีใครอยู่ในคิวถ่ายนี้</div>
                        </template>

                        <div class="divide-y divide-gray-100 max-h-40 overflow-y-auto">
                            <template x-for="(a, index) in assignees" :key="a.employee_id">
                                <div class="flex items-center justify-between px-3 py-2 hover:bg-gray-50 group transition-colors">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded overflow-hidden bg-indigo-100 text-indigo-700 flex items-center justify-center text-[9px] font-bold" x-text="a.name.substring(0, 1)"></div>
                                        <span class="text-xs font-medium text-gray-700" x-text="a.name"></span>
                                    </div>
                                    <button type="button" @click="removeAt(index)" class="text-gray-300 hover:text-red-500 w-5 h-5 flex items-center justify-center rounded hover:bg-red-50 transition-colors">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                    <input type="hidden" :name="`assignees[${index}][employee_id]`" :value="a.employee_id">
                                    <input type="hidden" :name="`assignees[${index}][role]`" :value="a.role">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm">บันทึก</button>
                </div>
            </form>
        </div>

        {{-- Recording Table (Active) --}}
        @php $activeRecordings = $recordings->whereNotIn('status', ['shot']); $shotRecordings = $recordings->where('status', 'shot'); @endphp
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อคิว</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Game Type / Game / Map</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันถ่าย / เวลา</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">เริ่มอัดจริง</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ระยะเวลา</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ทีม</th>
                        <th class="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $statusLabels = [
                            'draft' => 'ร่าง',
                            'scheduled' => 'นัดถ่าย',
                            'recording' => 'กำลังถ่าย',
                            'shot' => 'ถ่ายเสร็จ',
                            'cancelled' => 'ยกเลิก',
                        ];
                    @endphp
                    @forelse($activeRecordings as $rec)
                    @php
                        $logs = $recordingStatusLogs->get($rec->id, collect());
                        $startedLog = $logs->where('new_value', 'recording')->sortBy('created_at')->first();
                        $latestStatusLog = $logs->first();
                        $recSearchData = strtolower($rec->title . ' ' . ($rec->game_type ?? '') . ' ' . ($rec->game ?? '') . ' ' . ($rec->map ?? '') . ' ' . $rec->assignees->map(fn($a) => $a->employee->first_name)->join(' '));
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors" 
                        x-data="{ showTimeline: false, showScheduleEdit: false }"
                        x-show="!recSearch || '{{ addslashes($recSearchData) }}'.includes(recSearch.toLowerCase())">
                        <td class="px-3 py-2.5 font-medium text-gray-900">{{ $rec->title }}</td>
                        <td class="px-3 py-2.5 text-gray-500">
                            @if($rec->game_type)
                                <span class="inline-block bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded text-[10px] mr-1 font-medium">{{ $rec->game_type }}</span>
                            @endif
                            {{ $rec->game ?? '-' }} {{ $rec->map ? '/ '.$rec->map : '' }}
                        </td>
                        {{-- Schedule Column — clean display with edit on click --}}
                        <td class="px-3 py-2.5">
                            <div @click="showScheduleEdit = !showScheduleEdit" class="cursor-pointer group/sched inline-flex items-center gap-1.5">
                                <span class="text-gray-700">{{ $rec->scheduled_date?->format('d/m/Y') ?? '-' }}</span>
                                @if($rec->scheduled_time)
                                    <span class="text-gray-400">{{ \Illuminate\Support\Str::of($rec->scheduled_time)->substr(0, 5) }}</span>
                                @endif
                                <svg class="w-3 h-3 text-gray-300 group-hover/sched:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </div>
                            <div x-show="showScheduleEdit" x-cloak x-transition class="mt-1.5">
                                <form method="POST" action="{{ route('work.recording.schedule', $rec) }}" class="flex items-center gap-1.5 flex-wrap">
                                    @csrf @method('PATCH')
                                    <input type="date" name="scheduled_date" value="{{ $rec->scheduled_date?->format('Y-m-d') }}" class="border border-gray-200 rounded-md px-2 py-1 text-[11px] focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                                    <input type="time" name="scheduled_time" value="{{ $rec->scheduled_time ? \Illuminate\Support\Str::of($rec->scheduled_time)->substr(0, 5) : '' }}" class="border border-gray-200 rounded-md px-2 py-1 text-[11px] focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                                    <button type="submit" class="text-[11px] text-white bg-indigo-600 rounded-md px-2.5 py-1 hover:bg-indigo-700 font-medium transition-colors">บันทึก</button>
                                    <button type="button" @click="showScheduleEdit = false" class="text-[11px] text-gray-400 hover:text-gray-600">ยกเลิก</button>
                                </form>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-[11px]">
                            @if($startedLog)
                                <div class="font-medium text-emerald-700">{{ $startedLog->created_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">{{ $formatDurationMinutes($rec->planned_duration_minutes) }}</td>
                        {{-- Status Column — badge + popover for editing --}}
                        <td class="px-3 py-2.5" x-data="{ showStatusEdit: false, selectedStatus: '{{ $rec->status }}' }">
                            @php
                                $recStage = $jobStages->where('type', 'recording')->where('code', $rec->status)->first();
                                $recColor = $recStage ? $recStage->color : 'gray';
                                $longestHms = \App\Support\DurationInput::formatSecondsAsHms($rec->longest_footage_seconds);
                            @endphp
                            {{-- Status Badge --}}
                            <button type="button" @click="showStatusEdit = !showStatusEdit"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-{{ $recColor }}-100 text-{{ $recColor }}-700 hover:ring-2 hover:ring-{{ $recColor }}-200 transition-all cursor-pointer">
                                {{ $statusLabels[$rec->status] ?? $rec->status }}
                                <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            {{-- Status Popover --}}
                            <div x-show="showStatusEdit" x-cloak x-transition @click.away="showStatusEdit = false"
                                 class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg p-3 space-y-2 min-w-[220px] relative z-10">
                                <form method="POST" action="{{ route('work.recording.status', $rec) }}" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <select name="status" x-model="selectedStatus" class="w-full border border-gray-200 rounded-md text-xs px-2 py-1.5 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                                        @foreach($jobStages->where('type', 'recording') as $stg)
                                            @if($stg->is_active || $rec->status === $stg->code)
                                                <option value="{{ $stg->code }}" {{ $rec->status === $stg->code ? 'selected' : '' }}>{{ $stg->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <div x-show="selectedStatus === 'shot'" x-cloak class="space-y-1.5">
                                        <div>
                                            <label class="text-[10px] text-gray-500">จำนวนฟุตเทจ</label>
                                            <input type="number" name="footage_count" min="1" value="{{ $rec->footage_count }}" class="w-full border border-gray-200 rounded-md px-2 py-1 text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                                        </div>
                                        <div>
                                            <label class="text-[10px] text-gray-500">Longest Footage</label>
                                            <input type="time" step="1" name="longest_footage_hms" value="{{ $longestHms }}" class="w-full border border-gray-200 rounded-md px-2 py-1 text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="text-xs text-white bg-indigo-600 rounded-md px-3 py-1.5 hover:bg-indigo-700 font-medium transition-colors">อัปเดต</button>
                                        <button type="button" @click="showStatusEdit = false" class="text-xs text-gray-400 hover:text-gray-600">ยกเลิก</button>
                                    </div>
                                </form>
                            </div>

                            {{-- Timeline link --}}
                            <div class="mt-1.5 flex items-center gap-2">
                                @if($latestStatusLog)
                                    <span class="text-[10px] text-gray-400">
                                        ล่าสุด {{ $latestStatusLog->created_at?->format('d/m H:i') }}
                                    </span>
                                @endif
                                @if($logs->isNotEmpty())
                                    <button type="button" @click="showTimeline = true"
                                        class="text-[10px] text-indigo-600 hover:text-indigo-700 font-medium hover:underline">
                                        Timeline ({{ $logs->count() }})
                                    </button>
                                @endif
                            </div>

                            @if($logs->isNotEmpty())
                                <div x-show="showTimeline" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                                    <div class="absolute inset-0 bg-black/40" @click="showTimeline = false"></div>
                                    <div class="relative bg-white w-full max-w-lg rounded-xl shadow-xl border border-slate-200 overflow-hidden">
                                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                                            <h4 class="text-sm font-semibold text-slate-800">สถานะคิว: {{ $rec->title }}</h4>
                                            <button type="button" @click="showTimeline = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
                                        </div>
                                        <div class="max-h-80 overflow-auto p-3 space-y-1">
                                            @foreach($logs->take(20) as $log)
                                                <div class="text-xs text-slate-600 border-b border-slate-100 pb-1">
                                                    <span class="font-medium">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                                                        <span class="mx-1">{{ $statusLabels[$log->old_value] ?? ($log->old_value ?? '-') }} → {{ $statusLabels[$log->new_value] ?? ($log->new_value ?? '-') }}</span>
                                                    @if($log->user)
                                                        <span class="text-slate-400">({{ $log->user->name }})</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <div x-data="{ open: false }" class="space-y-0.5">
                                {{-- Existing assignees with remove button --}}
                                @foreach($rec->assignees as $a)
                                <div class="inline-flex items-center gap-0.5 bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 rounded-md text-[10px] mr-0.5 mb-0.5">
                                    <span class="text-indigo-700 font-medium">{{ $a->employee->first_name }}</span>
                                    <span class="text-indigo-400">({{ $a->role }})</span>
                                    <form method="POST" action="{{ route('work.recording.assignee.remove', $a) }}" class="inline ml-0.5">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-300 hover:text-red-500 leading-none transition-colors" title="ลบออก">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </div>
                                @endforeach

                                {{-- Inline assign form --}}
                                <button type="button" @click="open=!open"
                                    class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700 border border-indigo-200 bg-indigo-50 px-2 py-1 rounded-lg transition-colors">
                                    + assign
                                </button>
                                <div x-show="open" x-cloak x-transition class="mt-1">
                                    <form method="POST" action="{{ route('work.recording.assign', $rec) }}"
                                        class="flex gap-1.5 items-center flex-wrap">
                                        @csrf
                                        <select name="employee_id" class="border border-slate-300 rounded-lg px-2 py-1 text-[12px] text-slate-700 bg-white focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200" required>
                                            <option value="">-- เลือก --</option>
                                            @if($youtubers->count())
                                            <optgroup label="🎬 YouTubers">
                                                @foreach($youtubers as $emp)
                                                <option value="{{ $emp->id }}">{{ $emp->first_name }}</option>
                                                @endforeach
                                            </optgroup>
                                            @endif
                                            @php $others = $employees->whereNotIn('id', $youtubers->pluck('id')); @endphp
                                            @if($others->count())
                                            <optgroup label="อื่นๆ">
                                                @foreach($others as $emp)
                                                <option value="{{ $emp->id }}">{{ $emp->first_name }}</option>
                                                @endforeach
                                            </optgroup>
                                            @endif
                                        </select>
                                        <input name="role" type="text" value="youtuber" placeholder="role"
                                               class="border border-slate-300 rounded-lg px-2 py-1 text-[12px] w-24 text-slate-700 focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200">
                                        <button type="submit"
                                                class="bg-indigo-600 text-white px-2.5 py-1 rounded-lg text-[12px] font-medium hover:bg-indigo-700">OK</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <form method="POST" action="{{ route('work.recording.delete', $rec) }}" onsubmit="return confirm('ลบคิวนี้?')" class="inline">
                                @csrf @method('DELETE')
                                <button class="p-1.5 rounded-md text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors" title="ลบ">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-3 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-2xl">📭</div>
                                <p class="text-sm font-medium text-gray-500">ยังไม่มีคิวถ่าย</p>
                                <p class="text-xs text-gray-400">กด "+ สร้างคิวถ่าย" เพื่อเริ่มต้น</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Shot / Completed Section --}}
        @if($shotRecordings->isNotEmpty())
        <div class="mt-6">
            <div class="flex items-center gap-2 mb-2">
                <h3 class="text-sm font-semibold text-gray-700">ถ่ายเสร็จแล้ว</h3>
                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full font-medium">{{ $shotRecordings->count() }} คิว</span>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-xs">
                    <thead class="bg-green-50/60 border-b border-green-100">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-green-800 uppercase tracking-wider">ชื่อคิว</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-green-800 uppercase tracking-wider">Game Type / Game / Map</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-green-800 uppercase tracking-wider">วันถ่าย / เวลา</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-green-800 uppercase tracking-wider">เริ่มอัดจริง</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-green-800 uppercase tracking-wider">ระยะเวลา</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-green-800 uppercase tracking-wider">ทีม</th>
                            <th class="px-3 py-2.5 text-center text-xs font-medium text-green-800 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                        $statusLabels2 = [
                            'draft' => 'ร่าง', 'scheduled' => 'นัดถ่าย',
                            'recording' => 'กำลังถ่าย', 'shot' => 'ถ่ายเสร็จ', 'cancelled' => 'ยกเลิก',
                        ];
                        @endphp
                        @foreach($shotRecordings as $rec)
                        @php
                            $logs = $recordingStatusLogs->get($rec->id, collect());
                            $startedLog = $logs->where('new_value', 'recording')->sortBy('created_at')->first();
                        @endphp
                        <tr class="hover:bg-green-50/40 transition-colors">
                            <td class="px-3 py-2.5 font-medium text-gray-900">{{ $rec->title }}</td>
                            <td class="px-3 py-2.5 text-gray-500">
                                @if($rec->game_type)
                                    <span class="inline-block bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded text-[10px] mr-1 font-medium">{{ $rec->game_type }}</span>
                                @endif
                                {{ $rec->game ?? '-' }} {{ $rec->map ? '/ '.$rec->map : '' }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-600">
                                {{ $rec->scheduled_date?->format('d/m/Y') ?? '-' }}
                                @if($rec->scheduled_time)
                                    <span class="text-gray-400">{{ \Illuminate\Support\Str::of($rec->scheduled_time)->substr(0, 5) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-[11px]">
                                @if($startedLog)
                                    <div class="font-medium text-emerald-700">{{ $startedLog->created_at?->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">{{ $formatDurationMinutes($rec->planned_duration_minutes) }}</td>
                            <td class="px-3 py-2.5">
                                @foreach($rec->assignees as $a)
                                <span class="inline-flex items-center bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 rounded-md text-[10px] mr-0.5">
                                    <span class="text-indigo-700 font-medium">{{ $a->employee->first_name }}</span>
                                    <span class="text-indigo-400 ml-0.5">({{ $a->role }})</span>
                                </span>
                                @endforeach
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <form method="POST" action="{{ route('work.recording.status', $rec) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit" class="text-[10px] text-amber-600 border border-amber-200 bg-amber-50 rounded-md px-2 py-1 hover:bg-amber-100 font-medium transition-colors" title="ย้ายกลับเป็นร่าง">ย้ายกลับ</button>
                                    </form>
                                    <form method="POST" action="{{ route('work.recording.delete', $rec) }}" onsubmit="return confirm('ลบคิวนี้?')" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="p-1 rounded-md text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors" title="ลบ">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- ====== Resource Tab ====== --}}
    <div x-show="tab==='resource'" x-cloak class="space-y-4">
        @php
            $activeResources = $resources->filter(fn($res) => $res->status !== 'archived');
            $usedResources = $resources->where('status', 'archived');
            $resourceStatusLabel = function ($status) {
                return match ($status) {
                    'in_use' => 'Assigned',
                    'archived' => 'Used',
                    default => 'Ready for edit',
                };
            };
            $resourceStatusClass = function ($status) {
                return match ($status) {
                    'in_use' => 'bg-blue-100 text-blue-700',
                    'archived' => 'bg-slate-200 text-slate-600',
                    default => 'bg-green-100 text-green-700',
                };
            };
        @endphp
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold">Resource Library</h2>
                <p class="text-xs text-gray-500">คลังวัตถุดิบจากงานถ่าย พร้อม metadata ที่ใช้ส่งต่อให้ทีมตัดต่อ</p>
            </div>
            <button @click="showResForm=!showResForm" class="inline-flex items-center gap-1.5 bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                เพิ่ม Resource
            </button>
        </div>

        {{-- Search/Filter Bar --}}
        <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-4 py-2.5">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="resSearch" placeholder="ค้นหาตาม footage code, ชื่อ, recording..." class="w-full text-sm border-0 focus:ring-0 placeholder-gray-400 p-0">
            <button x-show="resSearch" @click="resSearch=''" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="showResForm" x-cloak class="bg-white rounded-xl shadow-sm border p-4">
            <form method="POST" action="{{ route('work.resource.store') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                @csrf
                <div>
                    <label class="text-xs text-gray-500">Footage Code</label>
                    <input type="text" value="ระบบสร้างอัตโนมัติ" disabled class="w-full border rounded px-2 py-1.5 text-sm bg-gray-50 text-gray-400">
                </div>
                <div>
                    <label class="text-xs text-gray-500">ชื่อ</label>
                    <input type="text" name="title" class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Length (HH:MM:SS)</label>
                    <input type="time" step="1" name="duration_hms" class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">จาก Recording Job</label>
                    <select name="recording_job_id" class="w-full border rounded px-2 py-1.5 text-sm">
                        <option value="">— ไม่ระบุ —</option>
                        @foreach($recordings as $r)
                        <option value="{{ $r->id }}">{{ $r->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">จำนวนฟุตเทจ</label>
                    <input type="number" min="1" name="footage_count" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="ใส่เมื่อเป็น standalone resource">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Game Category (ถ้าไม่ผูก Recording)</label>
                    <select name="game_category" class="w-full border rounded px-2 py-1.5 text-sm">
                        <option value="">— เลือกหมวด —</option>
                        <option value="ผี">เกมผี</option>
                        <option value="FUNNY">FUNNY</option>
                        <option value="SIMULATOR">SIMULATOR</option>
                        <option value="MOBA">MOBA</option>
                        <option value="RPG">RPG</option>
                        <option value="FPS">FPS</option>
                        <option value="PUZZLE">PUZZLE</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">หมายเหตุ</label>
                    <input type="text" name="notes" class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
                <div class="lg:col-span-4 flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-4 py-1.5 rounded text-sm mt-2">บันทึก</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Footage Code</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อ</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ต้นทาง / Recording</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Game Type</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Game / Map</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันถ่าย</th>
                        <th class="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">จำนวนฟุต</th>
                        <th class="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Longest Footage</th>
                        <th class="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Raw Length</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Edit Jobs</th>
                        <th class="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($activeResources as $res)
                    @php
                        $recording = $res->recordingJob;
                        $footageCount = $recording?->footage_count ?? $res->footage_count;
                        $longestFootage = $recording?->longest_footage_seconds
                            ? \App\Support\DurationInput::formatSecondsAsHms($recording->longest_footage_seconds)
                            : '-';
                        $resSearchData = strtolower(($res->footage_code ?? '') . ' ' . ($res->title ?? '') . ' ' . ($recording?->title ?? '') . ' ' . ($recording?->game_type ?? '') . ' ' . ($recording?->game ?? ''));
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors"
                        x-show="!resSearch || '{{ addslashes($resSearchData) }}'.includes(resSearch.toLowerCase())">
                        <td class="px-3 py-2.5 font-mono font-medium text-gray-900">{{ $res->footage_code }}</td>
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-gray-800">{{ $res->title ?? '-' }}</div>
                            @if($res->notes)
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $res->notes }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            @if($recording)
                                <div class="font-medium text-gray-700">{{ $recording->title }}</div>
                                <div class="text-[10px] text-green-600">linked from recording</div>
                            @else
                                <div class="text-gray-400">Standalone Resource</div>
                                <div class="text-[10px] text-slate-400">สร้างตรงจาก Resource tab</div>
                            @endif
                        </td>
                        {{-- Metadata split into separate columns --}}
                        <td class="px-3 py-2.5">
                            @if($recording?->game_type)
                                <span class="inline-block bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded text-[10px] font-medium">{{ $recording->game_type }}</span>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-[11px] text-gray-600">
                            @if($recording)
                                {{ $recording->game ?? '-' }}{{ $recording->map ? ' / ' . $recording->map : '' }}
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-[11px] text-gray-500">
                            @if($recording)
                                {{ $recording->scheduled_date?->format('d/m/Y') ?? '-' }}
                                @if($recording->scheduled_time)
                                    <span class="text-gray-400 ml-0.5">{{ \Illuminate\Support\Str::of($recording->scheduled_time)->substr(0, 5) }}</span>
                                @endif
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right">{{ $footageCount ?? '-' }}</td>
                        <td class="px-3 py-2.5 text-right">{{ $longestFootage }}</td>
                        <td class="px-3 py-2.5 text-right">{{ $res->raw_duration }}</td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $resourceStatusClass($res->status) }}">
                                {{ $resourceStatusLabel($res->status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right text-gray-400">{{ $res->edit_jobs_count }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <form method="POST" action="{{ route('work.resource.delete', $res) }}" onsubmit="return confirm('ลบ Resource นี้?')" class="inline">
                                @csrf @method('DELETE')
                                <button class="p-1.5 rounded-md text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors" title="ลบ">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-3 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-2xl">📦</div>
                                <p class="text-sm font-medium text-gray-500">ยังไม่มี Resource</p>
                                <p class="text-xs text-gray-400">กด "+ เพิ่ม Resource" หรือสร้างคิวถ่ายที่มี Resource อัตโนมัติ</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($usedResources->isNotEmpty())
        <div class="mt-6">
            <div class="flex items-center gap-2 mb-2">
                <h3 class="text-sm font-semibold text-gray-700">Used แล้ว</h3>
                <span class="bg-slate-200 text-slate-700 text-xs px-2 py-0.5 rounded-full font-medium">{{ $usedResources->count() }} รายการ</span>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50/80 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Footage Code</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ชื่อ</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ต้นทาง / Recording</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">จำนวนฟุต</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Raw Length</th>
                            <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Edit Jobs</th>
                            <th class="px-3 py-2.5 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($usedResources as $res)
                        @php
                            $recording = $res->recordingJob;
                            $footageCount = $recording?->footage_count ?? $res->footage_count;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-3 py-2.5 font-mono font-medium text-gray-900">{{ $res->footage_code }}</td>
                            <td class="px-3 py-2.5">{{ $res->title ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-gray-500">{{ $recording?->title ?? 'Standalone Resource' }}</td>
                            <td class="px-3 py-2.5 text-right">{{ $footageCount ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-right">{{ $res->raw_duration }}</td>
                            <td class="px-3 py-2.5 text-right text-gray-400">{{ $res->edit_jobs_count }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600">Used</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- ====== Editing Tab ====== --}}
    <div x-show="tab==='editing'" x-cloak class="space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold">งานตัดต่อ (Edit Jobs)</h2>
            <button @click="showEditForm=!showEditForm" class="inline-flex items-center gap-1.5 bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                สร้างงานตัดต่อ
            </button>
        </div>

        {{-- Search/Filter Bar --}}
        <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-4 py-2.5">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="editSearch" placeholder="ค้นหาตามชื่องาน, resource, ผู้ตัดต่อ..." class="w-full text-sm border-0 focus:ring-0 placeholder-gray-400 p-0">
            <button x-show="editSearch" @click="editSearch=''" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

                <div x-show="showEditForm" x-cloak class="bg-white rounded-xl shadow-sm border p-4">
                        <form method="POST" action="{{ route('work.edit-job.store') }}" class="space-y-3"
                                    x-data="editJobFormState()">
                @csrf
                
                {{-- Resource Selection (Primary) --}}
                <div class="p-3 bg-indigo-50 border border-indigo-200 rounded">
                    <label class="text-xs font-medium text-indigo-700">1. เลือก Resource ที่จะตัดต่อ *</label>
                    <select name="media_resource_id" 
                            x-model="selectedResourceId"
                            @change="onResourceChange($event)"
                            class="w-full border rounded px-2 py-2 text-sm mt-2 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                        <option value="">-- เลือก Resource --</option>
                        @foreach($resources->where('status', 'ready_for_edit') as $r)
                        <option value="{{ $r->id }}">{{ $r->footage_code }} - {{ $r->title }} (Ready for edit)</option>
                        @endforeach
                    </select>
                    @if($resources->where('status', 'ready_for_edit')->isEmpty())
                    <p class="text-xs text-amber-600 mt-1">💡 ไม่มี Resource พร้อมตัด ให้กลับไปหน้าคิวถ่ายกด "ถ่ายเสร็จ" ก่อน</p>
                    @endif
                </div>

                {{-- Job Details (Auto-filled) --}}
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="text-xs text-gray-500">ชื่องาน (Auto-fill from Resource) *</label>
                        <input type="text" name="title" required x-model="selectedResourceTitle" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="เลือก Resource ด้านบนก่อน">
                        <p class="text-[10px] text-gray-400 mt-1">ระบบเติมชื่ออัตโนมัติจาก Resource แล้วสามารถแก้ไขเองได้</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">มอบหมายให้</label>
                        <select name="assigned_to" x-model="selectedEmployeeId" @change="onEmployeeChange()" class="w-full border rounded px-2 py-1.5 text-sm">
                            <option value="">— ยังไม่ระบุ —</option>
                            @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->first_name }}
                                @if(in_array($emp->payroll_mode, ['freelance_layer', 'freelance_fixed']))
                                    ({{ $emp->payroll_mode === 'freelance_layer' ? 'FL-Layer' : 'FL-Fixed' }})
                                @endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Priority</label>
                        <select name="priority" class="w-full border rounded px-2 py-1.5 text-sm">
                            <option value="normal">Normal</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Due Date</label>
                        <input type="date" name="due_date" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">หมายเหตุ</label>
                        <input type="text" name="notes" class="w-full border rounded px-2 py-1.5 text-sm">
                    </div>
                </div>

                {{-- Freelance Layer: Pricing Group --}}
                <div x-show="employeePayrollMode === 'freelance_layer'" x-cloak
                     class="p-3 border rounded-lg space-y-3"
                     :class="pricingGroup === 'template' ? 'bg-green-50 border-green-200' : (pricingGroup === 'isolated' ? 'bg-indigo-50 border-indigo-200' : 'bg-gray-50 border-gray-200')">
                    <label class="text-xs font-medium text-gray-700">กลุ่มราคา (Freelance Layer)</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="pricing_group" value="template" x-model="pricingGroup" class="text-green-600 focus:ring-green-500">
                            <span class="text-sm font-medium text-green-700">Template Layer</span>
                            <span class="text-[10px] text-gray-400">(เลือกวงราคาจาก Rate Rules)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="pricing_group" value="isolated" x-model="pricingGroup" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-indigo-700">Isolated</span>
                            <span class="text-[10px] text-gray-400">(กำหนดเรทเอง)</span>
                        </label>
                    </div>

                    {{-- Template: select layer rate --}}
                    <div x-show="pricingGroup === 'template'" x-cloak class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-green-700">วงราคา (Layer Range)</label>
                            <select name="pricing_template_label" x-model="selectedTemplateLabel" @change="onTemplateLabelChange()" class="w-full border rounded px-2 py-1.5 text-sm">
                                <option value="">-- เลือกวงราคา --</option>
                                <template x-for="lr in employeeLayerRates" :key="lr.label">
                                    <option :value="lr.label" x-text="`${lr.label} (${lr.rate.toFixed(2)}/นาที)`"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-green-700">เรท/นาที (Auto-fill)</label>
                            <input type="number" step="0.0001" name="assigned_rate" x-model="assignedRate" readonly class="w-full border rounded px-2 py-1.5 text-sm bg-green-100 font-medium">
                        </div>
                    </div>

                    {{-- Isolated: manual rate --}}
                    <div x-show="pricingGroup === 'isolated'" x-cloak>
                        <label class="text-xs text-indigo-700">เรท/นาที (กำหนดเอง)</label>
                        <input type="number" step="0.0001" name="assigned_rate" x-model="assignedRate" class="w-full border rounded px-2 py-1.5 text-sm max-w-[200px]" placeholder="0.0000">
                    </div>
                </div>

                {{-- Freelance Fixed: Rate per piece --}}
                <div x-show="employeePayrollMode === 'freelance_fixed'" x-cloak
                     class="p-3 bg-teal-50 border border-teal-200 rounded-lg space-y-3">
                    <label class="text-xs font-medium text-teal-700">ราคาฟิกต่อชิ้น (Freelance Fixed)</label>
                    <div class="grid grid-cols-2 gap-3 max-w-md">
                        <div>
                            <label class="text-xs text-teal-600">จำนวน (ชิ้น)</label>
                            <input type="number" name="assigned_quantity" x-model="assignedQuantity" min="1" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="1">
                        </div>
                        <div>
                            <label class="text-xs text-teal-600">เรทต่อชิ้น (บาท)</label>
                            <input type="number" step="0.01" name="assigned_fixed_rate" x-model="assignedFixedRate" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="0.00">
                        </div>
                    </div>
                    <input type="hidden" name="pricing_group" value="fixed">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-orange-600 text-white px-4 py-1.5 rounded text-sm">สร้างงานตัดต่อ</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่องาน</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resource</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้ตัดต่อ</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ราคา</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">เวลาที่ใช้จริง</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($editJobs as $ej)
                    @php
                        $ejSearchData = strtolower($ej->title . ' ' . ($ej->mediaResource?->footage_code ?? '') . ' ' . ($ej->editor?->first_name ?? ''));
                        $isOverdue = $ej->due_date && $ej->due_date->isPast() && !in_array($ej->status, ['approved','done']);
                        $isDueSoon = $ej->due_date && !$ej->due_date->isPast() && $ej->due_date->diffInDays(now()) <= 2 && !in_array($ej->status, ['approved','done']);
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors {{ $isOverdue ? 'bg-red-50/30' : '' }}"
                        x-show="!editSearch || '{{ addslashes($ejSearchData) }}'.includes(editSearch.toLowerCase())">
                        <td class="px-3 py-2.5 font-medium text-gray-900">{{ $ej->title }}</td>
                        <td class="px-3 py-2.5 text-gray-500 font-mono">{{ $ej->mediaResource?->footage_code ?? '-' }}</td>
                        <td class="px-3 py-2.5">{{ $ej->editor ? $ej->editor->first_name : '-' }}</td>
                        <td class="px-3 py-2.5">
                            @if($ej->pricing_group === 'template')
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-green-100 text-green-700 font-medium">{{ $ej->pricing_template_label ?? 'Template' }}</span>
                                <span class="text-[10px] text-gray-400 ml-1">{{ number_format($ej->assigned_rate, 2) }}/min</span>
                            @elseif($ej->pricing_group === 'isolated')
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-indigo-100 text-indigo-700 font-medium">Isolated</span>
                                <span class="text-[10px] text-gray-400 ml-1">{{ number_format($ej->assigned_rate, 2) }}/min</span>
                            @elseif($ej->pricing_group === 'fixed')
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-teal-100 text-teal-700 font-medium">Fixed</span>
                                <span class="text-[10px] text-gray-400 ml-1">{{ $ej->assigned_quantity }}×{{ number_format($ej->assigned_fixed_rate, 2) }}</span>
                            @else
                                <span class="text-[10px] text-gray-300">-</span>
                            @endif
                        </td>
                        {{-- Actual Duration column --}}
                        <td class="px-3 py-2.5">
                            @if($ej->output_duration_seconds)
                                <span class="inline-flex items-center gap-1 text-[11px] text-emerald-700 font-semibold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ \App\Support\DurationInput::formatSecondsAsHms($ej->output_duration_seconds) }}
                                </span>
                            @else
                                <span class="text-[10px] text-gray-300">-</span>
                            @endif
                        </td>
                        {{-- Due Date with urgency --}}
                        <td class="px-3 py-2.5">
                            @if($ej->due_date)
                                <div class="flex items-center gap-1.5">
                                    <span class="{{ $isOverdue ? 'text-red-600 font-bold' : ($isDueSoon ? 'text-amber-600 font-medium' : 'text-gray-600') }}">
                                        {{ $ej->due_date->format('d/m/Y') }}
                                    </span>
                                    @if($isOverdue)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-100 text-red-700">OVERDUE</span>
                                    @elseif($isDueSoon)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700">SOON</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5" x-data="{ showStatusEdit: false, status: '{{ $ej->status }}', currentStatus: '{{ $ej->status }}' }">
                            @php
                                $ejStage = $jobStages->where('type', 'edit')->where('code', $ej->status)->first();
                                $ejColor = $ejStage ? $ejStage->color : 'gray';
                            @endphp
                            {{-- Status Badge with popover --}}
                            <button type="button" @click="showStatusEdit = !showStatusEdit"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-{{ $ejColor }}-100 text-{{ $ejColor }}-700 hover:ring-2 hover:ring-{{ $ejColor }}-200 transition-all cursor-pointer">
                                {{ $ejStage?->name ?? $ej->status }}
                                <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            {{-- Status Popover --}}
                            <div x-show="showStatusEdit" x-cloak x-transition @click.away="showStatusEdit = false"
                                 class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg p-3 space-y-2 min-w-[200px] relative z-10">
                                <form method="POST" action="{{ route('work.edit-job.status', $ej) }}" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <select name="status" x-model="status" class="w-full border border-gray-200 rounded-md text-xs px-2 py-1.5 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200">
                                        @foreach($jobStages->where('type', 'edit') as $stg)
                                            @if($stg->is_active || $ej->status === $stg->code)
                                                <option value="{{ $stg->code }}" {{ $ej->status === $stg->code ? 'selected' : '' }}>{{ $stg->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <div x-show="status === 'done' && currentStatus !== 'done'" x-cloak class="space-y-1.5">
                                        <div>
                                            <label class="text-[10px] text-gray-500">Output Duration (HH:MM:SS)</label>
                                            <input type="time" step="1" name="output_duration_hms" class="w-full border border-gray-200 rounded-md px-2 py-1 text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200" :required="status === 'done' && currentStatus !== 'done'">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="text-xs text-white bg-indigo-600 rounded-md px-3 py-1.5 hover:bg-indigo-700 font-medium transition-colors">อัปเดต</button>
                                        <button type="button" @click="showStatusEdit = false; status = currentStatus" class="text-xs text-gray-400 hover:text-gray-600">ยกเลิก</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
                                {{ $ej->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $ej->priority === 'high' ? 'bg-orange-100 text-orange-700' : '' }}
                                {{ $ej->priority === 'normal' ? 'bg-gray-100 text-gray-600' : '' }}
                                {{ $ej->priority === 'low' ? 'bg-gray-50 text-gray-400' : '' }}
                            ">{{ $ej->priority }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <form method="POST" action="{{ route('work.edit-job.delete', $ej) }}" onsubmit="return confirm('ลบงานนี้?')" class="inline">
                                @csrf @method('DELETE')
                                <button class="p-1.5 rounded-md text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors" title="ลบ">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-3 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-2xl">✂️</div>
                                <p class="text-sm font-medium text-gray-500">ยังไม่มีงานตัดต่อ</p>
                                <p class="text-xs text-gray-400">กด "+ สร้างงานตัดต่อ" เพื่อเริ่มต้น</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function editJobFormState() {
    return {
        resourceData: @js($resourcesForAlpine),
        employeeData: @js($employeesForAlpine),
        selectedResourceId: '',
        selectedResourceTitle: '',
        selectedEmployeeId: '',
        employeePayrollMode: '',
        employeeLayerRates: [],
        pricingGroup: '',
        selectedTemplateLabel: '',
        assignedRate: '',
        assignedQuantity: '',
        assignedFixedRate: '',
        onResourceChange(event) {
            const resourceId = event?.target?.value;
            if (!resourceId || !this.resourceData[resourceId]) {
                return;
            }

            const selected = this.resourceData[resourceId];
            this.selectedResourceTitle = selected.edit_job_title || selected.title || selected.footage_code || '';
        },
        onEmployeeChange() {
            const emp = this.employeeData[this.selectedEmployeeId];
            this.employeePayrollMode = emp?.payroll_mode ?? '';
            this.employeeLayerRates = emp?.layer_rates ?? [];
            // Reset pricing fields
            this.pricingGroup = '';
            this.selectedTemplateLabel = '';
            this.assignedRate = '';
            this.assignedQuantity = '';
            this.assignedFixedRate = '';
        },
        onTemplateLabelChange() {
            const selected = this.employeeLayerRates.find(lr => lr.label === this.selectedTemplateLabel);
            this.assignedRate = selected ? selected.rate.toFixed(4) : '';
        },
    };
}
</script>
@endsection
