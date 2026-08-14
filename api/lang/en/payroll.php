<?php

return [
    'zero_slips_generated' => 'No pay slips generated: make sure at least one active salary structure exists for this country before calculating payroll.',
    'public_holidays_admin_only' => 'Only a super-admin or a principal manager can manage public holidays.',
    'public_holidays_company_only' => 'A principal manager can only modify their own company\'s public holidays.',
    'rate_edit_locked' => "A submitted, active or superseded row can no longer be edited — propose a new change.",
    'rate_delete_draft_only' => "Only a draft row can be deleted.",
    'rate_country_unsupported' => "Unsupported country.",
    'placeholder_acknowledge_required' => "Payroll rules for :country are still at 'placeholder' stage: no legal values are implemented. Explicitly confirm (acknowledge_placeholder=true) — amounts are INDICATIVE and must not be used for a real payslip.",
    'compliance_warning_placeholder' => "Structure-only rules for :country: rates and contributions are not yet sourced — do not use for real payroll.",
    'compliance_warning_pilot' => "Pilot ruleset for :country, sourced from public references but not legally validated locally — confirm with local counsel before statutory use.",
    'compliance_warning_production' => "Rules validated for :country payroll — always confirm current rates with local counsel before filing.",
    'tax_scale_default_name' => ":country legal tax scale :year",
];
