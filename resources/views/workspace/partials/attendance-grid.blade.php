<!-- Attendance Grid for Monthly Staff / Youtuber Salary -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden" id="attendance-grid-container">
    <div class="px-4 py-3 bg-green-600 text-white font-semibold text-sm flex justify-between items-center">
        <span>ตารางเข้างาน — {{ $employee->payroll_mode === 'youtuber_salary' ? 'YouTuber รายเดือน' : 'ตัดต่อแบบ รายเดือน' }}</span>
        <div class="flex items-center gap-2">
            <span id="attendance-save-status" class="text-xs opacity-80 transition-all">คำนวณเวลา / performance</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs" id="attendance-table">
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
                    <th class="px-2 py-2 text-center">ประเภท OT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($attendanceLogs as $log)
                @php
                    $colorClass = $dayTypeColors[$log->day_type] ?? 'bg-gray-100 text-gray-800';
                    $isWorkday = in_array($log->day_type, ['workday', 'ot_full_day']);
                    $isHolidayOvertimeDay = in_array($log->day_type, ['holiday', 'company_holiday']);
                    $isLwop = $log->day_type === 'lwop';
                    $isHoliday = in_array($log->day_type, ['holiday', 'company_holiday', 'not_started']);
                    $isTimeEntryDay = $isWorkday || $isHolidayOvertimeDay;
                    $date = \Carbon\Carbon::parse($log->log_date);
                    $bgClass = $isHoliday ? 'bg-gray-100/50' : ($isLwop ? 'bg-red-50/30' : 'bg-white');
                    $showTimeInputs = $isTimeEntryDay && !$isLwop;
                    $disableLateInput = !$isWorkday;
                    $otTypeText = '-';
                    $otTypeClass = 'bg-gray-100 text-gray-500';
                    if ($log->ot_enabled && (int) $log->ot_minutes > 0) {
                        if ($isHolidayOvertimeDay) {
                            $otTypeText = 'OT วันหยุด';
                            $otTypeClass = 'bg-purple-100 text-purple-700';
                        } elseif ($isWorkday) {
                            $otTypeText = 'OT ปกติ';
                            $otTypeClass = 'bg-indigo-100 text-indigo-700';
                        }
                    }
                @endphp
                <tr class="{{ $bgClass }} border-t hover:bg-gray-50 transition-all attendance-row" data-log-id="{{ $log->id }}">
                    <td class="px-2 py-1">
                        <select data-field="day_type"
                            class="day-type-select px-1 py-0.5 rounded text-xs border-0 {{ $colorClass }} font-medium w-max">
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
                        <input type="time" data-field="check_in"
                            value="{{ $log->check_in ? \Carbon\Carbon::parse($log->check_in)->format('H:i') : '' }}"
                            {{ !$showTimeInputs ? 'disabled' : '' }}
                            class="check-in-input px-1 py-0.5 border rounded text-xs w-20 text-center {{ !$showTimeInputs ? 'bg-gray-100 text-gray-400' : '' }}">
                    </td>
                    <td class="px-2 py-1 text-center text-red-500 font-bold text-xs">
                        <span class="late-preview">{{ $log->late_minutes > 0 ? $log->late_minutes : '' }}</span>
                    </td>
                    <td class="px-2 py-1 text-center">
                        <input type="time" data-field="check_out"
                            value="{{ $log->check_out ? \Carbon\Carbon::parse($log->check_out)->format('H:i') : '' }}"
                            {{ !$showTimeInputs ? 'disabled' : '' }}
                            class="check-out-input px-1 py-0.5 border rounded text-xs w-20 text-center {{ !$showTimeInputs ? 'bg-gray-100 text-gray-400' : '' }}">
                    </td>
                    <td class="px-2 py-1 text-center">
                        <input type="number" data-field="late_minutes"
                            value="{{ $log->late_minutes }}" min="0"
                            {{ $disableLateInput ? 'disabled' : '' }}
                            class="late-minutes-input px-1 py-0.5 border rounded text-xs w-12 text-center {{ $disableLateInput ? 'bg-gray-100 text-gray-400' : '' }}">
                    </td>
                    <td class="px-2 py-1 text-center">
                        <input type="number" data-field="ot_minutes"
                            value="{{ $log->ot_minutes }}" min="0"
                            class="ot-minutes-input px-1 py-0.5 border rounded text-xs w-12 text-center">
                    </td>
                    <td class="px-2 py-1 text-center">
                        <div class="inline-flex items-center gap-1">
                            <input type="checkbox" data-field="ot_enabled"
                                {{ $log->ot_enabled ? 'checked' : '' }}
                                class="ot-enabled-input rounded border-gray-300 text-indigo-600 w-3 h-3">
                            @if(($log->ot_status ?? 'none') === 'requested' && $log->ot_request_id)
                                <button type="button"
                                        class="ot-request-info w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] font-bold inline-flex items-center justify-center hover:bg-sky-600 cursor-pointer"
                                        title="คำขอจากพนักงาน — คลิกเพื่อ Approve"
                                        data-ot-request-id="{{ $log->ot_request_id }}"
                                        data-ot-request-note="{{ e($log->ot_request_note) }}"
                                        data-ot-request-minutes="{{ $log->otRequest?->requested_minutes ?? $log->ot_minutes }}">i</button>
                            @elseif(($log->ot_status ?? 'none') === 'approved')
                                <span class="w-4 h-4 rounded-full bg-green-500 text-white text-[9px] font-bold inline-flex items-center justify-center" title="Approved by admin">✓</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-2 py-1 text-center">
                        <span class="ot-type-badge inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $otTypeClass }}">{{ $otTypeText }}</span>
                        @if(!empty($log->ot_request_note) && ($log->ot_status ?? 'none') !== 'approved')
                            <div class="text-[9px] text-sky-700 mt-0.5 truncate max-w-[160px]" title="{{ $log->ot_request_note }}">📝 {{ $log->ot_request_note }}</div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-2 bg-gray-50 flex justify-between items-center">
        <span id="attendance-row-status" class="text-xs text-gray-400"></span>
        <span class="text-[10px] text-gray-400">Auto-save เมื่อแก้ไข</span>
    </div>
