# SEEDERS ET DONNÉES INITIALES
# Leopardo RH | Données à insérer dès le démarrage
# Version 1.0 | Mars 2026

---

## 1. PLANS TARIFAIRES (PlanSeeder)

```php
// database/seeders/PlanSeeder.php
Plan::insert([
    [
        'name' => 'Starter',
        'price_monthly' => 29.00,
        'price_yearly' => 290.00,
        'max_employees' => 20,
        'features' => json_encode([
            'biometric' => false,
            'tasks' => false,
            'advanced_reports' => false,
            'excel_export' => true,
            'bank_export' => false,
            'billing_auto' => false,
            'multi_managers' => false,
            'photo_attendance' => false,
            'api_public' => false,
            'evaluations' => false,
            'schema_isolation' => false,
        ]),
        'trial_days' => 14,
        'is_active' => true,
    ],
    [
        'name' => 'Business',
        'price_monthly' => 79.00,
        'price_yearly' => 790.00,
        'max_employees' => 200,
        'features' => json_encode([
            'biometric' => true,
            'tasks' => true,
            'advanced_reports' => true,
            'excel_export' => true,
            'bank_export' => true,
            'billing_auto' => true,
            'multi_managers' => true,
            'photo_attendance' => true,
            'api_public' => false,
            'evaluations' => true,
            'schema_isolation' => false,
        ]),
        'trial_days' => 14,
        'is_active' => true,
    ],
    [
        'name' => 'Enterprise',
        'price_monthly' => 199.00,
        'price_yearly' => 1990.00,
        'max_employees' => null,    // illimité
        'features' => json_encode([
            'biometric' => true,
            'tasks' => true,
            'advanced_reports' => true,
            'excel_export' => true,
            'bank_export' => true,
            'billing_auto' => true,
            'multi_managers' => true,
            'photo_attendance' => true,
            'api_public' => true,
            'evaluations' => true,
            'schema_isolation' => true,
        ]),
        'trial_days' => 30,
        'is_active' => true,
    ],
]);
```

---

## 2. LANGUES (LanguageSeeder)

```php
Language::insert([
    ['code' => 'fr', 'name' => 'Français', 'direction' => 'ltr', 'is_active' => true],
    ['code' => 'ar', 'name' => 'العربية', 'direction' => 'rtl', 'is_active' => true],
    ['code' => 'tr', 'name' => 'Türkçe', 'direction' => 'ltr', 'is_active' => true],
    ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'is_active' => true],
]);
```

---

## 3. MODÈLES RH PAR PAYS (HRModelSeeder)

### 3.1 Algérie (DZ)

```php
[
    'country_code' => 'DZ',
    'name' => 'Droit du travail algérien',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'CNAS (Sécurité sociale)', 'rate' => 9.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Retraite complémentaire', 'rate' => 1.5, 'base' => 'gross', 'ceiling' => null],
        ],
        'patronales' => [
            ['name' => 'CNAS (Part patronale)', 'rate' => 26.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Retraite (Part patronale)', 'rate' => 9.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Accidents de travail', 'rate' => 1.25, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Chômage (FNAC)', 'rate' => 1.0, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0, 'max' => 120000, 'rate' => 0, 'deduction' => 0],
        ['min' => 120001, 'max' => 360000, 'rate' => 20, 'deduction' => 24000],
        ['min' => 360001, 'max' => 1440000, 'rate' => 30, 'deduction' => 60000],
        ['min' => 1440001, 'max' => null, 'rate' => 35, 'deduction' => 132000],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 2.5,
        'initial_balance' => 0,
        'carry_over' => false,
        'max_balance' => 60,
        'min_notice_days' => 10,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Jour de l\'An', 'is_recurring' => true],
        ['date' => '01-05', 'name' => 'Fête du Travail', 'is_recurring' => true],
        ['date' => '07-05', 'name' => 'Mouvement du 5 juillet', 'is_recurring' => true],
        ['date' => '11-01', 'name' => 'Fête de la Révolution', 'is_recurring' => true],
        // Fêtes islamiques (dates variables — à mettre à jour annuellement)
        ['date' => '2026-03-20', 'name' => 'Aïd Al-Fitr (1)', 'is_recurring' => false],
        ['date' => '2026-03-21', 'name' => 'Aïd Al-Fitr (2)', 'is_recurring' => false],
        ['date' => '2026-06-16', 'name' => 'Aïd Al-Adha (1)', 'is_recurring' => false],
        ['date' => '2026-06-17', 'name' => 'Aïd Al-Adha (2)', 'is_recurring' => false],
        ['date' => '2026-07-07', 'name' => 'Nouvel An Hégirien', 'is_recurring' => false],
        ['date' => '2026-09-15', 'name' => 'Mawlid Ennabaoui', 'is_recurring' => false],
        // Fêtes berbères
        ['date' => '01-12', 'name' => 'Nouvel An Berbère (Yennayer)', 'is_recurring' => true],
    ]),
]
```

