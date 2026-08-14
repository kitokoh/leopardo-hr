<?php

return [
    'zero_slips_generated' => 'No pay slips generated: make sure at least one active salary structure exists for this country before calculating payroll.',
    'public_holidays_admin_only' => 'Only a super-admin or a principal manager can manage public holidays.',
    'public_holidays_company_only' => 'A principal manager can only modify their own company\'s public holidays.',
    'rate_edit_locked' => "A submitted, active or superseded row can no longer be edited — propose a new change.",
    'rate_delete_draft_only' => "Only a draft row can be deleted.",
    'rate_country_unsupported' => "Unsupported country.",
    'tax_scale_default_name' => ":country legal tax scale :year",
    'compliance_warning_production' => "Legally validated for :country payroll use, but always confirm current rates with local counsel before relying on this for statutory filings.",
    'compliance_warning_pilot' => "Pilot ruleset for :country, sourced from general public labor-code references but not yet legally validated locally. Confirm with local legal/tax counsel before relying on these figures (tax slabs, social contributions, overtime thresholds) for statutory compliance.",
    'compliance_warning_placeholder' => "Structure-only placeholder for :country: tax/social-contribution figures are not yet researched and must not be used for real payroll runs without replacing them first.",
    'compliance_warning_unknown' => "No payroll rules available for :country yet.",
];
