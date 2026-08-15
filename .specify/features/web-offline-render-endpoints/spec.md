# Feature Specification: Web-offline PWA — endpoints /api/v1/ + URLs Render (issue #3772)

**Created**: 2026-08-15

**Status**: Ready for implementation

**Input**: Issue #3772 — la PWA `front/web-offline` doit appeler les endpoints réels (`/api/v1/edge/*` et non `/api/edge/*`) et documenter les URLs Render gratuites (pas de domaines NXDOMAIN). Clôture #3719 (déjà traitée sur les endpoints : état vérifié sur main).

## Contexte technique

Sur main (après #3747/#3749) :
- `front/web-offline/src/app/page.tsx` appelle déjà `GET ${EDGE_API}/api/v1/edge/health` et le bouton de synchronisation est honnêtement désactivé sans authentification Edge.
- `front/web-offline/.env.example` documente `NEXT_PUBLIC_EDGE_API=http://leopardo.local:7878` (nœud local) mais **pas** le fallback backend Render ni la raison des deux valeurs.

## User Scenarios & Testing

### US1 — La PWA documente les deux cibles : nœud Edge local et backend Render (Priority: P2)
Un intégrateur qui déploie la PWA hors réseau local sait quelle valeur utiliser et pourquoi.

**Acceptance Scenarios**:
1. **Given** `.env.example`, **When** lu, **Then** il explique `NEXT_PUBLIC_EDGE_API` : nœud Edge local (`http://leopardo.local:7878`) par défaut, backend Render (`https://gestionemployerbackend.onrender.com`) en fallback cloud, sans domaine NXDOMAIN.
2. **Given** le code `page.tsx`, **When** inspecté, **Then** aucun appel `/api/edge/*` (hors version) n'existe.

### US2 — Le health check versionné reste le contrat (Priority: P2)
La PWA sonde `/api/v1/edge/health` et affiche un état honnête hors-ligne.

**Acceptance Scenarios**:
1. **Given** un nœud Edge joignable, **When** la PWA charge, **Then** `GET /api/v1/edge/health` → statut « Edge en ligne ».
2. **Given** un nœud injoignable, **When** la PWA charge, **Then** statut « Hors ligne » avec note locale, aucune erreur réseau non gérée.

## Requirements

- FR-1: `front/web-offline/.env.example` — documenter les deux valeurs (locale + Render), commentaire clair, suppression de toute référence `*.leopardo-rh.com`/`*.leopardo.app`.
- FR-2: Vérifier `page.tsx` : aucun `api/edge` non versionné ; pas de changement de comportement nécessaire (déjà corrigé).
- FR-3: `front/web-offline/README.md` (ou section existante) — indiquer la cible Render si aucun nœud Edge local n'est installé.
- FR-4: Entrée `CHANGELOG.md` sous `## [Unreleased]` → `### Fixed` (Closes #3772).