### 3.2 Maroc (MA)

```php
[
    'country_code' => 'MA',
    'name' => 'Code du travail marocain',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'CNSS (Part salariale)', 'rate' => 4.48, 'base' => 'gross', 'ceiling' => 6000],
            ['name' => 'AMO (Assurance maladie)', 'rate' => 2.26, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'CIMR (Retraite)', 'rate' => 3.0, 'base' => 'gross', 'ceiling' => null],
        ],
        'patronales' => [
            ['name' => 'CNSS (Part patronale)', 'rate' => 8.98, 'base' => 'gross', 'ceiling' => 6000],
            ['name' => 'CNSS (Allocations familiales)', 'rate' => 6.40, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'AMO (Part patronale)', 'rate' => 1.85, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'CIMR (Part patronale)', 'rate' => 5.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Formation professionnelle', 'rate' => 1.6, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0, 'max' => 30000, 'rate' => 0, 'deduction' => 0],
        ['min' => 30001, 'max' => 50000, 'rate' => 10, 'deduction' => 3000],
        ['min' => 50001, 'max' => 60000, 'rate' => 20, 'deduction' => 8000],
        ['min' => 60001, 'max' => 80000, 'rate' => 30, 'deduction' => 14000],
        ['min' => 80001, 'max' => 180000, 'rate' => 34, 'deduction' => 17200],
        ['min' => 180001, 'max' => null, 'rate' => 38, 'deduction' => 24400],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 1.5,
        'initial_balance' => 0,
        'carry_over' => false,
        'max_balance' => 30,
        'min_notice_days' => 3,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Nouvel An', 'is_recurring' => true],
        ['date' => '01-11', 'name' => 'Manifeste de l\'Indépendance', 'is_recurring' => true],
        ['date' => '05-01', 'name' => 'Fête du Travail', 'is_recurring' => true],
        ['date' => '07-30', 'name' => 'Fête du Trône', 'is_recurring' => true],
        ['date' => '08-14', 'name' => 'Oued Ed-Dahab', 'is_recurring' => true],
        ['date' => '08-20', 'name' => 'Révolution du Roi et du Peuple', 'is_recurring' => true],
        ['date' => '08-21', 'name' => 'Fête de la Jeunesse', 'is_recurring' => true],
        ['date' => '11-06', 'name' => 'Marche Verte', 'is_recurring' => true],
        ['date' => '11-18', 'name' => 'Fête de l\'Indépendance', 'is_recurring' => true],
        // Fêtes islamiques variables 2026
        ['date' => '2026-03-20', 'name' => 'Aïd Al-Fitr', 'is_recurring' => false],
        ['date' => '2026-03-21', 'name' => 'Aïd Al-Fitr (2)', 'is_recurring' => false],
        ['date' => '2026-06-16', 'name' => 'Aïd Al-Adha', 'is_recurring' => false],
        ['date' => '2026-06-17', 'name' => 'Aïd Al-Adha (2)', 'is_recurring' => false],
    ]),
]
```

### 3.3 Tunisie (TN)

