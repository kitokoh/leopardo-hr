# ⚠️ DOSSIER ARCHIVÉ — NE PAS UTILISER POUR GÉNÉRER DU CODE

> **Date d'archivage : 2026-07-01**

Ce dossier contient les **prompts d'exécution historiques** utilisés pour construire les premières versions de Leopardo RH (v2/v3).

## Pourquoi NE PAS les utiliser comme référence de code

Les prompts `v2/`, `v3/` et `patches/` donnent des instructions de génération dans les **anciens namespaces** qui n'existent plus :

| Ancien namespace (supprimé) | Remplacé par |
|---|---|
| `App\Http\Controllers\Api\V1\*` | `App\Modules\<Module>\Interfaces\Api\V1\*` |
| `App\Services\*` | `App\Modules\<Module>\Infrastructure\Services\*` |
| `App\Models\*` _(en cours)_ | `App\Modules\<Module>\Domain\Models\*` |

## Ce qu'il faut lire à la place

- **Architecture actuelle** : `api/ARCHITECTURE.md`
- **État DDD** : `docs/ARCHITECTURE_STATUS.md`
- **Conventions** : `CONVENTIONS.md`
- **Création d'un module** : `php artisan make:module {Name}` (génère la structure DDD correcte)

Ces fichiers historiques sont conservés pour **traçabilité uniquement**.
Ne pas les utiliser dans des prompts agent/LLM sans neutraliser les namespaces qu'ils contiennent.
