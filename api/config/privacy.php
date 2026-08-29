<?php

declare(strict_types=1);

/**
 * MAT-011 (#5869) — Classification PII et cycle de vie.
 *
 * Registre machine des champs sensibles (PII) par entité : chaque champ
 * possède une politique (catégorie, chiffrement, anonymisation, export,
 * accès, rétention, base légale). Source de vérité consommée par
 * `App\Core\Privacy\PiiFieldRegistry` et par les tests de cohérence RGPD
 * (parité avec `gdpr:anonymize-employee`, les casts `encrypted` et le
 * bundle d'export `PrivacyController`).
 *
 * Conventions :
 * - `encrypted`    : le champ est chiffré au repos (cast `encrypted` Laravel) ;
 * - `anonymized`   : le champ est couvert par `gdpr:anonymize-employee`
 *                    (droit à l'effacement, historique de paie conservé) ;
 * - `exported`     : le champ figure dans le bundle d'export RGPD
 *                    (`GET /api/v1/privacy/export`) ;
 * - `access`       : contextes autorisés à lire le champ ;
 * - `retention`    : durée/règle de conservation ;
 * - `legal_basis`  : base légale (RGPD / Loi 18-07).
 *
 * Ne jamais ajouter de secret/PII réel ici : uniquement des noms de champs
 * et des politiques.
 */
return [
    'entities' => [
        'employee' => [
            'label' => 'Employé',
            'export_endpoint' => 'GET /api/v1/privacy/export',
            'anonymize_command' => 'gdpr:anonymize-employee',
            'fields' => [
                // Identité
                'first_name' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'middle_name' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'last_name' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'preferred_name' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'date_of_birth' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'place_of_birth' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'gender' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'nationality' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'marital_status' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh,payroll', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                // Contact
                'email' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh', 'retention' => 'durée contrat + 10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'personal_email' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'recovery_email' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'personal_phone' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'phone' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,manager,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'address_line' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'postal_code' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'emergency_contact_name' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'consentement / Loi 18-07'],
                'emergency_contact_phone' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'consentement / Loi 18-07'],
                'emergency_contact_relation' => ['category' => 'contact', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'consentement / Loi 18-07'],
                // Identifiants nationaux & bancaires (chiffrés au repos)
                'national_id' => ['category' => 'national_id', 'encrypted' => true, 'anonymized' => true, 'exported' => false, 'access' => 'rh,payroll', 'retention' => '10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'iban' => ['category' => 'banking', 'encrypted' => true, 'anonymized' => true, 'exported' => false, 'access' => 'rh,payroll', 'retention' => '10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                'bank_account' => ['category' => 'banking', 'encrypted' => true, 'anonymized' => true, 'exported' => false, 'access' => 'rh,payroll', 'retention' => '10 ans (paie DZ)', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                // Biométrie (consentement explicite)
                'biometric_face_reference_path' => ['category' => 'biometric', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'system', 'retention' => 'révocable à tout moment (consentement)', 'legal_basis' => 'consentement explicite'],
                'biometric_fingerprint_reference_path' => ['category' => 'biometric', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'system', 'retention' => 'révocable à tout moment (consentement)', 'legal_basis' => 'consentement explicite'],
                'biometric_consent_at' => ['category' => 'biometric', 'encrypted' => false, 'anonymized' => true, 'exported' => true, 'access' => 'self,rh', 'retention' => 'révocable à tout moment (consentement)', 'legal_basis' => 'consentement explicite'],
                'photo_path' => ['category' => 'biometric', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'self,rh', 'retention' => 'durée contrat', 'legal_basis' => 'consentement / Loi 18-07'],
                // Authentification (protégé par hash/accès système, pas par
                // cast `encrypted` — politique documentée dans DATA_AT_REST)
                'password_hash' => ['category' => 'auth', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'system', 'retention' => 'durée de vie du compte', 'legal_basis' => 'sécurité / RGPD art.32 (hash one-way)'],
                'two_fa_secret' => ['category' => 'auth', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'system', 'retention' => 'durée de vie du compte', 'legal_basis' => 'sécurité / RGPD art.32'],
                'two_fa_recovery_codes' => ['category' => 'auth', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'system', 'retention' => 'durée de vie du compte', 'legal_basis' => 'sécurité / RGPD art.32'],
                'zkteco_id' => ['category' => 'identity', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'system,rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
                // Données libres (peuvent contenir du PII — effacées à l'anonymisation)
                'extra_data' => ['category' => 'misc', 'encrypted' => false, 'anonymized' => true, 'exported' => false, 'access' => 'rh', 'retention' => 'durée contrat', 'legal_basis' => 'RGPD art.17 / Loi 18-07'],
            ],
        ],
    ],
];
