<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;

/**
 * Fills a .docx template with dynamic field values, then converts to PDF via LibreOffice headless.
 *
 * Template syntax: ${field_key} placeholders inside the .docx file
 * (PHPWord uses ${...} — NOT {{...}} like Blade)
 *
 * Field keys come from DocumentFieldCatalog::fields($docType).
 */
class DocxTemplateRenderer
{
    /**
     * Render a doc through its template: returns absolute path to generated PDF.
     * Throws RuntimeException with a helpful message if LibreOffice isn't installed.
     *
     * @return string Absolute path to the rendered PDF
     */
    public static function render(DocumentTemplate $template, $doc): string
    {
        $sourcePath = storage_path('app/public/' . $template->image_path);
        if (!is_file($sourcePath)) {
            throw new \RuntimeException("ไม่พบไฟล์แม่แบบ: {$template->image_path}");
        }

        // ─── 1. Fill placeholders ──────────────────────────────
        $tp = new TemplateProcessor($sourcePath);

        // Resolve every cataloged field — set value, even if empty, so unfilled
        // placeholders don't show as raw ${...} in the output.
        $catalog = DocumentFieldCatalog::fields($template->doc_type);
        foreach (array_keys($catalog) as $key) {
            $value = DocumentFieldCatalog::resolve($doc, $template->doc_type, $key);
            try {
                $tp->setValue($key, self::escape($value));
            } catch (\Throwable $e) {
                // Ignore unknown placeholders — PHPWord throws when a placeholder doesn't exist in the doc
            }
        }

        // ─── 2. Save filled .docx to temp ──────────────────────
        $tmpDir = storage_path('app/tmp_docx_' . uniqid());
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        $filename = ($doc->document_number ?? 'document') . '_' . time();
        $docxPath = $tmpDir . '/' . $filename . '.docx';
        $tp->saveAs($docxPath);

        // ─── 3. Convert .docx → .pdf via LibreOffice headless ──
        $soffice = self::findLibreOffice();
        if (!$soffice) {
            throw new \RuntimeException(
                "ไม่พบ LibreOffice บนระบบ — ต้องติดตั้งก่อน:\n" .
                "  macOS:  brew install --cask libreoffice\n" .
                "  Ubuntu: sudo apt install libreoffice\n" .
                "  หลังติดตั้งแล้ว ลองพิมพ์อีกครั้ง"
            );
        }

        $process = new Process([
            $soffice,
            '--headless',
            '--convert-to', 'pdf',
            '--outdir', $tmpDir,
            $docxPath,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                "แปลง DOCX → PDF ล้มเหลว:\n" . $process->getErrorOutput()
            );
        }

        $pdfPath = $tmpDir . '/' . $filename . '.pdf';
        if (!is_file($pdfPath)) {
            throw new \RuntimeException('LibreOffice ไม่ได้สร้างไฟล์ PDF — ลองตรวจสอบ .docx');
        }

        return $pdfPath;
    }

    /**
     * Returns the list of placeholders found in a .docx file — used by UI to show
     * "these fields are wired up" vs "these fields are typed in the template but not in our catalog".
     */
    public static function inspectPlaceholders(string $docxPath): array
    {
        if (!is_file($docxPath)) return [];
        try {
            $tp = new TemplateProcessor($docxPath);
            return $tp->getVariables(); // array of strings
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function escape(string $value): string
    {
        // PHPWord's setValue already XML-escapes via its internal cleanup, but we
        // strip control chars that can break docx XML.
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
    }

    /**
     * Locate the LibreOffice/soffice binary on common paths.
     */
    protected static function findLibreOffice(): ?string
    {
        $candidates = [
            'soffice',
            'libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/opt/homebrew/bin/soffice',
            '/usr/local/bin/soffice',
        ];

        foreach ($candidates as $path) {
            // For PATH lookups, use `which`; for absolute paths, check is_executable
            if (str_starts_with($path, '/')) {
                if (is_executable($path)) return $path;
            } else {
                $found = trim((string) @shell_exec('command -v ' . escapeshellarg($path) . ' 2>/dev/null'));
                if ($found && is_executable($found)) return $found;
            }
        }

        return null;
    }

    public static function libreOfficeAvailable(): bool
    {
        return self::findLibreOffice() !== null;
    }
}
