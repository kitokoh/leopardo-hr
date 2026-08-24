# Feature Specification: i18n mobile vague 1 — écrans pointage + paie (Closes #5278)

**Branch**: `mod/platform/5278-i18n-wave1` | **Created**: 2026-08-23 | **Issue**: #5278 (P1, mobile, i18n)

## Contexte

Suite du chantier #2755 : 8 983 chaînes UI hardcodées hors catalogues, dont 2 715 P1 (visibles utilisateur).
Répartition majoritaire : apps Flutter mobile (employee/hr/manager), écrans pointage, pauses, erreurs.
Mesure de référence (2026-08-23, `dev-hub/tools/i18n-debt.js`) : **P1 = 2 037** au global ; périmètre pointage+paie ≈ 72 signaux P1 hors catalogues, répartis sur `attendance/**`, `smart_attendance/**`, `payrolls/**` des 3 apps.

La vague 1 couvre UNIQUEMENT les écrans pointage + paie mobile (priorité P1). Les vagues 2 (HR + self-service) et 3 (restes + scan CI) restent hors périmètre de cette PR, sauf le scan CI anti-régression exigé par le DoD #5278.

## Requirements

- **FR-001** : chaque chaîne P1 visible utilisateur des fichiers `features/attendance/**`, `features/smart_attendance/**`, `features/payrolls/**` des apps `leopardo_employee`, `leopardo_hr`, `leopardo_manager` est extraite vers le catalogue ARB de `leopardo_core/lib/l10n/` (template `app_fr.arb`, miroirs `app_en/ar/tr`).
- **FR-002** : le code consomme les clés via `context.l10n.<key>` (widgets) ; pour les classes sans `BuildContext` (providers, repositories, services), via `AppLocalizations.lookupAppLocalizations(deviceLocale)` — pattern déjà utilisé par `error_messages.dart` (locale appareil fr/en/tr/ar, repli fr).
- **FR-003** : parité ARB ×4 maintenue (`flutter gen-l10n` + garde `i18n-enterprise.yml` existant).
- **FR-004** : scan CI anti-régression : job dédié exécutant `node dev-hub/tools/i18n-debt.js --strict` limité au périmètre mobile, bloquant si la dette P1 mobile remonte au-dessus du plancher de cette PR.
- **FR-005** : aucune chaîne extraite ne change de sens ni de format (`{placeholder}` conservés, pluriels gérés via placeholders numériques).

## Success Criteria

- **SC-001** : `node dev-hub/tools/i18n-debt.js --report /tmp/i18n.json` → 0 signal P1 dans le périmètre vague 1 (fichiers `attendance/**`, `smart_attendance/**`, `payrolls/**` des 3 apps).
- **SC-002** : parité ARB : `jq 'keys|length'` identique sur les 4 fichiers ; `flutter gen-l10n` sans erreur ; `flutter analyze` vert sur les 3 apps + core.
- **SC-003** : `flutter test` vert sur `leopardo_core` (tests i18n existants) + les apps modifiées.
- **SC-004** : les chaînes techniques (formats de date `HH:mm`, `EEEE d MMMM yyyy`, clés API, messages serveur) restent hors ARB : elles ne sont pas du texte utilisateur localisable.
- **SC-005** : CHANGELOG.md — 1 ligne en tête d'[Unreleased] ; `Closes #5278`.

## Anti-collision

- Périmètre : `front/mobile_apps/**` + `dev-hub/tools/i18n-debt.js` (+ workflow CI i18n) uniquement.
- Aucun chevauchement avec les PRs actives (Attendance/HR/Payroll backend, Platform workflows, Accounting data) — claim publié sur #5278 (2026-08-23).

## Hors périmètre (vagues 2/3)

- Écrans HR/self-service/social/evaluations (vague 2) ; restes + durcissement scan global (vague 3) ; vitrine Next.js ; admin-dashboard ; kiosk.
