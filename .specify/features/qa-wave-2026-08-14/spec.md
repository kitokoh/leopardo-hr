# Feature Specification: QA Wave 2026-08-14 — Fiabilisation backend + UI admin

**Feature Branch**: `qa-wave-2026-08-14`

**Created**: 2026-08-14

**Status**: Implemented (PR en revue)

**Input**: Constitution `.specify/constitution.md` + AGENTS.md — campagne de test fonctionnel de la plateforme (workflows API, vues, boutons, logique) avec rédaction d'issues et implémentation spec-first pour chaque manquement constaté.

## User Stories

### US1 - Suite de tests paie alignée sur la vérité documentée (P1)

Les tests unitaires et golden paie reflètent les règles documentées (CI_COMPLIANCE.md §1-§8, SN_COMPLIANCE.md §4-§5, guide CNPS) : ITS 2024 unifié, plafonds branche CNSS/CSS, préavis par catégorie, GA/CG/ML pilot.

**Independent Test**: `php artisan test --filter="CedeaoRulesUnitTest|CemacRulesUnitTest|AbstractCountryRulesCapTest|PayrollCalculatorUnitTest|Golden|PayrollCalculationContractTest"` → tout vert ; `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` → `[OK] No errors`.

**Acceptance Scenarios**:
1. **Given** la réforme ITS 2024 CI (#1918), **When** les tests CI tournent, **Then** ils reflètent l'ITS mensuel unique (6 tranches) et non l'ancien ITSAS annuel.
2. **Given** le guide CNPS (plafond branche 70 000 FCFA famille/AT), **When** les goldens CI tournent, **Then** patronal = 8 800,00 (SMIG) / 27 925,00 (500 000) — alignés sur `calculateSocialCharges`.
3. **Given** le préavis CI §8, **When** `noticePeriodDays()` est appelée, **Then** le palier 90 j par défaut non documenté disparaît (défaut employé 30/60 ; cadres 90 ; ouvriers 8/15).
4. **Given** la procédure CSS SN (#1913), **When** le golden SN T1 tourne, **Then** patronal = 51 768,00 (CSS plafonnée 63 000).

### US2 - Plus de liens morts sur le login admin (P2)

Un super-admin qui arrive sur la page de connexion du dashboard ne voit aucun lien `href="#"` inerte.

**Independent Test**: `grep -c 'href="#"' front/admin-dashboard/src/views/auth/LoginView.vue` → 0 ; `npm run lint` → 0 erreur.

**Acceptance Scenarios**:
1. **Given** la page de connexion admin, **When** un utilisateur cherche « Mot de passe oublié », **Then** il n'y a pas de lien mort (le process ops est documenté en commentaire).
2. **Given** le footer de connexion, **When** un utilisateur clique « Support », **Then** un `mailto:support@leopardo-rh.com` s'ouvre (email canonique vitrine).
---
