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
];
