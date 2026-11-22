<?php

declare(strict_types=1);

/**
 * Catalogue Trade (BC-36) — messages du profil commercial et de sa
 * publication dans l'annuaire public B2B (#9617).
 *
 * Ces messages etaient des litteraux accentues dans le controleur : la garde
 * PA2-I18N-007 (#5432) les refuse sur les surfaces a risque i18n, et a raison —
 * un message d'erreur francais code en dur est invisible pour un utilisateur
 * turc ou arabophone.
 */
return [
    'direction_required' => 'Declare at least one direction: import, export or domestic market.',
    'profile_required_before_publish' => 'Complete your trade profile before publishing it.',
    'public_slug_required' => 'A public slug and trade name are required to publish the company.',
    'public_slug_taken' => 'This public slug is already used by another company.',
    // #9777 — RFQ/quote lines: selected catalogue product.
    'lines' => [
        'product_invalid' => 'This product does not exist in your catalogue, or belongs to another company.',
        'product_archived' => 'This product is archived and cannot be added to a new line.',
    ],
    // #9779 — counterparties (customer/supplier directory).
    'counterparties' => [
        'roles_required' => 'Declare at least one role: you cannot buy from or sell to someone without knowing which way it goes.',
        'verification_not_self_assertable' => 'Verification is done by a member of your team and cannot be declared here.',
    ],

    // #9779 — orders.
    'orders' => [
        'cancel_reason_required' => 'Give the cancellation reason: without it the order is unexplainable later.',
    ],

    // #9779 — commercial documents.
    'documents' => [
        'type_invalid' => 'This document type is not part of the regulatory list.',
        'title_required' => 'Give the document a title.',
        'link_required' => 'Attach the document to an order, quote, request or deal.',
    ],
    // #9779 — RFQ and quote lifecycles.
    'rfqs' => [
        'awarded_quote_required' => 'Name the winning quote: awarding without saying which one is not a decision.',
        'awarded_quote_invalid' => 'That quote does not belong to this request for quotation.',
    ],
    'quotes' => [
        'accept_has_its_own_route' => 'Accepting a quote creates an order: use the accept action, not a status change.',
    ],
    'search_query_too_short' => 'The search must contain at least :min characters.',
];
