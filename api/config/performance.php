<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Report caches (tenant-scoped keys built from authenticated company_id)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'hr_headcount_ttl_seconds' => max(0, (int) env('HR_REPORT_HEADCOUNT_CACHE_TTL', 60)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll async helpers
    |--------------------------------------------------------------------------
    */
    'payroll' => [
        'queue_pdf_warmup' => filter_var(env('PAYROLL_QUEUE_PDF_WARMUP', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
