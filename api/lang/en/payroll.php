<?php

return [
    'zero_slips_generated' => 'No pay slips generated: make sure at least one active salary structure exists for this country before calculating payroll.',
    'public_holidays_admin_only' => 'Only a super-admin or a principal manager can manage public holidays.',
    'public_holidays_company_only' => 'A principal manager can only modify their own company\'s public holidays.',
    'rate_edit_locked' => "A submitted, active or superseded row can no longer be edited — propose a new change.",
    'rate_delete_draft_only' => "Only a draft row can be deleted.",
    'rate_country_unsupported' => "Unsupported country.",
    'tax_scale_default_name' => ":country legal tax scale :year",

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
];
