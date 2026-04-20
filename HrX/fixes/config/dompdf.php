<?php

/**
 * Fixes BUG-20. dompdf is locked down:
 *   - is_remote_enabled = false  → blocks SSRF via <img src="http://...">
 *   - is_php_enabled    = false  → blocks embedded PHP in HTML
 *   - is_javascript_enabled=false→ blocks JS execution during render
 *
 * If you need logos, ship them as local assets under public/ and reference
 * via an absolute filesystem path, NEVER a URL.
 */

return [
    'show_warnings' => false,

    'public_path' => null,

    'convert_entities' => true,

    'options' => [

        // ---- security-critical toggles ----
        'is_remote_enabled'     => false,
        'is_php_enabled'        => false,
        'is_javascript_enabled' => false,
        'is_html5_parser_enabled' => true,

        // ---- layout / fonts ----
        'font_dir'              => storage_path('fonts/'),
        'font_cache'            => storage_path('fonts/'),
        'temp_dir'              => sys_get_temp_dir(),
        'chroot'                => realpath(base_path()),

        'default_media_type'   => 'screen',
        'default_paper_size'   => 'A4',
        'default_paper_orientation' => 'portrait',
        'default_font'         => 'sarabun',   // Thai-friendly default

        'dpi'                  => 96,
        'enable_font_subsetting' => false,
        'pdf_backend'          => 'CPDF',
        'log_output_file'      => null,

        'http_context'         => null,

        // hardening — explicit string casts
        'debug_png'            => false,
        'debug_keep_temp'      => false,
        'debug_css'            => false,
        'debug_layout'         => false,
        'debug_layout_lines'   => false,
        'debug_layout_blocks'  => false,
        'debug_layout_inline'  => false,
        'debug_layout_padding_box' => false,
    ],
];
