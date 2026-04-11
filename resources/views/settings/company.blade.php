@extends('layouts.app')
@section('title', 'Company Settings')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">การตั้งค่าบริษัท</h1>
        <a href="{{ route('settings.rules') }}" class="text-indigo-600 hover:text-indigo-700 text-sm">← ย้อนกลับ</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
        {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-6 space-y-6">
        @csrf

        <!-- Company Identity -->
        <fieldset class="border border-gray-200 rounded-lg p-4">
            <legend class="text-lg font-semibold text-indigo-600 px-2">ข้อมูลบริษัท</legend>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อบริษัท</label>
                    <input type="text" name="name" value="{{ old('name', $company?->name) }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           required />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เลขประจำตัวประเมิน</label>
                    <input type="text" name="tax_id" value="{{ old('tax_id', $company?->tax_id) }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="1234567890" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tagline/คำอธิบาย</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $company?->tagline) }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="LowGrade โดย นิติบุคคล นายสรรวิน สาสาสันต์" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อเพิ่มเติมใน Payslip Header (หลัง /)</label>
                    <input type="text" name="payslip_header_subtitle" value="{{ old('payslip_header_subtitle', $company?->payslip_header_subtitle) }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="นิติบุคคล นายสรรวิน สาสาสันต์" />
                    <p class="text-xs text-gray-500 mt-1">ตัวอย่าง: Pro One IT Co., Ltd. / <strong>นิติบุคคล นายสรรวิน สาสาสันต์</strong></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
                    <textarea name="address" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">{{ old('address', $company?->address) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">โทรศัพท์</label>
                        <input type="tel" name="phone" value="{{ old('phone', $company?->phone) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                        <input type="email" name="email" value="{{ old('email', $company?->email) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- Branding Colors -->
        <fieldset class="border border-gray-200 rounded-lg p-4">
            <legend class="text-lg font-semibold text-indigo-600 px-2">สี CI / Branding</legend>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
                    <input type="color" name="primary_color" value="{{ old('primary_color', $company?->primary_color ?? '#4f46e5') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg h-10" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secondary Color</label>
                    <input type="color" name="secondary_color" value="{{ old('secondary_color', $company?->secondary_color ?? '#4338ca') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg h-10" />
                </div>
            </div>
        </fieldset>

        <!-- Payslip Customization -->
        <fieldset class="border border-gray-200 rounded-lg p-4">
            <legend class="text-lg font-semibold text-indigo-600 px-2">Payslip ลายเซ็น</legend>

            <div class="mt-4 space-y-4">
                <!-- Approver (ผู้จ่าย) -->
                <div class="p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-blue-900 mb-3">ผู้จ่ายเงินเดือน</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ</label>
                            <input type="text" name="signature_approver_name" value="{{ old('signature_approver_name', $company?->signature_approver_name) }}" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="นายสรรวิน สาสาสันต์" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">อัพโหลด PNG/JPG ลายเซ็น</label>
                            <input type="file" name="signature_approver_image" accept="image/png,image/jpeg" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                            <p class="text-xs text-gray-500 mt-1">สูงสุด 2MB (PNG/JPG สีขาว)</p>
                        </div>
                    </div>

                    @if($company?->signature_approver_image_path)
                    <div class="mt-2">
                        <p class="text-sm text-gray-600 mb-1">ลายเซ็นปัจจุบัน:</p>
                        <img src="{{ asset('storage/' . $company->signature_approver_image_path) }}" 
                             alt="Approver signature" class="max-h-12 border border-gray-300 rounded" />
                    </div>
                    @endif
                </div>

                <!-- Receiver (ผู้รับ) -->
                <div class="p-4 bg-green-50 rounded-lg">
                    <h3 class="font-semibold text-green-900 mb-3">ผู้รับเงินเดือน</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ (optional)</label>
                            <input type="text" name="signature_receiver_name" value="{{ old('signature_receiver_name', $company?->signature_receiver_name) }}" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="พนักงาน" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">อัพโหลด PNG/JPG ลายเซ็น</label>
                            <input type="file" name="signature_receiver_image" accept="image/png,image/jpeg" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                            <p class="text-xs text-gray-500 mt-1">สูงสุด 2MB (PNG/JPG สีขาว)</p>
                        </div>
                    </div>

                    @if($company?->signature_receiver_image_path)
                    <div class="mt-2">
                        <p class="text-sm text-gray-600 mb-1">ลายเซ็นปัจจุบัน:</p>
                        <img src="{{ asset('storage/' . $company->signature_receiver_image_path) }}" 
                             alt="Receiver signature" class="max-h-12 border border-gray-300 rounded" />
                    </div>
                    @endif
                </div>
            </div>
        </fieldset>

        <!-- Payslip Footer -->
        <fieldset class="border border-gray-200 rounded-lg p-4">
            <legend class="text-lg font-semibold text-indigo-600 px-2">ข้อท้าย Payslip</legend>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ข้อความท้ายสลิป</label>
                <textarea name="payslip_footer_text" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">{{ old('payslip_footer_text', $company?->payslip_footer_text) }}</textarea>
            </div>
        </fieldset>

        <!-- Submit -->
        <div class="flex justify-end gap-2">
            <a href="{{ route('settings.rules') }}" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</a>
            <button type="submit" class="px-4 py-2 text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">บันทึก</button>
        </div>
    </form>
</div>
@endsection
