# Runbook — BC-21 BILLING (abonnements, facturation, recouvrement)

- **Bounded context :** BC-21 Billing & Subscription (agent propriétaire 21)
- **Portée :** plans, abonnements, entitlements (feature_plan_matrix), factures, paiements plateforme, webhooks providers (Stripe/Chargily)
- **Dépendances :** BC-02 Tenant, BC-03 Identity, BC-01 Platform (registre `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md`)
- **Machine à états :** `SubscriptionStatus` + `InvoiceStatus` (transitions gardées via `transitionTo()` — DEP-BC21 #5897/#6246/#6248)

---

## 1. Cartographie des composants

| Composant | Emplacement |
|---|---|
| Modèles | `api/app/Modules/Billing/Domain/Models/` (`Subscription`, `Invoice`, `FeaturePlanMatrix`, …) |
| Enums d'état | `Domain/Enums/SubscriptionStatus.php`, `InvoiceStatus.php`, `PlanCode.php` |
| Services providers | `Infrastructure/Services/StripeService.php`, `ChargilyService.php` |
| Webhooks | `Interfaces/Api/V1/StripeWebhookController.php`, `PaymentWebhookController.php` (idempotence `WebhookEventRegistry` #5444) |
| Endpoints manager | `Interfaces/Api/V1/BillingController.php`, `FeatureFlagController.php` (+ `EntitlementGuard` #6247) |
| Contrats plateforme | `PlatformCompanySubscriptionController.php` (super-admin) |
| Commandes | `billing:enforce-delinquency`, `billing:check-overdue`, `billing:generate-invoices`, `billing:check-trials`, `billing:report`, `billing:reconcile-payments` (si livré) |

## 2. Supervision planifiée (scheduler)

| Fréquence | Commande | Rôle |
|---|---|---|
| Quotidien | `billing:enforce-delinquency` | Politique de recouvrement : actif à période expirée **ou facture due impayée** → `past_due` ; `past_due` hors grâce (due_date + `--grace-days`, défaut 7 j) → `expired`. **Idempotente, rejouable.** |
| Quotidien | `billing:check-overdue` | Factures `sent`/`pending` avec `due_date` passée → `overdue`. |
| Quotidien | `billing:check-trials` | Alerte trials expirant sous 3 jours (log). |
| Mensuel (1er, 03:00) | `billing:generate-invoices` | Génère les factures mensuelles des abonnements actifs (`sent`, échéance +30 j). |
| À la demande | `billing:report` | Compteurs par statut (souscriptions, factures, paiements) pour supervision. |
| À la demande | `billing:reconcile-payments` | Réconciliation paiements ↔ factures (dry-run par défaut). |

Vérification : `php artisan schedule:list` doit afficher ces entrées. En cas de doublon
(commande listée deux fois), purger les doublons de `api/routes/console.php`
(le scheduler canonique est `bootstrap/app.php` → `withSchedule`).

## 3. Scénarios d'incident

### 3.1 Un webhook provider n'arrive pas (Stripe/Chargily)
1. Vérifier le registre d'idempotence : `webhook_events` (source, event_id, code, réponse mémorisée).
2. Un événement en échec de traitement → 500 → le provider re-tente (Stripe ~1 h, jamboree). L'événement reste rejouable tant que la réservation n'est pas `complete`.
3. Rejeu manuel : renvoyer le payload signé depuis le dashboard provider, ou via le script de replay des files CRM si applicable.
4. **Ne jamais** marquer `complete` à la main sans avoir vérifié l'effet de bord (facture payée, souscription active).

### 3.2 Un tenant actif ne paie plus — recouvrement
1. `php artisan billing:report` — compter `past_due`/`expired`.
2. `php artisan billing:enforce-delinquency --grace-days=7` — appliquer la politique (idempotente ; l'accès opérationnel reste porté par `companies.status`).
3. Si un tenant doit être coupé immédiatement : passer `companies.status = suspended` (enforcement), **pas** l'état de souscription (distinction #5897).
4. Réactivation : paiement reçu → `transitionTo(Active)` (checkout Stripe, renew, upgrade). Une souscription résiliée localement n'est **pas** réactivée par un écho webhook (règle « cancelled est sticky », #6246).

### 3.3 Facture payée sans paiement enregistré (ou inverse)
1. `php artisan billing:reconcile-payments --dry-run` — lister les écarts (facture `sent`/`overdue` avec paiement `completed`, doublons `provider_reference`, montants incohérents).
2. Analyser chaque écart avant `--apply` : les corrections automatiques ne concernent que les écarts sûrs (montant + référence concordants).
3. En cas de doute : marquer manuellement depuis le dashboard provider, jamais par UPDATE SQL direct.

### 3.4 Une migration billing échoue sur Render
1. Les migrations tenant sont additives et réentrantes (`schemaTableExists`, conventions `docs/architecture/MIGRATIONS_CONVENTIONS.md`).
2. Vérifier le run `leopardo:migrate` dans les logs Render ; corriger puis re-run (idempotent).
3. Rollback : toute migration est additive (pas de DROP de colonne) — le rollback consiste à re-déployer le commit précédent (voir `RUNBOOK_ROLLBACK.md`).

## 4. Rôles & droits (support)

| Qui | Droits |
|---|---|
| Manager `principal` du tenant | `GET/POST /billing/subscription*`, `/billing/invoices*`, `/billing/checkout`, `/billing/portal`, `GET /feature-flags/*` |
| Employé du tenant | Aucun accès billing (403) |
| Super-admin plateforme | `GET/PATCH /platform/companies/{company}/subscription`, `PATCH /platform/companies/{company}/features` (jamais les routes tenant) |

## 5. Métriques & logs

- `php artisan billing:report` : counts par statut de souscription, factures par statut, paiements récents.
- Cockpit plateforme : `invoices_paid` / `invoices_overdue` (PlatformMetricsOverviewController).
- Logs : channel JSON structuré (`structured`), PII minimale — company_id uniquement, jamais de références complètes de paiement.

## 6. Backup / Restore / Rollback

- Données billing (subscriptions, invoices, payments, feature_plan_matrix) : tables tenant → couvertes par le dump schémas tenant (`RUNBOOK_BACKUP_RESTORE.md`).
- Restore : rejouer le dump tenant + re-synchroniser les webhooks providers (les événements non traités seront re-livrés ; l'idempotence #5444 évite les doublons).
- Rollback applicatif : re-déployer le commit précédent (`RUNBOOK_DEPLOY.md` / `RUNBOOK_ROLLBACK.md`).
- Drill : consigner toute restauration d'essai dans `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` (preuve datée MAT-015).

## 7. Procédure de vérification post-incident

1. `php artisan billing:report` — états cohérents.
2. `php artisan billing:enforce-delinquency --dry-run` (si supporté) — aucun effet de bord.
3. `billing:reconcile-payments --dry-run` — zéro écart.
4. Vérifier `webhook_events` : aucune réservation orpheline (`begin` sans `complete`).
