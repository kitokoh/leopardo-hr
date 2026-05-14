# Politique de versioning API — Leopardo RH

> Derniere mise a jour : 2026-05-14

---

## 1. Schema de versioning

L'API Leopardo RH suit un versioning par **prefixe URL** :

```
/api/v1/employees
/api/v1/payroll-runs
/api/v1/ai/chat
```

### Version actuelle : `v1`

Toutes les routes sont sous `/api/v1/`. Il n'y a pas encore de `v2`.

## 2. Regles de compatibilite

### Changements compatibles (pas de nouvelle version)

- Ajouter un champ optionnel a une reponse JSON
- Ajouter un nouvel endpoint
- Ajouter un parametre optionnel a une requete
- Ajouter une nouvelle valeur a un enum (cote reponse uniquement)
- Ameliorer les messages d'erreur

### Changements incompatibles (necessitent une nouvelle version)

- Supprimer ou renommer un champ de reponse existant
- Modifier le type d'un champ existant
- Rendre obligatoire un parametre auparavant optionnel
- Supprimer un endpoint
- Changer la semantique d'un endpoint (meme URL, comportement different)
- Modifier la structure d'authentification

## 3. Cycle de deprecation

Quand un changement incompatible est necessaire :

### Etape 1 — Annonce (J-90)

- Ajouter le header `Deprecation: true` + `Sunset: <date ISO 8601>` aux reponses de l'ancien endpoint
- Documenter dans `CHANGELOG.md` et `api/openapi.yaml`
- Envoyer une notification aux clients API via webhook `api.deprecation` (si configure)

### Etape 2 — Coexistence (90 jours)

- L'ancienne version et la nouvelle coexistent
- Routes dans des fichiers separes : `routes/api_v1.php`, `routes/api_v2.php`
- Controllers dans des namespaces separes : `App\Http\Controllers\Api\V1\*`, `App\Http\Controllers\Api\V2\*`
- Les deux versions partagent les memes modeles et services (seule la couche presentation change)

### Etape 3 — Retrait (J+0)

- Supprimer les routes v1 obsoletes
- Les anciens endpoints retournent `410 Gone` avec un body JSON explicatif
- Mettre a jour OpenAPI

## 4. Headers de versioning

Chaque reponse API inclut :

```
X-API-Version: v1
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
```

En cas de deprecation :

```
Deprecation: true
Sunset: 2026-09-01T00:00:00Z
Link: </api/v2/employees>; rel="successor-version"
```

## 5. Documentation OpenAPI

- Fichier canonique : `api/openapi.yaml`
- Servi en dev sur `/docs` (via le backend Laravel)
- Chaque version de l'API a sa propre section dans la spec
- Tout nouvel endpoint doit etre documente AVANT le merge du PR

## 6. Versioning semantique du produit

Le produit Leopardo RH suit SemVer dans `CHANGELOG.md` :

```
MAJOR.MINOR.PATCH (ex: 4.16.51)
```

- **MAJOR** : changements incompatibles majeurs (nouvelle version API, refonte architecture)
- **MINOR** : nouvelles fonctionnalites compatibles
- **PATCH** : corrections de bugs, documentation, CI

Le numero de version produit est independant de la version API (`v1`, `v2`).

## 7. Middleware ApiVersion (a implementer)

Un middleware `ApiVersion` est prevu pour :

- Detecter la version demandee (prefixe URL ou header `Accept-Version`)
- Injecter la version dans le contexte de la requete
- Logger la version utilisee pour les metriques d'usage
- Appliquer les transformations de compatibilite si necessaire

```php
// Futur : app/Http/Middleware/ApiVersionMiddleware.php
// Route::prefix('api/v1')->middleware(['api', 'api.version:v1'])->group(...)
```

## 8. References

- [OpenAPI Spec](../openapi.yaml) — Specification API canonique
- [CHANGELOG.md](../../CHANGELOG.md) — Historique des versions
- [CONVENTIONS.md](../../CONVENTIONS.md) — Conventions de code
