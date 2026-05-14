@extends('layouts.app')

@section('title', 'แก้ไขแม่แบบ — ' . $template->name)

@section('content')
@php
    $catalogJson = collect($catalog)->map(fn($v, $k) => ['key' => $k, 'label' => $v['label']])->values()->toJson(JSON_UNESCAPED_UNICODE);
    $fieldsJson = $template->fields->map(fn($f) => [
        'field_key'     => $f->field_key,
        'x'             => $f->x,
        'y'             => $f->y,
        'width'         => $f->width,
        'font_size'     => $f->font_size,
        'font_weight'   => $f->font_weight,
        'align'         => $f->align,
        'is_checkbox'   => (bool) $f->is_checkbox,
        'checkbox_when' => $f->checkbox_when,
    ])->toJson(JSON_UNESCAPED_UNICODE);
    $presetJson = collect($preset ?? [])->toJson(JSON_UNESCAPED_UNICODE);
    $hasPreset = !empty($preset);
@endphp

<div class="max-w-[1400px] mx-auto py-6 px-4"
     x-data="templateEditor({
        imageUrl: @js($template->image_url),
        imgW: {{ $template->image_width }},
        imgH: {{ $template->image_height }},
        catalog: {{ $catalogJson }},
        fields: {{ $fieldsJson }},
        preset: {{ $presetJson }},
     })">

    <a href="{{ route('document-templates.index') }}" class="inline-block mb-4 text-sm text-gray-500 hover:text-gray-700">← กลับไปรายการแม่แบบ</a>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('document-templates.update', $template) }}" @submit="syncBeforeSubmit($event)">
        @csrf @method('PUT')

        {{-- Metadata strip --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อแม่แบบ</label>
                <input type="text" name="name" value="{{ $template->name }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">ประเภท</label>
                <div class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg">{{ \App\Services\DocumentFieldCatalog::docTypeChoices()[$template->doc_type] ?? $template->doc_type }}</div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Variant</label>
                <select name="variant_key" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">— ทั่วไป —</option>
                    @foreach(\App\Services\DocumentFieldCatalog::variantChoices($template->doc_type) as $key => $label)
                        <option value="{{ $key }}" @selected($template->variant_key === $key)>{{ $label }} [{{ $key }}]</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-gray-300">
                    เปิดใช้แม่แบบนี้
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-4">

            {{-- ── Canvas ── --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                @if($hasPreset)
                    <div class="mb-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg flex items-center justify-between gap-3">
                        <div class="text-sm">
                            <p class="text-indigo-900 font-semibold">🪄 มีตัวอย่างพร้อมใช้สำหรับฟอร์มนี้</p>
                            <p class="text-xs text-indigo-700 mt-0.5">กดปุ่มเพื่อวางฟิลด์ทั้งหมดในตำแหน่งคร่าว ๆ — แล้วลากปรับเอา</p>
                        </div>
                        <button type="button" @click="loadPreset()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 whitespace-nowrap">โหลดตัวอย่าง</button>
                    </div>
                @endif
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm text-gray-700">คลิกบนภาพเพื่อวางฟิลด์ใหม่ · ลากเพื่อย้าย · ดับเบิ้ลคลิกที่ marker เพื่อลบ</p>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <button type="button" @click="zoom = Math.max(0.25, zoom - 0.1)" class="px-2 py-1 border border-gray-200 rounded">−</button>
                        <span x-text="Math.round(zoom * 100) + '%'"></span>
                        <button type="button" @click="zoom = Math.min(2, zoom + 0.1)" class="px-2 py-1 border border-gray-200 rounded">+</button>
                        <button type="button" @click="zoom = 1" class="px-2 py-1 border border-gray-200 rounded">100%</button>
                    </div>
                </div>

                <div class="overflow-auto border border-gray-100 rounded bg-gray-50" style="max-height: 78vh;">
                    <div class="relative inline-block" :style="`width: ${imgW * zoom}px; height: ${imgH * zoom}px;`">
                        <img :src="imageUrl" :style="`width: ${imgW * zoom}px; height: ${imgH * zoom}px;`"
                             @click="onCanvasClick($event)" class="block select-none" draggable="false">

                        <template x-for="(f, idx) in fields" :key="idx">
                            <div class="absolute group cursor-move"
                                 :class="idx === activeIdx ? 'ring-2 ring-indigo-500' : ''"
                                 :style="`left: ${f.x * zoom}px; top: ${f.y * zoom}px;`"
                                 @mousedown.stop="startDrag($event, idx)"
                                 @click.stop="activeIdx = idx"
                                 @dblclick.stop="removeField(idx)">
                                <div class="bg-indigo-600 text-white text-[10px] px-1 py-0.5 rounded-sm whitespace-nowrap shadow"
                                     x-text="`#${idx + 1} ${labelFor(f.field_key)}`"></div>
                                <div class="border-2 border-indigo-400 bg-indigo-50/40 mt-0.5"
                                     :style="`font-size: ${f.font_size * zoom}px; font-weight: ${f.font_weight}; text-align: ${f.align}; ${f.width ? 'width: ' + (f.width * zoom) + 'px;' : 'min-width: 80px;'}`"
                                     x-text="f.is_checkbox ? '✓' : labelFor(f.field_key)"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── Field editor panel ── --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">ฟิลด์ทั้งหมด (<span x-text="fields.length"></span>)</h3>

                <div class="space-y-2 max-h-[48vh] overflow-auto mb-4">
                    <template x-for="(f, idx) in fields" :key="idx">
                        <div class="border rounded p-2 text-xs"
                             :class="idx === activeIdx ? 'border-indigo-400 bg-indigo-50/40' : 'border-gray-200'">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold" x-text="`#${idx + 1}`"></span>
                                <button type="button" @click="removeField(idx)" class="text-rose-600 hover:underline">ลบ</button>
                            </div>
                            <div x-text="labelFor(f.field_key)" class="text-gray-700"></div>
                            <div class="text-gray-400 font-mono text-[10px]" x-text="`x:${f.x} y:${f.y} · ${f.font_size}px`"></div>
                            <button type="button" @click="activeIdx = idx" class="text-indigo-600 hover:underline mt-1">เลือกแก้ไข</button>
                        </div>
                    </template>
                    <p x-show="fields.length === 0" class="text-xs text-gray-400 italic">ยังไม่มีฟิลด์ — คลิกบนภาพเพื่อเริ่มต้น</p>
                </div>

                <template x-if="activeIdx !== null && fields[activeIdx]">
                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <h4 class="text-xs font-semibold text-gray-800">แก้ไขฟิลด์ที่ #<span x-text="activeIdx + 1"></span></h4>

                        <div>
                            <label class="block text-[11px] text-gray-600 mb-0.5">ฟิลด์ข้อมูล</label>
                            <select x-model="fields[activeIdx].field_key" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                                <template x-for="opt in catalog" :key="opt.key">
                                    <option :value="opt.key" x-text="`${opt.label} [${opt.key}]`"></option>
                                </template>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-0.5">X (px)</label>
                                <input type="number" x-model.number="fields[activeIdx].x" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-0.5">Y (px)</label>
                                <input type="number" x-model.number="fields[activeIdx].y" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-0.5">Font size (px)</label>
                                <input type="number" min="6" max="200" x-model.number="fields[activeIdx].font_size" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-0.5">ความกว้าง (optional)</label>
                                <input type="number" x-model.number="fields[activeIdx].width" placeholder="ไม่จำกัด" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-0.5">ตัวหนา</label>
                                <select x-model="fields[activeIdx].font_weight" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                                    <option value="normal">ปกติ</option>
                                    <option value="bold">หนา</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-0.5">การจัดวาง</label>
                                <select x-model="fields[activeIdx].align" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                                    <option value="left">ซ้าย</option>
                                    <option value="center">กลาง</option>
                                    <option value="right">ขวา</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="inline-flex items-center gap-1.5 text-xs">
                                <input type="checkbox" x-model="fields[activeIdx].is_checkbox" class="rounded">
                                เป็น checkbox (วาง ✓ เมื่อค่าตรงกับเงื่อนไข)
                            </label>
                            <input x-show="fields[activeIdx].is_checkbox" type="text" x-model="fields[activeIdx].checkbox_when"
                                   placeholder="ค่าเงื่อนไข เช่น sick_leave"
                                   class="mt-1 w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Hidden inputs synced from Alpine state on submit --}}
        <div id="hidden-fields-container"></div>

        <div class="flex justify-between items-center mt-4">
            <p class="text-xs text-gray-500">ขนาดภาพต้นฉบับ: {{ $template->image_width }} × {{ $template->image_height }} px</p>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">บันทึก</button>
        </div>
    </form>
</div>

<script>
function templateEditor(init) {
    return {
        imageUrl: init.imageUrl,
        imgW: init.imgW,
        imgH: init.imgH,
        catalog: init.catalog,
        preset: init.preset || [],
        fields: init.fields.map(f => ({ ...f, width: f.width || null })),
        activeIdx: null,
        zoom: 1,
        dragging: null,

        loadPreset() {
            if (this.fields.length > 0 && !confirm('แทนที่ฟิลด์ที่มีอยู่ด้วยตัวอย่าง?')) return;
            this.fields = this.preset.map(p => ({
                field_key:     p.field_key,
                x:             Math.round(p.xp * this.imgW),
                y:             Math.round(p.yp * this.imgH),
                width:         p.w ? Math.round(p.w * this.imgW) : null,
                font_size:     Math.max(8, Math.round((p.fs || 0.02) * this.imgH)),
                font_weight:   'normal',
                align:         'left',
                is_checkbox:   !!p.cb,
                checkbox_when: p.cb || null,
            }));
            this.activeIdx = null;
        },

        labelFor(key) {
            const found = this.catalog.find(o => o.key === key);
            return found ? found.label : key;
        },

        onCanvasClick(e) {
            // Translate display coords to image-space
            const rect = e.currentTarget.getBoundingClientRect();
            const x = Math.round((e.clientX - rect.left) / this.zoom);
            const y = Math.round((e.clientY - rect.top) / this.zoom);
            const defaultKey = this.catalog[0]?.key || '';
            this.fields.push({
                field_key: defaultKey,
                x, y,
                width: null,
                font_size: 18,
                font_weight: 'normal',
                align: 'left',
                is_checkbox: false,
                checkbox_when: null,
            });
            this.activeIdx = this.fields.length - 1;
        },

        startDrag(e, idx) {
            this.activeIdx = idx;
            const startX = e.clientX, startY = e.clientY;
            const origX = this.fields[idx].x, origY = this.fields[idx].y;
            const onMove = (ev) => {
                this.fields[idx].x = Math.round(origX + (ev.clientX - startX) / this.zoom);
                this.fields[idx].y = Math.round(origY + (ev.clientY - startY) / this.zoom);
            };
            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        removeField(idx) {
            if (!confirm('ลบฟิลด์นี้?')) return;
            this.fields.splice(idx, 1);
            if (this.activeIdx === idx) this.activeIdx = null;
            else if (this.activeIdx > idx) this.activeIdx--;
        },

        syncBeforeSubmit(e) {
            const container = document.getElementById('hidden-fields-container');
            container.innerHTML = '';
            this.fields.forEach((f, i) => {
                const make = (name, value) => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = `fields[${i}][${name}]`;
                    inp.value = value ?? '';
                    container.appendChild(inp);
                };
                make('field_key', f.field_key);
                make('x', f.x);
                make('y', f.y);
                if (f.width) make('width', f.width);
                make('font_size', f.font_size);
                make('font_weight', f.font_weight);
                make('align', f.align);
                make('is_checkbox', f.is_checkbox ? 1 : 0);
                if (f.checkbox_when) make('checkbox_when', f.checkbox_when);
            });
        },
    };
}
</script>
@endsection
