# STRATÉGIE INTERNATIONALISATION (i18n) — LEOPARDO RH
# Version 1.0 | Mars 2026
# Couvre : Backend Laravel + Frontend Vue.js/Inertia + Mobile Flutter

---

## 1. LANGUES SUPPORTÉES

| Code | Langue    | Direction | Phase 1        | Phase 2        |
|------|-----------|-----------|:--------------:|:--------------:|
| `fr` | Français  | LTR       | ✅ Principale  | —              |
| `ar` | Arabe     | **RTL**   | ✅ Priorité 1  | —              |
| `en` | Anglais   | LTR       | ✅ Priorité 2  | —              |
| `tr` | Turc      | LTR       | ✅ Priorité 3  | —              |

> **Règle absolue :** Le français (`fr`) est la langue de fallback.
> Si une clé est absente dans la langue demandée, Laravel/Flutter retombe sur `fr`.
> Une clé ne doit **jamais** retourner un code brut comme `absence.approved` à l'utilisateur.

---

## 2. TABLE `languages` (Schéma public)

```sql
CREATE TABLE languages (
    id          SERIAL      PRIMARY KEY,
    code        CHAR(2)     NOT NULL UNIQUE,   -- 'fr', 'ar', 'en', 'tr'
    name_fr     VARCHAR(50) NOT NULL,           -- 'Français', 'Arabe', 'Anglais', 'Turc'
    name_native VARCHAR(50) NOT NULL,           -- 'Français', 'العربية', 'English', 'Türkçe'
    is_rtl      BOOLEAN     NOT NULL DEFAULT FALSE,
    is_active   BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO languages (code, name_fr, name_native, is_rtl) VALUES
('fr', 'Français', 'Français',  FALSE),
('ar', 'Arabe',    'العربية',   TRUE),
('en', 'Anglais',  'English',   FALSE),
('tr', 'Turc',     'Türkçe',    FALSE);
```

Seules les langues `is_active = TRUE` apparaissent dans le formulaire de création d'entreprise.

---

## 3. RÉSOLUTION DE LA LANGUE (Ordre de priorité)

```
1. Langue de l'entreprise (companies.language)   ← Phase 1 : seul niveau actif
2. Fallback : 'fr'
```

**Phase 2 (optionnel) :** préférence personnelle par employé via `employees.preferred_language`.

### Middleware SetLocale (Laravel)

```php
// app/Http/Middleware/SetLocale.php
public function handle(Request $request, Closure $next): mixed
{
    $company = app('current_company');  // injecté par TenantMiddleware avant SetLocale
    $lang    = $company?->language ?? 'fr';
    $valid   = ['fr', 'ar', 'en', 'tr'];

    App::setLocale(in_array($lang, $valid) ? $lang : 'fr');

    return $next($request);
}
```

---

## 4. STRUCTURE FICHIERS DE TRADUCTION — BACKEND (Laravel)

```
api/lang/
├── fr/
│   ├── auth.php            ← Messages auth (INVALID_CREDENTIALS, ACCOUNT_LOCKED…)
│   ├── validation.php      ← Messages validation FormRequests
│   ├── errors.php          ← Codes d'erreur métier traduits
│   ├── notifications.php   ← Titres et corps push + emails
│   └── pdf.php             ← Labels bulletins de paie et rapports PDF
├── ar/
│   └── (même structure — textes RTL)
├── en/
│   └── (même structure)
└── tr/
    └── (même structure)
```

### Exemple — `lang/fr/errors.php`

