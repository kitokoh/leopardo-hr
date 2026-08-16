# Feature Specification: API — batch i18n v4, messages FR résiduels localisés (issue #4396)

**Feature Branch**: `fix/4396-api-i18n-batch4-residual`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA (session 2026-08-16) — après les batches #4277/#4280/#4275/#4371
(issues #3237/#4191), ~22 littéraux FR restent codés en dur dans 12 fichiers backend.
Les tenants EN/TR/AR reçoivent encore des messages français.

## Problème

Messages FR en dur dans les champs `message` / `localized_message` (hors périmètre
déjà traité par #3237/#4191/#4395) :
- `AuthController` (jeton Google invalide)
- `PlatformUserController` (auto-désactivation/suspension ×3)
- `UserEmployeeLinkController` (lien employé-utilisateur ×4)
- `PartnerDashboardController` (candidature déjà soumise — `message` + `localized_message`)
- `EmployeeImportController` (CSV ×3, dont 1 message dynamique `:columns`)
- `PlatformCompanyController` (web, création société ×2)
- `PlatformCompanyRequestController` (demande déjà traitée)
- `ProactiveNotificationService` (prédictions AI ×3)
- `BillingController` (paiement en ligne non configuré, portail facturation)
- `PlatformAdminFleetAlertController` (alertes flotte)
- `CompanyBrandingController` (identité mise à jour)
- `SocialDeclarationController` (run paie hors pays — dynamique `:country`)

## Décision

- Ajouter 22 clés dans `api/lang/{fr,en,ar,tr}/errors.php` (fichier maintenu à la
  main — PAS généré par le sync i18n, comme les batches précédents).
- Remplacer les littéraux par `__('errors.KEY')` — locale résolue par le middleware
  `SetLocale` (préférence user → Accept-Language → fallback `fr`).
- Messages dynamiques : placeholders Laravel `:columns` / `:country` (remplacement
  `['columns' => ...]` / `['country' => ...]`).
- Hors périmètre : `VerifyTrialSignup` (#4395, PR en cours), messages EN de succès
  (suivi séparé), `AttendanceModeController` déjà traité par #4371.

## User Scenarios & Testing

### User Story 1 — Un tenant EN/TR/AR reçoit les messages localisés (Priority: P2)

**Independent Test**: `php -l` sur les 12 fichiers modifiés + les 4 catalogues ;
suite Feature existante verte (fallback `fr` = assertions FR inchangées) ; parité
des 4 locales vérifiée par script (aucune clé manquante).

**Acceptance Scenarios**:

1. **Given** un client avec `Accept-Language: en`, **When** il déclenche une erreur
   listée, **Then** `message` est en anglais.
2. **Given** un client sans header de langue, **When** il déclenche une erreur,
   **Then** `message` reste en FR (fallback `Language::DEFAULT`).
3. **Given** la base de code, **When** on grep les littéraux FR supprimés,
   **Then** zéro occurrence dans les 12 fichiers.

## Edge Cases

- Clés absentes d'une locale → Laravel retombe sur le fallback (pas de crash).
- `:columns` / `:country` : si la valeur contient `:` ou des espaces, Laravel
  remplace uniquement les tokens exacts (sans risque d'injection).
- `errors.php` n'est PAS régénéré par `sync-backend.js` — pas de drift du sync.
