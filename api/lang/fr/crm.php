<?php

return [
    // Canaux de communication CRM (issues #5725/#5727)
    'CRM_CHANNEL_NOT_FOUND' => 'Canal CRM introuvable dans le tenant courant.',
    'CRM_CHANNEL_TYPE_INVALID' => 'Type de canal CRM inconnu.',
    'CRM_CHANNEL_NOT_CONFIGURED' => 'Canal CRM actif mais non configuré (token/provider absent).',
    'CRM_CONSENT_REQUIRED' => 'Consentement de communication requis pour ce contact, ce canal et cette finalité.',
    'CRM_QUOTA_EXCEEDED' => 'Quota mensuel du canal dépassé pour ce tenant.',
    'CRM_PROVIDER_ERROR' => 'Le fournisseur du canal a renvoyé une erreur.',
    'CRM_WEBHOOK_SIGNATURE_INVALID' => 'Signature de webhook CRM invalide.',
    'CRM_WEBHOOK_NOT_CONFIGURED' => 'Webhook CRM non configuré (secret absent).',
    'CRM_WEBHOOK_VERIFY_INVALID' => 'Vérification d\'abonnement du webhook CRM refusée.',
    'CRM_EXPORT_NOT_FOUND' => 'Job d'export CRM introuvable dans le tenant courant.',
    'CRM_EXPORT_NOT_READY' => 'Export CRM pas encore prêt (traitement en cours) ou échoué.',
    'CRM_EXPORT_EXPIRED' => 'Export CRM expiré — générer un nouvel export.',
    'CRM_EXPORT_ENTITY_UNAVAILABLE' => 'Entité d'export CRM indisponible (socle V0 non encore mergé sur cet environnement).',
    'CRM_EXPORT_INVALID_REQUEST' => 'Requête d'export CRM invalide (entité ou colonne non allowlistée).',
    'CRM_EXPORT_FAILED' => 'Génération de l'export CRM en échec.',
    'CRM_EXPORT_ENTITY_INVALID' => 'Entité d'export CRM inconnue.',

    'merge' => [
        'unknown_entity' => 'Entité inconnue (accounts, contacts ou leads).',
    ],];
