<?php

return [
    // BC-29 Communication R2 (#7687) — Gmail sync error notification.
    'sync_error_title' => 'Mailbox synchronization failed',
    'sync_error_body' => 'The connection to your mailbox :email has expired or was revoked. Please reconnect it to resume synchronization.',

    // BC-29 Communication R3 (#7688) — classification taxonomy (defaults).
    'category_prospect' => 'Prospect',
    'category_client' => 'Client',
    'category_supplier' => 'Supplier',
    'category_invoice' => 'Invoice',
    'category_commercial' => 'Commercial',
    'category_hr' => 'HR',
    'category_spam_newsletter' => 'Spam / newsletter',
    'category_personal' => 'Personal',
    'category_urgent' => 'Urgent',
    'category_other' => 'Other',

    // BC-29 Communication R3 (#7688) — API messages.
    'category_key_taken' => 'This category key already exists for your company.',
    'proposal_already_decided' => 'This contact proposal has already been decided.',

    // BC-29 Communication R4 (#7689) — follow-up API messages.
    'follow_up_send_scope_required' => 'Reconnect your Gmail mailbox with the send permission to enable automatic follow-ups.',
    'follow_up_not_cancellable' => 'This follow-up has already been processed and can no longer be cancelled.',
    'follow_up_mailbox_not_found' => 'Mailbox not found.',

    // BC-29 Communication R5 (#7690) — assisted replies API messages.
    'reply_mailbox_not_found' => 'Mailbox not found.',
    'reply_category_unknown' => 'This category does not exist in your company taxonomy.',
    'reply_auto_category_blocked' => 'Automatic sending is not allowed for this category (finance, HR or legal).',
    'reply_send_scope_required' => 'Reconnect your Gmail mailbox with the send permission to enable assisted replies.',
    'reply_compose_scope_required' => 'Reconnect your Gmail mailbox with the compose permission to enable Gmail drafts.',
    'pending_reply_not_pending' => 'This reply proposal has already been processed.',
    'reply_blocked' => 'This reply cannot be sent: a safeguard blocked it.',
    'reply_rate_limited' => 'Gmail is rate limiting sends. Please try again shortly.',
    'reply_auth_failed' => 'The connection to your Gmail mailbox has expired. Please reconnect it.',
    'reply_send_failed' => 'The reply could not be sent. Please try again.',
];
