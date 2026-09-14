<?php

// Clés i18n du cockpit super-admin (contrats SPA admin, issue #1764).
return [
    'alert_redis_unreachable' => 'Redis injoignable — cache/queue dégradés.',
    'alert_queue_depth' => "File d'attente élevée : :count jobs en attente.",
    'alert_failed_jobs' => ':count job(s) en échec — vérifier la file.',
    'alert_licenses_expiring' => ':count licence(s) Edge expirent sous 30 jours.',
    'alert_trials_expiring' => ':count essai(s) expirent sous 7 jours.',
    'alert_high_priority_tickets' => ':count ticket(s) support haute priorité ouverts.',
    'activity_company_created' => 'Nouvelle entreprise : :name',
    'activity_support_ticket' => 'Ticket support : :subject',
    'activity_edge_sync' => 'Sync Edge : :name',
    'activity_user_signup' => 'Nouvel utilisateur : :name (:email)',
    'admin_chat_unavailable' => "L'assistant IA est configuré par tenant : la console plateforme ne peut pas répondre au nom d'un tenant. Connectez-vous à l'espace du tenant pour utiliser l'assistant.",
    'conversation_not_found' => 'Conversation introuvable.',
    'conversations_unavailable' => 'Conversations indisponibles.',
    'oauth_save_failed' => 'Impossible d\'enregistrer la configuration.',
    'ai_settings_unknown_keys' => 'Réglage(s) inconnu(s) : :keys',
    'ai_settings_unknown_key' => 'Réglage inconnu : :key',
    'ai_test_driver_fake' => 'Driver « fake » : aucun appel réseau n\'est effectué. Choisissez un fournisseur réel pour tester une clé.',
    'ai_test_ok' => 'Le fournisseur a répondu correctement.',
    'ai_test_unauthorized' => 'Clé refusée par le fournisseur (401). Vérifiez la clé enregistrée pour ce driver.',
    'ai_test_quota' => 'Quota atteint chez le fournisseur (429). Réessayez plus tard ou changez d\'offre.',
    'ai_test_timeout' => 'Délai dépassé en joignant le fournisseur. Vérifiez la connectivité sortante du serveur.',
    'ai_test_failed' => 'Échec du test : :error',
];
