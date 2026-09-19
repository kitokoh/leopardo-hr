# Release Process — Leopardo

> ⚠️ **OBSOLÈTE (corrigé le 2026-09-09)** — la procédure décrite ci-dessous
> (création manuelle de la GitHub Release, tags `vX.Y.Z-rc.N` « Pre-release »,
> « déploiement déclenché automatiquement par Render au push sur main ») ne
> correspond plus à la chaîne réelle. **Ne pas la suivre.**
>
> **Chaîne réelle (vérifiée dans `.github/workflows/` le 2026-09-09) :**
> 1. Créer le tag `vX.Y.Z` (regex stricte `^v[0-9]+\.[0-9]+\.[0-9]+$` — les
>    suffixes `-rc`/`-beta` sont **rejetés**, la détection prerelease du
>    workflow est du code mort) ;
> 2. `release.yml` crée la GitHub Release depuis ce tag ;
> 3. `deploy-prod.yml` déclenche la production sur `release: published`
>    (**jamais** au push sur `main`).
>
> Sources à jour : `.github/workflows/README.md` (cartographie des workflows),
> `release.yml`, `deploy-prod.yml`, et le protocole de validation du corpus
> `docs/PROTOCOLES/P01_VALIDATION_MARCHE.md`. Modèle de bandeau d'obsoletion :
> `docs/DEMARRAGE_RAPIDE.md`.
>
> Piège constaté : les tags v4.25.0, v4.26.0 et v4.27.2 (2026-09) sont restés
> **sans GitHub Release** (donc sans déploiement prod) faute de processus
> clair — une release se crée par le tag, pas par l'UI « Releases ».

---

# Contenu historique (obsolète — à ne pas suivre)

## Versioning

Le projet suit [Semantic Versioning](https://semver.org/) :
- **MAJOR** (X.0.0) : Breaking changes API
- **MINOR** (0.X.0) : Nouvelles fonctionnalites
- **PATCH** (0.0.X) : Corrections de bugs

## Creer une release

### 1. Preparer le CHANGELOG

Verifier que `CHANGELOG.md` contient une entree pour la version a publier :

```markdown
## [4.12.0] - 2026-05-11

### Ajouts
- Feature A
- Feature B

### Corrections
- Fix C
```

### 2. Mettre a jour la version

```bash
# Dans api/config/app.php
'version' => '4.12.0',
```

### 3. Creer le tag Git

```bash
git tag -a v4.12.0 -m "Release v4.12.0 — description courte"
git push origin v4.12.0
```

### 4. Creer la GitHub Release

Sur GitHub : Releases > Draft a new release
- **Tag** : `v4.12.0`
- **Title** : `v4.12.0 — Description courte`
- **Body** : Copier la section du CHANGELOG correspondante
- **Pre-release** : Cocher si c'est une RC/beta

### 5. Deploiement

Le deploiement est declenche automatiquement par Render lors du push sur `main`.

## Convention de tags

| Type | Format | Exemple |
|------|--------|---------|
| Release stable | `vX.Y.Z` | `v4.12.0` |
| Pre-release | `vX.Y.Z-rc.N` | `v4.12.0-rc.1` |
| Hotfix | `vX.Y.Z` (patch) | `v4.12.1` |

## Cadence

- **Minor** : A chaque fin de sprint (toutes les 2 semaines)
- **Patch** : A la demande pour les corrections critiques
- **Major** : Planifie (breaking changes API)
