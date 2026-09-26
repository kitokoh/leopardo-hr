# Scheduler — source unique de planification (BOS-006A / #8139)

> Date : 2026-09-26 · Statut : règle active · Garde CI : `SchedulerSingleSourceTest`

## Règle

**`api/routes/console.php` est la SEULE source de planification.** Le bloc
`->withSchedule()` de `api/bootstrap/app.php` a été supprimé : Laravel charge
les deux définitions, donc 8 commandes tournaient en double avec des horaires
contradictoires (preuve et détail dans l'issue #8139).

- Toute nouvelle entrée s'ajoute dans `routes/console.php`, dans la section
  « Scheduled Jobs », avec son commentaire de justification (issue/ADR).
- Ne jamais réintroduire `->withSchedule()` dans `bootstrap/app.php` — un
  avertissement y figure en commentaire.
- L'inventaire commenté en tête de section (commande — fréquence — verrou)
  est mis à jour avec chaque ajout/retrait.

## Règle des verrous

Toute commande **à effets** (écritures, notifications, emails, webhooks,
provisioning, purge, synchronisation) porte `withoutOverlapping()`. Les
consommateurs d'outbox / dispatchers à la minute ajoutent `onOneServer()`.
Seul exempt documenté : `monitor:slow-queries` (monitor en lecture seule).
Le test `tests/Feature/Platform/SchedulerSingleSourceTest.php` vérifie cette
règle en CI (aucun doublon, fréquences canoniques, verrous, legacy absent).

## Vérification locale / prod

```bash
php artisan schedule:list        # aucune commande ne doit apparaître 2 fois
php artisan schedule:test        # simulation d'un run (optionnel)
```

## Fenêtre de déploiement recommandée (note #8139)

Le retrait des doublons ne modifie aucune donnée (configuration pure) : le
déploiement peut avoir lieu à tout moment **hors fenêtre critique des runs du
1er du mois, 02:00–04:30 UTC** (`billing:generate-invoices` 02:00,
`travel:settle-sales` 02:30, `leave:accrue` 03:00, `leave:carry-forward`
04:00 le 1er janvier). Déployer pendant qu'une de ces commandes s'exécute
peut interrompre un run en cours — chaque commande concernée est rejouable
au run suivant, mais hors 1er du mois l'exposition est nulle.

Effet attendu au premier déploiement : fin des doubles exécutions
(`leave:accrue` quotidien fantôme, second run de facturation de 03:00,
double `attendance:auto-close` sans verrou, second expireur Travel legacy).
L'audit rétrospectif des éventuels doublons déjà créés en production relève
de **BOS-006B (#8140)**.

## Historique

| Date | Changement |
|---|---|
| 2026-09-04 | Retrait d'un doublon `travel:outbox-dispatch` (consolidation CI) |
| 2026-09-22 | `travel:outbox-dispatch` recentré sur `routes/console.php` (quota Neon, #8050) |
| 2026-09-26 | **Unification complète (#8139)** : bloc `withSchedule` supprimé, 8 doublons résolus, expireur Travel legacy supprimé, verrous généralisés, test de garde |
