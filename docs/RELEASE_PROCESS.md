# Release Process — Leopardo RH

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
