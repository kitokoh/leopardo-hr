# Feature Specification: validate-and-sync vert — exemptions LFS des assets vitrine (issue #4124)

**Feature Branch**: `fix/4124-lfs-validate-sync`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA qa-expert14 2026-08-16 — `git diff --exit-code` de la garde `I18N Enterprise › validate-and-sync` rouge : 28 PNG binaires « modifiés » alors qu'aucun script de sync ne les touche.

## Problème

`.gitattributes:18` déclare `*.png filter=lfs` mais 28 fichiers binaires sous `front/` sont commités en **blobs pleins** (pas des pointeurs LFS) : `front/web/public/og/*.png` (22), `front/web/public/og-image.png`, `front/web-offline/public/icon-{192,512}.png`, etc.

En CI, `actions/checkout` (lfs: true) avec **git-lfs 3.7.1** : `Encountered 28 files that should have been pointers, but weren't` puis réécriture → `git diff --exit-code` les voit modifiés → **workflow rouge sur main et toute PR** (docs-only incluses). Non reproductible avec git-lfs 3.0.2.

## Décision

**Option 2 — exempter ces chemins du filtre LFS** (pas la migration LFS) :

- Ces assets sont **servis par Vercel/Render qui ne résout pas LFS** (commentaire #2829 : « pointeurs servis en prod → images/vidéo cassées ») — convertir en pointeurs casserait la prod.
- Les exemptions existantes (#2889 pour les icônes, #2829 pour screenshots/videos) suivent déjà ce motif.
- Ajout : `front/web/public/og/*.png`, `front/web/public/og-image.png`, `front/web-offline/public/icon-*.png` → `!filter !diff !merge -text` (motif `!filter` = unspecified, git-lfs ne les traite plus).
- Documentation : section LFS (prérequis + exceptions) ajoutée à AGENTS.md.

## User Scenarios & Testing

### User Story 1 — validate-and-sync vert sur main et toutes les PRs (Priority: P1)

**Independent Test**: `git check-attr filter -- front/web/public/og/landing.png` → `unspecified` (plus de `lfs`) ; `node shared/i18n/sync/*.js` + `git diff --exit-code` → 0 en CI (git-lfs 3.7+).

**Acceptance Scenarios**:

1. **Given** les 28 fichiers blobs pleins, **When** git-lfs 3.7 fait le checkout, **Then** aucun n'est réécrit (filtre absent).
2. **Given** les assets prod, **When** le build Vercel s'exécute, **Then** les binaires réels sont servis (aucun pointeur).
3. **Given** les vrais fichiers LFS (assets/design, icônes Android), **When** le checkout s'exécute, **Then** toujours résolus via LFS (non exemptés).
4. **Given** la garde i18n, **When** elle compare les fichiers générés, **Then** `git diff --exit-code` → 0.

## Edge Cases

- `!filter` (unspecified) vs `-filter` (unset) : les deux neutralisent LFS ; `!filter` aligné sur les exemptions #2889 existantes.
- Les pointeurs LFS légitimes (assets/design/mockups, mipmaps Android) restent `filter=lfs` — vérifié par `git check-attr` + `git cat-file` (contenu de blob).
- Aucune conversion d'historique (pas de `git lfs migrate`) — diff minimal, risque nul.
