<!-- YouTuber Salary (Fixed) Grid -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 bg-purple-600 text-white font-semibold text-sm flex justify-between items-center text-center">
        <span>YouTuber เงินเดือน — ยอดคงที่รายเดือน</span>
        <span class="text-xs opacity-80">ไม่ต้องเช็คเวลาเข้างานรายวัน</span>
    </div>

    <div class="p-8 text-center bg-purple-50/30">
        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mx-auto mb-4 border-4 border-white shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-800">พนักงานรับเงินเดือนคงที่</h3>
        <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">พนักงานท่านนี้อยู่ในโหมด YouTuber เงินเดือน ระบบจะดึงฐานเงินเดือนมาคำนวณจ่ายเต็มจำนวนโดยอัตโนมัติ (ยกเว้นมีการ Override ในช่องสรุปด้านขวา)</p>
        
        <div class="mt-6 flex flex-col items-center gap-2">
            <div class="px-4 py-2 bg-white border border-purple-200 rounded-lg shadow-sm">
                <span class="text-xs text-purple-600 font-bold uppercase tracking-wider block mb-1">ฐานเงินเดือนปัจจุบัน</span>
                <span class="text-2xl font-black text-gray-800">{{ number_format($employee->salaryProfile->base_salary ?? 0, 2) }} ฿</span>
            </div>
            
            <p class="text-[10px] text-gray-400 mt-2 italic px-8">
                * หากต้องการหักเงินมาสาย, ขาดงาน หรือระบุเงินโบนัสพิเศษ <br>
                ให้ใช้โมดูล **"เบิกเงิน / ค่าใช้จ่าย (Claims/Advances)"** ที่ส่วนล่างของหน้านี้
            </p>
        </div>
    </div>
</div>
