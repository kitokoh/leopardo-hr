<?php

/**
 * BC-39 PAGES — server messages (en).
 *
 * Key parity with fr/ar/tr is enforced by check-i18n-catalog-parity.sh.
 */
return [
    // #8723 — publishing and establishment binding
    'publish_seo_title_required' => 'A Page without an SEO title cannot be published: it would produce an indexable URL with no heading.',
    'site_not_found' => 'Establishment not found for this Page (site_id=:site).',
    'site_cross_tenant' => 'Establishment :site does not belong to company :company: a Page cannot be attached to another tenant\'s location.',

    // #8724 — block registry
    'block_type_duplicate' => 'Block type ":type" is already provided by BC ":owner": ":new" cannot register it again.',

    // #8725 — verification case
    'verification_method_disabled' => 'Verification method ":method" is not enabled on this platform.',
    'verification_already_open' => 'A verification case is already open for this Page.',
    'verification_closed' => 'This verification case is closed: open a new one to request another verification.',
    'verification_suspended' => 'A suspended Page cannot be submitted for verification.',
    'verification_wrong_method' => 'This operation requires method ":expected" (case is in ":actual").',
    'verification_cooldown' => 'A code was just sent: try again in :seconds second(s).',
    'verification_code_not_found' => 'No code was issued for this number on this case.',
    'verification_code_expired' => 'This code has expired: request a new one.',
    'verification_code_consumed' => 'This code has already been used.',
    'verification_code_locked' => 'Too many attempts: request a new code.',
    'verification_code_mismatch' => 'Incorrect code (:remaining attempt(s) left).',
    'verification_no_evidence' => 'A documentary case cannot be approved without any supporting evidence.',
    'verification_evidence_invalid' => 'Supporting evidence rejected: :errors',
    'verification_evidence_path_public' => 'An evidence storage path cannot be a public URL or an absolute path.',
    'verification_reason_invalid' => 'Unknown decision reason: ":reason".',
    'verification_transition_invalid' => 'Verification transition not allowed: :from -> :to.',

    // #8726 — custom domains
    'domain_invalid' => 'This domain is not valid: :reason',
    'domain_taken' => 'This domain is already attached to a Page. If you own it, contact support.',
    'domain_already_linked' => 'This domain is already attached to this Page.',
    'domain_entitlement_missing' => 'Custom domains are not included in your current plan.',
    'domain_quota_exceeded' => 'Your plan allows :limit custom domain(s).',
    'domain_removed' => 'This domain has been removed and can no longer be verified.',
    'domain_plan_expired' => 'Your subscription has expired: custom domains are disabled until renewal.',

    // #8728 - unusable or reserved Page slug
    'slug_unusable' => 'The name provided cannot produce a public address. Use letters and digits.',
    'slug_reserved' => 'The address ":slug" is reserved by the platform. Choose another one.',
    'slug_taken' => 'The address ":slug" is already in use.',

    // #8730 — public SSR rendering
    'public' => [
        'verified' => 'Verified establishment',
        'contact' => 'Contact',
        'map' => 'View on map',
        'actions' => [
            'directions' => 'Get directions',
            'contact' => 'Write',
        ],
    ],
];
