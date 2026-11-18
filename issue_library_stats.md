## Context

Le module Library (bibliothèque d'entreprise) ne dispose actuellement d'aucune **vue agrégée** de l'utilisation des ressources. Les responsables ne peuvent pas suivre :
- Le nombre total de ressources par type (livre, document, vidéo, cours...)
- Les emprunts actifs et en retard
- Les ressources les plus consultées
- Le taux d'utilisation de la bibliothèque
- Les statistiques de lecture (progression, temps moyen)

## Proposition

Ajouter un endpoint **`GET /library/stats`** qui agrège les KPIs de la bibliothèque pour dashboard RH/Formation.

### Endpoint

```
GET /api/v1/library/stats?from=YYYY-MM-DD&to=YYYY-MM-DD
```

**Permission** : `library.view` (Gate)  
**Scope** : tenant (`company_id`)

### Réponse (exemple)

```json
{
  "data": {
    "period": { "from": "2026-01-01", "to": "2026-01-31" },
    "resources": {
      "total": 1247,
      "by_type": {
        "book": 542,
        "document": 385,
        "video": 198,
        "course": 122
      },
      "by_status": {
        "published": 1180,
        "draft": 45,
        "archived": 22
      }
    },
    "borrowing": {
      "active": 127,
      "overdue": 18,
      "returned_on_time": 856,
      "avg_duration_days": 12.4
    },
    "engagement": {
      "total_views": 8947,
      "unique_users": 342,
      "completed_readings": 245,
      "avg_completion_rate": 0.6782
    },
    "top_resources": [
      { "id": 1543, "title": "Guide onboarding 2026", "views": 287 },
      { "id": 892, "title": "Procédures RH", "views": 241 }
    ]
  }
}
```

### Implémentation

1. Ajouter contrôleur `LibraryStatsController` ou méthode `stats()` dans contrôleur existant
2. Agrégations SQL sur `library_resources`, `library_checkouts`, `library_reading_progress`
3. Calcul des taux de complétion et durées moyennes
4. Top 10 des ressources les plus consultées
5. Route `GET /library/stats` dans `api/routes/modules/*.php`
6. Entrée CHANGELOG `### Library`

## Priorité

**P3** — feature utile pour dashboard formation, améliore le module Library

## Labels

`enhancement`, `backend`, `P3`
