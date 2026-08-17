# Feature Specification: Portail client Lot 2 — pages billing/contrats/absences/pointage/social ×4 (issue-fille #4574)

**Feature Branch**: `fix/4574-portal-i18n-lot2`

**Created**: 2026-08-17

**Status**: Draft → Implemented

**Input**: Issue de suivi #4574 (audit 360°) — le portail client `(dashboard)` était partiellement FR ; le CopyTree (`lib/i18n.ts`, getCopy ×4) couvrait nav/payroll/smart-attendance mais pas billing/contrats/absences/pointage/social.

## Décision

1. Étendre le type `CopyTree` + les 4 blocs de la map `copy` : sections `billing` (14 clés), `contracts` (8), `absencesPage` (1), `attendancePage` (1), `socialPage` (4), `socialMarketingPage` (10) — 40 clés ×4 locales (traductions complètes fr/en/tr/ar, apostrophes échappées).
2. Les 6 pages consomment `getCopy(locale)` (locale via `useSyncExternalStore(getPreferredLocale)`, fallback fr) ; les statuts de facture passent par un helper `statusLabel(key, copy)`.
3. `// eslint-disable-next-line react-hooks/exhaustive-deps` sur les hooks dont la chaîne d'erreur est lue depuis `copy` (le helper `getCopy` est stable par locale — pas de re-render attendu).
4. Le dashboard était déjà localisé via le catalogue (clés `dashboard.*` présentes ×4) — non modifié.

## User Scenarios & Testing

### US1 — Un admin EN voit la facturation en anglais (P2)
**Independent Test**: tsc 0, eslint 0, garde PA2-I18N-014 verte sur le diff, 455 tests vitrine verts.
1. **Given** utilisateur `language=en`, **When** `/billing`, **Then** statuts « Active/Cancelled/Past due », « No active period », « Cancel subscription ».
2. **Given** abonnement actif + réseau coupé, **When** chargement, **Then** « Unable to load billing information. ».

### US2 — Un employé TR/AR voit ses absences/pointage en TR/AR
1. **Given** locale `tr`, **When** `/absences` en erreur, **Then** « Devamsızlıklar yüklenemedi. ».
2. **Given** locale `ar`, **When** `/attendance` en erreur, **Then** « تعذر تحميل الحضور. ».

### US3 — Marketing social localisé
1. **Given** locale `en`, **When** erreur de connexion du compte, **Then** « Unable to connect the social account. ».

## Edge Cases

- Les libellés de statut inconnus retombent sur le code brut (`labels[key] ?? key`).
- `getPreferredLocale` renvoie la langue du compte utilisateur (store), fallback navigateur puis FR.
- Les clés sont typées (CopyTree) — une clé manquante casse tsc, pas le runtime.