```php
[
    'country_code' => 'TN',
    'name' => 'Code du travail tunisien',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'CNSS (Part salariale)', 'rate' => 9.18, 'base' => 'gross', 'ceiling' => null],
        ],
        'patronales' => [
            ['name' => 'CNSS (Part patronale)', 'rate' => 16.57, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0, 'max' => 5000, 'rate' => 0, 'deduction' => 0],
        ['min' => 5001, 'max' => 20000, 'rate' => 26, 'deduction' => 1300],
        ['min' => 20001, 'max' => 30000, 'rate' => 28, 'deduction' => 1700],
        ['min' => 30001, 'max' => 50000, 'rate' => 32, 'deduction' => 2900],
        ['min' => 50001, 'max' => null, 'rate' => 35, 'deduction' => 4400],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 2.0,
        'initial_balance' => 0,
        'carry_over' => true,
        'max_balance' => 45,
        'min_notice_days' => 5,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Jour de l\'An', 'is_recurring' => true],
        ['date' => '03-20', 'name' => 'Fête de l\'Indépendance', 'is_recurring' => true],
        ['date' => '04-09', 'name' => 'Fête des Martyrs', 'is_recurring' => true],
        ['date' => '05-01', 'name' => 'Fête du Travail', 'is_recurring' => true],
        ['date' => '07-25', 'name' => 'Fête de la République', 'is_recurring' => true],
        ['date' => '08-13', 'name' => 'Fête de la Femme', 'is_recurring' => true],
        ['date' => '10-15', 'name' => 'Fête de l\'Evacuation', 'is_recurring' => true],
    ]),
]
```

### 3.4 France (FR)

```php
[
    'country_code' => 'FR',
    'name' => 'Code du travail français',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'Sécurité sociale maladie', 'rate' => 0.75, 'base' => 'gross', 'ceiling' => null, 'multiplier' => 1.0],
            ['name' => 'Vieillesse (plafonné)', 'rate' => 6.90, 'base' => 'gross', 'ceiling' => 3666, 'multiplier' => 1.0],
            ['name' => 'Vieillesse (déplafonné)', 'rate' => 0.40, 'base' => 'gross', 'ceiling' => null, 'multiplier' => 1.0],
            ['name' => 'Chômage (Pôle emploi)', 'rate' => 0.0, 'base' => 'gross', 'ceiling' => null, 'multiplier' => 1.0],
            ['name' => 'Complémentaire retraite T1', 'rate' => 3.15, 'base' => 'gross', 'ceiling' => 3666, 'multiplier' => 1.0],
            // ⚠️ CSG/CRDS : base = gross × 0.9825 (abattement 1.75% frais professionnels)
            // multiplier indique le coefficient à appliquer sur gross avant le calcul du taux
            ['name' => 'CSG déductible', 'rate' => 6.80, 'base' => 'gross', 'ceiling' => null, 'multiplier' => 0.9825],
            ['name' => 'CSG non déductible + CRDS', 'rate' => 2.90, 'base' => 'gross', 'ceiling' => null, 'multiplier' => 0.9825],
        ],
        'patronales' => [
            ['name' => 'Sécurité sociale maladie', 'rate' => 7.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Allocations familiales', 'rate' => 3.45, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Vieillesse (plafonné)', 'rate' => 8.55, 'base' => 'gross', 'ceiling' => 3666],
            ['name' => 'Accident du travail', 'rate' => 1.5, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Chômage (UNEDIC)', 'rate' => 4.05, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        // Note : en France l'IR est annuel et déclaré par le salarié
        // La retenue à la source (PAS) est configurée individuellement
        // Le champ ci-dessous est indicatif pour le barème mensuel
        ['min' => 0, 'max' => 1477, 'rate' => 0, 'deduction' => 0],
        ['min' => 1478, 'max' => 2641, 'rate' => 11, 'deduction' => 162],
        ['min' => 2642, 'max' => 7084, 'rate' => 30, 'deduction' => 664],
        ['min' => 7085, 'max' => 12512, 'rate' => 41, 'deduction' => 1444],
        ['min' => 12513, 'max' => null, 'rate' => 45, 'deduction' => 1944],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 2.5,
        'initial_balance' => 0,
        'carry_over' => false,
        'max_balance' => 30,
        'min_notice_days' => 1,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Jour de l\'An', 'is_recurring' => true],
        ['date' => '05-01', 'name' => 'Fête du Travail', 'is_recurring' => true],
        ['date' => '05-08', 'name' => 'Victoire 1945', 'is_recurring' => true],
        ['date' => '07-14', 'name' => 'Fête Nationale', 'is_recurring' => true],
        ['date' => '08-15', 'name' => 'Assomption', 'is_recurring' => true],
        ['date' => '11-01', 'name' => 'Toussaint', 'is_recurring' => true],
        ['date' => '11-11', 'name' => 'Armistice', 'is_recurring' => true],
        ['date' => '12-25', 'name' => 'Noël', 'is_recurring' => true],
        // Variables 2026
        ['date' => '2026-04-06', 'name' => 'Lundi de Pâques', 'is_recurring' => false],
        ['date' => '2026-05-14', 'name' => 'Ascension', 'is_recurring' => false],
        ['date' => '2026-05-25', 'name' => 'Lundi de Pentecôte', 'is_recurring' => false],
    ]),
]
```

