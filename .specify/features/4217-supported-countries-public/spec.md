# Feature Specification: Registre multi-pays canonique accessible sans auth (issue #4217)

**Feature Branch**: `fix/4217-supported-countries-public`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Audit 360° 2026-08-16 (API locale v4.24.0) — `GET /api/v1/supported-countries` (registre canonique multi-pays #1867) est déclaré **dans le groupe `auth:sanctum` + `tenant`** (`routes/api.php`, groupe `throttle:api`) → 401 UNAUTHENTICATED en pré-auth, alors que :
- la vitrine duplique une liste `CURRENCY_OPTIONS` hardcodée (`front/web/src/modules/vitrine/data/currency.ts`) → risque de drift avec le registre API (même classe de bug que le drift pricing #3919) ;
- les apps mobiles et le formulaire public `trial/signup` (champ `country`) ne peuvent pas charger la liste canonique des pays supportés avant connexion.

## Problème

Le registre est une source de vérité **globale et non sensible** (codes pays ISO, devises, fuseaux, langues, `confidenceLevel`, `status` — aucune donnée métier/PII). Le placer derrière `auth:sanctum` + `tenant` le rend inutilisable par les parcours publics (vitrine, onboarding, mobile pré-login) et pousse les clients à dupliquer la liste (drift).

## Décision

1. Sortir `GET /supported-countries` du groupe authentifié ; le déclarer dans un groupe public dédié avec un throttle `public-registry` (60/min, GET-only).
2. Aucun breaking change : même chemin `GET /api/v1/supported-countries`, même payload.
3. Test de non-régression : 200 sans token + payload canonique (`confidenceLevel` présent) ; pas de route en écriture.

## User Scenarios & Testing

### User Story 1 — La vitrine/onboarding consomme le registre canonique sans connexion (Priority: P2)

**Independent Test**: nouveau `SupportedCountryControllerTest` (ou extension existante) vert.

**Acceptance Scenarios**:

1. **Given** aucun token, **When** `GET /api/v1/supported-countries`, **Then** 200 avec ≥ 4 pays et clés `confidenceLevel`/`payroll_enabled`.
2. **Given** un token valide, **When** la même requête, **Then** 200 (aucune régression).
3. **Given** le registre public, **When** un appel en écriture, **Then** 405 (GET-only).
4. **Given** le groupe authentifié, **When** les endpoints sensibles (`/auth/me` etc.), **Then** toujours 401 sans token (non-régression).

## Edge Cases

- Le throttle `public-registry` doit exister dans `App\Http\Kernel` / `bootstrap/app.php` (rate limiting).
- Le contrôleur ne dépend pas du tenant (registre global) — aucun changement de logique métier.
