## Context

Le module Expense (notes de frais) ne dispose actuellement d'aucune **vue agrégée** des dépenses. Les responsables RH et Finance ne peuvent pas suivre :
- Le montant total des notes de frais par statut (en attente, approuvées, rejetées, remboursées)
- La répartition par catégorie de dépense (transport, hébergement, repas...)
- Les employés avec le plus de notes de frais
- Les délais moyens de traitement et de remboursement
- Les tendances mensuelles

## Proposition

Ajouter un endpoint **`GET /expense/stats`** qui agrège les KPIs des notes de frais pour dashboard Finance/RH.

### Endpoint

```
GET /api/v1/expense/stats?from=YYYY-MM-DD&to=YYYY-MM-DD
```

**Permission** : `expense.reports` ou `expense.manage` (Gate)  
**Scope** : tenant (`company_id`)

### Réponse (exemple)

```json
{
  "data": {
    "period": { "from": "2026-01-01", "to": "2026-01-31" },
    "claims": {
      "total": 487,
      "by_status": {
        "draft": 45,
        "submitted": 127,
        "approved": 189,
        "rejected": 24,
        "paid": 102
      }
    },
    "amounts": {
      "total": 145780.50,
      "currency": "EUR",
      "by_status": {
        "submitted": 45230.00,
        "approved": 67820.00,
        "paid": 32730.50
      },
      "by_category": {
        "transport": 58900.00,
        "accommodation": 42150.00,
        "meals": 28340.50,
        "other": 16390.00
      }
    },
    "processing": {
      "avg_approval_time_hours": 18.4,
      "avg_payment_time_days": 5.2,
      "pending_approval": 127,
      "pending_payment": 189
    },
    "top_claimants": [
      { "employee_id": 543, "name": "Jean Dupont", "claims_count": 28, "total_amount": 12450.00 },
      { "employee_id": 892, "name": "Marie Martin", "claims_count": 24, "total_amount": 10230.00 }
    ]
  }
}
```

### Implémentation

1. Ajouter contrôleur `ExpenseStatsController` ou méthode `stats()` dans contrôleur existant
2. Agrégations SQL sur `expense_claims`, `expense_claim_items` (ou table équivalente)
3. Calcul des délais moyens de traitement (soumission → approbation) et remboursement (approbation → paiement)
4. Sommes par statut et par catégorie
5. Top 10 des employés par nombre de notes et montant total
6. Route `GET /expense/stats` dans `api/routes/modules/*.php`
7. Entrée CHANGELOG `### Expense`

## Priorité

**P2** — feature utile pour dashboard Finance, améliore le module Expense

## Labels

`enhancement`, `backend`, `P2`
