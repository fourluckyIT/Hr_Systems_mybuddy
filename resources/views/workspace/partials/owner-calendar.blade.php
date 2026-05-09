@php
    $cal = $ownerCalendar ?? [];
    $miniCalendarDays = $cal['miniCalendarDays'] ?? [];
    $upcomingEvents   = $cal['upcomingEvents']   ?? collect();
    $calendarDate     = $cal['calendarDate']     ?? now();
    $stats            = $cal['personalStats']    ?? null;

    $dayTypeBgLocal = [
        'sick_leave'     => 'bg-blue-200',
        'personal_leave' => 'bg-yellow-200',
        'vacation_leave' => 'bg-teal-200',
        'lwop'           => 'bg-red-200',
        'ot_full_day'    => 'bg-indigo-200',
    ];
    $dotColors = [
        'holiday'   => 'bg-purple-400',
        'leave'     => 'bg-blue-400',
        'ot'        => 'bg-indigo-400',
        'edit'      => 'bg-sky-400',
        'recording' => 'bg-amber-400',
    ];
    $upcomingDotColors = [
        'purple' => 'bg-purple-400', 'blue' => 'bg-blue-400',
        'indigo' => 'bg-indigo-400', 'sky' => 'bg-sky-400', 'amber' => 'bg-amber-400',
    ];
    $upcomingTagColors = [
        'purple' => 'bg-purple-100 text-purple-700', 'blue' => 'bg-blue-100 text-blue-700',
        'indigo' => 'bg-indigo-100 text-indigo-700', 'sky' => 'bg-sky-100 text-sky-700',
        'amber' => 'bg-amber-100 text-amber-700',
    ];

    $todayStr = now()->format('Y-m-d');

    // Build per-day metadata for click-to-request
    $miniDaysMeta = [];
    foreach ($miniCalendarDays as $md) {
        $isPast = $md['date_str'] < $todayStr;
        $isHoliday = in_array('holiday', $md['dots'] ?? [], true);
        $isWeekend = $md['is_weekend'];
        $miniDaysMeta[$md['date_str']] = [
            'isPast'    => $isPast,
            'isHoliday' => $isHoliday || $isWeekend,
            'isToday'   => $md['is_today'],
        ];
    }

    $vacPct = ($stats && ($stats['vacation']['limit'] ?? 0) > 0)
        ? round(($stats['vacation']['used'] / max(1, $stats['vacation']['limit'])) * 100)
        : 0;
    $worksPct = ($stats && ($stats['days_worked']['target'] ?? 0) > 0)
        ? round(($stats['days_worked']['count'] / max(1, $stats['days_worked']['target'])) * 100)
        : 0;
@endphp