</div>

@php
    $saveRowUrl = route('workspace.saveAttendanceRow', ['employee' => $employee->id, 'month' => $month, 'year' => $year]);
@endphp

<script>
(() => {
    const SAVE_URL = @json($saveRowUrl);
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const canManageWorkspace = {{ $canManageWorkspace ? 'true' : 'false' }};
    const targetCheckIn = "{{ $attendanceMeta['target_check_in'] ?? '09:30' }}";
    const targetCheckOut = "{{ $attendanceMeta['target_check_out'] ?? '18:30' }}";
    const targetMinutesPerDay = Number("{{ $attendanceMeta['target_minutes_per_day'] ?? 540 }}");
    const lunchBreakMinutes = Number("{{ $attendanceMeta['lunch_break_minutes'] ?? 60 }}");

    const dayTypeColors = @json($dayTypeColors);
    const holidayTypes = ['holiday', 'company_holiday', 'not_started'];
    const workdayTypes = ['workday', 'ot_full_day'];
    const holidayOvertimeTypes = ['holiday', 'company_holiday'];

    function toMinutes(timeString) {
        if (!timeString || !timeString.includes(':')) return null;
        const [h, m] = timeString.split(':').map(Number);
        if (Number.isNaN(h) || Number.isNaN(m)) return null;
        return h * 60 + m;
    }

    const targetInMinutes = toMinutes(targetCheckIn) ?? 570;
    const targetOutMinutes = toMinutes(targetCheckOut) ?? 1110;

    let isInitializing = true;
    setTimeout(() => { isInitializing = false; }, 1000);

    function formatMoney(n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatHoursClock(hours) {
        const totalMinutes = Math.round(Number(hours) * 60);
        const h = Math.floor(totalMinutes / 60);
        const m = totalMinutes % 60;
        return h + ':' + String(m).padStart(2, '0');
    }

    // Status indicator
    const statusEl = document.getElementById('attendance-save-status');
    const rowStatusEl = document.getElementById('attendance-row-status');

    function showStatus(text, color) {
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.className = `text-xs transition-all font-semibold ${color}`;
        }
    }

    function resetStatus() {
        setTimeout(() => {
            if (statusEl) {
                statusEl.textContent = 'คำนวณเวลา / performance';
                statusEl.className = 'text-xs opacity-80 transition-all';
            }
        }, 2000);
    }

    // Debounce per row
    const pendingTimers = {};

    function saveRow(row) {
        const logId = row.dataset.logId;
        const dayTypeSelect = row.querySelector('.day-type-select');
        const checkIn = row.querySelector('.check-in-input');
        const checkOut = row.querySelector('.check-out-input');
        const lateMin = row.querySelector('.late-minutes-input');
        const otMin = row.querySelector('.ot-minutes-input');
        const otEnabled = row.querySelector('.ot-enabled-input');

        const dayType = dayTypeSelect?.value || 'workday';

        const payload = {
            log_id: logId,
            data: {
                day_type: dayType,
                check_in: checkIn?.value || null,
                check_out: checkOut?.value || null,
                late_minutes: parseInt(lateMin?.value) || 0,
                ot_minutes: parseInt(otMin?.value) || 0,
                ot_enabled: otEnabled?.checked ? 1 : 0,
            }
        };

        showStatus('กำลังบันทึก...', 'text-yellow-200');
        row.classList.add('opacity-70');

        fetch(SAVE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(json => {
            row.classList.remove('opacity-70');
            if (json.ok) {
                // Update row fields from server response
                if (json.row) {
                    if (lateMin) lateMin.value = json.row.late_minutes;
                    if (otMin) otMin.value = json.row.ot_minutes;
                    const lp = row.querySelector('.late-preview');
                    if (lp) lp.textContent = json.row.late_minutes > 0 ? String(json.row.late_minutes) : '';
                }

                // Update summary cards
                updateSummary(json.summary, json.items);

                showStatus('✓ บันทึกแล้ว', 'text-green-200');
                row.style.outline = '2px solid #22c55e';
                setTimeout(() => { row.style.outline = ''; }, 800);
            } else {
                showStatus('✗ ' + (json.error || 'Error'), 'text-red-300');
            }
            resetStatus();
        })
        .catch(err => {
            row.classList.remove('opacity-70');
            showStatus('✗ เกิดข้อผิดพลาด', 'text-red-300');
            resetStatus();
            console.error('Attendance save error:', err);
        });
    }

    function scheduleRowSave(row) {
        if (isInitializing) return;
        const logId = row.dataset.logId;
        if (pendingTimers[logId]) clearTimeout(pendingTimers[logId]);
        pendingTimers[logId] = setTimeout(() => saveRow(row), 400);
    }

    // Update the payroll summary panel + top summary cards
    // Update the payroll summary panel + top summary cards
    window.updateSummary = function(summary, items) {
        // Top summary cards
        const incomeCard = document.getElementById('summary-total-income');
        const deductionCard = document.getElementById('summary-total-deduction');
        const netCard = document.getElementById('summary-net-pay');
        if (incomeCard) incomeCard.textContent = formatMoney(summary.total_income ?? 0);
        if (deductionCard) deductionCard.textContent = formatMoney(summary.total_deduction ?? 0);
        if (netCard) netCard.textContent = formatMoney(summary.net_pay ?? 0);

        // Right panel details
        const workHrs = document.getElementById('summary-work-hours');
        const otHrs = document.getElementById('summary-ot-hours');
        const lateInfo = document.getElementById('summary-late-info');
        const lwop = document.getElementById('summary-lwop-days');
        const netPay = document.getElementById('summary-net-pay-bottom');

        if (workHrs) workHrs.textContent = formatHoursClock(summary.total_work_hours ?? 0) + ' ชม.';
        if (otHrs) otHrs.textContent = formatHoursClock(summary.total_ot_hours ?? 0) + ' ชม.';
        if (lateInfo) lateInfo.textContent = (summary.late_count ?? 0) + ' ครั้ง (' + (summary.late_minutes ?? 0) + ' นาที)';
        if (lwop) lwop.textContent = (summary.lwop_days ?? 0) + ' วัน';
        if (netPay) netPay.textContent = formatMoney(summary.net_pay ?? 0);

        // Update income items
        const incomeContainer = document.getElementById('payroll-income-items');
        const deductionContainer = document.getElementById('payroll-deduction-items');

        if (incomeContainer && items) {
            incomeContainer.innerHTML = '';
            items.filter(i => i.category === 'income').forEach(item => {
                const isManual = ['manual', 'override'].includes(item.source_flag);
                const note = item.notes || item.note || '';
                const tipAttr = note ? `title="${note}"` : '';
                const tipClass = note ? 'cursor-help border-b border-dashed border-gray-300' : '';
                
                incomeContainer.innerHTML += `
                    <div class="flex justify-between text-sm py-1">
                        <span class="text-gray-600 flex items-center gap-1 ${tipClass}" ${tipAttr}>
                            ${item.label}
                            ${isManual && canManageWorkspace ? '<span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>' : ''}
                        </span>
                        <span class="font-medium ${item.amount > 0 ? '' : 'text-gray-400'}">${formatMoney(item.amount)}</span>
                    </div>
                `;
            });
        }

        if (deductionContainer && items) {
            deductionContainer.innerHTML = '';
            items.filter(i => i.category === 'deduction').forEach(item => {
                const isManual = ['manual', 'override'].includes(item.source_flag);
                const note = item.notes || item.note || '';
                const tipAttr = note ? `title="${note}"` : '';
                const tipClass = note ? 'cursor-help border-b border-dashed border-gray-300' : '';

                deductionContainer.innerHTML += `
                    <div class="flex justify-between text-sm py-1">
                        <span class="text-gray-600 flex items-center gap-1 ${tipClass}" ${tipAttr}>
                            ${item.label}
                            ${isManual && canManageWorkspace ? '<span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>' : ''}
                        </span>
                        <span class="font-medium ${item.amount > 0 ? '' : 'text-gray-400'}">${formatMoney(item.amount)}</span>
                    </div>
                `;
            });
        }
    }

    // Handle day type change: toggle row inputs & save immediately
    function handleDayTypeChange(row) {
        const dayType = row.querySelector('.day-type-select')?.value;
        const isHoliday = holidayTypes.includes(dayType);
        const isLwop = dayType === 'lwop';
        const isWorkday = workdayTypes.includes(dayType);
        const isHolidayOvertimeDay = holidayOvertimeTypes.includes(dayType);
        const showTime = (isWorkday || isHolidayOvertimeDay) && !isLwop;

        const checkIn = row.querySelector('.check-in-input');
        const checkOut = row.querySelector('.check-out-input');
        const lateInput = row.querySelector('.late-minutes-input');

        if (checkIn) {
            checkIn.disabled = !showTime;
            checkIn.classList.toggle('bg-gray-100', !showTime);
            checkIn.classList.toggle('text-gray-400', !showTime);
            if (!showTime) checkIn.value = '';
        }
        if (checkOut) {
            checkOut.disabled = !showTime;
            checkOut.classList.toggle('bg-gray-100', !showTime);
            checkOut.classList.toggle('text-gray-400', !showTime);
            if (!showTime) checkOut.value = '';
        }

        if (lateInput) {
            lateInput.disabled = !isWorkday;
            lateInput.classList.toggle('bg-gray-100', !isWorkday);
            lateInput.classList.toggle('text-gray-400', !isWorkday);
            if (!isWorkday) lateInput.value = 0;
        }

        // Update row background
        row.className = row.className.replace(/bg-\S+\/?\d*/g, '');
        if (isHoliday) row.classList.add('bg-gray-100/50');
        else if (isLwop) row.classList.add('bg-red-50/30');
        else row.classList.add('bg-white');

        // Update select color class
        const select = row.querySelector('.day-type-select');
        if (select && dayTypeColors[dayType]) {
            // Remove old color classes
            select.className = select.className.replace(/bg-\S+/g, '').replace(/text-\S+/g, '');
            select.classList.add('px-1', 'py-0.5', 'rounded', 'text-xs', 'border-0', 'font-medium', 'w-max', 'day-type-select');
            dayTypeColors[dayType].split(' ').forEach(c => select.classList.add(c));
        }

        updateOtTypeBadge(row);

        // Save immediately (no debounce for day type changes)
        saveRow(row);
    }

    // Local recalc for immediate feedback (before server response)
    function localRecalc(row) {
        const dayType = row.querySelector('.day-type-select')?.value || 'workday';
        const checkIn = row.querySelector('.check-in-input');
        const checkOut = row.querySelector('.check-out-input');
        const lateInput = row.querySelector('.late-minutes-input');
        const otInput = row.querySelector('.ot-minutes-input');
        const otEnabled = row.querySelector('.ot-enabled-input');
        const latePreview = row.querySelector('.late-preview');

        if (!checkIn || !checkOut || !lateInput || !otInput) return;

        const inMin = toMinutes(checkIn.value);
        const outMinRaw = toMinutes(checkOut.value);
        if (inMin === null || outMinRaw === null) return;

        let outMin = outMinRaw;
        if (outMin <= inMin) outMin += 24 * 60;

        const isWorkday = workdayTypes.includes(dayType);
        const isHolidayOvertimeDay = holidayOvertimeTypes.includes(dayType);
        const late = isWorkday ? Math.max(0, inMin - targetInMinutes) : 0;
        const worked = Math.max(0, (outMin - inMin) - lunchBreakMinutes);

        let ot = 0;
        if (otEnabled && otEnabled.checked) {
            // NEW: Clock-based OT (After target out)
            if (outMin > targetOutMinutes) {
                ot = outMin - targetOutMinutes;
            }
        }

        lateInput.value = late;
        otInput.value = ot;
        if (latePreview) latePreview.textContent = late > 0 ? String(late) : '';
    }

    function updateOtTypeBadge(row) {
        const dayType = row.querySelector('.day-type-select')?.value || 'workday';
        const otEnabled = row.querySelector('.ot-enabled-input')?.checked;
        const otMinutes = parseInt(row.querySelector('.ot-minutes-input')?.value || '0', 10);
        const badge = row.querySelector('.ot-type-badge');
        if (!badge) return;

        badge.className = 'ot-type-badge inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold';

        if (!otEnabled || otMinutes <= 0) {
            badge.textContent = '-';
            badge.classList.add('bg-gray-100', 'text-gray-500');
            return;
        }

        if (holidayOvertimeTypes.includes(dayType)) {
            badge.textContent = 'OT วันหยุด';
            badge.classList.add('bg-purple-100', 'text-purple-700');
            return;
        }

        if (workdayTypes.includes(dayType)) {
            badge.textContent = 'OT ปกติ';
            badge.classList.add('bg-indigo-100', 'text-indigo-700');
            return;
        }

        badge.textContent = '-';
        badge.classList.add('bg-gray-100', 'text-gray-500');
    }

    // Attach events
    document.querySelectorAll('.attendance-row').forEach(row => {
        const dayTypeSelect = row.querySelector('.day-type-select');
        const checkIn = row.querySelector('.check-in-input');
        const checkOut = row.querySelector('.check-out-input');
        const lateInput = row.querySelector('.late-minutes-input');
        const otInput = row.querySelector('.ot-minutes-input');
        const otEnabled = row.querySelector('.ot-enabled-input');

        // Day type change → immediate save
        dayTypeSelect?.addEventListener('change', () => handleDayTypeChange(row));

        // Time inputs → local recalc + debounced save
        [checkIn, checkOut].forEach(input => {
            input?.addEventListener('change', () => {
                localRecalc(row);
                updateOtTypeBadge(row);
                scheduleRowSave(row);
            });
        });

        // OT checkbox → local recalc + debounced save
        otEnabled?.addEventListener('change', () => {
            localRecalc(row);
            updateOtTypeBadge(row);
            scheduleRowSave(row);
        });

        // Manual late/ot inputs → debounced save
        [lateInput, otInput].forEach(input => {
            input?.addEventListener('change', () => {
                updateOtTypeBadge(row);
                scheduleRowSave(row);
            });
        });

        updateOtTypeBadge(row);
    });

    // OT request (i) click → approve popup
    document.querySelectorAll('.ot-request-info').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.dataset.otRequestId;
            const note = btn.dataset.otRequestNote || '';
            const mins = btn.dataset.otRequestMinutes || '0';
            if (!confirm(`คำขอ OT จากพนักงาน (${mins} นาที):\n\n"${note}"\n\nกด OK เพื่อ Approve`)) return;

            fetch(`/ot/request/${id}/approve`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                }
            }).then(r => {
                if (r.ok || r.redirected) {
                    window.toast && window.toast('อนุมัติ OT แล้ว', 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    window.toast && window.toast('อนุมัติไม่สำเร็จ', 'error');
                }
            });
        });
    });
})();
</script>
