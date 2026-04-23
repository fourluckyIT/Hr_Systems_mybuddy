@php
    $isAdmin = auth()->user()?->hasRole('admin') ?? false;
@endphp

<div class="bg-white rounded-xl shadow-sm border overflow-hidden" x-data="{ activeTab: null }">
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
            <button @click="modalSwapOpen = true"
                    class="flex flex-col items-center justify-center py-3 px-1 bg-white text-gray-700 hover:bg-amber-50 border border-gray-200 rounded-xl transition-all group shadow-sm">
                <svg class="w-5 h-5 mb-1 text-amber-500 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
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

        {{-- Status Tracking --}}
        <div class="pt-2 border-t border-gray-50">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">สถานะล่าสุด</span>
                <a href="{{ route('leave.index') }}" class="text-[9px] text-indigo-600 hover:underline">ดูทั้งหมด</a>
            </div>

            <div class="space-y-1.5">
                @foreach($recentOtRequests as $ot)
                <div class="flex items-center justify-between text-[11px] p-2 bg-gray-50 rounded-lg border border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="p-1 bg-indigo-100 text-indigo-600 rounded">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div>
                            <div class="font-bold text-gray-700">OT: {{ $ot->log_date->format('d/m/y') }}</div>
                            <div class="text-[9px] text-gray-400">{{ $ot->requested_minutes }} นาที</div>
                        </div>
                    </div>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase
                        @if($ot->status === 'approved') bg-green-100 text-green-700
                        @elseif($ot->status === 'rejected') bg-red-100 text-red-700
                        @else bg-amber-100 text-amber-700
                        @endif">
                        {{ $ot->status }}
                    </span>
                </div>
                @endforeach

                @foreach($recentLeaveRequests as $lv)
                <div class="flex items-center justify-between text-[11px] p-2 bg-gray-50 rounded-lg border border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="p-1 bg-rose-100 text-rose-600 rounded">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <div class="font-bold text-gray-700">ลา: {{ $lv->leave_date->format('d/m/y') }}</div>
                            <div class="text-[9px] text-gray-400">{{ $leaveTypes[$lv->leave_type] ?? $lv->leave_type }}</div>
                        </div>
                    </div>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase
                        @if($lv->status === 'approved') bg-green-100 text-green-700
                        @elseif($lv->status === 'rejected') bg-red-100 text-red-700
                        @else bg-amber-100 text-amber-700
                        @endif">
                        {{ $lv->status }}
                    </span>
                </div>
                @endforeach

                @if($recentOtRequests->isEmpty() && $recentLeaveRequests->isEmpty())
                <div class="text-center py-4 text-gray-400 text-[10px]">
                    ไม่มีคำขอที่รอดำเนินการ
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
