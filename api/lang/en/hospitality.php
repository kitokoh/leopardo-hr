<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — HospitalityManager vertical
// user-facing messages (PA2-I18N-007: catalog only, no hardcoded strings).
return [
    'solution_inactive' => 'The HospitalityManager solution is not active for this tenant.',
    'room_type_unavailable' => 'This room type is not available for this property.',
    'no_availability' => 'No availability for this room type on this interval.',
    'invalid_transition' => 'Invalid reservation transition (:from → :target).',
    'checkout_before_checkin' => 'Check-out date must be after the check-in date.',
    'unit_room_type_mismatch' => 'The assigned unit must belong to the same room type as the reservation.',
    'invalid_lease_transition' => 'Invalid lease transition (:from → :target).',
    'lease_terminal_immutable' => 'A terminated lease can no longer be modified: its terms are frozen by the rent payments already issued.',
    'rent_amount_must_be_positive' => 'The recorded amount must be strictly positive.',
    'rent_already_settled' => 'This rent payment is already settled: no further payment can be recorded.',
    'rent_amount_exceeds_outstanding' => 'The recorded amount (:amount) exceeds the outstanding balance (:outstanding).',
    'console' => [
        'expire_pending_description' => 'Expire overdue pending hospitality reservations: cancellation + inventory release (HOSP-004/#7946).',
        'expire_pending_no_tenant' => 'No active tenant — nothing to expire.',
        'expire_pending_tenant_summary' => 'Tenant :company: :count hospitality reservation(s) expired.',
        'expire_pending_total' => 'Total: :count hospitality reservation(s) expired.',
    ],
];
