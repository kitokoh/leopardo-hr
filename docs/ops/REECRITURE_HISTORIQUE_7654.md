# Réécriture d'historique — purge du pack 93 MiB (issue #7654)

> **Décision PROPRIÉTAIRE requise — ne pas exécuter sans son GO explicite.**
> Une réécriture d'historique change tous les SHA : elle **invalide tous les clones,
> forks, PRs ouvertes et références de commits** (CHANGELOG, docs, issues). Ce document
> décrit la procédure ; la tranche 3 de #7654 ne l'exécute pas.

## Pourquoi

Audit 2026-09-19 (`git count-objects -v`) : pack **93,27 MiB / ~158k objets**. Les
tranches 1–3 ont sorti les artefacts générés du **tree courant** (forward-only), mais
les blobs restent dans l'**historique** :

| Artefact (historique) | Coût cumulé mesuré |
|---|---|
| `api/openapi.yaml` régénéré sans cesse | ~694 MB |
| `dev-hub/openapi/v1.yaml` (miroir, dé-tracké en tranche 3) | ~618 MB |
| Catalogues i18n générés (`front/**/i18n/locales/*.json`, `app_*.arb`, `app_localizations.dart`) | ~300 MB |
| `dev-hub/sdk/` généré (dé-tracké en tranche 3) | ~120 MB |
| `front/mobile_apps/leopardo_travel_agent/build/test_cache/**/*.dill` | 52,7 MB (un blob) |
| `CHANGELOG_ARCHIVE.md` (sorti en tranche 2, consultable au blob `6a3819a`) | 4,37 MB |
| `api/composer.phar`, `ci_log.txt` | ~5,2 MB |

Objectif post-purge : **repo < 15 MiB**.

## État forward-only (déjà fait, tranches 1–3)

- Tranche 1 (PR #7679) : scripts d'audit orphelins avec credentials committés purgés.
- Tranche 2 (PR #7692) : `CHANGELOG_ARCHIVE.md`, `docs/api-mock-data/`, `.composed` dé-trackés.
- Tranche 3 : `dev-hub/openapi/v1.yaml` + SDK JS/Python dé-trackés (régénérés en CI par
  `openapi-ci.yml` et localement par `make openapi-sync`), `.gitignore` complété, garde
  anti-régression `.github/workflows/repo-hygiene-guard.yml`.
- Toujours trackés, **volontairement** (consommés committés — ne pas purger de HEAD,
  seulement de l'historique ancien) :
  - `api/openapi.yaml` : contrat canonique **servi au runtime** par Laravel
    (`routes/web.php` → `GET /docs/openapi.yaml` lit `base_path('openapi.yaml')`) et lu
    par `scripts/route_openapi_compare.py`. Il reste versionné.
  - Catalogues i18n cibles (`front/admin-dashboard/src/i18n/locales/*.json`,
    `front/web/src/lib/i18n/locales/*.json`, `front/mobile_apps/leopardo_core/lib/l10n/app_*.arb`) :
    **importés au build** par Next.js/Vue/Flutter sans étape de génération préalable dans
    les workflows de build. Les dé-tracker exige d'insérer `shared/i18n/sync/sync-*.js`
    (et `flutter gen-l10n`) dans CHAQUE pipeline de build + le setup dev local — chantier
    dédié, voir « Checklist propriétaire » ci-dessous.

## Procédure de purge (git filter-repo)

### 1. Préparation (avant le jour J)

- [ ] Geler les merges : annoncer une fenêtre, fermer/merger les PRs ouvertes
      (toute PR non mergée devra être rebasée à la main après la purge).
- [ ] Sauvegarde complète : `git clone --mirror` + archive du bundle
      (`git bundle create leopardo-pre-rewrite.bundle --all`) stockée hors GitHub.
- [ ] Noter les références de blobs à conserver en signets (ex. l'archive changelog
      `6a3819a` référencée par CHANGELOG.md — après purge, republier ce contenu en
      release asset AVANT de purger, sinon le lien meurt).
- [ ] Installer `git-filter-repo` (pas `filter-branch`, déprécié).

### 2. Réécriture (sur un clone frais `--mirror`)

```bash
git clone --mirror git@github.com:kitokoh/leopardo-hr.git leopardo-rewrite
cd leopardo-rewrite

# Purger les artefacts générés de TOUT l'historique, en conservant
# les fichiers encore présents dans HEAD (api/openapi.yaml, i18n) grâce
# à une purge par chemin ciblée sur les chemins retirés du tree :
git filter-repo \
  --invert-paths \
  --path dev-hub/openapi/v1.yaml \
  --path dev-hub/sdk/MANIFEST.json \
  --path dev-hub/sdk/javascript/leopardoClient.js \
  --path dev-hub/sdk/python/leopardo_client.py \
  --path CHANGELOG_ARCHIVE.md \
  --path api/composer.phar \
  --path ci_log.txt \
  --path-glob 'docs/api-mock-data/*' \
  --path-glob 'front/mobile_apps/*/build/test_cache/*' \
  --path-glob '*.dill'
```

> **Note `api/openapi.yaml` / i18n** : ces fichiers vivent encore dans HEAD. Pour
> récupérer leurs ~1 Go cumulés il faut soit les purger avec `--path` PUIS les
> re-committer en un seul commit final (perte de leur historique — acceptable pour du
> généré), soit utiliser `--strip-blobs-bigger-than` avec précaution. Décision
> propriétaire : purge simple (tableau ci-dessus, gain ~800 MB d'objets historiques)
> ou purge agressive incluant openapi/i18n (gain complet, repo < 15 MiB).

### 3. Vérification avant push

```bash
git count-objects -v          # taille du pack attendue < 15 MiB (purge agressive)
git log --oneline | head      # l'historique reste lisible
git ls-tree -r HEAD --name-only | grep -E 'openapi.yaml'  # HEAD intact
```

### 4. Publication (destructif)

- [ ] Désactiver temporairement la protection de `main` (la garde #7270 la re-vérifiera).
- [ ] `git push --force --mirror origin` depuis le clone réécrit.
- [ ] Ré-activer la protection de branche.
- [ ] Demander à GitHub Support de lancer un GC serveur / invalider les caches de forks
      si les anciens objets restent joignables via les PRs.

### 5. Après-coup

- [ ] Annoncer à tous les contributeurs : **re-cloner** (pas de pull sur un ancien clone).
- [ ] Rebaser les branches survivantes avec `git rebase --onto` sur les nouveaux SHA.
- [ ] Mettre à jour les références de SHA dans la doc (recherche `rg '[0-9a-f]{7,40}' docs/`
      sur les SHA purgés connus, dont `6a3819a`).
- [ ] Vérifier que `repo-hygiene-guard.yml` et `openapi-ci.yml` sont verts sur main.

## Checklist propriétaire (reste à décider / faire)

- [ ] **GO/NO-GO réécriture d'historique** + choix purge simple vs agressive (ci-dessus).
- [ ] Republier `CHANGELOG_ARCHIVE.md` (blob `6a3819a`) en release asset avant purge.
- [ ] **i18n générés** : arbitrer la sortie des catalogues cibles du versioning
      (nécessite `sync-*.js`/`gen-l10n` dans les pipelines de build web/admin/mobile et
      le setup dev) OU le split par namespace pour réduire la surface de conflit —
      tranche dédiée, non automatisable sans validation des builds.
- [ ] `api/openapi.yaml` : statu quo (servi au runtime, contrat canonique versionné) —
      son coût historique ne se récupère que par la réécriture.

---
_Tranche 3 de #7654 — audit externe, session Zentor. La purge d'historique n'a PAS été exécutée._
