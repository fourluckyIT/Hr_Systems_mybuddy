@extends('layouts.app')

@section('title', 'การตั้งค่ากฎระบบ')

@section('content')
<div x-data="{
    diligenceTiers: {{ json_encode($rules['diligence']?->config['tiers'] ?? []) }},
    addTier() {
        this.diligenceTiers.push({ late_count_max: 0, lwop_days_max: 0, amount: 0 });
    },
    removeTier(index) {
        this.diligenceTiers.splice(index, 1);
    }
}">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">การตั้งค่ากฎระบบ (Rule Dashboard) ⚙️</h1>
            <p class="text-gray-500 mt-1">จัดการเวลาทำงาน, ประกันสังคม และกฎเกณฑ์ต่างๆ ของบริษัท</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
        <span class="font-semibold">{{ session('success') }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- 1. Work Hours & Office Times -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between">
                <h3 class="font-bold text-indigo-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" /></svg>
                    เวลาทำงาน และวันทำงาน (Office Hours)
                </h3>
                <span class="text-[10px] text-indigo-400 font-bold uppercase tracking-widest cursor-help" title="กำหนดเวลาเข้า-ออกงานหลักของบริษัท">Helper (?)</span>
            </div>
            <div class="p-6 flex-grow">
                @php $rule = $rules['working_hours']; $config = $rule?->config; @endphp
                <form action="{{ route('settings.rules.update', 'working_hours') }}" method="POST" class="space-y-4">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1">
                                เวลาเข้างาน (In)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="เวลาเป้าหมายที่พนักงานต้องเข้างาน"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </label>
                            <input type="time" name="target_check_in" value="{{ $config['target_check_in'] ?? '09:00' }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">เวลาออกงาน (Out)</label>
                            <input type="time" name="target_check_out" value="{{ $config['target_check_out'] ?? '18:00' }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชั่วโมงทำงานจริง/วัน</label>
                            <input type="number" name="target_minutes_per_day" value="{{ $config['target_minutes_per_day'] ?? 540 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1">
                                วันทำงานเฉลี่ย/เดือน
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="ใช้เป็นตัวหารเงินเดือนเพื่อคิดค่าแรงรายวัน หรือหักเงินขาดงาน"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </label>
                            <input type="number" name="working_days_per_month" value="{{ $config['working_days_per_month'] ?? 22 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">บันทึกเวลาทำงาน</button>
                </form>
            </div>
        </div>

        <!-- 2. Social Security Settings -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                <h3 class="font-bold text-blue-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    ประกันสังคม (Social Security)
                </h3>
            </div>
            <div class="p-6 flex-grow">
                @php $sso = $rules['social_security_config']; @endphp
                <form action="{{ route('settings.rules.update', 'social_security') }}" method="POST" class="space-y-4">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1">
                            เพดานเงินเดือน (Salary Ceiling)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="ยอดเงินเดือนสูงสุดที่ใช้คิดประกันสังคม (เช่น 15,000 หรือ 17,500)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </label>
                        <div class="relative">
                            <input type="number" name="salary_ceiling" value="{{ (int)($sso->salary_ceiling ?? 15000) }}" class="w-full pl-3 pr-10 py-2 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-blue-500">
                            <span class="absolute right-3 top-2 text-xs text-gray-400">บาท</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">อัตราหักพนักงาน (%)</label>
                            <input type="number" step="0.1" name="employee_contribution_rate" value="{{ (float)($sso->employee_rate ?? 5) }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">อัตราสมทบนายจ้าง (%)</label>
                            <input type="number" step="0.1" name="employer_contribution_rate" value="{{ (float)($sso->employer_rate ?? 5) }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-blue-700 transition shadow-lg shadow-blue-100">บันทึกตั้งค่าประกันสังคม</button>
                </form>
            </div>
        </div>

        <!-- 3. Multi-Tier Diligence Rules -->
        <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-orange-50 border-b border-orange-100 flex items-center justify-between">
                <h3 class="font-bold text-orange-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM11 2a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V2zm2 2v2h2V4h-2zM11 12a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1v-5zm2 2v2h2v-2h-2z" clip-rule="evenodd" /></svg>
                    กฎเบี้ยขยันแบบ "ขั้นบันได" (Tiered Diligence)
                </h3>
                <span class="text-[10px] text-orange-400 font-bold uppercase tracking-widest cursor-help" title="พนักงานจะได้เบี้ยขยันตามลำดับขั้นที่ระบุข้างล่างนี้ โดยระบบจะเลือกขั้นที่ดีที่สุด">Help (?)</span>
            </div>
            <div class="p-6 flex-grow">
                <form action="{{ route('settings.rules.update', 'diligence') }}" method="POST">
                    @csrf @method('PATCH')
                    
                    <div class="space-y-3 mb-6">
                        <template x-for="(tier, index) in diligenceTiers" :key="index">
                            <div class="flex items-end gap-4 p-4 bg-orange-50/50 rounded-xl border border-orange-100 relative group animate-in slide-in-from-top duration-200">
                                <div class="w-12 text-[10px] font-black text-orange-400 uppercase">ขั้นที่ <span x-text="index + 1"></span></div>
                                <div class="flex-grow grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">สายไม่เกิน (ครั้ง)</label>
                                        <input type="number" :name="'tiers['+index+'][late_count_max]'" x-model="tier.late_count_max" class="w-full px-3 py-1.5 border rounded-lg text-sm font-bold focus:ring-1 focus:ring-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ลา/ขาดไม่เกิน (วัน)</label>
                                        <input type="number" :name="'tiers['+index+'][lwop_days_max]'" x-model="tier.lwop_days_max" class="w-full px-3 py-1.5 border rounded-lg text-sm font-bold focus:ring-1 focus:ring-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">จำนวนเงิน</label>
                                        <input type="number" :name="'tiers['+index+'][amount]'" x-model="tier.amount" class="w-full px-3 py-1.5 border rounded-lg text-sm font-bold text-orange-600 focus:ring-1 focus:ring-orange-500">
                                    </div>
                                </div>
                                <button type="button" @click="removeTier(index)" class="p-2 text-gray-300 hover:text-red-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </template>

                        <button type="button" @click="addTier()" class="w-full py-3 border-2 border-dashed border-orange-100 rounded-xl text-orange-400 font-bold text-sm hover:bg-orange-50 hover:border-orange-200 transition-all">
                            + เพิ่มขั้นบันไดเบี้ยขยัน
                        </button>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-dashed border-gray-200 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center text-orange-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-orange-800 uppercase leading-none mb-1">โหมดการจ่าย</p>
                                <p class="text-xs text-gray-500">ระบบจะคำนวณเบี้ยขยันให้อัตโนมัติในหน้า Workspace แต่คุณสามารถ<b>คลิกที่ยอดเงินเพื่อแก้เอง (Manual)</b> ได้ทุกเมื่อ</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-orange-600 text-white py-3 rounded-xl font-bold text-sm hover:bg-orange-700 transition shadow-lg shadow-orange-100">บันทึกกฎเบี้ยขยันทั้งหมด</button>
                </form>
            </div>
        </div>

        <!-- 4. OT & Late Rules -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-red-50 border-b border-red-100 flex items-center justify-between">
                <h3 class="font-bold text-red-900 flex items-center gap-2">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                    OT และการหักเงินมาสาย (OT & Late Penalty)
                </h3>
            </div>
            <div class="p-6 flex-grow space-y-6">
                <!-- OT Rate Card -->
                @php $rule = $rules['ot_rate']; $config = $rule?->config; @endphp
                <form action="{{ route('settings.rules.update', 'ot_rate') }}" method="POST" class="space-y-4">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ตัวคูณ OT (Multiplier)</label>
                            <input type="number" step="0.1" name="rate_multiplier" value="{{ $config['rate_multiplier'] ?? 1.0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">เพดานชั่วโมง/เดือน</label>
                            <input type="number" name="max_ot_hours" value="{{ $config['max_ot_hours'] ?? 40 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-xl font-bold text-xs hover:bg-red-700 transition">อัปเดตกฎ OT</button>
                </form>

                <hr class="border-gray-100">

                <!-- Late Deduction Card -->
                @php $rule = $rules['late_deduction']; $config = $rule?->config; @endphp
                <form action="{{ route('settings.rules.update', 'late_deduction') }}" method="POST" class="space-y-4">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">หักต่อนำที (บาท)</label>
                            <input type="number" name="rate_per_minute" value="{{ $config['rate_per_minute'] ?? 0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-gray-700 focus:ring-2 focus:ring-gray-400">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">อนุโลม (นาที)</label>
                            <input type="number" name="grace_period_minutes" value="{{ $config['grace_period_minutes'] ?? 0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-gray-700 focus:ring-2 focus:ring-gray-400">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-gray-100 text-gray-700 py-2 rounded-xl font-bold text-xs hover:bg-gray-200 transition">อัปเดตอัตราหักมาสาย</button>
                </form>
            </div>
        </div>

        <!-- 5. Holiday Management -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col md:col-span-2">
            <div class="px-6 py-4 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
                <h3 class="font-bold text-purple-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" /></svg>
                    จัดการวันหยุดบริษัท (Company Holidays)
                </h3>
                <form action="{{ route('settings.holidays.load-legal') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1 bg-purple-600 text-white rounded-lg text-[10px] font-bold hover:bg-purple-700 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        ดึงข้อมูลวันหยุดราชการ 2026
                    </button>
                </form>
            </div>
            <div class="p-6">
                <!-- Add Holiday Form -->
                <form action="{{ route('settings.holidays.add') }}" method="POST" class="mb-8 p-4 bg-gray-50 rounded-2xl border border-gray-100 flex flex-col md:flex-row gap-4 items-end">
                    @csrf
                    <div class="flex-grow w-full">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">ชื่อวันหยุด</label>
                        <input type="text" name="name" required placeholder="ระบุชื่อวันหยุด..." class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">วันที่หยุด</label>
                        <input type="date" name="holiday_date" required class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500">
                    </div>
                    <button type="submit" class="w-full md:w-auto bg-gray-800 text-white px-8 py-2 rounded-xl font-bold hover:bg-gray-900 transition shadow-lg shadow-gray-100">เพิ่มรายการ</button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @forelse($holidays as $holiday)
                    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm flex items-center justify-between group hover:border-purple-200 transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex flex-col items-center justify-center text-purple-600">
                                <span class="text-[8px] font-bold leading-none">{{ $holiday->holiday_date->format('M') }}</span>
                                <span class="text-sm font-black">{{ $holiday->holiday_date->format('d') }}</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-800">{{ $holiday->name }}</p>
                                <p class="text-[10px] text-gray-400">{{ $holiday->holiday_date->format('Y') }}</p>
                            </div>
                        </div>
                        <form action="{{ route('settings.holidays.delete', $holiday->id) }}" method="POST" onsubmit="return confirm('ลบวันหยุดนี้?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-2 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </form>
                    </div>
                    @empty
                    <div class="col-span-full py-8 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                        <p class="text-sm text-gray-400 italic">ไม่มีข้อมูลวันหยุดส่วนตัว ลองกด "ดึงข้อมูลวันหยุดราชการ" ด้านบนครับ</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
