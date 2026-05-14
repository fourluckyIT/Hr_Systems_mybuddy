<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Services\DocumentFieldCatalog;
use App\Services\DocxTemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateController extends Controller
{
    public function index()
    {
        $templates = DocumentTemplate::orderBy('doc_type')->orderBy('variant_key')->get();
        return view('settings.document-templates.index', [
            'templates' => $templates,
            'docTypes'  => DocumentFieldCatalog::docTypeChoices(),
            'libreOfficeOk' => DocxTemplateRenderer::libreOfficeAvailable(),
        ]);
    }

    public function create(Request $request)
    {
        $docType = $request->string('doc_type')->toString() ?: 'leave';
        return view('settings.document-templates.create', [
            'docType'        => $docType,
            'docTypes'       => DocumentFieldCatalog::docTypeChoices(),
            'variantChoices' => DocumentFieldCatalog::variantChoices($docType),
            'libreOfficeOk'  => DocxTemplateRenderer::libreOfficeAvailable(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'doc_type'    => ['required', 'string', 'in:' . implode(',', array_keys(DocumentFieldCatalog::docTypeChoices()))],
            'variant_key' => ['nullable', 'string', 'max:64'],
            'name'        => ['required', 'string', 'max:120'],
            'kind'        => ['required', 'string', 'in:docx,image'],
            'file'        => ['required', 'file', 'max:16384'],
        ]);

        $kind = $validated['kind'];
        $file = $request->file('file');

        if ($kind === 'docx') {
            $ext = strtolower($file->getClientOriginalExtension());
            abort_unless(in_array($ext, ['docx']), 422, 'ต้องอัปโหลด .docx เท่านั้น');
            $w = 0; $h = 0;
        } else {
            $mime = $file->getMimeType();
            abort_unless(str_starts_with((string) $mime, 'image/'), 422, 'ต้องอัปโหลดภาพ PNG/JPG');
            [$w, $h] = getimagesize($file->getRealPath()) ?: [0, 0];
        }

        $path = $file->store('document-templates', 'public');

        $template = DocumentTemplate::create([
            'doc_type'     => $validated['doc_type'],
            'kind'         => $kind,
            'variant_key'  => $validated['variant_key'] ?: null,
            'name'         => $validated['name'],
            'image_path'   => $path,  // also used for .docx files (column name is legacy)
            'image_width'  => $w,
            'image_height' => $h,
            'is_active'    => true,
        ]);

        return redirect()->route('document-templates.edit', $template)
            ->with('success', $kind === 'docx'
                ? 'อัปโหลด .docx สำเร็จ — ตรวจสอบ placeholder ที่ระบบรับรู้ด้านล่าง'
                : 'อัปโหลดภาพสำเร็จ — คลิกบนภาพเพื่อกำหนดตำแหน่งฟิลด์');
    }

    public function edit(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->load('fields');

        if ($documentTemplate->kind === 'docx') {
            $docxPath = storage_path('app/public/' . $documentTemplate->image_path);
            $placeholdersInDoc = DocxTemplateRenderer::inspectPlaceholders($docxPath);
            $catalog = DocumentFieldCatalog::fields($documentTemplate->doc_type);

            return view('settings.document-templates.edit-docx', [
                'template'          => $documentTemplate,
                'catalog'           => $catalog,
                'placeholdersInDoc' => $placeholdersInDoc,
                'libreOfficeOk'     => DocxTemplateRenderer::libreOfficeAvailable(),
            ]);
        }

        return view('settings.document-templates.edit', [
            'template' => $documentTemplate,
            'catalog'  => DocumentFieldCatalog::fields($documentTemplate->doc_type),
            'preset'   => DocumentFieldCatalog::presets($documentTemplate->doc_type, $documentTemplate->variant_key),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->kind === 'docx') {
            return $this->updateDocx($request, $documentTemplate);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'variant_key' => ['nullable', 'string', 'max:64'],
            'is_active'   => ['nullable', 'boolean'],
            'fields'      => ['nullable', 'array'],
            'fields.*.field_key'    => ['required', 'string', 'max:64'],
            'fields.*.x'            => ['required', 'integer'],
            'fields.*.y'            => ['required', 'integer'],
            'fields.*.width'        => ['nullable', 'integer'],
            'fields.*.font_size'    => ['required', 'integer', 'min:6', 'max:200'],
            'fields.*.font_weight'  => ['required', 'string', 'in:normal,bold'],
            'fields.*.align'        => ['required', 'string', 'in:left,center,right'],
            'fields.*.is_checkbox'  => ['nullable', 'boolean'],
            'fields.*.checkbox_when'=> ['nullable', 'string', 'max:64'],
        ]);

        $documentTemplate->update([
            'name'        => $validated['name'],
            'variant_key' => $validated['variant_key'] ?: null,
            'is_active'   => (bool) ($validated['is_active'] ?? false),
        ]);

        $documentTemplate->fields()->delete();
        foreach ($validated['fields'] ?? [] as $i => $f) {
            DocumentTemplateField::create([
                'template_id'   => $documentTemplate->id,
                'field_key'     => $f['field_key'],
                'x'             => $f['x'],
                'y'             => $f['y'],
                'width'         => $f['width'] ?? null,
                'font_size'     => $f['font_size'],
                'font_weight'   => $f['font_weight'],
                'align'         => $f['align'],
                'is_checkbox'   => (bool) ($f['is_checkbox'] ?? false),
                'checkbox_when' => $f['checkbox_when'] ?? null,
                'sort_order'    => $i,
            ]);
        }

        return back()->with('success', 'บันทึกแม่แบบและฟิลด์เรียบร้อย');
    }

    protected function updateDocx(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'variant_key' => ['nullable', 'string', 'max:64'],
            'is_active'   => ['nullable', 'boolean'],
            'file'        => ['nullable', 'file', 'max:16384'], // re-upload optional
        ]);

        $patch = [
            'name'        => $validated['name'],
            'variant_key' => $validated['variant_key'] ?: null,
            'is_active'   => (bool) ($validated['is_active'] ?? false),
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            abort_unless($ext === 'docx', 422, 'ต้องอัปโหลด .docx เท่านั้น');

            if ($documentTemplate->image_path) {
                Storage::disk('public')->delete($documentTemplate->image_path);
            }
            $patch['image_path'] = $file->store('document-templates', 'public');
        }

        $documentTemplate->update($patch);

        return back()->with('success', 'บันทึกแม่แบบเรียบร้อย');
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->image_path) {
            Storage::disk('public')->delete($documentTemplate->image_path);
        }
        $documentTemplate->delete();
        return redirect()->route('document-templates.index')->with('success', 'ลบแม่แบบเรียบร้อย');
    }
}
