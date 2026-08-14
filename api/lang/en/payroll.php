<?php

return [
    'zero_slips_generated' => 'No pay slips generated: make sure at least one active salary structure exists for this country before calculating payroll.',
    'public_holidays_admin_only' => 'Only a super-admin or a principal manager can manage public holidays.',
    'public_holidays_company_only' => 'A principal manager can only modify their own company\'s public holidays.',
    'rate_edit_locked' => "A submitted, active or superseded row can no longer be edited — propose a new change.",
    'rate_delete_draft_only' => "Only a draft row can be deleted.",
    'rate_country_unsupported' => "Unsupported country.",
    'tax_scale_default_name' => ":country legal tax scale :year",
    'rate_submit_draft_only' => "Only a draft row can be submitted (current status: :status).",
    'rate_approve_pending_only' => "Only a pending row can be approved (current status: :status).",
    'rate_reject_pending_only' => "Only a pending row can be rejected (current status: :status).",
    'rate_notif_title_submitted' => "Rate validation requested — :label",
    'rate_notif_body_submitted' => "A legal :kind (:label) is awaiting your validation in the admin interface.",
    'rate_notif_kind_slab' => "tax scale",
    'rate_notif_kind_contribution' => "contribution rate",
    'rate_notif_subject' => "Rate change :verb",
    'rate_notif_verb_approved' => "approved",
    'rate_notif_verb_rejected' => "rejected",
    'rate_notif_body_approved' => "Your legal rate change (:label) was approved and is now active.",
    'rate_notif_body_rejected' => "Your legal rate change (:label) was rejected: :reason",
    'rate_reject_reason_required' => "A rejection reason is required.",
];
