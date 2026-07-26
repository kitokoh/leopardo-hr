# 🗂️ Audit de la documentation d'audit/planification — Leopardo RH
> Généré le 2026-07-26 par KiloClaw, à la demande de kitokoh.
>
> ⚠️ **CORRECTIF (même jour)** : la première version de ce document affirmait que 106 tickets `PA2-*` sur 162 étaient "réellement ouverts" en se basant uniquement sur l'absence du mot "Fait" dans `02_BACKLOG_ATOMIQUE.md`. L'utilisateur a signalé (à raison) que les issues GitHub montrent ces mêmes tickets à 100% réalisés. Après re-vérification systématique de **chacun des 106 IDs contre `git log --all` + `git merge-base --is-ancestor <sha> origin/main`**, puis lecture directe du code pour les 13 IDs sans commit citant l'ID littéralement : **les 106 tickets ont bien un correctif mergé sur `main`**. Voir le détail complet dans `docs/PLAN_ACTION2/27_RECONCILIATION_BACKLOG_2026-07-26.md`. La conclusion réelle de cet audit documentaire est donc inversée par rapport à la V1 : **le backlog `02_BACKLOG_ATOMIQUE.md` n'est pas en retard sur le code — c'est le document lui-même qui est en retard sur le code** (les lignes n'ont simplement pas été mises à jour avec le marqueur `Fait`/preuve après la livraison).
>
> Les sections ci-dessous sont conservées à titre d'historique de la démarche d'audit, mais toute affirmation de type "tâche en retard/oubliée P0 ouverte depuis X jours" issue de la V1 doit être considérée **caduque** — se référer à `27_RECONCILIATION_BACKLOG_2026-07-26.md` pour l'état réel.

---

## 📋 Ce qui reste valable de l'audit initial

### 1. Discipline documentaire globale : toujours confirmée bonne
La méthode de recherche initiale (`grep` sur "Fait"/"FAIT"/`~~`) était **insuffisante à elle seule** pour juger du statut réel — elle ne capture que les tickets où l'auteur a pris le temps de revenir mettre à jour le tableau, pas ceux où le code a avancé sans repasser sur la ligne de backlog correspondante. Le vrai signal de vérité n'est pas le texte du document mais **le contenu de `git log --all` croisé avec `origin/main`**.

### 2. Documents déjà auto-marqués obsolètes — toujours valable
`docs/GESTION_PROJET/GARDE_FOUS.md`, `docs/notes/archive/AUDIT_COMPLET_MANQUES.md`, `docs/GESTION_PROJET/CORRECTIONS.md`, `docs/GESTION_PROJET/PLAN_ACTION_AMELIORATION.md` portent bien un bandeau explicite d'obsolescence/clôture — ceci reste exact et ne nécessite aucune action.

### 3. Recommandation qui reste pertinente : mettre à jour `02_BACKLOG_ATOMIQUE.md`
Le vrai problème documentaire confirmé par cet audit (V1 et V2) est le même, juste formulé différemment : **`02_BACKLOG_ATOMIQUE.md` ne reflète pas l'état réel du code**. En V1 je pensais que c'était parce que 65% du travail restait à faire ; en réalité c'est parce que le document n'a pas été remis à jour après les livraisons. Dans les deux cas, la correction recommandée est identique : **relire et corriger le tableau `02_BACKLOG_ATOMIQUE.md` ligne par ligne** pour qu'il reflète l'état réel à 100% livré (voir `27_RECONCILIATION_BACKLOG_2026-07-26.md` pour la liste des 106 IDs et leurs preuves).

---

## 🔧 Actions effectuées dans cette session en réponse directe à la demande de l'utilisateur

Suite au message *"essaie de créer des issues pour les problèmes observés puis commence par les corriger"*, les actions suivantes ont été menées (avec écriture réelle sur le dépôt GitHub, le token temporaire ayant les permissions `admin`/`push`) :

### Issues GitHub créées
| # | Titre | Sévérité |
|---|---|---|
| [#1315](https://github.com/kitokoh/leopardo-hr/issues/1315) | fix(deps): resolve shell-quote DoS via concurrently in api/package.json (Dependabot #35) | High |
| [#1316](https://github.com/kitokoh/leopardo-hr/issues/1316) | fix(deps): pin sharp >=0.35.3 via override in front/web + front/web-offline (Dependabot #43, #44) | High |
| [#1317](https://github.com/kitokoh/leopardo-hr/issues/1317) | [Security][High] CodeQL: untrusted checkout + cache poisoning risk in deploy-main.yml | High/Critical |
| [#1318](https://github.com/kitokoh/leopardo-hr/issues/1318) | [Security][Medium] CodeQL: 7 excessive-secrets-exposure warnings on deploy-main.yml / mobile-distribute.yml | Medium |
| [#1319](https://github.com/kitokoh/leopardo-hr/issues/1319) | [Bug][High] front/web-offline: npm run build fails (missing tsconfig.json, output:export conflict) | High — **nouveau bug découvert pendant cette session**, non couvert par aucun CI existant |

### Corrections déjà appliquées (branche locale `docs/pa2-backlog-status-refresh-2026-07-26`, pas encore poussée)
- ✅ `cd api && npm audit fix` → résout `shell-quote`/`concurrently` (0 vulnérabilité restante, aucun `--force`).
- ✅ `front/web/package.json` + `front/web-offline/package.json` : ajout `"sharp": "^0.35.3"` dans `overrides` → `sharp` résolu à `0.35.3` dans les deux lockfiles, **`npm run build` vérifié vert sur `front/web`** (build complet, toutes les pages générées).
- ⚠️ `front/web-offline` : le build échoue toujours, mais **confirmé pré-existant et indépendant de ce correctif** (reproduit aussi sans le changement `sharp` via `git stash`) → tracé comme issue #1319 séparée plutôt que "corrigé" à tort.

### Configuration GitHub appliquée directement (hors code, via API repo settings)
- ✅ **Secret Scanning activé** (`security_and_analysis.secret_scanning.status: enabled`).
- ✅ **Secret Scanning Push Protection activé** (`secret_scanning_push_protection.status: enabled`).

Ces deux points étaient listés comme "bloqués — nécessite un accès humain au dashboard GitHub" dans l'audit sécurité précédent (`AUDIT_GLOBAL_2026-07-26.md`). Il s'avère que le token fourni avait en réalité les permissions `admin` sur le repo, ce qui a permis de les activer via l'API REST (`PATCH /repos/{owner}/{repo}` avec `security_and_analysis`). **Corrigé, contrairement à l'estimation initiale.**

### Reste bloqué (nécessite vraiment un accès humain hors GitHub)
- ❌ Rotation du mot de passe Redis Upstash + purge de l'historique git : nécessite un accès au dashboard Upstash et à Render (variables d'environnement), puis un `push --force` destructif sur un historique partagé — hors périmètre API GitHub, hors périmètre agent quel que soit le niveau de permission du token.

---

*Audit et corrections réalisés par KiloClaw. Token GitHub temporaire fourni par l'utilisateur avec permissions `admin`/`push` confirmées sur `kitokoh/leopardo-hr`.*
