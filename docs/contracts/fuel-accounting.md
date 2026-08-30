# Contrat Accounting ↔ FuelStation (FUEL-015)

> **Issue :** #5809 (FUEL-015) — publier des agrégats validés de ventes,
> caisse, stock et écarts vers Accounting **sans accès direct aux tables**.
> **Statut :** actif (outbox `fuel_outbox_events`, consommateur
> `FuelAccountingOutboxConsumer`).
> **Date :** 2026-08-30.

## Principe

Le module FuelStation et le module Accounting **ne partagent aucune table**.
Tout agrégat comptable est publié dans l'outbox FuelStation
(`fuel_outbox_events`, tenant-scoped) APRÈS le commit de la transaction
métier, puis consommé de façon asynchrone et **idempotente** par
`fuel:outbox-dispatch` (claim atomique, lease 15 min, retry backoff borné,
dead-letter). Un crash entre commit et consommation ne perd rien (replay) ;
`UNIQUE (company_id, idempotency_key)` garantit zéro doublon.

## Événements versionnés

| Event type | Déclencheur | Agrégat | Payload (sans PII) |
|---|---|---|---|
| `fuel.sale.recorded.v1` | vente enregistrée (FUEL-008) | `fuel_sale` | sale_id, station_id, product, quantity, amount, sale_time, source |
| `fuel.cash_session.closed.v1` | clôture de session de caisse (FUEL-007) | `fuel_cash_session` | cash_session_id, station_id, opening/closing/expected balance, variance, closed_at |
| `fuel.stock.reconciled.v1` | rapprochement station/jour (FUEL-009) | `fuel_reconciliation_run` | station_id, run_date, variance_count, explained |

Aucune PII : pas d'employee_id, pas de nom, pas de notes, pas de
description. Les montants sont les valeurs **calculées serveur** (jamais
fournies par le client).

## Consommateur

`FuelAccountingOutboxConsumer` (enregistré dans
`FuelStationServiceProvider`) **valide l'agrégat référencé** (existence +
cohérence, ex. montant identique, session close) avant d'accuser réception.
Une agrégation inexistante ou incohérente → erreur permanente → dead-letter
(aucun retry inutile). Les écritures comptables réelles (journal, lettrage)
seront branchées par le module Accounting sur ce contrat.

## Garanties

- Tenant-scoped : `company_id` non nullable, consommation dans le contexte
  tenant (`TenantManager::withinTenant`).
- Idempotence : rejeu réseau ou métier → même événement (clé unique), pas de
  double écriture.
- Rejouable : `fuel:outbox-dispatch` peut être relancé sans effet de bord.
- Fail-closed : un événement sans consommateur → dead-letter `no_consumer`
  (visible, pas de perte silencieuse).

## Utilisation

```bash
php artisan fuel:outbox-dispatch --limit=100   # à la demande
# scheduler : routes/console.php → everyMinute (bootstrap/app.php)
```

## Règles

- Tout NOUVEL agrégat comptable = nouvel event type versionné (jamais de
  modification d'un event type existant).
- Toute modification d'un payload = nouveau numéro de version
  (`fuel.sale.recorded.v2`) + entrée dans ce document.
