<?php

declare(strict_types=1);

// BC-37 AGRICULTURE — Farm OS (#8549-#8570). Module i18n catalog:
// PA2-I18N-007 / issue #5432 — no hardcoded user-facing label on the backend,
// every message goes through __('farm.xxx').
return [
    // Step 4 (provisioning) checklist — `done` is re-read from the database,
    // only the label is translated here.
    'checklist' => [
        'activity_declared' => 'Farm activity declared',
        'tools_decided' => 'Tools enabled',
        'solution_active' => 'Farm OS solution active',
        'first_site' => 'First farm / site created',
        'first_parcel' => 'First plot created',
        'first_operation' => 'First operation planned',
        'workers_ready' => 'Workers tool enabled',
    ],
];
