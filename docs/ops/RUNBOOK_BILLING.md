# Runbook exploitation — Billing (BC-21)

> **Issue :** DEP-BC21 #6251
> **Module :** `api/app/Modules/Billing` (souscriptions, factures, webhooks providers, feature flags par plan)
> **Registre :** `dev-hub/tools/runbook-registry.json` (MAT-015)

## Périmètre

Ce runbook couvre l'exploitation courante du billing : supervision du recouvrement, rejeu de webhook, réconciliation paiements ↔ factures, incident provider, procédures manuelles et rollback. La couverture backup/restore/rollback plateforme s'applique en complément (`docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md`).

## Commandes planifiées (scheduler, `api/bootstrap/app.php`)

| Commande | Fréquence | Rôle |
|---|---|---|
| `billing:check-trials` | quotidien | Relance/expire les essais (trial) |
| `billing:check-overdue` | quotidien | Marque les factures `pending` échues → `overdue` (+ relances) |
| `billing:generate-invoices` | mensuel (1er, 03:00) | Génération des factures du cycle |
| `billing:reconcile-payments` | quotidien (supervision) | Réconciliation paiements ↔ factures (dry-run par défaut, `--apply` en maintenance) |
| `billing:report` | quotidien (supervision) | Métriques de recouvrement (souscriptions par statut, factures overdue/paid, paiements récents) |

> `billing:enforce-delinquency` (machine à états des souscriptions, #5960) arrive avec le merge du lot DEP-BC21 ; à planifier quotidiennement à ce moment (documenté dans la PR #5960).

## Supervision du recouvrement

```bash
# État global (sortie structurée, utilisable en alerting)
php artisan billing:report --json

# Écarts paiements ↔ factures (dry-run — ne mute rien)
php artisan billing:reconcile-payments
```

**Seuils d'alerte suggérés** :
- `invoices.overdue > 0` pendant plus de 48 h → investigation recouvrement ;
- `subscriptions.past_due` ou `expired` en hausse → vérifier les webhooks providers ;
- `payments_last_7d == 0` avec des factures émises → chaîne de paiement à vérifier.

## Procédures

### 1. Recouvrement manuel
1. `php artisan billing:report` — identifier les tenants `past_due`/`expired` et les factures `overdue`.
2. Contacter le client (canal habituel), relancer la facture via le portail (endpoint `/billing/portal` ou Stripe customer portal).
3. Après paiement confirmé côté provider, rejouer le webhook (procédure 2) pour que l'état local se synchronise.
4. En dernier recours : `billing:reconcile-payments --apply` (uniquement si montant + devise correspondent).

### 2. Rejeu d'un webhook provider
1. Identifier l'événement concerné (Stripe dashboard / logs Chargily).
2. Stripe : « Resend webhook » depuis le dashboard (même payload, nouvel essai).
3. Chargily : re-poster le payload signé via l'endpoint `/billing/webhook/{provider}` avec la signature d'origine (ou régénérer depuis le dashboard si disponible).
4. Vérifier l'effet : `billing:report` (statut de la souscription/facture mis à jour).
5. Si le webhook est perdu (payload introuvable) : `billing:reconcile-payments --apply` après vérification manuelle du paiement chez le provider.

### 3. Réconciliation paiements ↔ factures
```bash
# Phase 1 — diagnostic (aucune mutation)
php artisan billing:reconcile-payments

# Phase 2 — corrections sûres (montant + devise identiques uniquement)
php artisan billing:reconcile-payments --apply
```
Code retour : `0` = aucun écart ; `2` = écarts signalés (à investiguer). Un écart de montant n'est JAMAIS corrigé automatiquement — vérification manuelle requise. Rejouable : `--apply` rejoué ne change plus rien (idempotent).

### 4. Incident provider (Stripe/Chargily down)
1. Alerter : webhooks en échec → vérifier `webhook_deliveries` et les logs `billing.webhook.*`.
2. Les paiements passés pendant l'incident seront livrés en différé par le provider (retry automatique) ; ne PAS créer de paiement manuel avant la fin de la fenêtre de retry (24 h).
3. Après l'incident : `billing:reconcile-payments` (diagnostic) puis `--apply` si les écarts sont sûrs.
4. Déclarer un incident P1 si les souscriptions `active` sont impactées (perte de service client).

### 5. Rollback / corrections manuelles
- Toute écriture sensible est tracée : les webhooks sont enregistrés dans `webhook_deliveries`, les changements de souscription dans l'audit.
- Pour corriger une souscription erronée : privilégier le super-admin (`/platform/companies/{company}/subscription`) — jamais un UPDATE SQL direct.
- Une migration de données billing doit passer par la procédure habituelle (backup tenant avant, rollback documenté).

## Logs à surveiller
- `billing:reconcile-payments` : `[billing:reconcile-payments] écarts signalés` (warning) ;
- `billing:check-overdue` : `Invoice overdue: id=...` (warning) ;
- Webhooks : toute erreur de signature ou de traitement `billing.webhook.*` (error).

## Contacts
- Owner BC-21 : @kitokoh (registre `dev-hub/governance/bounded-context-registry.json`).
- Escalade incident : procédure `RUNBOOK_INCIDENT_P1.md`.