<div class="bg-white rounded-xl shadow-sm border overflow-hidden"
     x-data="{
        selectedDay: null,
        selectedEvents: [],
        selectedMeta: { isPast: false, isHoliday: false, isToday: false },
        actionForm: null,
        upcomingOpen: false,
        daysMeta: {{ Js::from($miniDaysMeta) }},
        pickDay(dateStr, events) {
            this.selectedDay = dateStr;
            this.selectedEvents = events;
            this.selectedMeta = this.daysMeta[dateStr] || { isPast: false, isHoliday: false, isToday: false };
            this.actionForm = null;
        },
        formatThaiDate(s) {
            if (!s) return '';
            const [y, m, d] = s.split('-');
            const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            return `${parseInt(d)} ${months[parseInt(m)-1]} ${parseInt(y)+543}`;
        }
     }">

    {{-- Header --}}
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-sm text-gray-800">ปฏิทินของฉัน</h3>
                <p class="text-[10px] text-gray-400 font-medium">{{ $calendarDate->translatedFormat('F Y') }} — คลิกที่วันเพื่อสั่งงาน</p>
            </div>
        </div>
        <a href="{{ route('calendar.index') }}" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold uppercase tracking-wider flex items-center gap-1">
            ดูปฏิทินบริษัท
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </a>
    </div>

    <div class="flex flex-col lg:flex-row">
        {{-- Calendar Grid --}}
        <div class="flex-1 p-4">
            {{-- Day-of-week headers --}}
            <div class="grid grid-cols-7 mb-2">
                @foreach(['อา','จ','อ','พ','พฤ','ศ','ส'] as $idx => $wl)
                <div class="text-center text-[10px] font-bold {{ in_array($idx, [0, 6]) ? 'text-red-400' : 'text-gray-400' }} uppercase tracking-wider py-1">{{ $wl }}</div>
                @endforeach
            </div>

            {{-- Day cells --}}
            <div class="grid grid-cols-7 gap-px bg-gray-100 rounded-xl overflow-hidden border border-gray-100">
                @foreach($miniCalendarDays as $md)
                @php
                    $hasEvents = !empty($md['events']);
                    $isToday = $md['is_today'];
                    $isCurrentMonth = $md['is_current_month'];
                    $isWeekend = $md['is_weekend'];
                    $attBg = isset($md['att_status']) ? ($dayTypeBgLocal[$md['att_status']] ?? '') : '';
                    $isClickable = $isCurrentMonth;
                @endphp
                <button type="button"
                    @if($isClickable)
                    @click="pickDay('{{ $md['date_str'] }}', {{ Js::from($md['events']) }})"
                    @endif
                    class="relative flex flex-col items-center py-2 px-1 min-h-[52px] transition-all
                        {{ $isCurrentMonth ? 'bg-white' : 'bg-gray-50' }}
                        {{ $isClickable ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default' }}
                        {{ $isToday ? 'ring-2 ring-inset ring-indigo-400' : '' }}"
                    :class="selectedDay === '{{ $md['date_str'] }}' ? 'bg-indigo-50' : ''">

                    <span class="flex items-center justify-center w-7 h-7 rounded-full text-[12px] font-semibold leading-none transition-colors
                        {{ $isToday ? 'bg-indigo-600 text-white' : '' }}
                        {{ !$isToday && $attBg ? $attBg . ' font-bold' : '' }}
                        {{ !$isCurrentMonth ? 'text-gray-300' : (!$isToday && !$attBg ? ($isWeekend ? 'text-red-400' : 'text-gray-700') : '') }}">
                        {{ $md['date']->format('j') }}
                    </span>

                    @if(!empty($md['dots']) && $isCurrentMonth)
                    <div class="flex items-center gap-0.5 mt-1">
                        @foreach(array_slice($md['dots'], 0, 4) as $dot)
                        <span class="w-1.5 h-1.5 rounded-full flex-none {{ $dotColors[$dot] ?? 'bg-gray-400' }}"></span>
                        @endforeach
                    </div>
                    @endif

                    @if(in_array('holiday', $md['dots'] ?? []) && $isCurrentMonth)
                    <div class="absolute top-0.5 right-0.5 w-1.5 h-1.5 bg-purple-500 rounded-full"></div>
                    @endif
                </button>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-3 mt-3 px-1">
                @foreach([
                    'bg-purple-400' => 'วันหยุด',
                    'bg-blue-400' => 'ลา',
                    'bg-indigo-400' => 'OT',
                    'bg-sky-400' => 'งานตัดต่อ',
                    'bg-amber-400' => 'ถ่ายทำ',
                ] as $color => $label)
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full {{ $color }}"></span>
                    <span class="text-[9px] text-gray-400 font-medium">{{ $label }}</span>
                </div>
                @endforeach
            </div>

            {{-- Selected day panel — events + click-to-request --}}
            <div x-show="selectedDay" x-cloak x-transition
                 class="mt-3 p-3 bg-indigo-50/50 rounded-xl border border-indigo-100">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[12px] font-bold text-indigo-700 flex items-center gap-2">
                        <span x-text="formatThaiDate(selectedDay)"></span>
                        <template x-if="selectedMeta.isToday">
                            <span class="px-1.5 py-0.5 text-[9px] bg-indigo-600 text-white rounded uppercase tracking-wider">วันนี้</span>
                        </template>
                    </h4>
                    <button @click="selectedDay = null; actionForm = null" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Existing events --}}
                <template x-if="selectedEvents.length > 0">
                    <div class="space-y-1.5 mb-3">
                        <template x-for="ev in selectedEvents" :key="ev.label">
                            <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border text-[11px] font-medium"
                                 :class="{
                                    'bg-purple-50 border-purple-200 text-purple-700': ev.color === 'purple',
                                    'bg-blue-50 border-blue-200 text-blue-700': ev.color === 'blue',
                                    'bg-indigo-50 border-indigo-200 text-indigo-700': ev.color === 'indigo',
                                    'bg-sky-50 border-sky-200 text-sky-700': ev.color === 'sky',
                                    'bg-amber-50 border-amber-200 text-amber-700': ev.color === 'amber',
                                 }">
                                <span x-text="ev.label"></span>
                                <template x-if="ev.status">
                                    <span class="ml-auto px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                                          :class="ev.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                          x-text="ev.status"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Action buttons (today / future only) --}}
                <template x-if="!selectedMeta.isPast && actionForm === null">
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1.5">⚡ สั่งงานวันนี้</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-if="!selectedMeta.isHoliday">
                                <button @click="actionForm = 'leave'" class="px-3 py-1.5 bg-rose-600 text-white rounded-lg text-[11px] font-bold hover:bg-rose-700 transition-colors flex items-center gap-1">
                                    📅 ขอลา
                                </button>
                            </template>
                            <template x-if="!selectedMeta.isHoliday">
                                <button @click="actionForm = 'ot'" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-[11px] font-bold hover:bg-indigo-700 transition-colors flex items-center gap-1">
                                    ⏰ ขอ OT
                                </button>
                            </template>
                            <template x-if="selectedMeta.isHoliday">
                                <button @click="actionForm = 'swap'" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-[11px] font-bold hover:bg-amber-700 transition-colors flex items-center gap-1">
                                    🔄 ขอสลับวันหยุด
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Inline form: Leave --}}
                <form x-show="actionForm === 'leave'" x-cloak action="{{ route('leave.store') }}" method="POST" class="mt-2 space-y-2 p-2.5 bg-white rounded-lg border border-rose-100">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <input type="hidden" name="leave_date" :value="selectedDay">
                    <div class="text-[10px] font-bold text-rose-600 uppercase">ขอลาวันที่ <span x-text="formatThaiDate(selectedDay)"></span></div>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="leave_type" required class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs bg-white">
                            <option value="sick_leave">ลาป่วย</option>
                            <option value="personal_leave">ลากิจ</option>
                            <option value="vacation_leave">ลาพักร้อน</option>
                            <option value="lwop">ลาไม่รับค่าจ้าง</option>
                        </select>
                        <input type="text" name="reason" placeholder="เหตุผล (ไม่บังคับ)" maxlength="500" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="actionForm = null" class="px-3 py-1 text-[11px] text-gray-500 hover:bg-gray-100 rounded">ยกเลิก</button>
                        <button type="submit" class="px-4 py-1.5 bg-rose-600 text-white rounded-lg text-[11px] font-bold hover:bg-rose-700">ส่งคำขอลา</button>
                    </div>
                </form>

                {{-- Inline form: OT --}}
                <form x-show="actionForm === 'ot'" x-cloak action="{{ route('ot.request.store') }}" method="POST" class="mt-2 space-y-2 p-2.5 bg-white rounded-lg border border-indigo-100">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <input type="hidden" name="log_date" :value="selectedDay">
                    <div class="text-[10px] font-bold text-indigo-600 uppercase">ขอ OT วันที่ <span x-text="formatThaiDate(selectedDay)"></span></div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" name="requested_minutes" required min="15" placeholder="นาที (เช่น 60)" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                        <input type="text" name="reason" required placeholder="งานที่ทำ" maxlength="500" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="actionForm = null" class="px-3 py-1 text-[11px] text-gray-500 hover:bg-gray-100 rounded">ยกเลิก</button>
                        <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-[11px] font-bold hover:bg-indigo-700">ส่งคำขอ OT</button>
                    </div>
                </form>

                {{-- Inline form: Swap --}}
                <form x-show="actionForm === 'swap'" x-cloak action="{{ route('leave.swap.store') }}" method="POST" class="mt-2 space-y-2 p-2.5 bg-white rounded-lg border border-amber-100">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <input type="hidden" name="work_date" :value="selectedDay">
                    <div class="text-[10px] font-bold text-amber-600 uppercase">มาทำงานวันที่ <span x-text="formatThaiDate(selectedDay)"></span> (วันหยุด)</div>
                    <div>
                        <label class="block text-[10px] text-gray-500 mb-1">หยุดแทนวันที่ (วันทำงาน)</label>
                        <input type="date" name="off_date" required class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    <input type="text" name="reason" placeholder="เหตุผล (ไม่บังคับ)" maxlength="500" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="actionForm = null" class="px-3 py-1 text-[11px] text-gray-500 hover:bg-gray-100 rounded">ยกเลิก</button>
                        <button type="submit" class="px-4 py-1.5 bg-amber-600 text-white rounded-lg text-[11px] font-bold hover:bg-amber-700">ส่งคำขอสลับวัน</button>
                    </div>
                </form>

                {{-- Past date hint --}}
                <template x-if="selectedMeta.isPast && selectedEvents.length === 0">
                    <p class="text-[11px] text-gray-400 italic">ไม่มีรายการในวันนี้</p>
                </template>
            </div>
        </div>

        {{-- Personal Stats Sidebar --}}
        @if($stats)
        <div class="w-full lg:w-64 border-t lg:border-t-0 lg:border-l border-gray-100 bg-gradient-to-b from-gray-50/30 to-white">
            <div class="px-4 py-3 border-b border-gray-100">
                <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">📊 สรุปของฉัน</h4>
                <p class="text-[9px] text-gray-400 mt-0.5">{{ $calendarDate->translatedFormat('F Y') }}</p>
            </div>

            <div class="p-3 space-y-2.5">
                {{-- Vacation balance --}}
                <a href="{{ route('leave.index') }}" class="block group">
                    <div class="p-3 rounded-xl bg-teal-50/70 border border-teal-100 hover:border-teal-300 hover:bg-teal-50 transition-all">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-bold text-teal-700 uppercase">🏖️ ลาพักร้อน</span>
                            <span class="text-[9px] text-teal-500 font-bold">{{ $stats['vacation']['remaining'] }} เหลือ</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-teal-700">{{ $stats['vacation']['used'] }}</span>
                            <span class="text-xs text-teal-500 font-bold">/ {{ $stats['vacation']['limit'] }} วัน</span>
                        </div>
                        <div class="mt-1.5 h-1 bg-teal-100 rounded-full overflow-hidden">
                            <div class="h-full bg-teal-500 transition-all" style="width: {{ $vacPct }}%"></div>
                        </div>
                    </div>
                </a>

                {{-- OT this month --}}
                <a href="{{ route('ot.request') }}" class="block group">
                    <div class="p-3 rounded-xl bg-indigo-50/70 border border-indigo-100 hover:border-indigo-300 hover:bg-indigo-50 transition-all">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-bold text-indigo-700 uppercase">⏰ OT เดือนนี้</span>
                            <span class="text-[9px] text-indigo-500 font-bold">{{ $stats['ot']['count'] }} ครั้ง</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-indigo-700">{{ $stats['ot']['hours'] }}</span>
                            <span class="text-xs text-indigo-500 font-bold">ชั่วโมง</span>
                        </div>
                    </div>
                </a>

                {{-- Days worked --}}
                <div class="p-3 rounded-xl bg-emerald-50/70 border border-emerald-100">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-bold text-emerald-700 uppercase">📅 มาทำงาน</span>
                        <span class="text-[9px] text-emerald-500 font-bold">{{ $worksPct }}%</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-xl font-extrabold text-emerald-700">{{ $stats['days_worked']['count'] }}</span>
                        <span class="text-xs text-emerald-500 font-bold">/ {{ $stats['days_worked']['target'] }} วัน</span>
                    </div>
                    <div class="mt-1.5 h-1 bg-emerald-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 transition-all" style="width: {{ min(100, $worksPct) }}%"></div>
                    </div>
                </div>

                {{-- Payday countdown --}}
                <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-100">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-bold text-amber-700 uppercase">💰 เงินเดือน</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        @if($stats['payday']['days'] === 0)
                            <span class="text-xl font-extrabold text-amber-700">วันนี้!</span>
                        @else
                            <span class="text-xl font-extrabold text-amber-700">อีก {{ $stats['payday']['days'] }}</span>
                            <span class="text-xs text-amber-500 font-bold">วัน</span>
                        @endif
                    </div>
                    <div class="text-[9px] text-amber-500 mt-0.5">{{ \Carbon\Carbon::parse($stats['payday']['date'])->translatedFormat('D j M') }}</div>
                </div>

                {{-- Pending requests --}}
                @if($stats['pending']['total'] > 0)
                <a href="{{ route('leave.index') }}" class="block group">
                    <div class="p-3 rounded-xl bg-rose-50/70 border border-rose-100 hover:border-rose-300 hover:bg-rose-50 transition-all">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-bold text-rose-700 uppercase">⏳ คำขอที่รอ</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-rose-700">{{ $stats['pending']['total'] }}</span>
                            <span class="text-xs text-rose-500 font-bold">รายการ</span>
                        </div>
                        <div class="text-[9px] text-rose-500 mt-1 flex flex-wrap gap-1">
                            @if($stats['pending']['leave'] > 0)<span>ลา {{ $stats['pending']['leave'] }}</span>@endif
                            @if($stats['pending']['ot'] > 0)<span>• OT {{ $stats['pending']['ot'] }}</span>@endif
                            @if($stats['pending']['swap'] > 0)<span>• สลับ {{ $stats['pending']['swap'] }}</span>@endif
                        </div>
                    </div>
                </a>
                @else
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="text-[10px] font-bold text-gray-500 uppercase">⏳ คำขอที่รอ</div>
                    <div class="text-xs text-gray-400 mt-1">ไม่มีรายการรออนุมัติ</div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Upcoming events (collapsible) --}}
    @if($upcomingEvents->isNotEmpty())
    <div class="border-t border-gray-100 bg-gray-50/30">
        <button type="button" @click="upcomingOpen = !upcomingOpen"
                class="w-full px-4 py-2.5 flex items-center justify-between hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">🗓 กำลังจะมาถึง</span>
                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-[10px] font-bold">{{ $upcomingEvents->count() }}</span>
            </div>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="upcomingOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="upcomingOpen" x-cloak x-collapse class="border-t border-gray-100">
            <div class="divide-y divide-gray-50 max-h-[280px] overflow-y-auto">
                @foreach($upcomingEvents as $ue)
                @php
                    $isToday = $ue['date']->isToday();
                    $isTomorrow = $ue['date']->isTomorrow();
                    $daysAway = (int) now()->startOfDay()->diffInDays($ue['date']->startOfDay(), false);
                    if ($isToday) $dayLabel = 'วันนี้';
                    elseif ($isTomorrow) $dayLabel = 'พรุ่งนี้';
                    else $dayLabel = 'อีก ' . $daysAway . ' วัน';
                @endphp
                <div class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-white transition-colors">
                    <span class="w-2 h-2 rounded-full mt-1.5 flex-none {{ $upcomingDotColors[$ue['color']] ?? 'bg-gray-400' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] font-semibold text-gray-800 truncate leading-snug">
                            {{ $ue['icon'] }} {{ $ue['label'] }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full {{ $upcomingTagColors[$ue['color']] ?? 'bg-gray-100 text-gray-600' }} font-medium">{{ $ue['sub'] }}</span>
                            <span class="text-[9px] text-gray-400">{{ $dayLabel }}</span>
                        </div>
                        <div class="text-[9px] text-gray-400 mt-px">{{ $ue['date']->translatedFormat('D j M') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
