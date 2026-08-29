# Protocole de branches & capacité CI — Programme CRM (issue #5746)

- **Statut :** actif — gate obligatoire avant tout travail parallèle CRM
- **Date :** 2026-08-28
- **Portée :** toutes les issues du programme CRM client (#5705 → #5731) et les issues de préparation (#5735 → #5746)
- **Règles source :** `AGENTS.md` (§ anti-doublon #2400, § garde migrations #1962/#5431, § procédure PR et merge), `BRANCH_PROTECTION_REQUIRED.md`

> Ce protocole est la version **CRM-verrouillée** des règles déjà en vigueur sur
> le dépôt. Il s'applique à tout agent (humain ou IA) qui touche une issue du
> programme CRM. Une issue = une branche = une PR. Toute déviation doit être
> visible (commentaire sur l'issue) et justifiée.

---

## 1. Prise de tâche (claim)

1. **Vérifier l'absence de doublon AVANT de commencer** — le nom de branche EST
   le lock (protocole #2400) :
   ```bash
   gh api "repos/kitokoh/leopardo-hr/branches" --paginate --jq '.[].name' | grep -i "<numero_issue>"
   gh pr list --state open --json number,title,headRefName
   ```
   Une branche `fix/<issue>-*` ou `feat/<issue>-*` existante = issue prise →
   contribuer dessus ou s'arrêter. **Jamais deux branches pour la même issue.**
2. **Self-assign** : `gh issue edit <N> --add-assignee kitokoh`
3. **Commentaire de claim** sur l'issue : « issue prise — marker branch
   `fix/<N>-<slug>` » (les autres agents voient le lock).
4. **Marker branch immédiate** : branche `fix/<N>-<slug>` (ou `feat/` pour une
   fonctionnalité) depuis `origin/main` à jour, avec un commit vide
   `claim marker #N`. Le premier arrivé conserve sa branche.

> Règle de nommage : **un seul** `fix/<issue>-*` par issue. Pas de suffixes
> multiples (`fix/2333-a`, `fix/2333-b`…). Les PRs dupliquées sur une même
> issue sont fermées avec renvoi vers la PR canonique.

## 2. Vie de la branche

- **Base = `origin/main` à jour** : `git fetch origin main` avant chaque
  session de travail ; rebaser dès que `main` a bougé (le rebase est le mode
  de synchronisation canonique — pas de merge de `main` dans la branche).
- **Taille de PR** : rester sous **40 fichiers** et **+2 500 lignes** ; au-delà,
  découper en lots par issue (ou signaler sur l'issue). Une PR énorme est un
  symptôme de périmètre non borné → à découper AVANT la review.
- **Jobs CI ciblés** : n'ajouter que les changements nécessaires à l'issue ;
  les path filters des workflows (`tests.yml`, `web-ci.yml`, …) limitent les
  runs inutiles — ne pas forcer de runs hors périmètre.
- **Migrations** (toute PR qui ajoute/renomme une migration) :
  ```bash
  bash dev-hub/tools/check-migration-basename-collisions.sh
  bash dev-hub/tools/check-migration-basename-collisions.sh --remote   # inter-PR
  ```
  Préfixe de séquence réservé : `YYYY_MM_DD_0000NN_<issue>_<slug>.php`
  (règle #5431 : le numéro d'issue DANS le nom rend la collision de préfixe
  structurellement impossible). Un préfixe déjà pris = renuméroter `0000NN`
  en gardant l'ordre chronologique.
- **Avant push** : `git diff --check` (zéro espace blanc parasite).

## 3. Checks requis (identifiés avant implémentation)

Checks **bloquants** sur toute PR → `main` (source : `BRANCH_PROTECTION_REQUIRED.md`) :

| Check | Workflow |
|---|---|
| `Backend Coverage (PHP 8.4 + PostgreSQL 16)` | `coverage-gate.yml` |
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `phpstan-baseline.yml` |
| `Module Structure Validator` | `architecture-check.yml` |
| `Frontend — ESLint + TypeScript` | `web-ci.yml` |
| `actionlint (+ shellcheck)` | `actionlint.yml` |

Signaux non bloquants (ne pas attendre leur vert pour merger) : `Vercel`,
`Workers Builds: gestionemploye`, `Ratio fix/feat` (mode warning), quota
`merge-quota-guard.yml`.

## 4. Merge

- **Jamais d'auto-merge d'une PR rouge** : attendre que TOUS les checks requis
  soient verts (`gh pr checks <N>`).
- **Arrêt si `main` rouge** : si un check requis échoue sur `main` (pas sur la
  PR), ne pas merger — signaler et corriger main d'abord (une PR verte sur un
  main rouge hérite du rouge au merge).
- Le body de la PR **doit** contenir `Closes #N` (mot-clé explicite, pas une
  mention entre parenthèses — garde #2512).
- Merger : `gh pr merge <N> --merge --delete-branch` puis vérifier
  `gh pr view <N> --json state,mergedAt` et l'absence de la branche distante.

## 5. Reprise après conflit

1. `git fetch origin main && git rebase origin/main` sur la branche.
2. Résoudre les conflits dans l'ordre du rebase ; pour un fichier CRM
   touché par une autre PR, **garder la version la plus récente de `main`**
   puis ré-appliquer la modification de l'issue si elle reste pertinente
   (règle anti-régression 2026-08-15 : une branche âgée peut écraser des
   fixes plus récents — vérifier `git log --oneline -3 origin/main -- <fichier>`).
3. `git diff origin/main...HEAD` pour valider que la branche n'apporte QUE
   son périmètre.
4. Re-pousser, attendre les checks, merger.

## 6. Capacité CI agents — vérification automatique

Le garde `dev-hub/tools/check-crm-branch-protocol.sh <owner/repo>` vérifie sur
le dépôt (rôle rapport, workflow `crm-branch-protocol.yml` quotidien) :

- doublons de branches par issue (2+ branches pour la même issue, dont au
  moins une sans PR ouverte) ;
- claim markers orphelins (branche `claim marker #N` sans PR, âgée > 2 jours) ;
- PRs ouvertes sans `Closes #N` / `Fixes #N` dans le body (#2512) ;
- PRs dépassant la taille max (40 fichiers / +2 500 lignes) ;
- collisions de préfixes de migrations inter-PR (#1962, mode `--remote`) ;
- `main` rouge : dernier SHA de `main` avec un check requis en échec.

En `--strict`, le garde sort en erreur sur les violations (usage : review de
protocole, runbook de crise). Par défaut il publie un rapport exploitable.

## 7. Nettoyage

- Après merge : `--delete-branch` (la branche distante disparaît), puis
  nettoyer les branches locales non `main` (`git branch -D <branche>` après
  vérification `git stash list`).
- Les branches orphelines (claim markers > 2 jours, PRs fermées) sont purgées
  par `branch-hygiene.yml` (#5506, dry-run par défaut).
