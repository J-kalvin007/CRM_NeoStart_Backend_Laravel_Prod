<?php
return [
    // Applied only while rendering an export. Existing higher/unlimited limits are retained.
    'memory_limit' => env('REPORT_EXPORT_MEMORY_LIMIT', '512M'),
    'font_cache' => storage_path('framework/cache/dompdf'),
];
