@php
    $isAdminLB = auth()->user()?->hasRole('admin') ?? false;
    $balances = $allLeaveBalances ?? [];
    $carryovers = $leaveCarryovers ?? collect();
    $encashments = $leaveEncashments ?? collect();
    $thaiYear = $year + 543;
    $colorMap = [
        'vacation_leave' => ['bg' => 'bg-teal-50', 'border' => 'border-teal-200', 'text' => 'text-teal-700', 'accent' => 'bg-teal-500', 'icon' => '🏖️'],
        'sick_leave'     => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-700', 'accent' => 'bg-blue-500', 'icon' => '🤒'],
        'personal_leave' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'accent' => 'bg-amber-500', 'icon' => '👤'],
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border overflow-hidden"
     x-data="{
        carryoverModal: false,
        encashModal: false,
        selectedType: 'vacation_leave',
        selectedTypeLabel: 'ลาพักร้อน'
     }">

    {{-- Header --}}
    <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-teal-50 to-white flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-teal-100 rounded-lg flex items-center justify-center text-base">🏖️</div>
            <div>
                <h3 class="font-bold text-sm text-gray-800">สิทธิวันลา ({{ $thaiYear }})</h3>
                <p class="text-[10px] text-gray-400 font-medium">ภาพรวมการใช้สิทธิและยกยอด</p>
            </div>
        </div>
        @if($isAdminLB)
        <div class="flex items-center gap-1.5">
            <button @click="carryoverModal = true; selectedType = 'vacation_leave'; selectedTypeLabel = 'ลาพักร้อน'"
                    class="px-2.5 py-1 bg-indigo-600 text-white rounded-lg text-[10px] font-bold hover:bg-indigo-700 transition-colors">
                + ยกยอดข้ามปี
            </button>
            <button @click="encashModal = true; selectedType = 'vacation_leave'; selectedTypeLabel = 'ลาพักร้อน'"
                    class="px-2.5 py-1 bg-emerald-600 text-white rounded-lg text-[10px] font-bold hover:bg-emerald-700 transition-colors">
                + แลกเป็นเงิน
            </button>
        </div>
        @endif
    </div>

    {{-- Balance Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-4">
        @foreach($balances as $type => $b)
        @php $c = $colorMap[$type] ?? $colorMap['vacation_leave']; @endphp
        <div class="p-3 rounded-xl {{ $c['bg'] }} border {{ $c['border'] }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold {{ $c['text'] }} uppercase">{{ $c['icon'] }} {{ $b['label'] }}</span>
                <span class="text-[10px] {{ $c['text'] }} font-bold opacity-70">เหลือ {{ rtrim(rtrim(number_format($b['remaining'], 1), '0'), '.') }}</span>
            </div>
            <div class="flex items-baseline gap-1 mb-1">
                <span class="text-2xl font-extrabold {{ $c['text'] }}">{{ rtrim(rtrim(number_format($b['used'], 1), '0'), '.') }}</span>
                <span class="text-xs {{ $c['text'] }} opacity-70">/ {{ rtrim(rtrim(number_format($b['total_available'], 1), '0'), '.') }} วัน</span>
            </div>
            <div class="h-1 bg-white/60 rounded-full overflow-hidden">
                <div class="h-full {{ $c['accent'] }} transition-all"
                     style="width: {{ $b['total_available'] > 0 ? min(100, round((($b['used'] + $b['encashed']) / $b['total_available']) * 100)) : 0 }}%"></div>
            </div>
            @php
                $showCarryover  = (bool) ($b['allow_carryover']  ?? false);
                $showEncashment = (bool) ($b['allow_encashment'] ?? false);
                $carryoverOut   = (float) ($b['carryover_out'] ?? 0);
                $showOut        = $showCarryover && $carryoverOut > 0;
                $colCount = 1 + (int) $showCarryover + (int) $showEncashment + (int) $showOut;
                $gridClass = ['1' => 'grid-cols-1', '2' => 'grid-cols-2', '3' => 'grid-cols-3', '4' => 'grid-cols-4'][$colCount] ?? 'grid-cols-1';
            @endphp
            <div class="grid {{ $gridClass }} gap-1 mt-2 text-[9px] {{ $c['text'] }} opacity-80">
                <div>
                    <div class="font-bold uppercase">เริ่มต้น</div>
                    <div>{{ rtrim(rtrim(number_format($b['limit'], 1), '0'), '.') }}</div>
                </div>
                @if($showCarryover)
                <div>
                    <div class="font-bold uppercase" title="ยกยอดมาจากปีก่อน">+ยกเข้า</div>
                    <div>+{{ rtrim(rtrim(number_format($b['carryover'], 1), '0'), '.') }}</div>
                </div>
                @endif
                @if($showOut)
                <div>
                    <div class="font-bold uppercase" title="ส่งยอดไปใช้ในปีหน้า">−ส่งออก</div>
                    <div>−{{ rtrim(rtrim(number_format($carryoverOut, 1), '0'), '.') }}</div>
                </div>
                @endif
                @if($showEncashment)
                <div>
                    <div class="font-bold uppercase">แลกเงิน</div>
                    <div>−{{ rtrim(rtrim(number_format($b['encashed'], 1), '0'), '.') }}</div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- History (Admin only) --}}
    @php
        $totalLogs = $carryovers->count() + $encashments->count();
        $pendingLogs = $carryovers->where('status', 'pending')->count() + $encashments->where('status', 'pending')->count();
    @endphp
    @if($isAdminLB && $totalLogs > 0)
    <div class="border-t border-gray-100" x-data="{ logsOpen: {{ $pendingLogs > 0 ? 'true' : 'false' }} }">
        <button type="button" @click="logsOpen = !logsOpen"
                class="w-full px-4 py-2.5 flex items-center justify-between hover:bg-gray-50 transition">
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold text-gray-600 uppercase tracking-wider">📜 ประวัติยกยอด/แลกเงิน</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600 font-bold">{{ $totalLogs }}</span>
                @if($pendingLogs > 0)
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 font-bold">{{ $pendingLogs }} รออนุมัติ</span>
                @endif
            </div>
            <span class="text-gray-400 text-xs" x-text="logsOpen ? '▾' : '▸'"></span>
        </button>
        <div x-show="logsOpen" x-cloak class="px-4 pb-4 space-y-2 border-t border-gray-100 pt-3">
        @if($carryovers->isNotEmpty())
        <div>
            <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">📥 รายการยกยอดในปี {{ $thaiYear }}</div>
            @foreach($carryovers as $co)
                @php
                    $isInbound = (int) $co->year === (int) $year;
                    $direction = $isInbound ? 'in' : 'out';
                    $tone = $co->status === 'pending'
                        ? 'bg-amber-50/50 border-amber-200'
                        : ($isInbound ? 'bg-indigo-50/50 border-indigo-100' : 'bg-rose-50/50 border-rose-100');
                @endphp
            <div class="flex items-center justify-between p-2 rounded-lg border mb-1 text-[11px] {{ $tone }}">
                <div class="flex items-center gap-1 flex-wrap">
                    @if($co->status === 'pending')
                        <span class="px-1.5 py-0.5 text-[9px] font-bold bg-amber-100 text-amber-700 rounded uppercase">รออนุมัติ</span>
                    @endif
                    <span class="font-bold {{ $isInbound ? 'text-indigo-700' : 'text-rose-700' }}">
                        {{ $isInbound ? '📥' : '📤' }} {{ \App\Models\Employee::LEAVE_TYPES_TRACKED[$co->leave_type]['label'] ?? $co->leave_type }}
                    </span>
                    <span class="text-gray-600">{{ $isInbound ? '+' : '−' }}{{ rtrim(rtrim(number_format($co->days, 1), '0'), '.') }} วัน</span>
                    <span class="text-gray-400">
                        @if($isInbound)
                            จากปี {{ $co->source_year ?? '-' }}
                        @else
                            ส่งไปปี {{ $co->year }}
                        @endif
                    </span>
                    @if($co->note)<span class="text-gray-400">— {{ $co->note }}</span>@endif
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('portal.show', ['type' => 'carryover', 'id' => $co->id]) }}" class="p-1 text-gray-300 hover:text-indigo-500" title="ดูเอกสาร">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </a>
                    <form action="{{ route('leave-balance.carryover.delete', $co->id) }}" method="POST" onsubmit="return confirm('ลบยอดยกยอด {{ $co->days }} วัน?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-1 text-gray-300 hover:text-rose-500" title="ลบ">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        @if($encashments->isNotEmpty())
        <div>
            <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">💵 รายการแลกเป็นเงินในปี {{ $thaiYear }}</div>
            @foreach($encashments as $en)
            <div class="flex items-center justify-between p-2 bg-emerald-50/50 rounded-lg border border-emerald-100 mb-1 text-[11px]">
                <div>
                    <span class="font-bold text-emerald-700">{{ \App\Models\Employee::LEAVE_TYPES_TRACKED[$en->leave_type]['label'] ?? $en->leave_type }}</span>
                    <span class="text-gray-600">{{ rtrim(rtrim(number_format($en->days, 1), '0'), '.') }} วัน</span>
                    <span class="text-emerald-700 font-bold">฿{{ number_format($en->amount, 2) }}</span>
                    <span class="text-gray-400">(จ่ายเดือน {{ $en->payout_month }}/{{ $en->payout_year }})</span>
                </div>
                <form action="{{ route('leave-balance.encashment.delete', $en->id) }}" method="POST" onsubmit="return confirm('ลบรายการแลกเป็นเงิน — รายรับใน Workspace ที่ผูกอยู่จะถูกลบด้วย ดำเนินการต่อหรือไม่?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-1 text-gray-300 hover:text-rose-500" title="ลบ">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
        </div>
    </div>
    @endif

    {{-- Carryover Modal --}}
    @if($isAdminLB)
    <div x-show="carryoverModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="carryoverModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold mb-1">📥 ยกยอดวันลาข้ามปี</h3>
            <p class="text-xs text-gray-500 mb-4">
                ส่งวันลาคงเหลือของปี <span class="font-bold text-indigo-700">{{ $thaiYear }}</span> ไปใช้ในปี <span class="font-bold text-emerald-700">{{ $thaiYear + 1 }}</span>
            </p>
            <form method="POST" action="{{ route('leave-balance.carryover', $employee->id) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="source_year" value="{{ $year }}">
                <input type="hidden" name="target_year" value="{{ $year + 1 }}">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทวันลา</label>
                    <select name="leave_type" required x-model="selectedType"
                            @change="selectedTypeLabel = $event.target.options[$event.target.selectedIndex].text"
                            class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="vacation_leave">ลาพักร้อน (ยกยอดได้)</option>
                    </select>
                    <p class="text-[10px] text-gray-400 mt-0.5">เฉพาะลาพักร้อน — ลาป่วย/ลากิจไม่ยกยอด</p>
                </div>

                <div class="grid grid-cols-2 gap-3 bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase">จาก (ปีนี้)</div>
                        <div class="text-base font-bold text-indigo-700">{{ $thaiYear }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase">ไป (ปีหน้า)</div>
                        <div class="text-base font-bold text-emerald-700">{{ $thaiYear + 1 }}</div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนวันที่ยกยอด</label>
                    <input type="number" name="days" step="0.5" min="0.5" max="365" required
                           class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น 4">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
                    <input type="text" name="note" maxlength="255" placeholder="เช่น สิทธิคงเหลือจากปี {{ $thaiYear }}"
                           class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="carryoverModal = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">ยกยอดไปปี {{ $thaiYear + 1 }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Encash Modal --}}
    <div x-show="encashModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="encashModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold mb-4">💵 แลกวันลาเป็นเงิน</h3>
            <form method="POST" action="{{ route('leave-balance.encash', $employee->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทวันลา</label>
                    <select name="leave_type" required x-model="selectedType" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="vacation_leave">ลาพักร้อน (แลกได้)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ปีของสิทธิ</label>
                    <input type="number" name="year" required value="{{ $year }}"
                           class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนวัน</label>
                        <input type="number" name="days" step="0.5" min="0.5" max="365" required
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">อัตรา/วัน</label>
                        <input type="number" name="rate_per_day" step="0.01" min="0"
                               placeholder="เว้นว่าง = ใช้ฐานเงินเดือน/30"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เดือนที่จ่าย</label>
                        <select name="payout_month" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            @foreach(['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'] as $i => $name)
                                <option value="{{ $i + 1 }}" {{ ($i + 1) === $month ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ปีที่จ่าย</label>
                        <input type="number" name="payout_year" required value="{{ $year }}"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
                    <input type="text" name="note" maxlength="255" placeholder="เช่น สิ้นปี 2025 — แลกพักร้อนเหลือ 3 วัน"
                           class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
                    ⚠️ เมื่อแลกแล้ว ระบบจะสร้างรายรับพิเศษเข้า Workspace ของเดือน/ปีที่ระบุอัตโนมัติ
                </p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="encashModal = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">แลกเป็นเงิน</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
