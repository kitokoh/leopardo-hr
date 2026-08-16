<?php

return [
    // Auth
    'INVALID_CREDENTIALS' => 'Email ou mot de passe incorrect.',
    'ACCOUNT_SUSPENDED' => 'Votre compte a été suspendu. Contactez votre responsable.',
    'ACCOUNT_ARCHIVED' => 'Ce compte est archivé.',
    'TOKEN_EXPIRED' => 'Votre session a expiré. Veuillez vous reconnecter.',
    'TOO_MANY_ATTEMPTS' => 'Trop de tentatives. Réessayez dans :minutes minutes.',
    'EMPLOYEE_NOT_ACTIVE' => 'Ce compte employé n\'est pas actif.',
    'COMPANY_NOT_FOUND' => 'Entreprise introuvable.',
    'INVALID_CURRENT_PASSWORD' => 'Le mot de passe actuel est incorrect.',
    'UNAUTHENTICATED' => 'Connexion requise.',

    // Pointage
    'ALREADY_CHECKED_IN' => 'Vous avez déjà pointé votre arrivée aujourd\'hui.',
    'MISSING_CHECK_IN' => 'Pointez d\'abord votre arrivée avant de sortir.',
    'ALREADY_CHECKED_OUT' => 'Vous avez déjà pointé votre départ aujourd\'hui.',
    'PUNCH_PHOTO_REQUIRED' => 'Une photo est obligatoire pour pointer dans votre entreprise.',

    // Finance
    'PLAN_CAMERAS_REQUIRED' => 'Votre plan n\'inclut pas le module caméras. Passez au plan Business.',
    'MAX_CAMERAS_REACHED' => 'Limite de :limit caméras atteinte pour votre plan.',
    'PLAN_FINANCE_REQUIRED' => 'Votre plan n\'inclut pas le module finance.',
    'FINANCE_MAX_DOCS_REACHED' => 'Limite de :limit documents atteinte ce mois.',
    'INVOICE_ALREADY_SENT' => 'Cette facture a déjà été envoyée et ne peut plus être modifiée.',

    // Invitations
    'INVITATION_ALREADY_ACCEPTED' => 'Cette invitation a déjà été acceptée.',
    'INVITATION_EXPIRED' => 'Cette invitation a expiré.',
    'INVITATION_NOT_FOUND' => 'Invitation introuvable.',

    // Biometric
    'CAMERA_TOKEN_EXPIRED' => 'L\'accès à cette caméra a expiré.',
    'CAMERA_TOKEN_REVOKED' => 'Cet accès a été révoqué.',

    // Payroll
    'PAYROLL_BALANCE_UNAVAILABLE' => 'Le solde employé est temporairement indisponible. Veuillez réessayer dans quelques instants.',
    // Général
    'NOT_FOUND' => 'Ressource introuvable.',
    'FORBIDDEN' => 'Vous n\'avez pas les droits pour cette action.',
    'SERVER_ERROR' => 'Une erreur est survenue. Veuillez réessayer.',
    'VALIDATION_ERROR' => 'Certains champs sont incorrects.',
    'BAD_REQUEST' => 'Requête invalide.',
    'CONFLICT' => 'Le conflit de données empêche cette opération.',
    'VALIDATION_FAILED' => 'Certains champs sont incorrects.',
    'TOO_MANY_REQUESTS' => 'Trop de requêtes. Réessayez plus tard.',
    'SERVICE_UNAVAILABLE' => 'Service temporairement indisponible.',
    'HTTP_ERROR' => 'Une erreur est survenue. Veuillez réessayer.',
    'UNSUPPORTED_API_VERSION' => 'Version API non supportee.',

    // #3810 — codes stables (audit 2026-08-15) : plus de message brut exposé
    'PAYROLL_RUN_VALIDATION_FAILED' => 'La validation du run de paie a échoué. Réessayez ou contactez le support.',
    'PAYROLL_RUN_LOCK_FAILED' => 'Le verrouillage du run de paie a échoué. Réessayez.',
    'PAYROLL_RUN_UNLOCK_FAILED' => 'Le déverrouillage du run de paie a échoué. Réessayez.',
    'PAYROLL_REGULARIZATION_FAILED' => 'La création du run de régularisation a échoué. Réessayez.',
    'SAML_AUTH_FAILED' => 'L\'authentification SAML a échoué. Réessayez ou contactez votre administrateur.',
    'OIDC_AUTHORIZE_FAILED' => 'Le démarrage de la connexion OIDC a échoué. Réessayez.',
    'OIDC_CALLBACK_FAILED' => 'La finalisation de la connexion OIDC a échoué. Réessayez.',
    'ANNOUNCEMENT_PUBLISH_FAILED' => 'La publication de l\'annonce a échoué. Réessayez.',
    'ANNOUNCEMENT_CANCEL_FAILED' => 'L\'annulation de l\'annonce a échoué. Réessayez.',
    'RATE_APPROVAL_FAILED' => 'L\'approbation du taux a échoué. Vérifiez l\'état de la ligne puis réessayez.',
    'RATE_REJECTION_FAILED' => 'Le rejet du taux a échoué. Vérifiez l\'état de la ligne puis réessayez.',
    'SOCIAL_CONTRIBUTION_SUBMIT_FAILED' => 'La soumission de la cotisation a échoué. Vérifiez l\'état de la ligne puis réessayez.',
    'TAX_SLAB_SUBMIT_FAILED' => 'La soumission de la tranche fiscale a échoué. Vérifiez l\'état de la ligne puis réessayez.',

    'PAYMENT_SESSION_FAILED' => 'Impossible de créer la session de paiement.',
    'NO_PAYMENT_ACCOUNT' => 'Aucun compte de paiement associé. Souscrivez d\'abord à un plan.',
    'VERIFICATION_CODE_SENT' => 'Code de vérification envoyé.',
    'VERIFICATION_TEMPORARILY_UNAVAILABLE' => 'La vérification de votre demande est temporairement indisponible. Réessayez dans quelques instants.',
    'TRIAL_SPACE_READY' => 'Votre espace Leopardo est prêt !',
    'SESSION_ALREADY_OPEN' => 'Une session est déjà ouverte pour cet employé.',
    'OUTSIDE_GEOFENCE' => 'Position hors zone de présence.',
    'ATTENDANCE_MODE_PERSONALIZATION_DISABLED' => 'La personnalisation du mode de pointage est désactivée.',
    'PREFERENCE_UPDATED' => 'Préférence mise à jour.',
    'CONFIG_UPDATED' => 'Configuration mise à jour.',
];
