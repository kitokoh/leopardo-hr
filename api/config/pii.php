<?php

declare(strict_types=1);

/**
 * MAT-011 (#5869) — Catalogue de classification PII et cycle de vie (BC-01 PLATFORM).
 *
 * Chaque champ/type de donnée sensible possède une POLITIQUE explicite :
 * contexte (module/BC), sensibilité, chiffrement attendu, rétention légale,
 * et droits RGPD (export / anonymisation / suppression). Source de vérité
 * pour {@see \App\Core\Privacy\Infrastructure\Services\PiiRegistry} et le
 * rapport `docs/security/PII_CLASSIFICATION_MAT011.md`.
 *
 * Contexte d'application :
 *  - docs/security/DATA_AT_REST.md (chiffrement par surface) ;
 *  - docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md (traitements) ;
 *  - docs/security/POLITIQUE_RETENTION_DOCUMENTS.md (rétention documents) ;
 *  - commandes de purge existantes : audit:purge (36 mois), biometric:purge-expired
 *    (24 mois), accounting purge (120 mois), PurgeTtsFilesCommand.
 */
return [
    'version' => '1.0.0',

    // Politique globale par défaut : en l'absence d'entrée, la donnée est
    // considérée non sensible — MAIS toute donnée listée ici est soumise à la
    // politique de son entrée.
    'default_policy' => [
        'sensitivity' => 'low',
        'encrypted' => false,
        'retention_months' => 36,
        'exportable' => true,
        'anonymizable' => true,
        'deletable' => true,
    ],

    'categories' => [
        'identity' => [
            'label' => 'Identité',
            'entries' => [
                'first_name' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'last_name' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'middle_name' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'preferred_name' => ['context' => 'hr', 'sensitivity' => 'low', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'date_of_birth' => ['context' => 'hr', 'sensitivity' => 'high', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'place_of_birth' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'gender' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'nationality' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'marital_status' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'national_id' => ['context' => 'hr', 'sensitivity' => 'high', 'encrypted' => true, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'photo_path' => ['context' => 'hr', 'sensitivity' => 'high', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
            ],
        ],
        'contact' => [
            'label' => 'Coordonnées',
            'entries' => [
                'email' => ['context' => 'auth', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'personal_email' => ['context' => 'auth', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'recovery_email' => ['context' => 'auth', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'phone' => ['context' => 'auth', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'personal_phone' => ['context' => 'auth', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'address_line' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'postal_code' => ['context' => 'hr', 'sensitivity' => 'low', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'emergency_contact_name' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'emergency_contact_phone' => ['context' => 'hr', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'emergency_contact_relation' => ['context' => 'hr', 'sensitivity' => 'low', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
            ],
        ],
        'financial' => [
            'label' => 'Données financières & bancaires',
            'entries' => [
                'iban' => ['context' => 'payroll', 'sensitivity' => 'high', 'encrypted' => true, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => true, 'deletable' => false],
                'bank_account' => ['context' => 'payroll', 'sensitivity' => 'high', 'encrypted' => true, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => true, 'deletable' => false],
                'salary_base' => ['context' => 'payroll', 'sensitivity' => 'high', 'encrypted' => false, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => false, 'deletable' => false],
                'salary_type' => ['context' => 'payroll', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => false, 'deletable' => false],
                'hourly_rate' => ['context' => 'payroll', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => false, 'deletable' => false],
            ],
        ],
        'biometric' => [
            'label' => 'Données biométriques',
            'entries' => [
                'biometric_face_reference_path' => ['context' => 'attendance', 'sensitivity' => 'high', 'encrypted' => true, 'retention_months' => 24, 'exportable' => false, 'anonymizable' => true, 'deletable' => true],
                'biometric_fingerprint_reference_path' => ['context' => 'attendance', 'sensitivity' => 'high', 'encrypted' => true, 'retention_months' => 24, 'exportable' => false, 'anonymizable' => true, 'deletable' => true],
                'biometric_consent_at' => ['context' => 'attendance', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => 24, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
                'zkteco_id' => ['context' => 'attendance', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => true, 'deletable' => true],
            ],
        ],
        'workforce' => [
            'label' => 'Données de vie professionnelle',
            'entries' => [
                'attendance_logs' => ['context' => 'attendance', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => false, 'deletable' => true],
                'absences' => ['context' => 'leave', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => null, 'exportable' => true, 'anonymizable' => false, 'deletable' => true],
                'pay_slips' => ['context' => 'payroll', 'sensitivity' => 'high', 'encrypted' => false, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => false, 'deletable' => false],
                'expense_claims' => ['context' => 'expense', 'sensitivity' => 'medium', 'encrypted' => false, 'retention_months' => 120, 'exportable' => true, 'anonymizable' => false, 'deletable' => false],
                'password_hash' => ['context' => 'auth', 'sensitivity' => 'high', 'encrypted' => true, 'retention_months' => null, 'exportable' => false, 'anonymizable' => true, 'deletable' => true],
            ],
        ],
    ],
];
