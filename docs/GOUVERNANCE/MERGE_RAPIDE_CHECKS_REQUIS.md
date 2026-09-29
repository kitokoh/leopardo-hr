# Merge rapide — vérifier les 4 checks requis EN LOCAL avant de merger

> RETEX du 2026-09-29 (drain de 19 PR en ~90 min) : la file CI met des heures à rendre un
> verdict sur les 4 checks requis, alors que **chacun se rejoue en local en 1 à 10 minutes**.
> Merger peut donc se faire en quelques minutes au lieu de plusieurs heures… à condition de
> rejouer **exactement** les commandes du check requis — c'est là que se joue la différence
> entre « j'ai vérifié » et « j'ai vérifié la bonne chose » (cf. §4, piège n°1).

## 1. Les 4 checks requis (`.github/BRANCH_PROTECTION_REQUIRED.md`)

| Check | Workflow | Commande locale EXACTE |
|---|---|---|
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `architecture-check.yml` (job `phpstan-strict`) | `cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon --memory-limit=3G --no-progress` |
| `Module Structure Validator` | `architecture-check.yml` (job `module-structure-check`) | 7 gardes — voir §2 |
| `Frontend — ESLint + TypeScript` | `architecture-check.yml` | `npx tsc --noEmit` puis `npx eslint src --ext .ts,.tsx --max-warnings 0` (dans le front concerné, `npm ci` préalable) |
| `actionlint (+ shellcheck)` | `actionlint.yml` | `actionlint` (intègre shellcheck sur les `run:`) sur les `.github/workflows/**` modifiés |

## 2. `Module Structure Validator` — les 7 commandes

```bash
bash dev-hub/tools/check-bounded-context-registry.sh
dev-hub/tools/check-module-isolation.sh
dev-hub/tools/check-bounded-context-dependencies.sh .
dev-hub/tools/check-crm-boundary-imports.sh
python3 dev-hub/tools/check-event-catalogue-test.py
python3 dev-hub/tools/check-event-catalogue.py .
dev-hub/tools/check-unrouted-controllers.sh api
```

Gardes complémentaires utiles (non requises, mais rouges visibles souvent causées par une PR) :

```bash
bash dev-hub/tools/check-layer-purity.sh api           # facades hors Interfaces/Infrastructure
bash dev-hub/tools/check-env-example-parity.sh         # clés env() de config/ vs .env.example
bash dev-hub/tools/check-actions-convention.sh api     # 1 Action = 1 execute()
bash dev-hub/tools/check-migration-basename-collisions.sh
```

> ⚠️ Plusieurs gardes sont **sensibles aux arguments** : `check-bounded-context-dependencies.sh`
> prend `.` (et non `api`), `check-migration-basename-collisions.sh` ne prend **aucun** argument
> (avec `api` il scanne `vendor/` et échoue à tort). Reprendre les invocations ci-dessus.

## 3. Runbook de merge rapide (fenêtre contrôlée, protocole §4)

```bash
# 0. Sauvegarder la protection de main (OBLIGATOIRE, avant toute fenêtre)
curl -s -H "Authorization: Bearer $TOKEN" "$API/branches/main/protection" > /tmp/protection.json

# 1. Synchroniser la branche (le « dirty » GitHub est souvent un cache périmé)
git fetch origin '+refs/heads/*:refs/remotes/origin/*' --prune
git checkout -B <branche> origin/<branche>
git merge origin/main --no-edit          # CHANGELOG.md s'auto-merge dans la quasi-totalité des cas
# → en cas de conflit : résoudre fichier par fichier (jamais -X ours/theirs)

# 2. Vérifier EN LOCAL (§1 et §2) + phpstan ci-config sur les fichiers changés
bash dev-hub/tools/verify-required-checks.sh <branche-ou-main>

# 3. Pousser, puis merger dans une fenêtre la plus courte possible
git push origin HEAD:refs/heads/<branche>
#   PUT /branches/main/protection  → required_status_checks.contexts=[], enforce_admins=false
#   PUT /pulls/<n>/merge {"merge_method":"merge"}
#   PUT /branches/main/protection  → restauration EXACTE depuis /tmp/protection.json (finally)
#   GET /branches/main/protection  → vérifier contexts + enforce_admins AVANT de continuer
```

**Règle d'or** : on ne merge jamais avec un check requis **rouge**. La fenêtre ne sert qu'à ne pas
attendre un verdict vert *déjà démontré en local* — pas à contourner un rouge.

## 4. Pièges mesurés (à ne pas répéter)

1. **`api/phpstan.ci.neon` n'est PAS la config de la CI** : le job `backend-quality` **régénère**
   ce fichier à la volée avec `phpstan.neon + phpstan-strict-baseline.neon + phpstan-modules-baseline.neon`
   et `level: 8`. Analyser avec la version versionnée du dépôt donne des **faux positifs** (dette
   préexistante non baselinée) — et, symétriquement, passer `ci.neon` ne prouve **pas** le check
   requis, qui tourne sur `phpstan-strict.neon`. **Rejouer les deux.**
2. **`strict: false` sur la protection réelle** ⇒ une PR peut être testée contre un `main` plus
   ancien. Deux PR vertes séparément peuvent donc être rouges **une fois combinées** (constat
   #8303/#8304 : 24 erreurs PHPStan Strict apparues après le drain). ⇒ Toujours re-merger `main`
   dans la branche **juste avant** le merge, et re-vérifier.
3. **Allowlists sensibles aux numéros de ligne** : ajouter un `use` décale les entrées de
   `layer-purity-allowlist.txt` / `module-isolation-allowlist.txt` → garde rouge alors que rien
   n'a empiré. Les fichiers doivent rester **triés** (`comm` exige un ordre lexicographique).
4. **`mergeable_state: dirty`** est souvent un **cache périmé** : `git merge origin/main` en local
   prouve l'absence de conflit, puis le push resynchronise l'état GitHub.
5. **Brouillon GitHub** : le REST `PATCH draft:false` est sans effet ; utiliser GraphQL
   `markPullRequestReadyForReview` (il échoue en `FORBIDDEN` si la branche a été supprimée).
6. **Après chaque merge dans `main`**, rejouer les gardes d'hygiène (§2) sur `main` : une clé
   `env()` ajoutée dans `config/` sans `.env.example` (constat #8297) rougit `Hygiene Guards`.

## 5. Quand NE PAS utiliser la fenêtre

- Un check requis est **rouge** (le corriger, jamais le contourner).
- Le diff touche `front/**` ou `.github/workflows/**` et le lint/actionlint n'a pas été rejoué.
- La PR touche une zone dont un autre agent a la main (collision) — ou une issue `blocked`.