### 3.5 Turquie (TR)

```php
[
    'country_code' => 'TR',
    'name' => 'Droit du travail turc',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'SGK Santé (Salarié)', 'rate' => 5.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'SGK Retraite (Salarié)', 'rate' => 9.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Chômage (Salarié)', 'rate' => 1.0, 'base' => 'gross', 'ceiling' => null],
        ],
        'patronales' => [
            ['name' => 'SGK Santé (Employeur)', 'rate' => 7.5, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'SGK Retraite (Employeur)', 'rate' => 11.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'Chômage (Employeur)', 'rate' => 2.0, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0, 'max' => 110000, 'rate' => 15, 'deduction' => 0],
        ['min' => 110001, 'max' => 230000, 'rate' => 20, 'deduction' => 5500],
        ['min' => 230001, 'max' => 580000, 'rate' => 27, 'deduction' => 21600],
        ['min' => 580001, 'max' => 3000000, 'rate' => 35, 'deduction' => 68000],
        ['min' => 3000001, 'max' => null, 'rate' => 40, 'deduction' => 218000],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 1.83,
        'initial_balance' => 0,
        'carry_over' => false,
        'max_balance' => 30,
        'min_notice_days' => 3,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Yılbaşı (Nouvel An)', 'is_recurring' => true],
        ['date' => '04-23', 'name' => 'Ulusal Egemenlik ve Çocuk Bayramı', 'is_recurring' => true],
        ['date' => '05-01', 'name' => 'İşçi ve Emekçi Bayramı', 'is_recurring' => true],
        ['date' => '05-19', 'name' => 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı', 'is_recurring' => true],
        ['date' => '07-15', 'name' => 'Demokrasi ve Millî Birlik Günü', 'is_recurring' => true],
        ['date' => '08-30', 'name' => 'Zafer Bayramı', 'is_recurring' => true],
        ['date' => '10-29', 'name' => 'Cumhuriyet Bayramı (1 jour)', 'is_recurring' => true],
        ['date' => '10-28', 'name' => 'Cumhuriyet Bayramı (demi-journée)', 'is_recurring' => true],
    ]),
]
```

### 3.6 Sénégal (SN)

```php
[
    'country_code' => 'SN',
    'name' => 'Code du travail sénégalais',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'IPRES (Retraite de base)', 'rate' => 5.6, 'base' => 'gross', 'ceiling' => 630000],
            ['name' => 'CSS (Sécurité sociale)', 'rate' => 0.0, 'base' => 'gross', 'ceiling' => null],
        ],
        'patronales' => [
            ['name' => 'IPRES (Part patronale)', 'rate' => 8.4, 'base' => 'gross', 'ceiling' => 630000],
            ['name' => 'CSS (Accidents de travail)', 'rate' => 1.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'CSS (Allocations familiales)', 'rate' => 7.0, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0, 'max' => 630000, 'rate' => 0, 'deduction' => 0],
        ['min' => 630001, 'max' => 1500000, 'rate' => 20, 'deduction' => 126000],
        ['min' => 1500001, 'max' => 4000000, 'rate' => 30, 'deduction' => 276000],
        ['min' => 4000001, 'max' => 8000000, 'rate' => 35, 'deduction' => 476000],
        ['min' => 8000001, 'max' => 13500000, 'rate' => 37, 'deduction' => 636000],
        ['min' => 13500001, 'max' => null, 'rate' => 40, 'deduction' => 1041000],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 1.5,
        'initial_balance' => 0,
        'carry_over' => false,
        'max_balance' => 24,
        'min_notice_days' => 5,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Jour de l\'An', 'is_recurring' => true],
        ['date' => '04-04', 'name' => 'Fête de l\'Indépendance', 'is_recurring' => true],
        ['date' => '05-01', 'name' => 'Fête du Travail', 'is_recurring' => true],
        ['date' => '08-15', 'name' => 'Assomption', 'is_recurring' => true],
        ['date' => '11-01', 'name' => 'Toussaint', 'is_recurring' => true],
        ['date' => '12-25', 'name' => 'Noël', 'is_recurring' => true],
    ]),
]
```

