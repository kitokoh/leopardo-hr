# Décisions — #8578 : vocabulaire d'états canonique Fuel

**Date :** 2026-10-26 · **Branche :** `fix/fuel-canonical-state-vocabulary` · **Issue :** #8578

**Contexte.** 9 défauts de test restants sur le module Fuel (sur les 13 de l'issue, 4 ayant
disparu lors des passes précédentes). Aucun n'est un bug de production : chacun est une
**contradiction de spécification** que deux tests expriment différemment, ou une assertion
qui décrit un contrat que le code n'a jamais eu.

Méthode : pour chaque défaut, je tranche comme **fondateur/PM** en faveur de ce qui rend le
domaine *cohérent et démontrable*, puis j'aligne le code **et** le test minoritaire sur cette
décision. Le test majoritaire n'est jamais réécrit pour faire passer du code : c'est le
contraire qui est vrai ici — le code est mis au niveau du contrat dominant.

---

## D1 — Incident : `reported` est le premier état, pas `open`

**Contradiction.** `FuelIncident::STATUS_OPEN` est la valeur par défaut de la colonne et du
modèle, mais `TRANSITIONS` déclare **deux** états de naissance (`open` et `reported`), et
l'état exposé en lecture (`STATUSES`) est `open|in_progress|resolved|closed` — `reported`
n'y figure pas. Deux tests exigent `reported` à la création, les autres tolèrent `open`.

**Décision : `reported` devient l'état de naissance canonique ; `open` disparaît.**

Raisonnement de fondateur : un incident est *signalé*, puis *assigné*, puis *en cours*. Un
état nommé `open` ne dit rien de plus que « pas encore fermé » et crée un doublon sémantique
avec `reported`. Garder les deux oblige chaque rapport et chaque filtre à traiter deux cas
pour un seul fait métier — c'est exactement ce qui produit des statistiques fausses
(cf. ADR-04 sur les codes canoniques). Un seul état de naissance, nommé après l'**acte** qui
le crée.

**Conséquences.** `STATUSES` = `reported|assigned|in_progress|resolved|closed|suspended`.
La colonne garde `'open'` en défaut SQL ? **Non** : le défaut est aligné sur `reported`,
sinon un `INSERT` hors modèle ressusciterait l'état supprimé.

## D2 — Incident : `assigned` est un état réel, `assign` ne saute pas à `in_progress`

**Contradiction prouvée par l'issue.** `FuelIncidentApiTest:149` attend `in_progress` après
`/assign`, `:257` attend `assigned`. `STATUS_ASSIGNED` est déclaré, présent dans
`TRANSITIONS` et dans `TRANSITION_TARGETS`, mais **aucun code ne l'écrit jamais** — le
service pose `in_progress` en dur.

**Décision : `assign` → `assigned`.**

Raisonnement de PM : l'état `assigned` existe déjà dans le graphe, il est déjà une cible de
transition légitime, et'il porte une information opérationnelle distincte — *« quelqu'un en
est responsable »* ≠ *« quelqu'un y travaille »*. Un incident assigné mais pas commencé est un
état réel dans une station-service (le technicien est désigné, il passe demain). Le supprimer
obligerait à stocker cette information dans `assigned_to !== null`, c'est-à-dire à réinventer
l'état dans une jointure.

C'est aussi la seule lecture qui rend `STATUS_ASSIGNED` autre chose que du code mort.

## D3 — Incident : `assigned` et `in_progress` ne peuvent pas clôturer directement

**Défaut.** `test_illegal_transition_is_rejected` exige `reported → closed` = 422, mais le
graphe autorise `reported → closed` **et** `assigned → closed`. Le test reçoit 200.

**Décision : on ne clôture jamais sans résolution. Chemin canonique =
`reported → assigned → in_progress → resolved → closed`.**

Raisonnement de fondateur : clôturer sans passer par `resolved` vide la clôture de son sens —
on ne sait plus *si* le problème a été réglé ni *comment*. `closed` doit signifier « réglé et
classé », pas « abandonné ». L'abandon a son propre état (`suspended`, déjà déclaré) ; s'il
n'existe pas encore de chemin d'abandon, on l'ajoute plus tard, on ne détourne pas `closed`.

C'est un durcissement volontaire : il casse la flexibilité « clôturer vite » au nom de la
traçabilité. Assumé — sur un module d'incidents d'équipement en station-service, la valeur
d'audit prime sur la commodité.

## D4 — Tâche de maintenance : `todo` est l'état initial

**Défaut.** `FuelMaintenanceTask::STATUS_TODO` = `'todo'` est le défaut de colonne et du
modèle, mais `STATUS_OPEN = 'open'` et `STATUS_PENDING = 'pending'` sont déclarés et utilisés
par `transitionTask()` comme état de départ. Deux tests attendent `open`/`pending` à la
création, un troisième attend `pending`.

**Décision : `todo` est le seul état de naissance. `open` et `pending` sont supprimés.**

