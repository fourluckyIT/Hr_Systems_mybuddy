{{-- Day Swap Request Modal --}}
<div x-show="swapModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" @keydown.escape.window="swapModal = false">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="swapModal = false"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
        {{-- Modal Header --}}
        <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white">
            <div class="flex justify-between items-center">
                <h2 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    ขอสลับวันทำงาน
                </h2>
                <button @click="swapModal = false" class="text-white/70 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="text-xs text-amber-100 mt-1">สลับวันทำงานกับวันหยุด — ต้องไม่ทำงานเกิน 6 วันติด</p>
        </div>

        {{-- Modal Body --}}
        <form method="POST" action="{{ route('leave.swap.store') }}" class="p-6 space-y-4">
            @csrf
            @if($isAdmin)
            <div>
                <label class="text-xs font-semibold text-gray-600">พนักงาน</label>
                <select name="employee_id" required class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-amber-200 focus:border-amber-400">
                    <option value="">— เลือกพนักงาน —</option>
                    @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->full_name }}</option>@endforeach
                </select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->user()->employee?->id }}">
            @endif

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                    <label class="text-[10px] font-bold text-green-700 uppercase tracking-wider flex items-center gap-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        มาทำงานแทน
                    </label>
                    <p class="text-[9px] text-green-500 mt-0.5 mb-2">เลือกวันหยุดที่จะมาทำงาน</p>
                    <input type="date" name="work_date" required class="block w-full border border-green-200 rounded-lg px-2.5 py-2 text-sm bg-white focus:ring-2 focus:ring-green-200 focus:border-green-400">
                </div>
                <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                    <label class="text-[10px] font-bold text-red-700 uppercase tracking-wider flex items-center gap-1">
                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                        หยุดแทน
                    </label>
                    <p class="text-[9px] text-red-500 mt-0.5 mb-2">เลือกวันทำงานที่จะหยุด</p>
                    <input type="date" name="off_date" required class="block w-full border border-red-200 rounded-lg px-2.5 py-2 text-sm bg-white focus:ring-2 focus:ring-red-200 focus:border-red-400">
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600">เหตุผล <span class="text-gray-400 font-normal">(ไม่บังคับ)</span></label>
                <textarea name="reason" rows="2" class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-amber-200 focus:border-amber-400" placeholder="ระบุเหตุผลการสลับวัน..."></textarea>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-[10px] text-amber-700">
                <strong>กฎ:</strong> วันมาทำงานแทนต้องเป็นวันหยุด · วันหยุดแทนต้องเป็นวันทำงาน · ห้ามทำงานเกิน 6 วันติดต่อกัน
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-500 text-white rounded-xl font-semibold text-sm hover:bg-amber-600 transition-colors shadow-sm">
                {{ $isAdmin ? 'บันทึกการสลับวัน' : 'ส่งคำขอสลับวัน' }}
            </button>
        </form>
    </div>
</div>