### 3.7 Côte d'Ivoire (CI)

```php
[
    'country_code' => 'CI',
    'name' => 'Code du travail ivoirien',
    'cotisations' => json_encode([
        'salariales' => [
            ['name' => 'CNPS (Part salariale)', 'rate' => 3.2, 'base' => 'gross', 'ceiling' => 1647315],
        ],
        'patronales' => [
            ['name' => 'CNPS (Retraite patronale)', 'rate' => 4.8, 'base' => 'gross', 'ceiling' => 1647315],
            ['name' => 'CNPS (Accidents de travail)', 'rate' => 2.0, 'base' => 'gross', 'ceiling' => null],
            ['name' => 'CNPS (Allocations familiales)', 'rate' => 5.75, 'base' => 'gross', 'ceiling' => null],
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0, 'max' => 600000, 'rate' => 0, 'deduction' => 0],
        ['min' => 600001, 'max' => 1200000, 'rate' => 5, 'deduction' => 30000],
        ['min' => 1200001, 'max' => 2400000, 'rate' => 15, 'deduction' => 150000],
        ['min' => 2400001, 'max' => 4800000, 'rate' => 25, 'deduction' => 390000],
        ['min' => 4800001, 'max' => null, 'rate' => 35, 'deduction' => 870000],
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 2.5,
        'initial_balance' => 0,
        'carry_over' => false,
        'max_balance' => 30,
        'min_notice_days' => 5,
    ]),
    'holiday_calendar' => json_encode([
        ['date' => '01-01', 'name' => 'Jour de l\'An', 'is_recurring' => true],
        ['date' => '05-01', 'name' => 'Fête du Travail', 'is_recurring' => true],
        ['date' => '08-07', 'name' => 'Fête Nationale', 'is_recurring' => true],
        ['date' => '08-15', 'name' => 'Assomption', 'is_recurring' => true],
        ['date' => '11-01', 'name' => 'Toussaint', 'is_recurring' => true],
        ['date' => '11-15', 'name' => 'Fête Nationale (Paix)', 'is_recurring' => true],
        ['date' => '12-25', 'name' => 'Noël', 'is_recurring' => true],
    ]),
]
```

---

## 4. TYPES D'ABSENCE PAR DÉFAUT (TenantSeeder — inséré à la création de chaque entreprise)

```php
// Ces types sont créés automatiquement dans le schéma tenant à la création de l'entreprise
$defaultAbsenceTypes = [
    [
        'label' => 'Congé payé',
        'is_paid' => true,
        'pay_rate' => null,
        'deducts_leave' => true,
        'requires_justification' => false,
        'min_notice_days' => 3,
        'max_days' => null,
        'who_submits' => 'employee',
        'color' => '#4CAF50',
    ],
    [
        'label' => 'Congé sans solde',
        'is_paid' => false,
        'pay_rate' => null,
        'deducts_leave' => false,
        'requires_justification' => false,
        'min_notice_days' => 3,
        'max_days' => 30,
        'who_submits' => 'employee',
        'color' => '#9E9E9E',
    ],
    [
        'label' => 'Maladie (avec justificatif)',
        'is_paid' => true,
        'pay_rate' => 100,
        'deducts_leave' => false,
        'requires_justification' => true,
        'min_notice_days' => 0,
        'max_days' => 180,
        'who_submits' => 'employee',
        'color' => '#FF9800',
    ],
    [
        'label' => 'Absence injustifiée',
        'is_paid' => false,
        'pay_rate' => null,
        'deducts_leave' => false,
        'requires_justification' => false,
        'min_notice_days' => 0,
        'max_days' => null,
        'who_submits' => 'manager',
        'color' => '#F44336',
    ],
    [
        'label' => 'Congé maternité',
        'is_paid' => true,
        'pay_rate' => 100,
        'deducts_leave' => false,
        'requires_justification' => true,
        'min_notice_days' => 30,
        'max_days' => 98,
        'who_submits' => 'employee',
        'color' => '#E91E63',
    ],
    [
        'label' => 'Congé paternité',
        'is_paid' => true,
        'pay_rate' => 100,
        'deducts_leave' => false,
        'requires_justification' => true,
        'min_notice_days' => 7,
        'max_days' => 10,
        'who_submits' => 'employee',
        'color' => '#2196F3',
    ],
    [
        'label' => 'Événement familial',
        'is_paid' => true,
        'pay_rate' => 100,
        'deducts_leave' => false,
        'requires_justification' => true,
        'min_notice_days' => 0,
        'max_days' => 5,
        'who_submits' => 'employee',
        'color' => '#9C27B0',
    ],
];
```

