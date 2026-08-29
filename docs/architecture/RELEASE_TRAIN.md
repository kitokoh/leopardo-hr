# Release Train & compatibilité applications

> **Issue :** [MAT-016 #5874](https://github.com/kitokoh/leopardo-hr/issues/5874)
> **Matrice :** `dev-hub/tools/release-compat-matrix.json`
> **Garde CI :** `dev-hub/tools/check-release-compat.sh` (job Hygiene Guards)
> **Tests :** `dev-hub/tools/tests/check-release-compat.test.sh` (6 scénarios)

## Objectif

Versionner l'API et les événements, et établir la compatibilité
backend / apps mobiles / kiosk / web avec un calendrier de release. Une
**app ancienne reçoit une réponse compatible ou un blocage explicite** —
jamais de breaking change silencieux.

## Politique (matrice `release-compat-matrix.json`)

| Règle | Valeur |
|---|---|
| Versioning | SemVer stricte : MAJEUR = breaking change API/événements ; MINEUR = additif rétro-compatible ; PATCH = correctif |
| Plancher supporté | `api_min_supported` — les clients sous ce plancher reçoivent un blocage explicite |
| Breaking change | Interdit sans (1) migration documentée, (2) période de compatibilité sur le plancher, (3) blocage explicite — jamais de casse silencieuse |
| Contrat de compat | `GET /api/v1/features/compatible/{version}` (FeatureManifestController) : manifeste de features compatible ou incompatible |
| Cadence | Train hebdomadaire ; la matrice est mise à jour dans la PR qui bump `APP_VERSION` |

## Ce que vérifie le garde

1. `api` de la matrice == défaut `APP_VERSION` de `api/config/app.php` (complété
   par `check-app-version-sync.sh` sur `.env.example`) ;
2. chaque app mobile listée existe avec une version `pubspec.yaml` identique à
   `current` ;
3. le kiosk (`front/zkteco-kiosk/package.json`) correspond à `current` ;
4. `api_min_supported` ≤ `api` ;
5. chaque composant expose un plancher `min_api`.

## Exécution locale

```bash
bash dev-hub/tools/check-release-compat.sh api
bash dev-hub/tools/tests/check-release-compat.test.sh
```

## Rollback

- Revert du commit du garde/de la matrice ; script bash autonome sans état.
- Une matrice qui bloque une PR : corriger la version divergente (bump réel)
  ou la matrice (si le bump est documenté) — ne pas désactiver le garde.
