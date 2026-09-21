<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — HospitalityManager vertical
// user-facing messages (PA2-I18N-007: catalog only, no hardcoded strings).
return [
    'solution_inactive' => 'The HospitalityManager solution is not active for this tenant.',
    'no_availability' => 'No availability for this room type on this interval.',
    'invalid_transition' => 'Invalid reservation transition (:from → :target).',
    'console' => [
        'expire_pending_description' => 'Expire overdue pending hospitality reservations: cancellation + inventory release (HOSP-004/#7946).',
        'expire_pending_no_tenant' => 'No active tenant — nothing to expire.',
        'expire_pending_tenant_summary' => 'Tenant :company: :count hospitality reservation(s) expired.',
        'expire_pending_total' => 'Total: :count hospitality reservation(s) expired.',
    ],
];
