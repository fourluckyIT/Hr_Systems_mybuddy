{{-- Tiers Management Modal Wrapper --}}
<div x-show="tierListModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="tierListModal = false"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
        
        <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center shrink-0">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Performance Tiers</h2>
                <p class="text-xs text-gray-500 mt-1">จัดการระดับผลงาน (Global Tier) และเกณฑ์การประเมินอัตโนมัติ</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="editTier = null; editModal = true" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 shadow-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    เพิ่ม Tier ใหม่
                </button>
                <button @click="tierListModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto p-6 bg-gray-50/50">
            {{-- Tiers List --}}
            <div class="bg-white border rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Code (ชื่อ)</th>
                                <th class="px-4 py-3 text-right">Multiplier</th>
                                <th class="px-4 py-3 text-right">นาทีขั้นต่ำ (Auto)</th>
                                <th class="px-4 py-3 text-center">สถานะ</th>
                                <th class="px-4 py-3 text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($tiers as $tier)
                            <tr class="hover:bg-gray-50 {{ !$tier->is_active ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900 flex items-center gap-2">
                                        {{ $tier->tier_code }}
                                        @if($tier->auto_select_enabled)
                                        <span title="เปิดใช้งาน Auto-select" class="px-1.5 py-0.5 bg-blue-100 text-blue-700 text-[9px] rounded uppercase">Auto</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-gray-500">{{ $tier->tier_name }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                    {{ number_format((float) $tier->multiplier * 100, 1) }}%
                                    <div class="text-[9px] text-gray-400">({{ $tier->multiplier }})</div>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-gray-600">
                                    @if($tier->auto_select_enabled)
                                        {{ $tier->min_clip_minutes_per_month ? number_format($tier->min_clip_minutes_per_month) . ' น.' : 'ไม่จำกัด' }}
                                        <span class="text-gray-400 px-1">ถึง</span>
                                        {{ $tier->max_clip_minutes_per_month ? number_format($tier->max_clip_minutes_per_month) . ' น.' : 'ขึ้นไป' }}
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($tier->is_active)
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px] font-bold">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right flex items-center justify-end gap-2">
                                    <button @click="openEdit({{ $tier->toJson() }})" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">แก้ไข</button>
                                    <form method="POST" action="{{ route('settings.tiers.destroy', $tier) }}" class="inline" onsubmit="return confirm('ยืนยันการลบ Tier นี้?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">ลบ</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-400">ยังไม่มีข้อมูล Tier</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit/Add Modal (Nested Logic, but visually fixed over the other) --}}
<div x-show="editModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="editModal = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100 flex justify-between items-center shrink-0">
            <h3 class="text-base font-bold text-indigo-900" x-text="editTier ? 'แก้ไข Tier' : 'เพิ่ม Tier ใหม่'"></h3>
            <button @click="editModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form :action="editTier ? '{{ url('settings/tiers') }}/' + editTier.id : '{{ route('settings.tiers.store') }}'" method="POST" class="p-6 overflow-y-auto space-y-4">
            @csrf
            <template x-if="editTier">
                <input type="hidden" name="_method" value="PATCH">
            </template>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Tier Code</label>
                    <input type="text" name="tier_code" :value="editTier ? editTier.tier_code : ''" required :readonly="editTier !== null" :class="editTier ? 'bg-gray-50 cursor-not-allowed' : ''" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น S, A, B">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">ชื่อเรียก (Tier Name)</label>
                    <input type="text" name="tier_name" :value="editTier ? editTier.tier_name : ''" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Multiplier (ตัวคูณ)</label>
                    <input type="number" step="0.001" min="0" max="10" name="multiplier" :value="editTier ? editTier.multiplier : '0.000'" required class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น 0.20 สำหรับ 20%">
                    <p class="text-[9px] text-gray-400 mt-1">เช่น 0.10 = 10%, 0.20 = 20%</p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">ลำดับการแสดงผล</label>
                    <input type="number" name="display_order" :value="editTier ? editTier.display_order : '1'" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4 mt-2">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold text-gray-800">กฎการประเมินอัตโนมัติ (Auto-select)</h4>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="auto_select_enabled" value="1" :checked="editTier ? editTier.auto_select_enabled : true" class="rounded text-indigo-600">
                        <span class="text-[10px] font-bold text-gray-500 uppercase">เปิดใช้งาน</span>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-4 bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">นาทีขั้นต่ำ/เดือน (Min Mins)</label>
                        <input type="number" name="min_clip_minutes_per_month" :value="editTier ? editTier.min_clip_minutes_per_month : ''" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">นาทีสูงสุด/เดือน (Max Mins)</label>
                        <input type="number" name="max_clip_minutes_per_month" :value="editTier ? editTier.max_clip_minutes_per_month : ''" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เว้นว่าง = ไม่จำกัด">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">จำนวนเดือนขั้นต่ำ (Min Months)</label>
                        <input type="number" name="min_qualified_months" :value="editTier ? editTier.min_qualified_months : ''" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">จำนวนเดือนสูงสุด (Max Months)</label>
                        <input type="number" name="max_qualified_months" :value="editTier ? editTier.max_qualified_months : ''" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" value="1" :checked="editTier ? editTier.is_active : true" class="rounded text-emerald-600 w-4 h-4">
                <label class="text-sm font-semibold text-gray-700">เปิดใช้งาน (Active)</label>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" @click="editModal = false" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 shadow-sm" x-text="editTier ? 'บันทึกการแก้ไข' : 'สร้าง Tier'"></button>
            </div>
        </form>
    </div>
</div>
