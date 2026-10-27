# D-7947 — Gestion locative (HOSP-005, BC-32)

**Issue** #7947 · **Branche** `bc/bc32-hospitality-rentals` · **Date** 2026-10-27
**Statut** Implémenté, spec `docs/specifications/SOLUTION_HOSPITALITY.md` validée (fondateur, 2026-09-20).

---

## Contexte

La spec §5 décrit le locatif comme le pendant « longue durée » de l'hôtellerie :
un bail, des échéances mensuelles, des encaissements. Le socle (migration,
modèles `HospitalityLease` / `HospitalityRentPayment`) existait déjà mais
n'était **relié à rien** : aucune route, aucun service, aucun test. HOSP-005 ne
pouvait donc pas être close.

Ce document trace les arbitrages pris en écrivant la couche applicative.

---

## D1 — `billing_day` est ramené au dernier jour du mois, jamais reporté

Un bail « au 31 » échu en février tombe le **28 (ou 29)**.

L'alternative — reporter au 3 mars — produit un effet pervers silencieux : le
locataire apparaît `late` sur un jour du mois qui **n'a jamais existé**, et rien
dans l'écran ne permet de comprendre pourquoi. Le ramener au dernier jour du
mois est la convention des bailleurs et la seule qui garde l'échéance **dans le
mois qu'elle facture**.

`billing_day` reste borné à `1..31` en validation ; le ramenage est fait à la
génération (`dueDateFor`), pas au stockage — un bail « au 31 » reste « au 31 »
en base, on ne réécrit pas l'intention du contrat.

## D2 — `late` est DÉRIVÉ, et recalculé à la lecture

Une échéance est `late` quand `due_on` est dépassée et qu'elle n'est pas soldée.

La migration annotait déjà « recalculé par la commande
`hospitality:mark-late-rent-payments`, jamais saisi à la main ». **Je n'ai pas
créé cette commande**, et je l'ai fait exprès : le calcul est **déterministe et
pas cher** (`due_on < now()`), donc le recalculer à la lecture (`index`,
`refreshLateStatus`) est gratuit et toujours juste.

Une commande planifiée aurait introduit une fenêtre pendant laquelle un impayé
de 2020 s'affiche encore `due` — jusqu'au prochain passage de la cron. Sur un
écran de relance locative, c'est exactement le bug qu'on ne veut pas.

La commande garde un intérêt pour les **notifications** (`late → email au
locataire`), pas pour la vérité du statut. Elle reste donc à créer dans
HOSP-008 (relances), avec un rôle différent.

## D3 — Un encaissement ne peut pas dépasser le dû : refus, pas troncature

Accepter 1000 sur une échéance de 800 créerait un **crédit de 200 que rien ne
restitue** : pas de report automatique sur le mois suivant, pas d'écran de
remboursement. Le locataire paierait 200 € dans le vide.

On renvoie donc `422 RENT_AMOUNT_EXCEEDS_OUTSTANDING`. La troncature silencieuse
est le pire des trois choix (refus / troncature / crédit) parce qu'elle perd de
l'argent sans laisser de trace.

## D4 — `UNIQUE(lease_id, period)` porte l'idempotence, pas le code

La génération utilise `exists()` + `create()` dans une transaction, **et** la
base porte la contrainte. Le code est là pour renvoyer un compteur
`{created, skipped}` utile à l'appelant ; la contrainte est là pour que deux
requêtes concurrentes ne puissent pas créer un doublon.

C'est la même logique que GSI-3 (campagnes SEA) : l'invariant vit en base dès
qu'il est exprimable en SQL, le code applicatif ne fait que le rendre lisible.

### Le bug que cette contrainte a révélé

La première écriture du service avançait le curseur de mois **dans la branche
« création » uniquement**, avec un `continue` sur la branche « période déjà
présente ». Conséquence : le **second** passage de `generate-periods` bouclait
indéfiniment sur la première période existante.

Le test d'idempotence ne rendait jamais la main (timeout à 400 s), et c'est
comme ça que le bug a été trouvé — **dans le chemin exact que l'issue demande de
garantir**. L'avancement du curseur est désormais inconditionnel et placé hors
des branches.

## D5 — Un bail ne se supprime pas, il se résilie

Aucune route `DELETE`. Un bail est le contrat qui engage un logement ; le
supprimer rendrait orphelines les échéances déjà émises et effacerait
l'historique locatif. `terminate` accepte `ended` (terme naturel) ou
`terminated` (résiliation anticipée).

Corollaire : un bail terminal est **immuable** (`422` sur `PATCH`). Réécrire le
loyer d'un bail terminé rendrait incohérentes les échéances déjà émises.

## D6 — L'index d'unicité est PARTIEL, exprès

`CREATE UNIQUE INDEX ... (company_id, unit_id) WHERE status = 'active'`

Un index total interdirait à jamais de relouer une unité après un premier bail.
Le partiel laisse l'historique (`ended`, `terminated`) et ne contraint que le
bail en cours. Testé : résilier puis relouer la même unité fonctionne, et deux
baux actifs simultanés sont refusés **par la base** (pas seulement par le code).

---

## Ce qui n'est PAS fait (et pourquoi)

| Sujet | Raison |
|---|---|
| Commande `mark-late-rent-payments` | `late` est dérivé à la lecture (D2) ; la commande n'a de sens qu'avec les relances (HOSP-008) |
| Report de crédit / remboursement | Hors spec ; le refus (D3) est le comportement sûr en attendant une décision produit |
| Génération automatique mensuelle (cron) | L'endpoint `generate-periods` est idempotent et appelable ; l'automatiser demande de trancher la politique de rattrapage — HOSP-008 |
| Quittances / PDF | HOSP-007 (documents) |
| Index de recherche locataire | Hors périmètre ; l'index filtre déjà par bail/statut/période |

---

## Vérification

```
$ php artisan test tests/Feature/Hospitality
  Tests:    106 passed (408 assertions)     # 19 nouveaux, 87 préexistants verts

$ ./vendor/bin/phpstan analyse --level=8 app/Modules/HospitalityManager
  [OK] No errors

$ ./vendor/bin/pint --test app/Modules/HospitalityManager tests/Feature/Hospitality
  PASS   74 files
```

Les quatre critères d'acceptation de l'issue sont couverts nommément :
unicité du bail actif, génération idempotente, encaissement partiel, calcul du
retard — plus six invariants de bord (fin de bail, ramenage du jour, refus de
dépassement, bail terminal immuable, étanchéité cross-tenant, unité d'un autre
établissement).
