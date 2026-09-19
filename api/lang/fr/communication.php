<?php

return [
    // BC-29 Communication R2 (#7687) — notification d'erreur de sync Gmail.
    'sync_error_title' => 'Échec de la synchronisation de la boîte mail',
    'sync_error_body' => 'La connexion à votre boîte :email a expiré ou a été révoquée. Reconnectez-la pour reprendre la synchronisation.',

    // BC-29 Communication R3 (#7688) — taxonomie de classification (défauts).
    'category_prospect' => 'Prospect',
    'category_client' => 'Client',
    'category_supplier' => 'Fournisseur',
    'category_invoice' => 'Facture',
    'category_commercial' => 'Commercial',
    'category_hr' => 'RH',
    'category_spam_newsletter' => 'Spam / newsletter',
    'category_personal' => 'Personnel',
    'category_urgent' => 'Urgent',
    'category_other' => 'Autre',

    // BC-29 Communication R3 (#7688) — messages API.
    'category_key_taken' => 'Cette clé de catégorie existe déjà pour votre entreprise.',
    'proposal_already_decided' => 'Cette proposition de contact a déjà été traitée.',

    // BC-29 Communication R4 (#7689) — messages API relances.
    'follow_up_send_scope_required' => "Reconnectez votre boîte Gmail avec l'autorisation d'envoi pour activer les relances automatiques.",
    'follow_up_not_cancellable' => 'Cette relance a déjà été traitée et ne peut plus être annulée.',
    'follow_up_mailbox_not_found' => 'Boîte mail introuvable.',

    // BC-29 Communication R5 (#7690) — messages API des réponses assistées.
    'reply_mailbox_not_found' => 'Boîte mail introuvable.',
    'reply_category_unknown' => "Cette catégorie n'existe pas dans la taxonomie de votre entreprise.",
    'reply_auto_category_blocked' => "L'envoi automatique n'est pas autorisé pour cette catégorie (finance, RH ou juridique).",
    'reply_send_scope_required' => "Reconnectez votre boîte Gmail avec l'autorisation d'envoi pour activer les réponses assistées.",
    'reply_compose_scope_required' => "Reconnectez votre boîte Gmail avec l'autorisation de composition pour activer les brouillons Gmail.",
    'pending_reply_not_pending' => 'Cette proposition de réponse a déjà été traitée.',
    'reply_blocked' => "Cette réponse ne peut pas être envoyée : un garde-fou l'a bloquée.",
    'reply_rate_limited' => "Gmail limite les envois pour le moment. Réessayez dans quelques instants.",
    'reply_auth_failed' => 'La connexion à votre boîte Gmail a expiré. Veuillez la reconnecter.',
    'reply_send_failed' => "La réponse n'a pas pu être envoyée. Veuillez réessayer.",
];
