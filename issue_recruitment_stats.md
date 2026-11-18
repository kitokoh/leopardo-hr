## Context

Le module Recruitment (BC-08 RECRUITMENT) n'offre actuellement aucune **vue agrégée** des activités de recrutement. Les responsables RH ne peuvent pas suivre :
- Le nombre total d'offres actives
- Les candidatures en cours de traitement
- Le taux de conversion par étape du funnel
- La durée moyenne de recrutement
- Les sources de candidatures les plus performantes

## Proposition

Ajouter un endpoint **`GET /recruitment/stats`** qui agrège les KPIs de recrutement pour dashboard RH.

### Endpoint

```
GET /api/v1/recruitment/stats?from=YYYY-MM-DD&to=YYYY-MM-DD
```

**Permission** : `recruitment.view` (Gate)  
**Scope** : tenant (`company_id`)

### Réponse (exemple)

```json
{
  "data": {
    "period": { "from": "2026-01-01", "to": "2026-01-31" },
    "job_postings": {
      "total": 15,
      "active": 8,
      "filled": 3,
      "closed": 4
    },
    "applications": {
      "total": 247,
      "new": 45,
      "screening": 82,
      "interview": 64,
      "offer": 28,
      "hired": 18,
      "rejected": 10
    },
    "funnel": {
      "screening_rate": 0.7521,
      "interview_rate": 0.7805,
      "offer_rate": 0.4375,
      "conversion_rate": 0.0729
    },
    "avg_time_to_hire_days": 28.5,
    "top_sources": [
      { "source": "linkedin", "count": 98 },
      { "source": "direct", "count": 67 },
      { "source": "referral", "count": 45 }
    ]
  }
}
```

### Implémentation

1. Ajouter méthode `stats(Request $request)` dans `RecruitmentController`
2. Agrégations SQL sur `job_postings`, `applicants`, `interviews`
3. Calcul des taux de conversion entre étapes
4. Top 5 des sources de candidatures
5. Route `GET /recruitment/stats` dans `api/routes/modules/rh.php`
6. Entrée CHANGELOG `### Recruitment`

## Priorité

**P2** — feature utile pour dashboard RH, améliore le module Recruitment

## Labels

`enhancement`, `backend`, `P2`
