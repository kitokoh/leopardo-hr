# Runbook — Fiabilité des files CRM : inbox, outbox, pause, replay, dead-letter (issue #5741, CRM-PRE)

- **Statut :** actif — procédures opérationnelles des files CRM
- **Date :** 2026-08-28
- **Composants :** `crm_outbox_events` (outbox), `webhook_events` (inbox idempotente existante, `WebhookEventRegistry`), `crm:outbox-dispatch`, worker queue `database`

---

## 1. Architecture (en une minute)

- **Inbox** : les webhooks entrants (callbacks providers) sont **persistés AVANT la réponse 2xx** dans `webhook_events` (`WebhookEventRegistry::begin()` — verrou unique `(source, event_id)`). Rejeu = réponse mémorisée, zéro effet double.
- **Outbox** : tout effet métier CRM (notification, export, callback sortant, relance) est d'abord **persisté dans `crm_outbox_events` APRÈS le commit** de la transaction métier (`CrmOutboxPublisher::publish()`), puis consommé par `crm:outbox-dispatch`.
- **Idempotence** : contrainte unique `(company_id, idempotency_key)` — rejeu = skip. Un consommateur vérifie l'état traité avant d'appliquer son effet.
- **Erreurs** : transitoire (provider down, timeout) → retry backoff exponentiel + jitter (10 s, ~20 s, ~40 s, ~80 s, max 300 s) ; permanente (payload invalide) → **dead-letter** immédiate ; après `MAX_ATTEMPTS` (5) → dead-letter.

## 2. Pause (arrêt d'urgence)

Suspendre la consommation SANS perte (les événements restent `pending`) :

```bash
# Option 1 — arrêter/commenter le scheduler (déploiement)
php artisan schedule:list | grep outbox   # localiser le job

# Option 2 — repousser tous les events dus (maintenance programmée)
UPDATE crm_outbox_events
   SET available_at = now() + interval '4 hours'
 WHERE status = 'pending' AND available_at <= now();
```

L'inbox continue d'**enregistrer** les webhooks entrants (persistance avant réponse) ; seul le traitement diffère.

## 3. Replay

Après une panne worker ou une pause, tout ce qui est `pending` et dû est automatiquement repris au prochain `crm:outbox-dispatch`. Pour **forcer un replay immédiat** (y compris d'événements en erreur) :

```bash
# Replay des pending dont le backoff n'est pas encore arrivé à terme
UPDATE crm_outbox_events SET available_at = now() WHERE status = 'pending';
php artisan crm:outbox-dispatch --limit=500

# Replay d'un dead-letter APRÈS correction de la cause (revue DLQ ci-dessous) :
UPDATE crm_outbox_events
   SET status = 'pending', attempts = 0, available_at = now(), last_error = NULL
 WHERE status = 'failed' AND id = <id>;
php artisan crm:outbox-dispatch --limit=1
```

**Jamais de double effet au replay** : la clé d'idempotence est conservée ; un événement déjà appliqué est rejeté par la contrainte unique.

## 4. Dead-letter review

```sql
-- Lister la DLQ (statut failed)
SELECT id, company_id, event_type, attempts, last_error, updated_at
  FROM crm_outbox_events
 WHERE status = 'failed'
 ORDER BY updated_at DESC
 LIMIT 100;

-- Analyse : transitoire épuisée (5 tentatives) vs permanente (payload invalide)
SELECT event_type, last_error, count(*) FROM crm_outbox_events WHERE status='failed' GROUP BY 1,2 ORDER BY 3 DESC;
```

Décisions : corriger la cause → replay (section 3) ; ou acter le rejet (payload sans issue) → purge contrôlée (section 5).

## 5. Purge contrôlée

```bash
# Purge des événements SENT de plus de 30 jours (rétention standard)
DELETE FROM crm_outbox_events WHERE status = 'sent' AND processed_at < now() - interval '30 days';

# Purge d'un dead-letter EXPLICITEMENT validé (après revue, jamais automatique)
DELETE FROM crm_outbox_events WHERE status = 'failed' AND id = <id>;
```

Règle : **jamais de purge automatique des `failed`** — une revue humaine décide (perte d'effet assumée vs replay).

## 6. Surveillance (alertes)

| Métrique | Seuil d'alerte | Requête |
|---|---|---|
| Queue lag (events pending dus non consommés) | > 100 pendant 10 min | `SELECT count(*) FROM crm_outbox_events WHERE status='pending' AND available_at <= now();` |
| Dead-letter rate | > 0 nouveau par heure | `SELECT count(*) FROM crm_outbox_events WHERE status='failed' AND updated_at > now() - interval '1 hour';` |
| Worker mort | aucun `crm:outbox-dispatch` depuis 5 min | healthcheck scheduler / `schedule:list` |

Le worker de queue `database` existant (`leopardo-queue-worker`) consomme la file par défaut ; le dispatch outbox est un **polling** (scheduler) ou un worker dédié — pas de dépendance au worker pour la durabilité (persistance avant effet).
