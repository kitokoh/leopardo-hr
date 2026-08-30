# Protocole de branches par lot — Bounded Context (batch BC)

- **Statut :** actif — protocole par défaut pour tout travail multi-issues sur un même BC
- **Date :** 2026-08-29
- **Portée :** toutes les issues affectées par lot à un agent sur un même bounded context
  (label `BC-XX <NOM>`, registre `dev-hub/governance/bounded-context-registry.json`), **hors
  programme CRM** qui garde son protocole dédié plus strict (`CRM_BRANCH_PROTOCOL.md`, #5746,
  une issue = une branche = une PR) tant qu'il n'a pas été explicitement révisé.
- **Règles source :** `AGENTS.md` (§ Affectation par Bounded Context #5859, § anti-doublon
  #2400, § garde migrations #1962/#5431, § procédure PR et merge), `BRANCH_PROTECTION_REQUIRED.md`.

## Pourquoi ce protocole

`AGENTS.md` affecte déjà le travail par BC (« un seul agent par BC à la fois »), mais le
protocole anti-doublon #2400 imposait quand même **une branche + une PR par issue**. Résultat :
un agent qui traite 8 issues d'un même BC ouvre 8 branches et 8 PRs, chacune déclenchant sa
propre vague de CI — exactement le facteur multiplicateur documenté dans
`docs/infra/02_alignement/CI_SATURATION.md` et gardé sous surveillance par
`merge-quota-guard.yml` (25 merges/jour). Puisqu'un seul agent travaille déjà le BC à la fois
(aucun risque de collision concurrente sur le même BC), rien n'oblige à fragmenter son travail
en plusieurs branches — regrouper les commits d'un même BC sur une branche unique, avec une PR
qui ferme plusieurs issues à la fois, réduit le nombre de branches/PRs/runs CI sans perdre la
traçabilité issue-par-issue (chaque commit référence son issue).

## 1. Prise de tâche (claim)

1. **Le fondateur/chef de projet affecte un lot d'issues d'un même BC** à un agent (ex. « traite
   les issues BC-24 TRAVEL #6015 à #6037 »). L'agent sélectionne uniquement des issues de ce BC.
2. **Vérifier l'absence de branche BC déjà active** avant de commencer :
   ```bash
   gh api "repos/kitokoh/leopardo-hr/branches" --paginate --jq '.[].name' | grep -i "^bc/<code-bc>-"
   gh pr list --state open --json number,title,headRefName
   ```
   Une branche `bc/<code-bc>-*` existante = le BC est déjà en cours de traitement → contribuer
   dessus (même agent reprenant son propre lot) ou s'arrêter (règle « un seul agent par BC à la
   fois », `AGENTS.md`).
3. **Nommage de branche** : `bc/<code-bc-minuscule>-<slug-du-lot>` (ex. `bc/bc24-travel-fondations`,
   `bc/bc16-edu-notifications`). Un seul préfixe `bc/<code-bc>-` actif à la fois par BC — pas de
   variantes parallèles pour le même lot.
4. **Self-assign** sur chaque issue traitée au fil du lot (`gh issue edit <N> --add-assignee @me`)
   — la trace d'assignation reste par issue, seule la branche/PR se regroupe.
5. **Marker branch immédiate** : dès le début du lot, pousser `bc/<code-bc>-<slug>` depuis
   `origin/main` à jour avec un commit vide `claim marker BC-<code>`.

## 2. Vie de la branche

- **Base = `origin/main` à jour** : rebaser dès que `main` a bougé (mode de synchronisation
  canonique, pas de merge de `main` dans la branche) — comme pour toute branche du dépôt.
- **Un commit par issue livrée**, message au format `<type>(<scope>): <résumé> (#<issue>)` —
  chaque commit reste attribuable à son issue même si la PR en ferme plusieurs.
- **Taille de PR** : le plafond CRM (40 fichiers / +2 500 lignes) ne s'applique pas tel quel à
  une PR multi-issues — utiliser un budget proportionnel : **~15 fichiers et ~1 000 lignes par
  issue fermée**, à ajuster au jugement (une PR qui livre 5 petites issues peut légitimement
  dépasser une seule grosse limite fixe). Si le lot grossit trop, le découper en plusieurs PRs
  successives sur la même branche plutôt qu'en branches parallèles.
- **Jobs CI ciblés** : les path filters existants (`tests.yml`, `web-ci.yml`, …) s'appliquent
  sans changement — un push sur `bc/*` ne déclenche que les workflows pertinents aux fichiers
  modifiés.
- **Migrations** (toute PR qui ajoute/renomme une migration) : inchangé —
  ```bash
  bash dev-hub/tools/check-migration-basename-collisions.sh
  bash dev-hub/tools/check-migration-basename-collisions.sh --remote
  ```
  Préfixe `YYYY_MM_DD_0000NN_<issue>_<slug>.php` (règle #5431) — chaque migration garde la
  référence de SON issue même groupée dans une PR multi-issues.

## 3. Checks requis

Inchangés — mêmes 5 checks bloquants que sur toute PR (source : `BRANCH_PROTECTION_REQUIRED.md`) :

| Check | Workflow |
|---|---|
| `Backend Coverage (PHP 8.4 + PostgreSQL 16)` | `coverage-gate.yml` |
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `phpstan-baseline.yml` |
| `Module Structure Validator` | `architecture-check.yml` |
| `Frontend — ESLint + TypeScript` | `web-ci.yml` |
| `actionlint (+ shellcheck)` | `actionlint.yml` |

## 4. Merge — le body de la PR doit fermer TOUTES les issues livrées

- Le body **doit** contenir un mot-clé de fermeture par issue livrée dans ce lot :
  ```
  Closes #6015
  Closes #6019
  Closes #6031
  ```
  (mot-clé explicite par issue — garde #2512 ; le garde `check-pr-closes-issue.sh` exige au
  moins une occurrence, mais toutes les issues du lot doivent être listées pour se fermer au
  merge — sinon elles restent ouvertes à tort, cf. garde de gouvernance `issue-governance-guard.yml`
  qui détecte les « ghost close »).
- **Toutes les issues référencées doivent porter le même label `BC-XX`** que la branche — une
  PR `bc/bc24-travel-*` ne doit fermer que des issues `BC-24 TRAVEL` (garde §6 ci-dessous).
- Jamais d'auto-merge d'une PR rouge ; arrêt si `main` est rouge sur un check requis (règles
  inchangées, cf. `CRM_BRANCH_PROTOCOL.md` §4 pour le détail identique).
- Merger : `gh pr merge <N> --merge --delete-branch`, puis vérifier `gh pr view <N> --json
  state,mergedAt` et l'absence de la branche distante.
- **Lot suivant du même BC** : si le fondateur confie de nouvelles issues du même BC après le
  merge, ouvrir une **nouvelle** branche `bc/<code-bc>-<nouveau-slug>` (ne pas rouvrir l'ancienne,
  déjà supprimée) — même principe qu'une nouvelle vague de travail.

## 5. Reprise après conflit

Identique au protocole général (`AGENTS.md`) : `git fetch origin main && git rebase
origin/main`, résoudre en gardant la version la plus récente de `main` pour tout fichier déjà
modifié par une autre PR mergée, puis `git diff origin/main...HEAD` pour valider que la branche
n'apporte que le périmètre des issues de son lot.

## 6. Capacité CI — vérification automatique

Le garde `dev-hub/tools/check-bc-batch-branch-protocol.sh <owner/repo>` (workflow
`bc-batch-branch-protocol.yml`, quotidien) vérifie :

- une seule branche `bc/<code-bc>-*` active par BC (pas deux lots parallèles sur le même BC) ;
- claim markers orphelins (branche `claim marker BC-<code>` sans PR, âgée) ;
- PRs ouvertes sur `bc/*` sans aucun mot-clé `Closes #N` ;
- **cohérence de label** : toute issue fermée par une PR `bc/<code-bc>-*` porte bien le label
  `BC-<code>` correspondant (évite qu'un lot BC-24 ferme discrètement une issue BC-11) ;
- taille de PR au-delà du budget proportionnel (~15 fichiers / ~1 000 lignes par issue fermée) ;
- collisions de préfixes de migrations inter-PR (réutilise `check-migration-basename-collisions.sh --remote`) ;
- `main` rouge sur un check requis.

Comme les autres gardes de gouvernance (`check-crm-branch-protocol.sh`,
`check-issues-closed-without-merge.sh`), rôle **rapport par défaut** (`exit 0`), `--strict` pour
sortir en erreur.

## 7. Nettoyage

Identique au protocole général : `--delete-branch` au merge, purge des branches locales/orphelines
via `branch-hygiene.yml` (#5506, dry-run par défaut).
