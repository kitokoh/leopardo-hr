# Mini-spec — Issue #4124

## Problème

`I18N Enterprise › validate-and-sync` rouge sur main et sur toute PR
(`git diff --exit-code` non vide) : `.gitattributes` déclare `*.png filter=lfs`
mais les médias vitrine (`front/web/public/og/*.png` ×26, `og-image.png`,
`front/web-offline/public/icon-*.png`, screenshots, videos) sont commités en
**blobs pleins**. git-lfs ≥3.7 au checkout CI (`lfs: true`) les réécrit
(« Encountered 28 files that should have been pointers ») → diff binaire →
workflow rouge, y compris pour des PRs docs-only. Non reproductible avec
git-lfs 3.0.2 (comportement dépendant de la version).

## Correctif

`.gitattributes` :
- Suppression du bloc dupliqué `*.png filter=lfs…` (fusion parallèle #2889).
- Exemptions larges du filtre LFS pour les médias commités en binaires réels
  (pattern existant #2889 étendu — le build Vercel ne résout pas LFS) :
  `front/web/public/*.png`, `front/web/public/og/*.png`,
  `front/web/public/screenshots/*.png`, `front/web/public/videos/*`,
  `front/web-offline/public/*.png` → `!filter !diff !merge -text`.
- `assets/**` et icônes iOS/macOS restent en VRAI Git LFS (pointeurs).

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `git check-attr filter` sur les médias vitrine | `unspecified` |
| `git check-attr filter` sur `assets/**` + AppIcon | `lfs` |
| Blobs pleins sous filtre LFS (scan repo) | 0 |
| `validate-and-sync` CI (git-lfs ≥3.7) | Vert |

## Validation

Scan complet du repo : 0 fichier « blob sous filtre LFS » restant ;
check-attr conforme pour les 2 familles (web exempté / assets LFS).

Closes #4124
