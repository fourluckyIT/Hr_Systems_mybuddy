<!-- Attendance Grid for Monthly Staff / Youtuber Salary -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 bg-green-600 text-white font-semibold text-sm flex justify-between items-center">
        <span>ตารางเข้างาน — {{ $employee->payroll_mode === 'youtuber_salary' ? 'YouTuber รายเดือน' : 'ตัดต่อแบบ รายเดือน' }}</span>
        <span class="text-xs opacity-80">คำนวณเวลา / performance</span>
    </div>

    <form method="POST" action="{{ route('workspace.saveAttendance', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
        @csrf
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 text-left">ตรงจ.วัน</th>
                        <th class="px-2 py-2 text-left">วันที่</th>
                        <th class="px-2 py-2 text-center">เวลาเข้า</th>
                        <th class="px-2 py-2 text-center">สาย</th>
                        <th class="px-2 py-2 text-center">เวลาออก</th>
                        <th class="px-2 py-2 text-center">สาย(น.)</th>
                        <th class="px-2 py-2 text-center">OT(น.)</th>
                        <th class="px-2 py-2 text-center w-8">OT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendanceLogs as $log)
                    @php
                        $colorClass = $dayTypeColors[$log->day_type] ?? 'bg-gray-100 text-gray-800';
                        $isWorkday = in_array($log->day_type, ['workday', 'ot_full_day']);
                        $isLwop = $log->day_type === 'lwop';
                        $isHoliday = in_array($log->day_type, ['holiday', 'company_holiday', 'not_started']);
                        $date = \Carbon\Carbon::parse($log->log_date); 
                        $bgClass = $isHoliday ? 'bg-gray-100/50' : ($isLwop ? 'bg-red-50/30' : 'bg-white');
                        $showTimeInputs = !$isHoliday && !$isLwop;
                    @endphp
                    <tr class="{{ $bgClass }} border-t hover:bg-gray-50 transition-all">
                        <td class="px-2 py-1">
                            <select name="attendance[{{ $log->id }}][day_type]"
                                onchange="this.closest('form').submit()"
                                class="px-1 py-0.5 rounded text-xs border-0 {{ $colorClass }} font-medium w-max">
                                @foreach($dayTypeLabels as $val => $label)
                                <option value="{{ $val }}" {{ $log->day_type === $val ? 'selected' : '' }}
                                    class="{{ $dayTypeColors[$val] ?? '' }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-1 text-gray-600 font-medium">
                            <div>{{ \Carbon\Carbon::parse($log->log_date)->format('D, j M Y') }}</div>
                            @if($log->is_swapped_day && $log->swapped_from_day_type)
                                <div class="text-[10px] text-indigo-600 font-semibold">
                                    สลับจาก: {{ $dayTypeLabels[$log->swapped_from_day_type] ?? $log->swapped_from_day_type }}
                                </div>
                            @endif
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="time" name="attendance[{{ $log->id }}][check_in]"
                                value="{{ $log->check_in ? \Carbon\Carbon::parse($log->check_in)->format('H:i') : '' }}"
                                {{ !$showTimeInputs ? 'disabled' : '' }}
                                class="check-in-input px-1 py-0.5 border rounded text-xs w-20 text-center {{ !$showTimeInputs ? 'bg-gray-100 text-gray-400' : '' }}">
                        </td>
                        <td class="px-2 py-1 text-center text-red-500 font-bold text-xs">
                            <span class="late-preview">{{ $log->late_minutes > 0 ? $log->late_minutes : '' }}</span>
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="time" name="attendance[{{ $log->id }}][check_out]"
                                value="{{ $log->check_out ? \Carbon\Carbon::parse($log->check_out)->format('H:i') : '' }}"
                                {{ !$showTimeInputs ? 'disabled' : '' }}
                                class="check-out-input px-1 py-0.5 border rounded text-xs w-20 text-center {{ !$showTimeInputs ? 'bg-gray-100 text-gray-400' : '' }}">
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="number" name="attendance[{{ $log->id }}][late_minutes]"
                                value="{{ $log->late_minutes }}" min="0"
                                class="late-minutes-input px-1 py-0.5 border rounded text-xs w-12 text-center">
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="number" name="attendance[{{ $log->id }}][ot_minutes]"
                                value="{{ $log->ot_minutes }}" min="0"
                                class="ot-minutes-input px-1 py-0.5 border rounded text-xs w-12 text-center">
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="checkbox" name="attendance[{{ $log->id }}][ot_enabled]"
                                {{ $log->ot_enabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 w-3 h-3">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 bg-gray-50 flex justify-end">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700">
                บันทึกข้อมูลเข้างาน
            </button>
        </div>
    </form>
</div>

<script>
(() => {
    const targetCheckIn = "{{ $attendanceMeta['target_check_in'] ?? '09:30' }}";
    const targetMinutesPerDay = Number("{{ $attendanceMeta['target_minutes_per_day'] ?? 540 }}");

    function toMinutes(timeString) {
        if (!timeString || !timeString.includes(':')) return null;
        const [h, m] = timeString.split(':').map(Number);
        if (Number.isNaN(h) || Number.isNaN(m)) return null;
        return h * 60 + m;
    }

    const targetInMinutes = toMinutes(targetCheckIn) ?? 570;

    document.querySelectorAll('table tbody tr').forEach((row) => {
        const checkInInput = row.querySelector('.check-in-input');
        const checkOutInput = row.querySelector('.check-out-input');
        const lateInput = row.querySelector('.late-minutes-input');
        const otInput = row.querySelector('.ot-minutes-input');
        const otEnabledInput = row.querySelector('input[type="checkbox"][name*="[ot_enabled]"]');
        const latePreview = row.querySelector('.late-preview');

        if (!checkInInput || !checkOutInput || !lateInput || !otInput) return;

        const recalc = () => {
            const inMinutes = toMinutes(checkInInput.value);
            const outMinutesRaw = toMinutes(checkOutInput.value);

            if (inMinutes === null || outMinutesRaw === null) return;

            let outMinutes = outMinutesRaw;
            if (outMinutes <= inMinutes) {
                outMinutes += 24 * 60;
            }

            const late = Math.max(0, inMinutes - targetInMinutes);
            const worked = Math.max(0, outMinutes - inMinutes);
            const ot = (otEnabledInput && otEnabledInput.checked)
                ? Math.max(0, worked - targetMinutesPerDay)
                : 0;

            lateInput.value = late;
            otInput.value = ot;
            if (latePreview) {
                latePreview.textContent = late > 0 ? String(late) : '';
            }
        };

        checkInInput.addEventListener('change', recalc);
        checkOutInput.addEventListener('change', recalc);
        if (otEnabledInput) {
            otEnabledInput.addEventListener('change', recalc);
        }
    });
})();
</script>
