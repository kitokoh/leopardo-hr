# Feature Specification: Vague Multi-Pays Paie Afrique 2026-08 (complétion)

**Feature Branch**: `multi-pays-wave-2026-08-14`

**Created**: 2026-08-14

**Status**: Implemented (mostly) — convergence en cours

**Input**: Constitution `.specify/constitution.md` + AGENTS.md + registre `.specify/memory/project-state.md` — compléter la vague multi-pays (issues #1811-#2041) jusqu'à un état « main vert, backlog actionnable ».

## User Scenarios & Testing

### User Story 1 - Moteur de paie multi-pays correct et verrouillé (Priority: P1)

Un employé d'un tenant dont le pays est DZ, CM, GA, CG, CI, SN, BF ou ML obtient un bulletin calculé avec les barèmes et cotisations légaux de son pays, verrouillés par des golden tests calculés à la main.

**Independent Test**: `php artisan test --filter=Golden` — chaque pays ≥ 3 golden tests sourcés (SMIG, cadre moyen, haut salaire).

**Acceptance Scenarios**:

1. **Given** un pays avec règles implémentées, **When** on calcule un bulletin, **Then** les montants correspondent aux golden tests et chaque taux porte une référence légale en commentaire.
2. **Given** un pays `pilot`, **When** un calcul est exécuté, **Then** le niveau de confiance et la source sont exposés (bloc `compliance`).
3. **Given** un pays `placeholder` (BJ/TG/NE/CF/TD/GQ), **When** une simulation est demandée, **Then** une confirmation explicite `acknowledge_placeholder=true` est requise (422 sinon), et l'acceptation est auditée.
4. **Given** deux tenants différents, **When** des calculs sont exécutés, **Then** aucune donnée cross-tenant n'est accessible (isolation 404).

### User Story 2 - Transparence de conformité côté clients (Priority: P1)

Un RH qui simule ou consulte une fiche de paie voit clairement le niveau de confiance des règles utilisées (production/pilot/placeholder), la source légale et la date de vérification experte, avec un avertissement localisé.

**Independent Test**: la Web App `front/web` affiche le bloc `compliance` (niveau + avertissement + source + verification_date) sur la simulation et la fiche de paie ; les catalogues i18n partagés contiennent les clés.

**Acceptance Scenarios**:

1. **Given** une simulation pour un pays `placeholder` non confirmé, **When** le client affiche le résultat, **Then** un avertissement « montants INDICATIFS » est visible.
2. **Given** un pays `pilot`, **When** le client affiche le résultat, **Then** l'avertissement piloté est affiché avec la source.
3. **Given** un pays `production` validé (verification_date non nulle), **When** le client affiche le résultat, **Then** la date de validation experte est affichée.

### User Story 3 - Validation experte traçable avant production (Priority: P1)

Aucun pays ne passe en `production` sans une fiche de validation experte signée, tracée dans `docs/payroll/VALIDATION_EXPERTE.md` et les fiches pays.

**Independent Test**: registre `VALIDATION_EXPERTE.md` — chaque pays `production` a une ligne validée ; chaque pays `pilot` liste ses questions bloquantes avec un ticket GitHub.

**Acceptance Scenarios**:

1. **Given** un pays `pilot` avec des valeurs « à valider expert », **When** on audite le registre, **Then** chaque question bloquante a un ticket GitHub ouvert (ex. #1912 SN, #2124 reliquats).
2. **Given** une fiche de validation signée, **When** une PR passe `confidenceLevel()` → `production`, **Then** la fiche est mise à jour dans la même PR.

### User Story 4 - Onboarding d'un nouveau pays reproductible (Priority: P2)

Un nouvel agent peut ajouter un pays (ex. TG) en suivant le playbook : fiche compliance, règles, golden tests, i18n, garde catalogue.

**Independent Test**: `dev-hub/tools/check-country-catalog.sh` passe avec 0 doublon et le registre complet ; le playbook `docs/specifications/PAYS_ONBOARDING_PLAYBOOK.md` est référencé.

**Acceptance Scenarios**:

1. **Given** un pays à ajouter, **When** on suit le playbook #1875, **Then** fiche `docs/payroll/{CC}_COMPLIANCE.md`, règles, ≥ 6 golden tests, inscription `CountryDefaults` et i18n sont livrés dans une seule PR.
2. **Given** une PR d'onboarding, **When** la CI tourne, **Then** la garde catalogue est verte.

---

## Edge Cases

- Pays legacy sans pays défini (FR/TR/CA/MA/TN) : allowlist historique, dette documentée (golden manquants MA/TN — issue #2122).
- Migration de barème (ITS CI 2024) : lignes admin custom préservées, migration idempotente F-17.
- Fusion de PR parallèles : garde anti-collision migrations (#1962), merge queue (#2032).
