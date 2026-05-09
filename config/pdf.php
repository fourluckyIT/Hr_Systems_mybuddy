<?php

return [
	'mode'                  => 'utf-8',
	'format'                => 'A4',
	'author'                => '',
	'subject'               => '',
	'keywords'              => '',
	'creator'               => 'Laravel Pdf',
	'display_mode'          => 'fullpage',
	'tempDir'               => base_path('../temp/'),
	'pdf_a'                 => false,
	'pdf_a_auto'            => false,
	'icc_profile_path'      => '',
    'autoScriptToLang'      => true,
    'autoLangToFont'        => true,
    'default_font'          => 'thsarabunnew',
    'custom_font_dir'       => storage_path('fonts/'),
    'custom_fontdata'       => [
        'thsarabunnew' => [
            'R'  => 'THSarabunNew.ttf',
            'B'  => 'THSarabunNew-Bold.ttf',
            'I'  => 'THSarabunNew.ttf',
            'BI' => 'THSarabunNew-Bold.ttf',
        ],
    ],
];
