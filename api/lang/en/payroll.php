<?php

return [
    'calculation_failed' => 'Payroll calculation failed. Details in the logs.',
    'zero_slips_generated' => 'No pay slips generated: make sure at least one active salary structure exists for this country before calculating payroll.',
    'public_holidays_admin_only' => 'Only a super-admin or a principal manager can manage public holidays.',
    'public_holidays_company_only' => 'A principal manager can only modify their own company\'s public holidays.',
    'rate_edit_locked' => 'A submitted, active or superseded row can no longer be edited — propose a new change.',
    'rate_delete_draft_only' => 'Only a draft row can be deleted.',
    'rate_country_unsupported' => 'Unsupported country.',
    'placeholder_acknowledge_required' => "Payroll rules for :country are still at 'placeholder' stage: no legal values are implemented. Explicitly confirm (acknowledge_placeholder=true) — amounts are INDICATIVE and must not be used for a real payslip.",
    'compliance_warning_placeholder' => 'Structure-only rules for :country: rates and contributions are not yet sourced — do not use for real payroll.',
    'compliance_warning_pilot' => 'Pilot ruleset for :country, sourced from public references but not legally validated locally — confirm with local counsel before statutory use.',
    'compliance_warning_production' => 'Rules validated for :country payroll — always confirm current rates with local counsel before filing.',

    'compliance_warning_unknown' => 'Country without implemented payroll rules — no legal values available.',
    'tax_scale_default_name' => ':country legal tax scale :year',

    // Issue #1923 — legal rate validation workflow (#1813): service/listener/
    // admin controller messages, no more hardcoded accented strings.
    'rate_submit_draft_only' => 'Only a draft row can be submitted (current status: :status).',
    'rate_approve_pending_only' => 'Only a row pending validation can be approved (current status: :status).',
    'rate_reject_pending_only' => 'Only a row pending validation can be rejected (current status: :status).',
    'rate_reject_reason_required' => 'A rejection reason is required.',
    'rate_table_unknown' => 'Unknown table.',
    'rate_overlap_conflict' => 'An active row already exists for this same identity over a period overlapping the new effective window: close the existing row\'s window first.',
    'rate_validation_requested_title' => 'Rate validation requested — :label',
    'rate_validation_requested_body' => 'A legal :kind (:label) is awaiting your validation in the admin interface.',
    'rate_kind_tax_scale' => 'tax scale',
    'rate_kind_contribution' => 'contribution rate',
    'rate_approved_title' => 'Rate change approved',
    'rate_approved_body' => 'Your legal rate change (:label) has been approved and is now active.',
    'rate_rejected_title' => 'Rate change rejected',
    'rate_rejected_body' => 'Your legal rate change (:label) has been rejected: :reason',
    // Issue #2112 — niveau de confiance des règles pays : libellés et
    // messages localisés (consommés par l'admin TaxSlabsView).
    'confidence' => [
        'label' => 'Payroll rules confidence',
        'level_production' => 'Production',
        'level_pilot' => 'Pilot',
        'level_placeholder' => 'Placeholder',
        'level_unknown' => 'Unknown',
        'production' => ['message' => 'Rules validated and used in production for :country. Always confirm current rates with a local advisor before relying on these amounts for statutory filings.'],
        'pilot' => ['message' => 'Pilot rules for :country: amounts based on general public references (labor code) but not yet legally validated locally. Confirm with a local legal or tax advisor before relying on these figures (tax brackets, social contributions, overtime thresholds) for your statutory obligations.'],
        'placeholder' => ['message' => 'Placeholder without values for :country: tax and social contribution amounts are not documented yet and must not be used for real payroll cycles until they are replaced.'],
        'unknown' => ['message' => 'No payroll rules are available for :country: payroll calculation is not available for this country.'],
    ],
];
