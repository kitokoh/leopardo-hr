# ADR-0032 — Autorisation avant validation, et encodage des query strings dans les tests

- **Statut** : accepté
- **Date** : 2026-11-16
- **Portée** : tous les endpoints API `api/app/Modules/*/Interfaces/Api/V1/` et leurs tests `api/tests/Feature/**`
- **Origine** : tranche #9098 (dette de tests backend), mesures des runs `43570213398`, `43680616469`, `43690261821`, `43893629397`

## Contexte

Trois familles de défauts, découvertes **par la mesure** et non par revue, ont un point commun : la réponse d'un endpoint dépendait d'une étape évaluée dans le mauvais ordre.

### 1. Permission évaluée après la validation (422 au lieu de 403)

Sur Laravel, une `FormRequest` s'exécute **avant** le corps du contrôleur : `authorize()` puis `rules()` puis la méthode. Un contrôle de permission écrit dans le contrôleur est donc évalué **après** la validation.

Occurrences mesurées (chacune avec un test qui échouait) :

| Endpoint | Symptôme mesuré |
|---|---|
| `POST /travel/adverts` | `Expected [403] but received 422` |
| `POST /travel/adverts/{id}/renew` | `Expected [403] but received 404` |
| `GET /travel/reports/*` | `Expected [403] but received 422` |

Conséquence de fond : **le refus dépendait du contenu envoyé**. Un acteur sans droit apprenait, selon son payload, qu'il était « refusé » ou « invalide » — et un attaquant pouvait distinguer les deux.

### 2. `abort(404)` utilisé pour un refus de permission

`renew()` faisait `abort(404)` sur `cannot('renew', …)`. Un 404 doit signifier « la ressource n'existe pas dans mon périmètre » (anti-énumération cross-tenant), **jamais** « je n'ai pas le droit ». Le test attendait 403, contrat correct.

### 3. Le `+` d'un timestamp ISO n'est pas encodé dans une query string

`now()->toIso8601String()` produit `2026-11-15T04:00:00+00:00`. Interpolé dans une URL (`?from=…&to=…+00:00`), le `+` est décodé côté serveur en **espace** : `to` devient `2026-11-15T04:00:00 00:00`, la règle `date` échoue, l'API répond 422.

Mesure : `TravelReportTest` — deux tests rouges (`422` sur des rapports pourtant valides, et un test de permission qui ne testait donc plus la permission, la validation le devançant).

## Décision

1. **Toute autorisation se tranche dans `FormRequest::authorize()`** (ou dans un middleware), jamais dans le corps du contrôleur quand la méthode prend une `FormRequest`. Le contrôle devient alors inatteignable : il est supprimé, pas commenté.
2. **403 = refus de permission**, **404 = ressource absente du périmètre** (y compris cross-tenant), **422 = payload invalide pour un acteur autorisé**. Aucune de ces trois réponses ne se substitue à une autre.
3. **Toute URL de test construite avec des dates passe par `http_build_query()`** (ou `->toIso8601ZuluString()`), jamais par interpolation directe d'un timestamp qui contient `+`.
4. Un test de permission doit **envoyer un payload valide** : sinon il prouve la validation, pas la permission.

## Conséquences

- Les corrections sont petites (une méthode `authorize()` par endpoint) mais le défaut est **systémique** : `search/code` recense 91 `FormRequest` dont `authorize()` retourne `true` avec un commentaire du type « la policy tranche ailleurs ». Chacun doit être vérifié : middleware (correct) ou contrôleur (défaut).
- Un test de permission écrit avec un payload invalide **passe au vert pour la mauvaise raison** après correction de l'ordre : il faut l'encoder correctement pour qu'il mesure ce qu'il annonce.
- La dette restante est mesurable : `#9098` (90 échecs, dont 25 Travel au 2026-11-16), chaque tranche fermant les siens avec `Closes`.

## Alternatives écartées

- **Middleware global « authorize-then-validate »** : impossible sans réécrire chaque `FormRequest`; le contrat Laravel est déjà celui-là, le défaut est de ne pas l'utiliser.
- **Garder 404 sur les refus** : indistinguable d'une absence réelle, et casse les contrats de test documentés.
- **Autoriser l'interpolation de timestamps dans les tests** : le comportement est spécifique à la query string et silencieux ; on ne peut pas le rattraper côté serveur sans accepter des dates ambiguës.
