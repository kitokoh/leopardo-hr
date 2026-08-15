# Feature Specification: QA Expert #5 — 2026-08-15 (vague de constats)

**Feature Branch**: `docs/qa-expert5-specs-2026-08-15`
**Created**: 2026-08-15
**Status**: Draft → Implemented
**Input**: Session QA expert #5 — tests dynamiques prod + revue statique 4 surfaces + builds locaux.

## User Scenarios & Testing

### User Story 1 — Les écritures payroll restent réservées aux managers habilités (Priority: P1)
Tout manager authentifié (y compris dept/superviseur) peut aujourd'hui créer/modifier/valider/
supprimer des payrolls via rh.php ; la policy produit (payroll_engine.php) restreint à
principal/comptable. **Why**: autorisation (escalade de privilège latente).
**Independent Test**: `PUT /payrolls/{id}` avec token dept → 403 ; avec principal → 200.
**Acceptance Scenarios**:
1. **Given** un manager `dept` authentifié, **When** il appelle `PUT /api/v1/payrolls/{id}`,
   **Then** réponse 403 (politique `api.manager:principal,comptable`).
2. **Given** un manager `principal`, **When** il appelle la même route, **Then** 200.

### User Story 2 — Le cockpit de lancement est honnête sur les tenants vides (Priority: P2)
`communication_governance` passe à 1 quand 0 employé actif (0 ≥ 0). **Why**: score go-live trompeur.
**Independent Test**: tenant sans employé → `communication_governance = 0` et score cohérent.

### User Story 3 — Les erreurs de correction de pointage sont attachées au bon champ (Priority: P3)
**Independent Test**: `requested_check_out` futur → erreur sur `requested_check_out`, pas
`requested_check_in`.

### User Story 4 — La fiche entreprise admin affiche l'identité technique complète (Priority: P2)
`PlatformCompanyHealthService` n'émet ni `slug` ni `created_at` → champs vides dans
`CompanyDetailView`. **Independent Test**: `/platform/companies/{id}/health` contient
`company.slug` et `company.created_at`.

### User Story 5 — Les labels pays admin existent pour tous les codes référencés (Priority: P3)
18 codes référencés, 12 clés `common.countries.*` définies → clés brutes à l'écran.
**Independent Test**: `$t('common.countries.CG')` rend « Congo » dans les 4 locales.

### User Story 6 — Pas de devise codée en dur dans les apps mobiles (Priority: P2)
`DZD` par défaut dans la création entreprise platform_admin + 5 modèles partagés.
**Independent Test**: création entreprise pays=FR → devise API (EUR), jamais DZD.

### User Story 7 — La cartographie des apps reflète la réalité (Priority: P3)
AGENTS.md/README listent `leopardo_kiosk` (web) parmi les apps Flutter et omettent
`leopardo_employee`. **Independent Test**: lecture des 2 fichiers → 6 apps mobile_apps dont
employee, kiosk étiqueté web.

### User Story 8 — Le sitemap ne publie pas /blog quand le blog est désactivé (Priority: P3)
**Independent Test**: build avec flag off → sitemap.xml sans `/blog`.

## Edge Cases
- Route payroll : ne pas casser le workflow double validation salary-advances (même groupe).
- Health service : garder la tolérance aux champs optionnels (CompanyResource existant).
- L10n mobile : `generate: true` retiré sans casser le build (CI mobile le vérifie).

## Requirements

### Functional Requirements
- **FR-001**: Les routes écriture `/payrolls` de rh.php portent la politique de rôle
  `principal,comptable` (alignement payroll_engine.php).
- **FR-002**: `LaunchReadiness` exige `activeEmployees > 0` pour valider communication_governance.
- **FR-003**: La validation correction attache l'erreur au champ fautif.
- **FR-004**: `/platform/companies/{id}/health` émet `company.slug` + `company.created_at`.
- **FR-005**: Les 4 locales admin définissent les clés pays manquantes.
- **FR-006**: Aucun `DZD` codé en dur dans la création entreprise mobile ni les modèles partagés.
- **FR-007**: La cartographie AGENTS.md/README est corrigée.
- **FR-008**: `sitemap.ts` gate l'entrée `/blog` sur `NEXT_PUBLIC_ENABLE_BLOG`.

## Success Criteria
- **SC-001**: Issues créées avec label `qa-expert5-2026-08-15`, specs/plan/tasks dans
  `.specify/features/qa-expert5-2026-08-15/`.
- **SC-002**: PRs avec `Closes #N` + CHANGELOG ; checks requis verts ; main reste vert.
- **SC-003**: Builds locaux web + admin verts avant push.

## Assumptions
- CI = source de vérité pour API/mobile ; les changements PHP/Dart passent par les checks
  GitHub Actions.
- Déploiements prod (Render/Vercel) hors périmètre sans accord propriétaire.
