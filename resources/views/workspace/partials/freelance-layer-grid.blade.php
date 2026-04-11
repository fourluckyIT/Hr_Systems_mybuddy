{{-- Freelance Layer Grid — Assignment-driven only --}}
@php
    $templateLogs = $workLogs->filter(fn($log) => ($log->pricing_mode ?? 'template') !== 'custom')->values();
    $isolatedLogs = $workLogs->filter(fn($log) => ($log->pricing_mode ?? 'template') === 'custom')->values();
@endphp

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 bg-green-600 text-white font-semibold text-sm flex items-center justify-between">
        <span>ฟรีแลนซ์ เรทเลเยอร์ ({{ $employee->display_name }})</span>
        <span class="text-[10px] px-2 py-0.5 bg-white/20 rounded">{{ $workLogs->count() }} รายการ</span>
    </div>

    {{-- กลุ่มราคา 1: Template --}}
    <div class="p-3 bg-green-50 border-b border-green-100">
        <h4 class="font-semibold text-sm text-green-800">กลุ่มราคา 1: Template Layer</h4>
        <p class="text-xs text-green-600 mt-0.5">งานที่ assign แบบเลือกวงราคาจาก Rate Rules</p>
    </div>

    <div class="overflow-x-auto border-b">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-2 py-2 text-center w-10">ข้าม</th>
                    <th class="px-2 py-2 text-center w-10">#</th>
                    <th class="px-2 py-2 text-left min-w-[180px]">ชื่องาน</th>
                    <th class="px-2 py-2 text-center w-24">วงราคา</th>
                    <th class="px-2 py-2 text-center w-28">เวลา</th>
                    <th class="px-2 py-2 text-right w-24">เรท/นาที</th>
                    <th class="px-2 py-2 text-right w-28">รายได้</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templateLogs as $i => $log)
                @php
                    $durationMinutes = ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
                    $durationHms = \App\Support\DurationInput::formatMinutesAsHms($durationMinutes);
                    $amount = (float) $log->amount;
                @endphp
                <tr class="border-t hover:bg-gray-50 {{ $log->is_disabled ? 'opacity-40' : '' }}">
                    <td class="px-2 py-1.5 text-center">
                        <form method="POST" action="{{ route('workspace.toggleWorkLog', $log->id) }}" class="inline">
                            @csrf
                            <input type="checkbox" onchange="this.form.submit()" {{ $log->is_disabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-red-600 focus:ring-red-500 w-3.5 h-3.5 cursor-pointer">
                        </form>
                    </td>
                    <td class="px-2 py-1.5 text-center text-gray-400 text-xs">
                        {{ $i + 1 }}
                        @if($log->source_flag === 'auto')
                            <span class="block text-[8px] bg-blue-100 text-blue-600 px-1 rounded font-bold mt-0.5">Auto</span>
                        @endif
                    </td>
                    <td class="px-2 py-1.5 text-sm font-medium text-gray-800">
                        {{ $log->notes ? str_replace('Auto: ', '', $log->notes) : ($log->work_type ?? '-') }}
                    </td>
                    <td class="px-2 py-1.5 text-center">
                        <span class="inline-block px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">
                            {{ $log->pricing_template_label ?? 'L?' }}
                        </span>
                    </td>
                    <td class="px-2 py-1.5 text-center font-mono text-xs">{{ $durationHms }}</td>
                    <td class="px-2 py-1.5 text-right text-xs">{{ number_format((float) $log->rate, 2) }}</td>
                    <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($amount, 2) }}</td>
                </tr>
                @empty
                <tr class="border-t">
                    <td colspan="7" class="px-2 py-6 text-center">
                        <div class="text-sm font-medium text-gray-500">ยังไม่มีงาน Template</div>
                        <div class="mt-1 text-[11px] text-gray-400">Assign งานตัดต่อจาก WORK Center แล้วเลือก &ldquo;Template Layer&rdquo; &rarr; งานจะขึ้นอัตโนมัติเมื่อปิดงาน</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- กลุ่มราคา 2: Isolated --}}
    <div class="p-3 bg-indigo-50 border-b border-indigo-100">
        <h4 class="font-semibold text-sm text-indigo-800">กลุ่มราคา 2: Isolated</h4>
        <p class="text-xs text-indigo-600 mt-0.5">งานที่ assign แบบกำหนดเรทเฉพาะรายการ</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-2 py-2 text-center w-10">ข้าม</th>
                    <th class="px-2 py-2 text-center w-10">#</th>
                    <th class="px-2 py-2 text-left min-w-[180px]">ชื่องาน</th>
                    <th class="px-2 py-2 text-center w-20">Layer</th>
                    <th class="px-2 py-2 text-center w-28">เวลา</th>
                    <th class="px-2 py-2 text-right w-24">เรท/นาที</th>
                    <th class="px-2 py-2 text-right w-28">รายได้</th>
                </tr>
            </thead>
            <tbody>
                @forelse($isolatedLogs as $i => $log)
                @php
                    $durationMinutes = ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
                    $durationHms = \App\Support\DurationInput::formatMinutesAsHms($durationMinutes);
                    $amount = (float) $log->amount;
                @endphp
                <tr class="border-t hover:bg-gray-50 {{ $log->is_disabled ? 'opacity-40' : '' }}">
                    <td class="px-2 py-1.5 text-center">
                        <form method="POST" action="{{ route('workspace.toggleWorkLog', $log->id) }}" class="inline">
                            @csrf
                            <input type="checkbox" onchange="this.form.submit()" {{ $log->is_disabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-red-600 focus:ring-red-500 w-3.5 h-3.5 cursor-pointer">
                        </form>
                    </td>
                    <td class="px-2 py-1.5 text-center text-gray-400 text-xs">
                        {{ $i + 1 }}
                        @if($log->source_flag === 'auto')
                            <span class="block text-[8px] bg-blue-100 text-blue-600 px-1 rounded font-bold mt-0.5">Auto</span>
                        @endif
                    </td>
                    <td class="px-2 py-1.5 text-sm font-medium text-gray-800">
                        {{ $log->notes ? str_replace('Auto: ', '', $log->notes) : ($log->work_type ?? '-') }}
                    </td>
                    <td class="px-2 py-1.5 text-center text-xs">{{ $log->layer ?? '-' }}</td>
                    <td class="px-2 py-1.5 text-center font-mono text-xs">{{ $durationHms }}</td>
                    <td class="px-2 py-1.5 text-right text-xs">{{ number_format((float) ($log->custom_rate ?? $log->rate), 2) }}</td>
                    <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($amount, 2) }}</td>
                </tr>
                @empty
                <tr class="border-t">
                    <td colspan="7" class="px-2 py-6 text-center">
                        <div class="text-sm font-medium text-gray-500">ยังไม่มีงาน Isolated</div>
                        <div class="mt-1 text-[11px] text-gray-400">Assign งานตัดต่อจาก WORK Center แล้วเลือก &ldquo;Isolated&rdquo; &rarr; งานจะขึ้นอัตโนมัติเมื่อปิดงาน</div>
                    </td>
                </tr>
                @endforelse
            </tbody>

        </table>
    </div>
</div>