```php
return [
    'ALREADY_CHECKED_IN'         => 'Vous avez déjà pointé votre arrivée à :time.',
    'MISSING_CHECK_IN'           => "Aucun pointage d'arrivée trouvé pour aujourd'hui.",
    'CHECKOUT_BEFORE_CHECKIN'    => "L'heure de départ ne peut pas être antérieure à l'arrivée.",
    'ACCOUNT_SUSPENDED'          => 'Compte suspendu. Contactez votre administrateur.',
    'SUBSCRIPTION_EXPIRED'       => "L'abonnement de votre entreprise a expiré.",
    'PLAN_EMPLOYEE_LIMIT_REACHED'=> 'Limite de :max employés atteinte pour le plan :plan.',
    'LEAVE_BALANCE_INSUFFICIENT' => 'Solde insuffisant. Disponible : :balance jours.',
    'PAYROLL_ALREADY_VALIDATED'  => 'La paie de :period est déjà validée.',
    'EMAIL_ALREADY_EXISTS'       => 'Cet email est déjà utilisé.',
];
```

### Exemple — `lang/ar/errors.php`

```php
return [
    'ALREADY_CHECKED_IN'         => 'لقد سجّلت حضورك بالفعل في الساعة :time.',
    'MISSING_CHECK_IN'           => 'لم يُسجَّل حضور لهذا اليوم.',
    'CHECKOUT_BEFORE_CHECKIN'    => 'لا يمكن أن يكون وقت الانصراف قبل وقت الحضور.',
    'ACCOUNT_SUSPENDED'          => 'الحساب موقوف. تواصل مع المسؤول.',
    'SUBSCRIPTION_EXPIRED'       => 'انتهت صلاحية اشتراك شركتك.',
    'PLAN_EMPLOYEE_LIMIT_REACHED'=> 'بلغت الحد الأقصى وهو :max موظف للخطة :plan.',
    'LEAVE_BALANCE_INSUFFICIENT' => 'رصيد إجازات غير كافٍ. المتاح: :balance يوم.',
    'PAYROLL_ALREADY_VALIDATED'  => 'رواتب الفترة :period مؤكدة بالفعل.',
    'EMAIL_ALREADY_EXISTS'       => 'هذا البريد الإلكتروني مستخدم بالفعل.',
];
```

---

## 5. STRUCTURE FICHIERS DE TRADUCTION — MOBILE (Flutter)

```
mobile/lib/l10n/
├── app_fr.arb     ← Langue de référence (toujours complète)
├── app_ar.arb
├── app_en.arb
└── app_tr.arb
```

### Configuration `pubspec.yaml`

```yaml
dependencies:
  flutter_localizations:
    sdk: flutter
  intl: ^0.19.0

flutter:
  generate: true
```

### Exemple — `app_fr.arb`

```json
{
  "@@locale": "fr",
  "loginTitle": "Connexion",
  "loginEmailHint": "Adresse email",
  "loginPasswordHint": "Mot de passe",
  "loginButton": "Se connecter",
  "checkInButton": "Pointer l'arrivée",
  "checkOutButton": "Pointer le départ",
  "absenceBalance": "{days, plural, =0{Aucun jour disponible} =1{1 jour disponible} other{{days} jours disponibles}}",
  "@absenceBalance": {
    "placeholders": { "days": { "type": "int" } }
  },
  "payslipTitle": "Bulletin — {month}/{year}",
  "@payslipTitle": {
    "placeholders": {
      "month": { "type": "String" },
      "year":  { "type": "String" }
    }
  },
  "notificationAbsenceApproved": "Votre demande de congé a été approuvée",
  "notificationTaskAssigned": "Une tâche vous a été assignée : {taskTitle}",
  "@notificationTaskAssigned": {
    "placeholders": { "taskTitle": { "type": "String" } }
  }
}
```

### Configuration `MaterialApp`

```dart
MaterialApp(
  localizationsDelegates: [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  supportedLocales: [
    Locale('fr'),
    Locale('ar'),
    Locale('en'),
    Locale('tr'),
  ],
  // Récupéré depuis la réponse /auth/me → company.language
  locale: Locale(ref.watch(sessionProvider).companyLanguage),
)
```

---

## 6. GESTION DU RTL — ARABE

### Flutter (automatique si Locale('ar') est actif)

Flutter inverse automatiquement les layouts avec `Locale('ar')`.
**Règle unique : ne jamais hardcoder `TextAlign.right` — utiliser `TextAlign.start`.**

