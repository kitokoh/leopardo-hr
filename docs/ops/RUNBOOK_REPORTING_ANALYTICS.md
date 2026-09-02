# RUNBOOK — Reporting & Analytics (BC-22)

> **Issue :** DEP-BC22 #5898 — Deep maturity BC-22 Analytics & Reporting.
> **Lineage :** `docs/architecture/ANALYTICS_READ_MODEL_LINEAGE.md`.
> **Budgets :** `dev-hub/tools/performance-budgets.json` (§critical_endpoints).
> **SLA :** reporting = lecture seule ; aucune écriture transactionnelle.

Ce runbook couvre l'exploitation des read models de reporting : tableau de
bord comptable, exports CSV/FEC, exports bancaires asynchrones et métriques
plateforme. Il décrit symptômes → diagnostic → action → rollback.

---

## 1. « Le dashboard comptable est lent » (p95 dépassé)

**Symptômes :** `GET /api/v1/accounting/dashboard` > 300 ms p95 en CI (k6)
ou en prod ; requêtes lentes sur `accounting_documents` / `accounting_payments`.

**Diagnostic :**

```bash
# Requêtes lentes PostgreSQL (top 10)
psql "$DATABASE_URL" -c "SELECT query, calls, mean_exec_time FROM pg_stat_statements ORDER BY mean_exec_time DESC LIMIT 10;"
```

**Vérifier que les index attendus existent** (registre
`performance-budgets.json` §required_indexes) :

```sql
\d accounting_documents
-- attendu : accounting_documents_company_issue_index (company_id, issue_date)
-- attendu : accounting_documents_company_status_due_index (company_id, status, due_date)
\d accounting_payments
-- attendu : accounting_payments_company_document_status_index (company_id, document_id, status)
```

**Actions :**

1. Vérifier qu'aucun endpoint ne scanne hors tenant (plan `EXPLAIN ANALYZE`
   avec `WHERE company_id = …`).
2. Vérifier la pagination / le bornage (`limit 100` sur la liste impayés).
3. Si un index manque : migration additive idempotente (convention
   `2026_08_23_000005_add_accounting_performance_indexes.php`), PR dédiée,
   mesure avant/après en CI.
4. Si le volume justifie un snapshot : suivre la politique du lineage
   (ADR + PR dédiée, snapshot versionné, `refreshed_at` exposé).

**Rollback :** aucun changement de comportement (lecture seule) ; un index
inutile se supprime par migration `down()`.

---

## 2. « Les chiffres du dashboard changent entre deux écrans »

**Symptômes :** un total affiché diffère d'une requête à l'autre.

**Diagnostic :**

1. **Déterminisme** : rejouer le recompute — deux appels successifs de
   `AccountingDashboardService::summary()` doivent produire le même résultat
   (verrouillé par `GoldenDashboardRecomputeTest`). Si ce n'est pas le cas,
   c'est une régression d'invariant → P1.
2. **Période** : vérifier les paramètres `from`/`to` (validation
   `AccountingDashboardRequest`, bornes `startOfMonth`/`today` par défaut).
3. **Données sources** : un document/paiement a-t-il changé entre les deux
   écrans (statut, montant, date) ? Le dashboard reflète l'état courant —
   pas d'incohérence si l'écart s'explique par une écriture récente.
4. **Isolation tenant** : s'assurer que le tenant visualisé est bien celui
   dont les données sont lues (scope `company_id` fail-closed #3727).

**Actions :** corriger la source (BC-07/08/05) — le read model n'est jamais
la cause d'un écart non expliqué par ses sources.

---

## 3. « L'export CSV des impayés est vide / incomplet / corrompu »

**Symptômes :** export vide alors qu'il y a des impayés ; formules Excel
exécutées ; encodage cassé.

**Diagnostic :**

1. Vérifier la période (`from`/`to`) — défaut = mois courant.
2. Vérifier le filtre statuts (`sent/partially_paid/overdue` +
   `total_ttc > paid_amount`).
3. Vérifier la borne `limit 100` : au-delà, la liste est tronquée (les
   totaux `count`/`total_due` restent exacts — agrégations séparées).

**Actions :**

- **Injection CSV** : toute cellule passe par `CsvCellSanitizer`
  (`api/app/Support/CsvCellSanitizer.php`, testé par
  `CsvCellSanitizerTest`). Ne jamais écrire de cellule brute dans un CSV.
- **Encodage** : réponse `text/csv; charset=UTF-8` ; un BOM UTF-8 est
  recommandé pour Excel.
- **Fiabilité** : les exports lourds passent par un job asynchrone
  (`GenerateBankExportJob`, statuts `pending → generating → generated/failed`,
  retry borné) — ne jamais générer un gros fichier dans le cycle HTTP.

**Rollback :** l'export est stateless (recalcul à la volée) — rejouer la
requête suffit.

---

## 4. « Un job d'export bancaire reste en pending/failed »

**Symptômes :** `bank_exports.status = failed` avec `error_message` ; job
dans la queue `documents`.

**Diagnostic :**

```bash
php artisan queue:failed --json   # jobs en échec + raison
php artisan queue:monitor documents
```

**Actions :**

1. Lire `error_message` (aucune PII — logs redacted).
2. `GenerateBankExportJob` : `tries 3`, `timeout 120`, tenant-scoped
   (`TenantScopedJob` + middleware `EnsureTenantContext`) — le contexte
   tenant est restauré en `finally`.
3. Rejouer : `php artisan queue:retry <job-id>` (idempotent : le job ne
   régénère pas un fichier si `bank_export.status` est déjà `generated`).
4. Si échec persistant : DLQ / `queue:failed` + alerte (RUNBOOK_ALERTING).

---

## 5. « Un chiffre du cockpit admin est suspect » (métriques plateforme)

**Symptômes :** compteurs d'observabilité / communication incohérents.

**Diagnostic :**

1. Le cockpit admin lit des agrégats plateforme **hors tenant** — vérifier
   que la route est bien sous RBAC super-admin (`/admin/*`).
2. Comparer avec les logs structurés (corrélation `request_id` / `job_id`,
   cf. `docs/architecture/OBSERVABILITY.md`).
3. Les compteurs de files (`QueueObservabilityController`) reflètent l'état
   des queues Redis — vérifier les workers (`php artisan queue:work`).

**Actions :** corriger la source (BC-01/13/14) ; le reporting plateforme
n'écrit jamais lui-même.

---

## 6. Alertes liées

| Alerte | Seuil | Action |
|---|---|---|
| `reporting_dashboard_p95` | > 300 ms sur `GET /api/v1/accounting/dashboard` | §1 |
| `reporting_export_failed` | `GenerateBankExportJob` en échec après retries | §4 |
| `reporting_csv_injection` | cellule non neutralisée détectée | §3 (P1) |
| `reporting_determinism` | échec `GoldenDashboardRecomputeTest` en CI | §2 (P1) |

## 7. Prouver que le reporting est sain (CI)

```bash
php artisan test --filter=GoldenDashboardRecomputeTest   # invariants read models
php artisan test --filter=AccountingDashboardTest        # RBAC + agrégations + isolation
php artisan test --filter=CsvCellSanitizerTest           # masquage CSV
php artisan test --filter=GenerateBankExportJobTest      # export async + retry
bash dev-hub/tools/check-performance-budgets.sh api      # registre p95 cohérent
```

## 8. Non-régression

Aucun de ces scénarios ne modifie de données : le reporting est
**lecture seule**, tous les correctifs se font à la source (BC-05/07/08).
Tout changement de read model doit passer par le lineage et le registre des
budgets avant merge.
