# Spec — Tableaux de bord comptables (issue #5230)

- **Module** : `accounting` — périmètre : `api/app/Modules/Accounting/**` + routes module + `front/admin-dashboard` (vue dashboard) + OpenAPI/CHANGELOG.
- **Source** : `docs/architecture/COMPTABILITE_CONCEPTION.md` §2 (rapports) + issue #5230.
- **Statut** : à implémenter — branche `mod/accounting/5230-dashboards`.

## 1. Objectif

Rapports de pilotage pour le comptable/principal : **factures émises (période),
encaissements, impayés (aging), dépenses (achats fournisseurs)** + export CSV.
Lecture seule, agrége sur les modèles existants (documents, paiements,
contacts) — zéro dépendance aux endpoints en vol (journal #5363, paiements
#5365, PDF #5341).

## 2. Contrat API

RBAC : `api.manager:comptable,principal`. Isolation tenant : compagnie courante
de la requête + `BelongsToCompany` (fail-closed #3727).

### `GET /api/v1/accounting/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD`

Période par défaut : mois courant. Réponse :

```json
{
  "data": {
    "period": { "from": "2026-08-01", "to": "2026-08-24" },
    "invoices":   { "count": 12, "total_ttc": 12345.0 },
    "collections":{ "count": 8,  "total": 8901.0 },
    "expenses":   { "count": 5,  "total_ttc": 3456.0 },
    "outstanding":{
      "count": 4, "total_due": 4567.0,
      "aging": [
        { "bucket": "0_30",  "count": 2, "total_due": 1200.0 },
        { "bucket": "31_60", "count": 1, "total_due": 800.0 },
        { "bucket": "61_90", "count": 0, "total_due": 0.0 },
        { "bucket": "90_plus","count": 1, "total_due": 2567.0 }
      ],
      "list": [ { "id": 1, "number": "FAC-2026-0012", "contact": "Client A",
                  "issue_date": "2026-07-01", "due_date": "2026-07-31",
                  "days_late": 24, "total_ttc": 2567.0, "paid_amount": 0.0,
                  "due_amount": 2567.0, "status": "overdue" } ]
    }
  }
}
```

Définitions :
- **factures émises** : documents hors `draft`/`cancelled`, `issue_date` dans la période ;
- **encaissements** : `AccountingPayment.received_at` dans la période ;
- **impayés** : documents émis (`sent`/`partially_paid`/`overdue`) dont
  `total_ttc > paid_amount` — `total_due` = Σ(t​TTC − payé) ; `aging` par
  écart d'échéance (0-30, 31-60, 61-90, 90+) sur les documents **en retard**
  (`due_date ≤ aujourd'hui`) ; `list` = 100 plus anciens (tri échéance ASC) ;
- **dépenses** : documents liés à un contact `supplier`/`both`, hors
  `draft`/`cancelled`, `issue_date` dans la période.

### `GET /api/v1/accounting/dashboard/export`

CSV (UTF-8, BOM) de la liste des impayés — `Content-Disposition` :
`accounting-dashboard-outstanding-<from>_<to>.csv`. Colonnes : number, contact,
issue_date, due_date, days_late, total_ttc, paid_amount, due_amount, status.
Neutralisation injection CSV (`CsvCellSanitizer`, #4169).

## 3. Implémentation

- `Application/Actions/AccountingDashboardService.php` : agrégations + CSV ;
- `Interfaces/Api/V1/AccountingDashboardController.php` : `show` + `export` ;
- `Interfaces/Api/V1/Requests/AccountingDashboardRequest.php` : validation
  `from`/`to` (dates, `from ≤ to`) ;
- `routes/modules/accounting.php` : 2 routes sous `api.manager:comptable,principal`.

## 4. UI — `/accounting/dashboard` (admin dashboard)

- 4 cartes KPI (glass-*, i18n) : factures émises (count + TTC), encaissements
  (count + total), impayés (count + total dû), dépenses (count + TTC) ;
- tableau des impayés avec aging par bucket + bouton export CSV ;
- entrée sidebar comptable/principal ; i18n ×4 (`accounting.dashboard.*`).

## 5. Tests (Feature)

`AccountingDashboardTest` : agrégations recoupées (fixtures documents/
paiements/contacts), buckets aging, exclusion draft/cancelled, RBAC
(employé/marketing → 403, comptable/principal → 200), isolation tenant,
export CSV (contenu + type + filename), validation 422 (période invalide).

## 6. DoD

- [ ] Chiffres recoupés par tests ; export CSV
- [ ] CI verte (phpstan strict, pint, gate accounting ≥ 70 %, openapi-check)
- [ ] i18n ×4 à parité ; CHANGELOG ; `Closes #5230` dans le body de la PR
