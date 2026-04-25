@php
    $isAdmin = auth()->user()?->hasRole('admin') ?? false;
    $statusBadge = function ($status) {
        return match($status) {
            'approved'  => ['bg' => 'bg-green-100 text-green-700', 'label' => 'APPROVED'],
            'rejected'  => ['bg' => 'bg-red-100 text-red-700',     'label' => 'REJECTED'],
            'cancelled' => ['bg' => 'bg-gray-100 text-gray-500',   'label' => 'CANCELLED'],
            default     => ['bg' => 'bg-amber-100 text-amber-700', 'label' => 'PENDING'],
        };
    };
@endphp

<div class="bg-white rounded-xl shadow-sm border overflow-hidden"
     x-data="{ activeTab: null, drawerOpen: false, drawerTab: 'ot' }">
    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-bold text-sm text-gray-800">ศูนย์คำขอ & บริการ</h3>
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    </div>

    <div class="p-4 space-y-3">
        {{-- Actions Buttons --}}
        <div class="grid grid-cols-3 gap-2">
            <button @click="activeTab = (activeTab === 'ot' ? null : 'ot')"
                    :class="activeTab === 'ot' ? 'bg-indigo-600 text-white shadow-indigo-100' : 'bg-white text-gray-700 hover:bg-indigo-50 border-gray-200'"
                    class="flex flex-col items-center justify-center py-3 px-1 rounded-xl border transition-all group shadow-sm">
                <svg class="w-5 h-5 mb-1" :class="activeTab === 'ot' ? 'text-white' : 'text-indigo-500 group-hover:scale-110 transition-transform'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[10px] font-bold uppercase">ขอ OT</span>
            </button>
            <button @click="activeTab = (activeTab === 'leave' ? null : 'leave')"
                    :class="activeTab === 'leave' ? 'bg-rose-600 text-white shadow-rose-100' : 'bg-white text-gray-700 hover:bg-rose-50 border-gray-200'"
                    class="flex flex-col items-center justify-center py-3 px-1 rounded-xl border transition-all group shadow-sm">
                <svg class="w-5 h-5 mb-1" :class="activeTab === 'leave' ? 'text-white' : 'text-rose-500 group-hover:scale-110 transition-transform'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span class="text-[10px] font-bold uppercase">ขอลา</span>
            </button>
            <button @click="activeTab = (activeTab === 'swap' ? null : 'swap')"
                    :class="activeTab === 'swap' ? 'bg-amber-600 text-white shadow-amber-100' : 'bg-white text-gray-700 hover:bg-amber-50 border-gray-200'"
                    class="flex flex-col items-center justify-center py-3 px-1 rounded-xl border transition-all group shadow-sm">
                <svg class="w-5 h-5 mb-1" :class="activeTab === 'swap' ? 'text-white' : 'text-amber-500 group-hover:scale-110 transition-transform'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span class="text-[10px] font-bold uppercase">สลับวัน</span>
            </button>
        </div>

        {{-- OT Form --}}
        <div x-show="activeTab === 'ot'" x-cloak x-transition class="p-3 bg-indigo-50 rounded-xl border border-indigo-100 space-y-3">
            <h4 class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider">ส่งคำขอล่วงเวลา (OT)</h4>
            <form action="{{ route('ot.request.store') }}" method="POST" class="space-y-2">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[9px] font-bold text-gray-400 uppercase">วันที่ขอ</label>
                        <input type="date" name="log_date" required value="{{ date('Y-m-d') }}"
                               class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-gray-400 uppercase">เวลา (นาที)</label>
                        <input type="number" name="requested_minutes" required placeholder="60"
                               class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-bold text-gray-400 uppercase">เหตุผล / งานที่ทำ</label>
                    <textarea name="reason" rows="2" required placeholder="ระบุงานที่ทำในช่วง OT"
                              class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs resize-none focus:ring-indigo-500"></textarea>
                </div>
                <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-100">
                    ส่งคำขอ OT
                </button>
            </form>
        </div>

        {{-- Leave Form --}}
        <div x-show="activeTab === 'leave'" x-cloak x-transition class="p-3 bg-rose-50 rounded-xl border border-rose-100 space-y-3">
            <h4 class="text-[11px] font-bold text-rose-700 uppercase tracking-wider">ส่งคำขอลา</h4>
            <form action="{{ route('leave.store') }}" method="POST" class="space-y-2">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[9px] font-bold text-gray-400 uppercase">วันที่ลา</label>
                        <input type="date" name="leave_date" required value="{{ date('Y-m-d') }}"
                               class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-rose-500">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-gray-400 uppercase">ประเภท</label>
                        <select name="leave_type" required class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-rose-500">
                            @foreach($leaveTypes as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-bold text-gray-400 uppercase">เหตุผล</label>
                    <textarea name="reason" rows="2" placeholder="ระบุเหตุผลการลา"
                              class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs resize-none focus:ring-rose-500"></textarea>
                </div>
                <button type="submit" class="w-full py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors shadow-sm shadow-rose-100">
                    ส่งคำขอลา
                </button>
            </form>
        </div>

        {{-- Swap Form --}}
        <div x-show="activeTab === 'swap'" x-cloak x-transition class="p-3 bg-amber-50 rounded-xl border border-amber-100 space-y-3">
            <h4 class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">ส่งคำขอสลับวันหยุด</h4>
            <form action="{{ route('leave.swap.store') }}" method="POST" class="space-y-2">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[9px] font-bold text-gray-400 uppercase">มาทำงานวันที่ (วันหยุด)</label>
                        <input type="date" name="work_date" required
                               class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-gray-400 uppercase">หยุดแทนวันที่ (วันทำงาน)</label>
                        <input type="date" name="off_date" required
                               class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-amber-500">
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-bold text-gray-400 uppercase">เหตุผล</label>
                    <textarea name="reason" rows="2" placeholder="ระบุเหตุผลในการสลับวัน"
                              class="w-full bg-white border-gray-200 rounded-lg px-2 py-1.5 text-xs resize-none focus:ring-amber-500"></textarea>
                </div>
                <button type="submit" class="w-full py-2 bg-amber-600 text-white rounded-lg text-xs font-bold hover:bg-amber-700 transition-colors shadow-sm shadow-amber-100">
                    ส่งคำขอสลับวัน
                </button>
            </form>
        </div>

        {{-- Status Tracking --}}
        <div class="pt-2 border-t border-gray-50">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">สถานะล่าสุด</span>
                <button type="button" @click="drawerOpen = true; drawerTab = 'ot'"
                        class="text-[9px] text-indigo-600 hover:underline">ดูทั้งหมด</button>
            </div>

            <div class="space-y-1.5">
                @php
                    $recent = collect()
                        ->concat($recentOtRequests->map(fn($r) => ['kind' => 'ot', 'item' => $r, 'sort' => $r->created_at]))
                        ->concat($recentLeaveRequests->map(fn($r) => ['kind' => 'leave', 'item' => $r, 'sort' => $r->created_at]))
                        ->concat(($recentSwapRequests ?? collect())->map(fn($r) => ['kind' => 'swap', 'item' => $r, 'sort' => $r->created_at]))
                        ->sortByDesc('sort')
                        ->take(6);
                @endphp

                @foreach($recent as $row)
                    @php $r = $row['item']; $b = $statusBadge($r->status); @endphp
                    @if($row['kind'] === 'ot')
                        <div class="flex items-center justify-between text-[11px] p-2 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="p-1 bg-indigo-100 text-indigo-600 rounded">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </span>
                                <div>
                                    <div class="font-bold text-gray-700">OT: {{ $r->log_date->format('d/m/y') }}</div>
                                    <div class="text-[9px] text-gray-400">{{ $r->requested_minutes }} นาที</div>
                                </div>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $b['bg'] }}">{{ $b['label'] }}</span>
                        </div>
                    @elseif($row['kind'] === 'leave')
                        <div class="flex items-center justify-between text-[11px] p-2 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="p-1 bg-rose-100 text-rose-600 rounded">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </span>
                                <div>
                                    <div class="font-bold text-gray-700">ลา: {{ $r->leave_date->format('d/m/y') }}</div>
                                    <div class="text-[9px] text-gray-400">{{ $leaveTypes[$r->leave_type] ?? $r->leave_type }}</div>
                                </div>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $b['bg'] }}">{{ $b['label'] }}</span>
                        </div>
                    @else
                        <div class="flex items-center justify-between text-[11px] p-2 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="p-1 bg-amber-100 text-amber-600 rounded">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                </span>
                                <div>
                                    <div class="font-bold text-gray-700">สลับ: {{ $r->work_date->format('d/m/y') }} ↔ {{ $r->off_date->format('d/m/y') }}</div>
                                    <div class="text-[9px] text-gray-400">มาทำงานวันหยุด แลกหยุดวันทำงาน</div>
                                </div>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $b['bg'] }}">{{ $b['label'] }}</span>
                        </div>
                    @endif
                @endforeach

                @if($recent->isEmpty())
                <div class="text-center py-4 text-gray-400 text-[10px]">
                    ไม่มีคำขอที่รอดำเนินการ
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Drawer: full request history --}}
    <template x-teleport="body">
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-[100]">
            <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"
                 x-show="drawerOpen"
                 x-transition.opacity
                 @click="drawerOpen = false"></div>

            <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col"
                 x-show="drawerOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full">

                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-base text-gray-800">คำขอทั้งหมด</h2>
                        <p class="text-[11px] text-gray-500">{{ $employee->display_name }}</p>
                    </div>
                    <button @click="drawerOpen = false" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-5 pt-3 border-b border-gray-100 flex gap-1">
                    <button @click="drawerTab = 'ot'"
                            :class="drawerTab === 'ot' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">OT ({{ $recentOtRequests->count() }})</button>
                    <button @click="drawerTab = 'leave'"
                            :class="drawerTab === 'leave' ? 'border-rose-500 text-rose-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">ลา ({{ $recentLeaveRequests->count() }})</button>
                    <button @click="drawerTab = 'swap'"
                            :class="drawerTab === 'swap' ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">สลับวัน ({{ ($recentSwapRequests ?? collect())->count() }})</button>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-2">
                    {{-- OT list --}}
                    <div x-show="drawerTab === 'ot'" class="space-y-2">
                        @forelse($recentOtRequests as $r)
                            @php $b = $statusBadge($r->status); @endphp
                            <div class="border border-gray-100 rounded-xl p-3 bg-white">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-bold text-sm text-gray-800">{{ $r->log_date->format('d/m/Y') }} · {{ $r->requested_minutes }} นาที</div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $b['bg'] }}">{{ $b['label'] }}</span>
                                </div>
                                @if($r->reason)<p class="text-xs text-gray-600">{{ $r->reason }}</p>@endif
                                @if($r->review_note)<p class="text-[11px] text-gray-400 mt-1">หมายเหตุ: {{ $r->review_note }}</p>@endif
                                <div class="text-[10px] text-gray-400 mt-1">ส่งเมื่อ {{ $r->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        @empty
                            <div class="text-center text-xs text-gray-400 py-8">ยังไม่มีคำขอ OT</div>
                        @endforelse
                    </div>

                    {{-- Leave list --}}
                    <div x-show="drawerTab === 'leave'" x-cloak class="space-y-2">
                        @forelse($recentLeaveRequests as $r)
                            @php $b = $statusBadge($r->status); @endphp
                            <div class="border border-gray-100 rounded-xl p-3 bg-white">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-bold text-sm text-gray-800">{{ $r->leave_date->format('d/m/Y') }} · {{ $leaveTypes[$r->leave_type] ?? $r->leave_type }}</div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $b['bg'] }}">{{ $b['label'] }}</span>
                                </div>
                                @if($r->reason)<p class="text-xs text-gray-600">{{ $r->reason }}</p>@endif
                                @if($r->review_note)<p class="text-[11px] text-gray-400 mt-1">หมายเหตุ: {{ $r->review_note }}</p>@endif
                                <div class="text-[10px] text-gray-400 mt-1">ส่งเมื่อ {{ $r->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        @empty
                            <div class="text-center text-xs text-gray-400 py-8">ยังไม่มีคำขอลา</div>
                        @endforelse
                    </div>

                    {{-- Swap list --}}
                    <div x-show="drawerTab === 'swap'" x-cloak class="space-y-2">
                        @forelse(($recentSwapRequests ?? collect()) as $r)
                            @php $b = $statusBadge($r->status); @endphp
                            <div class="border border-gray-100 rounded-xl p-3 bg-white">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-bold text-sm text-gray-800">มาทำงาน {{ $r->work_date->format('d/m/Y') }} ↔ หยุด {{ $r->off_date->format('d/m/Y') }}</div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $b['bg'] }}">{{ $b['label'] }}</span>
                                </div>
                                @if($r->reason)<p class="text-xs text-gray-600">{{ $r->reason }}</p>@endif
                                @if($r->review_note)<p class="text-[11px] text-gray-400 mt-1">หมายเหตุ: {{ $r->review_note }}</p>@endif
                                <div class="text-[10px] text-gray-400 mt-1">ส่งเมื่อ {{ $r->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        @empty
                            <div class="text-center text-xs text-gray-400 py-8">ยังไม่มีคำขอสลับวัน</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
