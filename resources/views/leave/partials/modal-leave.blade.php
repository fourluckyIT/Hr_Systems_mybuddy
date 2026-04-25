{{-- Leave Request Modal --}}
<div x-show="leaveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" @keydown.escape.window="leaveModal = false">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="leaveModal = false"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
        {{-- Modal Header --}}
        <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-indigo-500 text-white">
            <div class="flex justify-between items-center">
                <h2 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    ขอลา
                </h2>
                <button @click="leaveModal = false" class="text-white/70 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="text-xs text-indigo-100 mt-1">{{ $isAdmin ? 'บันทึกการลาให้พนักงาน (อนุมัติทันที)' : 'ส่งคำขอลาเพื่อรอแอดมินตรวจสอบ' }}</p>
        </div>

        {{-- Modal Body --}}
        <form method="POST" action="{{ route('leave.store') }}" class="p-6 space-y-4">
            @csrf
            @if($isAdmin)
            <div>
                <label class="text-xs font-semibold text-gray-600">พนักงาน</label>
                <select name="employee_id" required class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-colors">
                    <option value="">— เลือกพนักงาน —</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ (int) request('employee_id') === (int) $emp->id ? 'selected' : '' }}>{{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->user()->employee?->id }}">
            @endif
            <div>
                <label class="text-xs font-semibold text-gray-600">วันที่ลา</label>
                <input type="date" name="leave_date" required class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">ประเภทการลา</label>
                <select name="leave_type" required class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
                    @foreach($leaveTypes as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">เหตุผล <span class="text-gray-400 font-normal">(ไม่บังคับ)</span></label>
                <textarea name="reason" rows="2" class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400" placeholder="ระบุเหตุผลการลา..."></textarea>
            </div>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl font-semibold text-sm hover:bg-indigo-700 transition-colors shadow-sm">
                {{ $isAdmin ? 'บันทึกการลา' : 'ส่งคำขอลา' }}
            </button>
        </form>
    </div>
</div>