```dart
// ❌ Cassé en arabe
Text('Nom', textAlign: TextAlign.right)

// ✅ Correct — s'adapte LTR/RTL
Text('Nom', textAlign: TextAlign.start)
```

**Points de vigilance :**
- Icônes directionnelles : utiliser `Icons.arrow_forward` (s'inverse automatiquement)
- Champs de saisie numériques (IBAN, téléphone) : toujours `textDirection: TextDirection.ltr`
- Graphiques / charts : vérifier que l'axe X n'est pas inversé

### Frontend Vue.js / Inertia

```html
<!-- Dans app.blade.php — dir dynamique selon la langue -->
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
```

```js
// Dans Inertia shared data (HandleInertiaRequests.php)
'locale'    => app()->getLocale(),
'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
```

```css
/* Tailwind CSS — classes RTL */
/* start/end s'inversent automatiquement selon dir="rtl" */
.container { padding-inline-start: 1rem; }  /* remplace padding-left */
```

---

## 7. FORMAT DATES ET MONTANTS PAR LOCALE

| Locale | Format date   | Séparateur décimal | Exemple             |
|--------|---------------|--------------------|---------------------|
| `fr`   | `DD/MM/YYYY`  | `,`                | `45 000,00 DZD`     |
| `ar`   | `DD/MM/YYYY`  | `,`                | `٤٥ ٠٠٠٫٠٠ د.ج`    |
| `en`   | `MM/DD/YYYY`  | `.`                | `45,000.00 DZD`     |
| `tr`   | `DD.MM.YYYY`  | `,`                | `45.000,00 DZD`     |

**Flutter :**
```dart
// Montant
NumberFormat.currency(locale: locale, symbol: currency).format(amount)

// Date
DateFormat.yMd(locale).format(date)
```

**Laravel :**
```php
Carbon::parse($date)->locale(app()->getLocale())->isoFormat('L')
```

---

## 8. TRADUCTION DES BULLETINS DE PAIE (PDF)

Les cotisations dans `hr_model_templates` ont un champ `name_i18n` :

```json
{
  "cotisations": [
    {
      "name_i18n": {
        "fr": "Sécurité Sociale (CNAS)",
        "ar": "الضمان الاجتماعي (CNAS)",
        "en": "Social Security (CNAS)",
        "tr": "Sosyal Güvenlik (CNAS)"
      },
      "rate_employee": 9.0,
      "base": "gross"
    }
  ]
}
```

Dans `PayrollService`, pour générer le PDF :
```php
$cotisationLabel = $cotisation['name_i18n'][app()->getLocale()]
    ?? $cotisation['name_i18n']['fr'];  // fallback fr
```

---

## 9. CE QUI N'EST PAS TRADUIT

Ces données restent dans leur format d'origine sans traduction :
- Noms / prénoms des employés
- Noms de départements, postes, projets, tâches (saisis par l'entreprise)
- Matricules, IBAN, numéros de compte
- Logs techniques (audit_logs)
- Codes d'erreur internes (dans les logs serveur)

---

## 10. CHECKLIST AVANT DÉPLOIEMENT D'UNE NOUVELLE LANGUE

- [ ] `api/lang/{code}/auth.php` créé et complet
- [ ] `api/lang/{code}/errors.php` créé et complet
- [ ] `api/lang/{code}/notifications.php` créé et complet
- [ ] `api/lang/{code}/pdf.php` créé et complet
- [ ] `mobile/lib/l10n/app_{code}.arb` créé et complet
- [ ] `Locale('{code}')` ajouté dans `supportedLocales` Flutter
- [ ] Si RTL : tests visuels sur tous les écrans (login, dashboard, bulletin, pointage)
- [ ] Format dates et montants validé
- [ ] INSERT dans la table `languages` (Super Admin)
- [ ] `name_i18n` ajouté dans tous les `hr_model_templates` concernés
- [ ] Test Pest : `it('SetLocale middleware applies company language', ...)`
