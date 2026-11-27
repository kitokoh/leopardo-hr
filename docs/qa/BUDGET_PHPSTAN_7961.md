# Budget de réduction des baselines PHPStan — cliquet branche vs main (#7961)

> **Mise à jour #9978 (2026-11-27).** Le budget n'est plus « opposable en
> revue » : il est **bloquant**. Mesure du jour : **2 577 occurrences**,
> réparties 1 411 / 1 160 / 6 sur les trois baselines. Deux constats que
> l'audit initial n'avait pas vus :
>
> - **1 232 occurrences (48 %) sont dans `tests/Feature`**, pas dans le code
>   de production. La dette « 1 716 erreurs en mode strict » est donc d'abord
>   une dette de **typage des tests** — un mock mal typé masque une vraie
>   signature ;
> - **`app/Modules/Accounting` est déjà à 0 occurrence**, alors que l'issue le
>   présentait comme prioritaire. `app/Core/Auth` et `app/Core/Tenant` aussi
>   (atteints par #9922).
>
> Échéancier : **-30 % en 13 semaines** (2 577 → 1 804), jalons hebdomadaires
> dans `dev-hub/governance/phpstan-baseline-budget.json` §`schedule`.

> Complément de la cartographie `docs/qa/DETTE_PHPSTAN_7655.md` (analyse par
> parsing neon, sans exécution de PHPStan). Snapshot du **2026-09-20** :
> **2 719 occurrences** gelées (somme des `count:`) — 1 553 dans
> `api/phpstan-strict-baseline.neon`, 1 160 dans `api/phpstan-baseline.neon`,
> 6 dans `api/phpstan-modules-baseline.neon`.

## 1. Le cliquet (bloquant)

`dev-hub/tools/check-phpstan-baseline-budget.sh [base_ref] [api_dir]`
(texte pur : bash + python3, aucun PHP requis) :

1. calcule le total des `count:` de chaque baseline sur la **branche courante
   (working tree)** ET sur **`origin/main`** ;
2. **échoue si le total global croît** vs main — interdiction de croissance,
   quel que soit le découpage en commits ;
3. affiche les totaux par baseline, le delta, le **top 10 des modules et des
   fichiers les plus chargés** (priorisation des campagnes de réduction), et
   l'état vs le budget daté.

Branché en CI dans `.github/workflows/architecture-check.yml` (job
`hygiene-guards`, toujours exécuté — même famille que les gardes #5448 et
PA2-ARCH-005). Différence avec les gardes existantes : celles-ci sont
**diff-scopées** (base→head d'une PR) ; le cliquet #7961 compare l'**état
complet de la branche** au dernier `origin/main`, ce qui couvre aussi les
merges de branches anciennes et les fenêtres d'exception mal refermées.

## 2. Le budget (opposable en revue)

Source de vérité : `dev-hub/governance/phpstan-baseline-budget.json`.

- **Objectif global : −5 % d'occurrences par semaine** à partir du snapshot
  (2 719 occ au 2026-09-20). La garde calcule le « attendu aujourd'hui » par
  décroissance composée et signale un retard (⚠️ informatif, non bloquant —
  seule la CROISSANCE bloque).
- **Priorité baseline strict, modules cœur** (cible 0, dans l'ordre) :

  | Module | Occ (2026-09-20) | Pourquoi en premier |
  |---|---:|---|
  | `app/Core/Tenant` | 4 | Quasi propre — verrouiller à 0 (quick win) |
  | `app/Modules/Billing` | 26 | Montants/devises facturés |
  | `app/Core/Auth` | 32 | Sécurité authentification |
  | `app/Modules/Payroll` | 41 | Calculs de bulletins de paie |

- Leviers massifs identifiés par #7655 : ~42 % des occurrences sont des
  `property.notFound`/`method.notFound` Eloquent → campagne d'annotations
  `@property`/`@method` (ide-helper) ; ~10 % purement mécaniques (PHPDoc,
  `?->` superflus).

## 3. Procédure de mise à jour du snapshot

Après chaque campagne de réduction mergée sur main :

1. lancer `dev-hub/tools/check-phpstan-baseline-budget.sh` pour mesurer ;
2. mettre à jour `snapshot.date`, `snapshot.total_occurrences`,
   `by_baseline` et les `occurrences_*` des modules prioritaires dans
   `dev-hub/governance/phpstan-baseline-budget.json` — **jamais à la
   hausse** (le cliquet le bloquerait de toute façon) ;
3. PR dédiée si le budget lui-même change (règle RET-1 : une garde ne se
   modifie que par PR).

## 4. Régénération des baselines

La régénération (workflow manuel `phpstan-baseline.yml`) produit un artefact
candidat à examiner : ne **jamais** remplacer une baseline par un candidat
plus gros — le cliquet #7961 rejettera la PR.
