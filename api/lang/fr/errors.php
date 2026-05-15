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

    // Général
    'NOT_FOUND' => 'Ressource introuvable.',
    'FORBIDDEN' => 'Vous n\'avez pas les droits pour cette action.',
    'SERVER_ERROR' => 'Une erreur est survenue. Veuillez réessayer.',
    'VALIDATION_ERROR' => 'Certains champs sont incorrects.',
    'UNSUPPORTED_API_VERSION' => 'Version API non supportee.',
];