---

## 5. PLANNING PAR DÉFAUT (créé pour chaque nouvelle entreprise)

```php
$defaultSchedule = [
    'name' => 'Standard (8h - 17h)',
    'start_time' => '08:00:00',
    'end_time' => '17:00:00',
    'break_minutes' => 60,
    'work_days' => [1, 2, 3, 4, 5],   // Lun-Ven
    'late_tolerance_minutes' => 15,
    'overtime_threshold_daily' => 8.0,
    'overtime_threshold_weekly' => 40.0,
    'is_default' => true,
];
```

---

## 6. PARAMÈTRES PAR DÉFAUT (company_settings — créés à la création de chaque entreprise)

```php
$defaultSettings = [
    // Pointage
    'attendance.gps_enabled' => 'false',
    'attendance.gps_radius_m' => '100',
    'attendance.photo_enabled' => 'false',
    'attendance.biometric_enabled' => 'false',

    // Congés
    'leave.carry_over' => 'false',
    'leave.carry_over_max_days' => '0',
    'leave.validation_levels' => '1',

    // Avances (désactivé par défaut)
    'advance.enabled' => 'false',
    'advance.max_percentage' => '50',
    'advance.max_simultaneous' => '1',
    'advance.max_repayment_months' => '3',
    'advance.min_delay_days' => '30',

    // Paie
    'payroll.bank_export_format' => 'GENERIC_CSV',
    'payroll.overtime_rate_1' => '1.25',
    'payroll.overtime_rate_2' => '1.50',
    'payroll.overtime_threshold_hours' => '2',

    // Tâches et évaluation
    'tasks.enabled' => 'true',
    'evaluation.auto_score_enabled' => 'false',
    'evaluation.self_evaluation_enabled' => 'false',

    // Notifications
    'notifications.late_alert_enabled' => 'true',
    'notifications.missing_attendance_delay_hours' => '1',
    'notifications.contract_alert_days' => '30,15,7',
    'notifications.subscription_alert_days' => '30,15,7',

    // Interface
    'ui.allow_employee_language_choice' => 'false',
    'ui.show_org_chart' => 'true',
];
```

---

## 7. COMPTE SUPER ADMIN INITIAL

```bash
# Créer via commande Artisan après le premier déploiement
php artisan leopardo:create-super-admin \
    --name="Super Admin" \
    --email="admin@leopardo-rh.com" \
    --password="MotDePasseAdmin2026!"

# ⚠️ CHANGER CE MOT DE PASSE immédiatement après la première connexion
```

---

## 8. DONNÉES DE TEST — ENTREPRISE DEMO (dev uniquement)

```php
// database/seeders/DemoSeeder.php (ne pas déployer en production)
// Crée une entreprise de démo avec 20 employés fictifs pour les tests

// Entreprise : TechCorp SPA (Algérie, FR, plan Business)
// Gestionnaire : admin@techcorp-demo.com / Demo2026!
// Employés : 18 actifs, 1 suspendu, 1 archivé
// Données : 3 mois de pointages, absences, paies générées
```
