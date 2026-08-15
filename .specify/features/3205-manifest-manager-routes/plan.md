# Plan: Régression manifeste mobile — routes manager restaurées (issue #3205)

**Input**: spec.md — 1 régression P1 (#3205), constat QA 2026-08-15.

## Stratégie

1. Restaurer les 11 GoRoutes dans `front/mobile_apps/leopardo_manager/lib/app.dart` (déclarations fidèles à a0642a64, écrans existants), en conservant les évolutions postérieures (`/cabinet/folder/:folderId`, `/access-denied`, errorBuilder, `/manager/*`).
2. Garder hors périmètre les vraies routes mortes (#2801) : `/modules/rh`, `/ai-chat`, `/vehicle-map`.
3. Vérifier la garde `check-mobile-manifest-routes.sh` locale (exit 0).
4. La CI (`Mobile Apps CI - Flutter` : manifest guard + flutter analyze) est la validation finale — pas de Flutter local.
5. CHANGELOG.md entrée sous `## [Unreleased]` → ### Fixed.
6. PR `fix/3205-manifest-manager-routes` avec `Closes #3205` dans le body.

## Phases

### Phase 1 — Restaurer les routes (branche `fix/3205-manifest-manager-routes` depuis origin/main)
- [ ] 1. Brancher depuis `origin/main` à jour.
- [ ] 2. Réinsérer dans la ShellRoute de `app.dart` les 11 GoRoutes (modules + quick actions), après `/cabinet/folder/:folderId` ou à l'emplacement d'origine, sans créer de doublon `/cabinet/:folderId`.
- [ ] 3. Vérifier qu'aucun import n'est manquant (les 11 écrans déjà importés).
- [ ] 4. Exécuter la garde `bash dev-hub/tools/check-mobile-manifest-routes.sh` → OK.

### Phase 2 — CHANGELOG + PR
- [ ] 5. Entrée CHANGELOG sous `[Unreleased]` → ### Fixed.
- [ ] 6. Commit + push + PR `Closes #3205`, attendre checks CI verts.

### Phase 3 — Post-merge
- [ ] 7. Après merge : vérifier garde sur main + fermer #3205 automatiquement.
