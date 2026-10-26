# Décision — Gates pilote Fuel : traçabilité et arbitrage (#8586)

> **Issue :** [#8586](https://github.com/kitokoh/leopardo-hr/issues/8586) — « Gates pilote tous pending : aucun GO pilote possible »
> **Registre :** `dev-hub/tools/pilot-gates.json`
> **Garde :** `dev-hub/tools/check-pilot-gates.sh` (+ tests)
> **Date :** 2026-10-26

## Le problème réel

L'issue constate que les 9 gates du pilote FuelStation sont tous `pending` et
que la recette métier n'est pas signée. Elle demande trois choses :

1. chaque gate a un **propriétaire** et une **date** ;
2. les gates **techniques** sont adossés à des **preuves d'exécution** ;
3. la décision **GO/NO-GO** est tracée.

Les points 1 et 2 sont **codables** : ce sont des champs à ajouter au registre
et des règles à faire respecter par le garde. Le point 3 est un **acte de
signature** — il ne s'implémente pas.

## Décision D-8586-1 — Le propriétaire est un RÔLE, jamais un nom

Chaque gate porte un `owner` pris dans un vocabulaire fermé de rôles
(`platform`, `vertical-owner`, `security`, `ops`, `qa`, `product`).

**Pourquoi pas un nom de personne :** le registre est **versionné et public**
dans le dépôt. Y inscrire « qui » au sens d'une identité nominative ferait
entrer des données personnelles dans l'historique Git, où elles sont
indélébiles. Un rôle désigne la même responsabilité sans exposer personne, et
il survit à un changement d'affectation — un nom, non.

## Décision D-8586-2 — La preuve n'est exigée que sur les gates TECHNIQUES

Sept gates sont techniques (`manifest`, `core_flow`, `api_security`,
`security_review`, `performance`, `observability`, `golden_journey`) et portent
une `evidence` : la **commande exécutée et son résultat**.

Les gates `recette` et `signed_uat` en sont **exemptés**.

**Pourquoi :** une recette métier se **signe**, elle ne s'exécute pas en CI.
Exiger une « preuve d'exécution » sur un acte de signature pousserait à écrire
une preuve factice pour satisfaire le garde — exactement le contraire de ce que
la traçabilité cherche à obtenir. Un garde qui pousse à mentir est un mauvais
garde.

## Décision D-8586-3 — La date est ISO et obligatoire

`updated_at` au format `AAAA-MM-JJ`, sur **chaque** gate. Une date non ISO est
refusée : `26/10/2026` et `10/26/2026` sont le même jour pour un humain et deux
jours différents pour un tri lexicographique. Un registre daté de façon ambiguë
ne permet pas de savoir quel gate a bougé en dernier.

## Décision D-8586-4 — Le GO reste `pending`, et c'est le bon résultat

**Le pilote FuelStation reste en `go_decision: pending`.** Ce n'est pas un
échec de la mission, c'est la conclusion correcte.

Le garde interdit un `go` tant qu'un gate n'est pas validé. Or :

| Gate | Statut | Motif |
|---|---|---|
| `core_flow` | pending | **Preuve solide** : 190 tests Fuel verts (827 assertions, PR #8894) — mais un gate se valide par une **décision**, pas par un test qui passe |
| `performance` | pending | Budgets p95 non mesurés (MAT-014) |
| `golden_journey` | pending | Golden journey Fuel non branché en CI (MAT-013) |
| `recette` | pending | **Recette métier non signée** — acte humain |
| les 5 autres | pending | Preuves partielles, décision non prise |

**Le point clé :** `core_flow` a désormais une preuve d'exécution **réelle et
vérifiable** (190 tests verts). C'est précisément ce que l'issue demandait au
critère 2. Mais **une preuve n'est pas une validation** : le passage de
`pending` à `validated` est une décision de chef de projet, et le garde a été
conçu pour qu'un agent ne puisse pas la prendre seul. Le registre le dit
lui-même : « La décision finale (`go_decision`) est prise par le chef de projet,
jamais par l'agent seul. »

**Ce qui a changé concrètement :** avant, on ne savait pas *qui* portait chaque
gate ni *sur quoi* il s'appuyait. Maintenant, chaque gate a un responsable
identifié, une date, et — pour les gates techniques — une preuve exécutable.
Le pilote n'est pas débloqué, mais il est devenu **auditable**.

## Décision D-8586-5 — Le garde échoue si la traçabilité régresse

Les règles 6 et 6bis du garde rendent la traçabilité **non contournable** : un
gate sans propriétaire, sans date, ou un gate technique sans preuve fait
échouer le job `Hygiene Guards`. Sans cela, les champs ajoutés aujourd'hui
seraient supprimés au premier gate ajouté à la main dans six mois.

## Preuve

```
$ bash dev-hub/tools/check-pilot-gates.sh
✅ Critères go/no-go cohérents — 5 pilotes, gates obligatoires vérifiés,
   aucune décision GO prématurée.

$ bash dev-hub/tools/tests/check-pilot-gates.test.sh
✅ registre réel → vert
✅ fixture valide → vert
✅ GO prématuré → rouge
✅ status invalide → rouge
✅ gate obligatoire absent → rouge
✅ gate sans propriétaire → rouge
✅ gate sans date → rouge
✅ date non ISO → rouge
✅ gate technique sans preuve → rouge
✅ gate non technique sans preuve → vert
── 10 passés, 0 échecs ──
```

## Ce qui reste à faire (hors de portée d'un commit)

1. **Signer la recette métier** FuelStation (acte humain, chef de projet).
2. **Mesurer les budgets p95** (MAT-014) et brancher le golden journey en CI
   (MAT-013) — puis passer ces gates à `validated` avec leur preuve.
3. **Prononcer le GO/NO-GO** et le dater dans le registre.

**Critère de clôture de #8586 :** les 9 gates `validated` + recette signée +
`go_decision` renseigné. Le garde refusera tout GO prématuré.
