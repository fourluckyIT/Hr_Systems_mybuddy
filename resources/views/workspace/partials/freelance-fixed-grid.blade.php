{{-- Freelance Fixed Grid — Assignment-driven only --}}
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 bg-emerald-600 text-white font-semibold text-sm flex items-center justify-between">
        <span>ฟรีแลนซ์ ฟิกเรท ({{ $employee->display_name }})</span>
        <span class="text-[10px] px-2 py-0.5 bg-white/20 rounded">{{ $workLogs->count() }} รายการ</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-2 py-2 text-center w-10">ข้าม</th>
                    <th class="px-2 py-2 text-center w-10">#</th>
                    <th class="px-2 py-2 text-left min-w-[180px]">ชื่องาน</th>
                    <th class="px-2 py-2 text-center w-24">จำนวน (ชิ้น)</th>
                    <th class="px-2 py-2 text-right w-32">เรท/ชิ้น</th>
                    <th class="px-2 py-2 text-right w-32">ยอดเงิน</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workLogs as $i => $log)
                <tr class="border-t hover:bg-gray-50 {{ $log->is_disabled ? 'opacity-40' : '' }}">
                    <td class="px-2 py-1.5 text-center">
                        <form method="POST" action="{{ route('workspace.toggleWorkLog', $log->id) }}" class="inline">
                            @csrf
                            <input type="checkbox" onchange="this.form.submit()" {{ $log->is_disabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-red-600 focus:ring-red-500 w-3.5 h-3.5 cursor-pointer">
                        </form>
                    </td>
                    <td class="px-2 py-1.5 text-center text-gray-400">
                        {{ $i + 1 }}
                        @if($log->source_flag === 'auto')
                            <span class="block text-[8px] bg-blue-100 text-blue-600 px-1 rounded font-bold mt-0.5">Auto</span>
                        @endif
                    </td>
                    <td class="px-2 py-1.5 text-sm font-medium text-gray-800">
                        {{ $log->notes ? str_replace('Auto: ', '', $log->notes) : ($log->work_type ?? '-') }}
                    </td>
                    <td class="px-2 py-1.5 text-center">{{ $log->quantity }}</td>
                    <td class="px-2 py-1.5 text-right">{{ number_format((float) $log->rate, 2) }}</td>
                    <td class="px-2 py-1.5 text-right font-semibold">{{ number_format((float) $log->amount, 2) }}</td>
                </tr>
                @empty
                <tr class="border-t">
                    <td colspan="6" class="px-2 py-6 text-center">
                        <div class="text-sm font-medium text-gray-500">ยังไม่มีงานฟิกเรท</div>
                        <div class="mt-1 text-[11px] text-gray-400">Assign งานตัดต่อจาก WORK Center ให้ฟรีแลนซ์คนนี้ &rarr; งานจะขึ้นอัตโนมัติเมื่อปิดงาน</div>
                    </td>
                </tr>
                @endforelse
            </tbody>

        </table>
    </div>
</div>
