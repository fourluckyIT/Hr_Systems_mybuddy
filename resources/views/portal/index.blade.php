@extends('layouts.app')

@section('title', 'ศูนย์เอกสาร')

@section('content')
@php
    $statusMeta = [
        'pending'   => ['label' => 'รออนุมัติ',  'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'approved'  => ['label' => 'อนุมัติแล้ว', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'rejected'  => ['label' => 'ไม่อนุมัติ',  'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว',  'class' => 'bg-gray-100 text-gray-600 border-gray-300'],
    ];
@endphp

<div class="max-w-7xl mx-auto"
     x-data="{
         selected: [],
         toggle(key) { this.selected.includes(key) ? this.selected = this.selected.filter(s => s !== key) : this.selected.push(key); },
         clear() { this.selected = []; },
     }">

    @php
        $myEmp = auth()->user()->employee ?? null;
    @endphp

    <div class="mb-5 flex items-start justify-between gap-3 flex-wrap" x-data="{ submitMenu: false, carryoverOpen: false, encashOpen: false, leaveModal: false, swapModal: false }">
        <div>
            <h1 class="text-xl font-bold text-gray-900">ศูนย์เอกสาร</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($isAdmin)
                    เอกสารคำขอทั้งหมดของพนักงาน — อนุมัติ พิมพ์ และส่งออก
                @else
                    เอกสารคำขอของฉัน — ดู พิมพ์ และแนบไฟล์
                @endif
            </p>
        </div>
        @if($myEmp && !$isAdmin)
            <div class="relative">
                <button @click="submitMenu = !submitMenu" @click.outside="submitMenu = false"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 inline-flex items-center gap-1.5">
                    + ส่งคำขอใหม่
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="submitMenu" x-cloak class="absolute right-0 mt-1 w-60 bg-white rounded-lg shadow-xl border border-gray-100 z-20 py-1 overflow-hidden">
                    <button @click="leaveModal = true; submitMenu = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-rose-50 hover:text-rose-700">🏖 ขอลา</button>
                    <a href="{{ route('ot.request') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700">⏰ ขอ OT</a>
                    <button @click="swapModal = true; submitMenu = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700">🔄 สลับวันหยุด</button>
                    <div class="my-1 border-t border-gray-100"></div>
                    <button @click="carryoverOpen = true; submitMenu = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700">📥 ยกยอดวันลาข้ามปี</button>
                    <button @click="encashOpen = true; submitMenu = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">💵 แลกวันลาเป็นเงิน</button>
                </div>

                {{-- Leave / Swap Modals (reusing partials from leave.partials) --}}
                @include('leave.partials.modal-leave')
                @include('leave.partials.modal-swap')
            </div>
        @elseif($myEmp && $isAdmin)
            <div class="text-xs text-gray-400 self-center italic">แอดมินส่งคำขอ direct จากหน้า workspace</div>

            {{-- Carryover Request Modal --}}
            <div x-show="carryoverOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="carryoverOpen = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
                    <h3 class="text-lg font-bold mb-1">📥 ขอยกยอดวันลาข้ามปี</h3>
                    <p class="text-xs text-gray-500 mb-4">ส่งคำขอ — ผู้ดูแลจะตรวจสอบและอนุมัติ</p>
                    <form method="POST" action="{{ route('leave-balance.carryover.request', $myEmp->id) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="leave_type" value="vacation_leave">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">จากปี (ปัจจุบัน)</label>
                                <input type="number" name="source_year" required value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">ไปปี (ปลายทาง)</label>
                                <input type="number" name="target_year" required value="{{ now()->year + 1 }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนวัน</label>
                            <input type="number" name="days" step="0.5" min="0.5" max="365" required class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น 3">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
                            <input type="text" name="note" maxlength="255" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น ใช้ปีนี้ไม่ทัน">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="carryoverOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                            <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">ส่งคำขอ</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Encash Request Modal --}}
            <div x-show="encashOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="encashOpen = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
                    <h3 class="text-lg font-bold mb-1">💵 ขอแลกวันลาเป็นเงิน</h3>
                    <p class="text-xs text-gray-500 mb-4">ส่งคำขอ — ผู้ดูแลจะตรวจสอบและอนุมัติ (เงินจะถูกเพิ่มในสลิปเดือนที่ระบุ)</p>
                    <form method="POST" action="{{ route('leave-balance.encash.request', $myEmp->id) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="leave_type" value="vacation_leave">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">ปีของสิทธิ</label>
                                <input type="number" name="year" required value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนวัน</label>
                                <input type="number" name="days" step="0.5" min="0.5" max="365" required class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="เช่น 2">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">อัตรา/วัน (เว้นว่าง = ใช้ฐานเงินเดือน/30)</label>
                            <input type="number" name="rate_per_day" step="0.01" min="0" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">เดือนที่จะจ่าย</label>
                                <select name="payout_month" required class="w-full px-3 py-2 border rounded-lg text-sm">
                                    @foreach(['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'] as $i => $name)
                                        <option value="{{ $i + 1 }}" {{ ($i + 1) === (int) now()->month ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">ปีที่จะจ่าย</label>
                                <input type="number" name="payout_year" required value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
                            <input type="text" name="note" maxlength="255" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="encashOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                            <button type="submit" class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">ส่งคำขอ</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-3 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-3 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Stats (neutral, restrained) --}}
    <div class="bg-white border border-gray-200 rounded-lg mb-3 grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-100 text-sm overflow-hidden">
        <div class="px-5 py-3 flex items-baseline gap-2">
            <span class="text-gray-500">ทั้งหมด</span>
            <strong class="text-gray-900 text-base">{{ $stats['total'] }}</strong>
        </div>
        <div class="px-5 py-3 flex items-baseline gap-2">
            <span class="text-gray-500">รออนุมัติ</span>
            <strong class="text-amber-700 text-base">{{ $stats['pending'] }}</strong>
        </div>
        <div class="px-5 py-3 flex items-baseline gap-2">
            <span class="text-gray-500">อนุมัติแล้ว</span>
            <strong class="text-emerald-700 text-base">{{ $stats['approved'] }}</strong>
        </div>
        <div class="px-5 py-3 flex items-baseline gap-2">
            <span class="text-gray-500">ไม่อนุมัติ</span>
            <strong class="text-rose-700 text-base">{{ $stats['rejected'] }}</strong>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('portal.index') }}"
          class="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[150px]">
            <label class="block text-xs font-semibold text-gray-600 mb-1">ประเภท</label>
            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white transition-colors">
                <option value="all">ทั้งหมด</option>
                @foreach($types as $slug => $meta)
                    <option value="{{ $slug }}" @selected($filters['typeFilter'] === $slug)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[120px]">
            <label class="block text-xs font-semibold text-gray-600 mb-1">สถานะ</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white transition-colors">
                <option value="all">ทั้งหมด</option>
                <option value="pending"   @selected($filters['statusFilter'] === 'pending')>รออนุมัติ</option>
                <option value="approved"  @selected($filters['statusFilter'] === 'approved')>อนุมัติแล้ว</option>
                <option value="rejected"  @selected($filters['statusFilter'] === 'rejected')>ไม่อนุมัติ</option>
                <option value="cancelled" @selected($filters['statusFilter'] === 'cancelled')>ยกเลิกแล้ว</option>
            </select>
        </div>
        @if($isAdmin)
        <div class="w-full md:w-auto md:flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-gray-600 mb-1">พนักงาน</label>
            <select name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white transition-colors">
                <option value="">— ทั้งหมด —</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" @selected($filters['employeeFilter'] == $emp->id)>
                        [{{ $emp->employee_code }}] {{ $emp->first_name }} {{ $emp->last_name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="w-24">
            <label class="block text-xs font-semibold text-gray-600 mb-1">ปี</label>
            <input type="number" name="year" value="{{ $filters['year'] }}" min="2020" max="2099" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white transition-colors">
        </div>
        <div class="w-24">
            <label class="block text-xs font-semibold text-gray-600 mb-1">เดือน</label>
            <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white transition-colors">
                <option value="">ทั้งปี</option>
                @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" @selected($filters['month'] == $m)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-5 py-2 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900 shadow-sm transition-colors">กรอง</button>
            <a href="{{ route('portal.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 transition-colors">ล้าง</a>
        </div>
    </form>

    {{-- Bulk export bar (admin only) --}}
    @if($isAdmin)
        <div x-show="selected.length > 0"
             x-cloak
             x-transition
             class="mb-3 p-3 bg-gray-800 text-white rounded-lg flex items-center justify-between">
            <div class="text-sm">
                เลือกไว้ <strong x-text="selected.length"></strong> เอกสาร
                <button type="button" @click="clear()" class="ml-3 text-gray-300 hover:text-white text-xs underline">ยกเลิก</button>
            </div>
            <div class="flex gap-2">
                <form action="{{ route('portal.bulk-export') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="mode" value="single">
                    <template x-for="key in selected" :key="key">
                        <input type="hidden" name="documents[]" :value="key">
                    </template>
                    <button type="submit" class="px-3 py-1.5 bg-white text-gray-800 rounded-lg text-xs font-semibold hover:bg-gray-100">รวม PDF เดียว</button>
                </form>
                <form action="{{ route('portal.bulk-export') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="mode" value="separate">
                    <template x-for="key in selected" :key="key">
                        <input type="hidden" name="documents[]" :value="key">
                    </template>
                    <button type="submit" class="px-3 py-1.5 bg-white text-gray-800 rounded-lg text-xs font-semibold hover:bg-gray-100">ZIP แยกไฟล์</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Document list --}}
    @if($documents->isEmpty())
        <div class="py-16 text-center bg-white border border-gray-200 rounded-lg">
            <p class="text-sm text-gray-500">ไม่พบเอกสาร</p>
            <p class="text-xs text-gray-400 mt-1">ลองปรับตัวกรอง หรือสร้างคำขอใหม่</p>
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    @if($isAdmin)<col class="w-10">@endif
                    <col class="w-32">
                    <col class="w-28">
                    <col class="w-48">
                    <col class="w-28">
                    <col>
                    <col class="w-28">
                    <col class="w-28">
                </colgroup>
                <thead class="bg-gray-50 text-xs text-gray-500 border-b border-gray-200">
                    <tr>
                        @if($isAdmin)<th class="px-3 py-3"></th>@endif
                        <th class="px-3 py-3 text-left font-semibold">เอกสาร</th>
                        <th class="px-3 py-3 text-left font-semibold">ประเภท</th>
                        <th class="px-3 py-3 text-left font-semibold">พนักงาน</th>
                        <th class="px-3 py-3 text-left font-semibold">วันที่</th>
                        <th class="px-3 py-3 text-left font-semibold">รายละเอียด</th>
                        <th class="px-3 py-3 text-center font-semibold">สถานะ</th>
                        <th class="px-3 py-3 text-right font-semibold">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($documents as $d)
                        @php
                            $key = $d['type'] . ':' . $d['id'];
                            $st = $statusMeta[$d['status']] ?? ['label' => $d['status'], 'class' => 'bg-gray-50 text-gray-700 border-gray-200'];
                        @endphp
                        <tr class="hover:bg-gray-50/70 align-middle">
                            @if($isAdmin)
                                <td class="px-3 py-3">
                                    <input type="checkbox" :checked="selected.includes('{{ $key }}')" @change="toggle('{{ $key }}')"
                                           class="rounded border-gray-300">
                                </td>
                            @endif
                            <td class="px-3 py-3 font-mono text-xs text-gray-700 truncate">{{ $d['doc_number'] }}</td>
                            <td class="px-3 py-3 text-gray-700 truncate">{{ $d['meta']['label'] }}</td>
                            <td class="px-3 py-3 min-w-0">
                                <div class="text-gray-800 truncate">{{ $d['employee']?->first_name }} {{ $d['employee']?->last_name }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ $d['employee']?->position?->name ?? '—' }}</div>
                            </td>
                            <td class="px-3 py-3 text-gray-700 whitespace-nowrap tabular-nums">{{ optional($d['date'])->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-3 min-w-0">
                                <p class="text-gray-700 truncate" title="{{ $d['summary'] }}">{{ $d['summary'] }}</p>
                                @if($d['attachments_count'] > 0)
                                    <p class="text-xs text-gray-400 mt-0.5">ไฟล์แนบ {{ $d['attachments_count'] }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium border whitespace-nowrap min-w-[78px] {{ $st['class'] }}">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('portal.show', [$d['type'], $d['id']]) }}"
                                       class="px-2 py-1 text-xs text-gray-700 border border-gray-200 hover:bg-gray-100 rounded">ดู</a>
                                    <a href="{{ route('portal.print', [$d['type'], $d['id']]) }}" target="_blank"
                                       class="px-2 py-1 text-xs text-gray-700 border border-gray-200 hover:bg-gray-100 rounded">พิมพ์</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
