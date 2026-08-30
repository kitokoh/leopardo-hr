# Threat model — FuelStation (BC-15)

> **Issue :** FUEL-020 #5814
> **Surface registrée :** `dev-hub/tools/security-threat-models.json` (`id: fuel_station`, MAT-017)
> **Périmètre :** stocks, livraisons, ajustements, rapprochements, incidents équipements, tâches de maintenance, référentiel (stations, sites, pompes, cuves, produits), relevés de compteurs et sessions de caisse (FUEL-002..011).

## Actifs protégés

| Actif | Sensibilité | Localisation |
|---|---|---|
| Relevés de compteurs (production opérationnelle) | Élevée (données opérationnelles, pas PII) | `fuel_meter_readings`, `fuel_meter_intervals` |
| Niveaux de stock, livraisons, ajustements | Élevée (fraude possible, écart jamais silencieux) | `fuel_stock_movements`, `fuel_deliveries`, `fuel_stock_reconciliations` |
| Sessions de caisse et ventes | Élevée (montants) | `fuel_cash_sessions`, `fuel_sales` |
| Incidents et résolution | Moyenne (traçabilité) | `fuel_incidents`, `fuel_maintenance_tasks` |
| Affectations pompistes | Moyenne (employés) | `fuel_shift_assignments` |
| Métadonnées de station/site | Faible (chiffrées au repos) | `fuel_stations.metadata`, `fuel_sites.metadata` (cast `encrypted:array`) |

## Menaces et contrôles

| # | Menace | Contrôles | Tests |
|---|---|---|---|
| T1 | **Cross-tenant** : lire/écrire le stock, un incident ou une livraison d'un autre tenant | FK composites `(station_id, company_id)` partout ; 404 sûr cross-tenant (fail-closed avant Policy) ; `BelongsToCompany` | `FuelStockApiTest::test_cross_tenant_stock_is_404`, `FuelReferentialApiTest::test_cross_tenant_*` |
| T2 | **Élévation RBAC** : pompiste gère le référentiel/stocks/incidents | Policies deny-by-default (`isManager()`), signalement d'incident seul cas ouvert à tout employé (report terrain) | `FuelReferentialApiTest::test_operator_*`, `FuelIncidentApiTest::test_operator_can_report_but_not_manage` |
| T3 | **Rejeu** : livraison ou ajustement en double (double comptage du stock) | `external_id` UNIQUE `(company_id, external_id)` sur `fuel_deliveries` ; `idempotency_key` UNIQUE `(company_id, idempotency_key)` sur `fuel_stock_movements` ; réponses mémorisées | `FuelStockApiTest::test_manager_records_delivery_and_stock_increases` (rejeu), `test_adjustment_requires_reason` (rejeu) |
| T4 | **Écart de stock masqué / ajustement silencieux** | Ajustement avec `reason` OBLIGATOIRE (422 sinon) ; rapprochement upsert rejouable ; `status=variance` + notes explicatives ; jamais d'ajustement automatique | `FuelStockApiTest::test_adjustment_requires_reason`, `test_reconciliation_is_replayable_and_never_silent`, `test_reconciliation_reports_variance_when_metered_delta_differs` |
| T5 | **Perte de traçabilité incident** | Cycle `open → assigned → in_progress → resolved → closed` validé en application ; chaque transition écrite dans `audit_logs` (`fuel.incident.*`) ; notes de résolution obligatoires | `FuelIncidentApiTest::test_full_lifecycle_with_audit_trail`, `test_illegal_transition_returns_422` |
| T6 | **PII/secrets dans les logs ou réponses** | Payloads de logs sans PII (company_id, station_id, action, pas de noms/emails) ; `metadata` jamais exposée par les payloads API ; TruffleHog + garde secrets | revue manuelle des payloads + CI secret-scan |
| T7 | **Abus volumétrique des écritures** | `throttle:api` global (300/min/company) + limiteur dédié `fuel-station-write` (120/min/company) sur les POST stock/incidents | registre RateLimiter + politique documentée |

## Limiteur dédié (FUEL-020)

`RateLimiter::for('fuel-station-write', 120/min par company)` appliqué aux routes
d'écriture du module (`deliveries`, `adjustments`, `incidents`, `maintenance-tasks`) :
les écritures de stock sont des opérations métier sensibles (fraude possible) —
une rafale anormale est bornée avant d'atteindre la couche métier.

## Invariants (verrouillés par les tests)

1. Aucune ligne sans `company_id` ; aucun accès cross-tenant (FK composites + 404).
2. Un rejeu (même `external_id`/`idempotency_key`) n'a aucun effet doublon.
3. Un écart de rapprochement n'est jamais corrigé automatiquement (`variance` + notes).
4. Toute transition d'incident est auditable (acteur, avant/après).
5. Aucun ajustement sans raison ; aucune résolution sans notes.
