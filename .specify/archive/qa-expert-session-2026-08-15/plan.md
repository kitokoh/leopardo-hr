# Implementation Plan: Session QA Expert 2026-08-15 — manquements résiduels

**Branch**: `fix/qa-expert-session-2026-08-15` | **Date**: 2026-08-15 | **Spec**: `.specify/features/qa-expert-session-2026-08-15/spec.md`

**Input**: Feature specification (4 user stories : Postman, api/CHANGELOG, .env.example, lien X).

## Summary

Session de test expert complet le 2026-08-15. 184 issues ouvertes du wave `qa-audit-2026-08-15` couvrent déjà l'essentiel (P0 login 500 → #2652, CORS admin → #2627/#2812, i18n → #2642/#2657…). Quatre manquements ne sont couverts par aucune issue et sont traités ici : collection Postman à 2 requêtes, api/CHANGELOG.md périmé de 3 versions, clé dupliquée dans .env.example, lien X/Twitter mort dans le footer vitrine.

## Technical Context

**Language/Version**: JSON (Postman), Markdown (CHANGELOG), dotenv, TypeScript/React (footer vitrine)

**Primary Dependencies**: `postman/leopardo_hr.postman_collection.json`, `api/CHANGELOG.md`, `api/.env.example`, `front/web/src/modules/vitrine/components/Footer.tsx`, `front/web/src/lib/seo.ts` / `structured-data.ts`

**Storage**: n/a (artefacts repo)

**Testing**: 
- Collection : jq count + vérification routes vs `api/routes/`
- CHANGELOG : diff visuel vs CHANGELOG racine
- .env.example : `grep -c` (0 doublon)
- Footer : `npm run lint` + `npm run build` (front/web), curl 200/retrait

**Target Platform**: Web (vitrine), docs, outils

**Constraints**:
- Constitution §VII : une PR = une issue ; PR title `fix(module): … (Closes #N)` ; CHANGELOG racine mis à jour dans la PR.
- Constitution §I : anti-doublon — aucune issue dupliquée (les 4 manquements sont nouveaux).
- Ne jamais toucher aux slugs, paths, clés i18n EN/TR/AR.

## Constitution Check

*GATE* :
- §I Spec-First : ✓ spec.md rédigée avant toute modification.
- §VII Gouvernance : PR unique par issue, `Closes #N`, CHANGELOG mis à jour, branche supprimée après merge.
- Anti-doublon : branches vérifiées (`git branch -r` le 2026-08-15) — aucune branche existante pour ces 4 manquements.

## Project Structure

### Documentation
```text
.specify/features/qa-expert-session-2026-08-15/
├── spec.md    # Ce document source (user stories + acceptance)
├── plan.md    # Ce fichier
└── tasks.md   # Tâches actionnables
```

### Source changes
```text
postman/leopardo_hr.postman_collection.json   # régénération ≥ 50 requêtes
api/CHANGELOG.md                              # sections 4.22.0 → 4.24.0
api/.env.example                              # suppression doublon BIOMETRIC_RETENTION_MONTHS
front/web/src/modules/vitrine/components/Footer.tsx  # lien X mort → retrait/remplacement
front/web/src/lib/seo.ts + structured-data.ts        # sameAs sans URL morte (coordination #2608)
```

## Implementation Steps

1. **Postman** : analyser `postman/leopardo_hr.postman_collection.json` ; générer une collection étendue (endpoints publics + auth + CRUD représentatif par module) en s'appuyant sur `api/openapi.yaml` et `api/routes/`. Vérifier `dev-hub/tools/` pour un générateur existant.
2. **api/CHANGELOG.md** : reporter les sections 4.22.0, 4.23.0, 4.24.0 depuis le CHANGELOG racine (correctifs backend uniquement, condensés).
3. **.env.example** : supprimer la seconde occurrence de `BIOMETRIC_RETENTION_MONTHS` (l.402), conserver celle avec le commentaire le plus riche.
4. **Footer X** : vérifier l'existence du compte ; remplacer le lien par GitHub (ou retirer l'icône) ; vérifier `sameAs` dans seo.ts/structured-data.ts et ne pas y inclure l'URL morte.
5. **Validation** : `npm run lint` + `npm run build` (front/web) ; `grep -c` .env.example ; jq Postman ; diff CHANGELOG.
6. **Livraison** : branche `fix/qa-expert-session-2026-08-15`, 4 commits (un par manquement), PR `Closes #A #B #C #D`, CHANGELOG racine `[Unreleased]`, CI verte, merge.

## Performance Goals

n/a (artefacts statiques).

## Risks

- Postman : génération manuelle longue → se limiter à un ensemble représentatif bien formé (≥ 50 requêtes, variables `{{baseUrl}}`, auth Bearer par collection).
- Footer X : si le compte est rétabli entre-temps, conserver le lien ; sinon retirer. Décision par défaut : retrait + lien GitHub.
