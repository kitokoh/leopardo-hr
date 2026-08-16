# Feature Specification: API — messages FR codés en dur localisés (issue #3237)

**Feature Branch**: `fix/3237-api-fr-strings`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA expert5 2026-08-15 — les champs `message` de l'API contiennent du FR brut alors que le produit est multilingue (catalogue `/i18n/catalog`). Impact : les tenants EN/TR/AR reçoivent des erreurs françaises ; catalogue incohérent avec les réponses réelles.

## Problème

22 chaînes FR codées en dur dans 8 contrôleurs (PasswordReset, PlatformAuth, Billing, SelfServiceTrial, Evaluation, PaySlip, GeoAttendance, AttendanceMode) → `message` non localisé.

## Décision

- Ajouter 22 clés dans `api/lang/{fr,en,ar,tr}/errors.php` (fichier maintenu à la main — PAS généré par le sync i18n, contrairement à `shared.php`).
- Remplacer les littéraux FR par `__('errors.KEY')` — la locale est résolue par le middleware `SetLocale` (préférence user → Accept-Language → fallback `fr`).
- `Language::DEFAULT = 'fr'` → les tests existants assertant les messages FR restent verts.

## User Scenarios & Testing

### User Story 1 — Un tenant EN reçoit les messages d'erreur en anglais (Priority: P2)

**Independent Test**: suite Feature existante verte (les assertions FR passent via le fallback fr) + `php -l` sur les 8 contrôleurs et 4 fichiers lang.

**Acceptance Scenarios**:

1. **Given** un client avec `Accept-Language: en`, **When** il appelle un endpoint d'erreur, **Then** `message` est en anglais (`errors.KEY` résolu).
2. **Given** un client sans header de langue, **When** il appelle, **Then** `message` reste en FR (fallback `Language::DEFAULT`).
3. **Given** la base de code, **When** on grep les littéraux FR supprimés, **Then** zéro occurrence dans les 8 contrôleurs.

## Edge Cases

- Clés absentes d'une locale → Laravel retombe sur le fallback (pas de crash).
- `errors.php` n'est PAS régénéré par `sync-backend.js` (seul `shared.php` l'est) — pas de risque de drift du sync.
