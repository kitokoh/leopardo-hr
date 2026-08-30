# Audit sécurité & RGPD — FuelStation (FUEL-020)

> **Issue :** #5814 (FUEL-020) — sécurité, performance et observabilité.
> **Périmètre :** batch A BC-15 (FUEL-009/010/011/014/015/016/017/018/019/020).
> **Date :** 2026-08-30.
> **Statut :** ✅ aucun finding bloquant — 4 recommandations.

## 1. Surface d'attaque (routes ajoutées, toutes `/api/v1/fuel-station/*`)

- Référentiel : stations, sites, équipements (pompes/cuves/compteurs), produits.
- Opérationnel : stock (entrées, niveau, rapprochement), incidents, maintenance.
- Clients & fidélité : upsert, consentement, dépense de points.
- Rapports & exports : ventes journalières, synthèse shift, anomalies, CSV.
- Synchronisation terminal : outbox bornée, lots relevés/ventes.

## 2. Contrôles appliqués

| # | Contrôle | Où |
|---|---|---|
| S1 | **Auth obligatoire** (401) sur toutes les routes | middleware `auth:sanctum` du groupe |
| S2 | **Fail-closed solution inactive** (403) | `assertSolutionActive()` dans chaque contrôleur |
| S3 | **Policies deny-by-default** (403 opérateur sur routes manager) | FuelStation/Site/Equipment/Product/StockEntry/Incident/Maintenance/Customer/Report Policies |
| S4 | **Isolation tenant stricte** : cross-tenant → 404 | scopes `company_id` + FK composites `(x, company_id)` + vérifications explicites |
| S5 | **Idempotence** : `idempotency_key` (relevés, stock), `external_id` (ventes), UNIQUE `(station, date)` (rapprochement), outbox `UNIQUE (company_id, idempotency_key)` | migrations + services |
| S6 | **Pagination bornée** (1..100), exports bornés (10 000 lignes), sync bornée (500) | contrôleurs |
| S7 | **Filtres allowlist** (status, q, kind, station_id…) — aucun paramètre arbitraire en `where` brut | contrôleurs |
| S8 | **Aucun ajustement silencieux** : `reason` obligatoire pour un ajustement de stock | Request + FuelStockService (double garde) |
| S9 | **PII chiffrées au repos** : phone/email/metadata (casts `encrypted`) | fuel_customers |
| S10 | **Consentement marketing explicite** (opt-in RGPD) versionné | fuel_customers.marketing_consent + événement outbox |
| S11 | **Aucune PII dans les événements/notifications** : payloads agrégés uniquement | FuelOutboxPublisher + templates `fuel_*` (fr/en/tr/ar) |
| S12 | **Pièces jointes contrôlées** : MIME allowlist, taille ≤ 5 Mo, nom assaini (basename) | StoreFuelIncidentRequest + FuelIncidentService |
| S13 | **Exports CSV neutralisés OWASP** (injection de formule) | CsvCellSanitizer (pattern #4169) |
| S14 | **Audit des exports** : `export_history` + DataAccessAuditLogger (données sensibles) | FuelImportExportService |
| S15 | **Secrets jamais loggés** : pas de mot de passe/clé dans les payloads ; erreurs tronquées (500) | fuel_outbox_events.last_error |
| S16 | **Journalisation dédiée** : channel `fuel-station` (daily, 14 jours) — observabilité sans PII | config/logging.php + services |

## 3. Tests de durcissement (FuelSecurityHardeningTest)

- 401 sur 16 routes fuel sans authentification ;
- 403 solution inactive (flag absent) ;
- 403 opérateur sur routes manager (stock, rapports, exports, clients) ;
- 404 cross-tenant (stations, incidents, clients) ;
- pagination bornée (per_page=100000 → ≤ 100).

## 4. Recommandations (non bloquantes)

1. **Rate limiting spécifique** : un throttle dédié `fuel-station` (ex.
   120 req/min) sur les routes de synchronisation terminal une fois le
   parc mobile déployé (les routes sont déjà derrière `throttle:api`).
2. **Séparation des tâches caisse** : approbation de session par un manager
   différent de l'ouvreur (aujourd'hui possible mais non vérifiée).
3. **Rotation des clés** : suivre le RUNBOOK_SECRET_ROTATION_PURGE pour les
   secrets d'environnement liés aux fournisseurs de notification.
4. **KPIs d'observabilité** : seuil d'alerte sur `fuel_outbox_events.failed`
   (dead-letter) à brancher dans la supervision existante.

## 5. Vérifications

- PHPStan strict (delta) : 0 erreur sur les fichiers du batch.
- Pint : formatage appliqué.
- Tests Feature : verts (stock, incidents, référentiel, sync, outbox,
  clients, rapports, imports/exports, notifications, durcissement).
