# Scheduler : créneaux perdus et rattrapage (#7967)

## Le problème, en une phrase

Le plan free Render endort le conteneur web après ~15 min d'inactivité, et le
scheduler tourne **dedans** (`WEB_SCHEDULER_LOOP`, `api/docker-entrypoint.sh`).
Laravel ne relit jamais l'historique : une échéance due pendant le sommeil
n'existe plus, et rien ne le signale.

Conséquence réelle, pas théorique : `leave:accrue`, `billing:generate-invoices`,
`billing:reconcile-payments` et les relances de recouvrement pouvaient sauter
une journée entière pendant que tous les tableaux de bord restaient verts.

## Ce qui est en place

| Élément | Rôle |
|---|---|
| table `scheduler_slot_runs` | une ligne par entrée de planning et par **échéance théorique** |
| `RecordSchedulerRun` | branché sur `ScheduledTaskStarting` (un point d’accroche, pas 46) |
| `scheduler:catch-up` | compare les échéances théoriques au registre, rejoue ou signale |

Le créneau enregistré est **aligné sur l'expression cron**, pas sur l'heure
réelle : un run à 09:07 pour `0 9 * * *` enregistre le créneau 09:00. C'est ce
qui permet de distinguer « exécuté » de « perdu », et ce qui rend la contrainte
`UNIQUE (key, due_at)` utile comme verrou anti-double-exécution.

## Politique de rejeu — volontairement étroite

Une commande n'est rejouée que si elle figure dans l'allowlist du
`SchedulerRunLedger`, c'est-à-dire si la rejouer plus tard produit le même état
qu'une exécution à l'heure :

- **drains d'outbox** (`travel:outbox-dispatch`, `restaurant:outbox-dispatch`,
  `fuel:outbox-dispatch`, `travel:webhook-dispatch`) — rejouer consomme le
  backlog accumulé, c'est exactement l'effet voulu ;
- **expirations dérivées de l'horloge** (`travel:expire-pending-bookings`,
  `travel:expire-adverts`, `hospitality:expire-pending-reservations`) ;
- **relances adossées à une table de dédoublonnage**
  (`crm:tasks:send-overdue-reminders`, `communication:send-follow-ups`,
  `onboarding:send-reminders`) ;
- **publications filtrées sur date** (`announcements:publish-scheduled`,
  `marketing:publish-scheduled-posts`) ;
- **sondes en lecture seule** (`edge:monitor`).

Tout le reste est **signalé, jamais rejoué**. En particulier
`billing:generate-invoices` : rejouer trois jours plus tard émet des factures
rétroactives avec une date de prestation fausse — c'est un incident comptable,
pas une réparation. Un humain tranche.

## Lire le signal

```bash
# diagnostic seul, aucune écriture, code retour 0
php artisan scheduler:catch-up --report

# diagnostic seul, code retour 1 s'il reste des créneaux non rattrapables
php artisan scheduler:catch-up --dry-run

# rattrapage réel des seules commandes éligibles
php artisan scheduler:catch-up
```

Sortie type :

```
Créneaux perdus sur des commandes NON rattrapables — action humaine requise :
  billing:generate-invoices                     1 créneau(x)
      dernier manqué : 2026-10-29T02:00:00+00:00
      raison : Génération monétaire rétroactive : une facture émise en retard
               est un incident comptable (TVA, date de prestation). Un humain tranche.
```

La tâche planifiée tourne en `--report` : un créneau perdu non rattrapable ne
doit pas faire échouer `schedule:run` en boucle. **L'alerte doit donc être
portée par la supervision** (voir `queue-supervision.yml`), pas par le statut
du cron.

## Deux pièges à ne pas réintroduire

1. **`insert()` + catch de la violation d'unicité ne marche pas ici.** Sur
   PostgreSQL, une violation de contrainte **avorte la transaction en cours** :
   toutes les requêtes suivantes de la même transaction échouent, y compris
   celles du scheduler qu'on observe. D'où `insertOrIgnore`
   (`ON CONFLICT DO NOTHING`), dont le booléen de retour est la source de vérité.
2. **Sur un registre neuf, une absence ne prouve rien.** Sans borne basse, le
   premier passage après déploiement déclare perdues toutes les échéances de la
   fenêtre et rejoue une semaine de factures. Le balayage ne remonte donc jamais
   avant le **premier créneau réellement observé** pour l'entrée.

## Sortie d'interim (hors dépôt)

Le rattrapage **réduit** le risque, il ne le supprime pas : un créneau rattrapé
reste un créneau exécuté en retard. La vraie sortie est le worker dédié —
moyen de paiement Render + `leopardo-queue-worker-prod` (starter, ~7 $/mois),
puis `WEB_QUEUE_DRAIN=false` et `WEB_SCHEDULER_LOOP=false` sur `leopardo-prod`.
Procédure : `docs/ops/RENDER_WORKER_PROVISIONING_7649.md`.

`APP_KEY: sync: false` est déjà posé sur les quatre blueprints
(`render.yaml`, `render.prod.yaml`, `render.dev-b.yaml`, `render.prod-b.yaml`) :
le risque de régénération de clé à chaque re-sync est déjà fermé.