Raisonnement : `pending` (« en attente ») et `todo` (« à faire ») décrivent le **même** fait
avec deux mots — c'est le doublon exact que #8631 a tranché ailleurs. `open` est encore un
troisième mot pour la même chose. On garde le mot le plus précis et le moins ambigu :
une tâche est *à faire*.

`transitionTask()` est réécrit pour lire `STATUS_TODO` en état de départ — actuellement il
lit `STATUS_OPEN`, qui n'est jamais écrit, donc **le graphe de transition des tâches est
mort** : toute transition depuis une tâche réelle lève 422. C'est un vrai défaut de
production découvert en alignant les tests, pas seulement un défaut de test.

## D5 — Incident : `category`, pas `description_redacted`

**Défaut.** Un payload de test envoie `description_redacted` (vestige d'un ancien schéma) et
un `category`; ni l'un ni l'autre n'existe dans le contrat actuel (`title` + `description`).
Le `StoreFuelIncidentRequest` n'accepte pas `category`.

**Décision : le contrat reste `title` obligatoire + `description` optionnelle ; on ajoute
`category` en **allowlist fermée** (`equipment|safety|environment|other`).**

Raisonnement : `description_redacted` est un vestige — un champ dont le nom décrit un
*traitement* (rédaction) plutôt qu'une *donnée*. Le supprimer du test est correct. En
revanche `category` est une vraie dimension d'analyse (un incident de sécurité et une pompe
en panne ne se traitent pas pareil, et ne se comptent pas ensemble) ; l'ajouter coûte une
colonne et évite de déduire la catégorie du texte libre — ce qui ne marche pas.

## D6 — Ordre des gardes : `403` (autorisation) avant `422` (validation)

**Contradictions.** `test_operator_cannot_manage_incidents` attend 403 sur un POST vide,
mais la validation FormRequest s'exécute avant `authorize()` → 422.
`test_task_illegal_transition_is_rejected` attend 422 mais reçoit 403.

**Décision : l'autorisation prime toujours sur la validation.** Un acteur non autorisé reçoit
403 quel que soit son payload ; un acteur autorisé reçoit 422 pour une transition illégale.

Raisonnement DevOps/sécurité : renvoyer 422 à un acteur non autorisé **fuit le schéma** — un
attaquant apprend quels champs sont requis en observant les messages d'erreur, sans jamais
être authentifié pour l'action. Le 403 d'abord est la seule réponse qui n'informe pas.
Côté tâche, l'inverse était demandé : la route `transition` n'était pas derrière le groupe
`manager`, donc la policy répondait 403 avant que le service ne juge la transition — le test
avait raison sur le fond (la transition est illégale) mais le contrôle d'accès était trop
large. Les deux corrections vont dans le même sens : **policy d'abord, métier ensuite**, et
la route est reclassée pour que le manager autorisé atteigne bien le métier.

## D7 — Suppression de station : `archived` est la vérité, `deleted:true` est un contrat

**Défaut.** `test_manager_crud_station` exige à la fois `data.deleted === true` **et**
`assertDatabaseMissing('fuel_stations', ['id' => ...])`. Or `destroy()` archive
(`status = 'archived'`) et ne supprime rien — c'est délibéré et documenté dans le contrôleur.

**Décision : la station est **archivée**, jamais supprimée. Le test est corrigé pour vérifier
l'archivage.**

Raisonnement de fondateur : une station porte des ventes, des relevés, des incidents, des
sessions de caisse. Une suppression physique détruirait l'historique comptable — inacceptable
sur un module qui alimente la comptabilité. L'enveloppe `{id, deleted: true}` est conservée
(contrat d'API stable pour les clients), mais elle signifie « retiré du référentiel actif »,
pas « effacé ». Le commentaire du contrôleur le disait déjà ; c'est le test qui contredisait
le code.

**Hors périmètre de cette branche :** les défauts 9 à 13 de l'issue (outbox `failed` vs
`sent`, clé d'idempotence sans contrainte de forme, incidents fantômes sans `reported_by`,
rapprochements vs clôtures de caisse) ne sont **pas** dans le périmètre « vocabulaire
d'états » et restent ouverts. Les traiter ici mélangerait sept décisions indépendantes dans
une branche ; ils méritent leur propre cycle.

---

## Résumé des changements de contrat

| Objet | Avant | Après |
|---|---|---|
| Naissance incident | `open` (défaut SQL) | `reported` |
| Après `assign` | `in_progress` | `assigned` |
| `reported → closed` | autorisé | **422** |
| Naissance tâche | `todo` (défaut) / `open` / `pending` selon le code | `todo` seul |
| Départ de transition tâche | `STATUS_OPEN` (jamais écrit → graphe mort) | `STATUS_TODO` |
| Champ catégorie | absent | `category` allowlist fermée |
| Ordre des gardes | validation → autorisation | **autorisation → validation** |
| Suppression station | `archived` en base, `deleted:true` en API, test exigeant les deux | `archived` assumé, test aligné |
