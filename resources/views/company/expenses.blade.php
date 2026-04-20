@extends('layouts.app')

@section('title', 'ค่าใช้จ่ายบริษัท')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">ค่าใช้จ่ายบริษัท (Company Expenses)</h1>
        <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700">
            + บันทึกค่าใช้จ่าย
        </button>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 p-8 text-center text-gray-500">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-1">ยังไม่มีรายการค่าใช้จ่าย</h3>
        <p>หน้านี้เป็นส่วนที่รอการพัฒนาเพิ่มเติมสำหรับสรุป P&L รายได้และรายจ่ายบริษัทย้อนหลัง</p>
    </div>
</div>
@endsection
