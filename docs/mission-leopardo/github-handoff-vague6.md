# Handoff distant — vague 6 (héros premium)

État local **prêt et vérifié**. Rien n'a été poussé : cet environnement ne
contient **aucun identifiant GitHub en écriture**.

## Constat, vérifié dans cette session

```
$ env | grep -iE 'GH_TOKEN|GITHUB_TOKEN|GH_PAT'   → (aucun)
$ git config --get credential.helper               → (aucun)
$ which gh                                          → gh absent
$ get_credential_profiles                           → 0 profil
$ GIT_TERMINAL_PROMPT=0 git push --dry-run origin <branche>
  fatal: could not read Username for 'https://github.com': terminal prompts disabled
```

Aucun secret n'a été lu, écrit, affiché ni commité — il n'y en avait aucun.

## Prérequis manquant

Un **PAT GitHub limité au dépôt `kitokoh/leopardo-hr`**, portée `repo`
(ou `contents:write` + `issues:write`). À fournir en variable d'environnement
ou via un credential helper **éphémère** ; ne jamais l'écrire dans le dépôt.

## État de la branche

```
Branche   : feat/vitrine-vague6-premium-ui
Base      : origin/main = 26b5c6b3c (fusionnée dans la branche, sans conflit)
Commits   : 3 (feat, merge, docs)
Arbre     : propre
main      : intacte, jamais touchée
```

```
66ddf4f41  feat(vitrine): vague 6 — direction artistique premium du héros stage
17afc7fa6  Merge remote-tracking branch 'origin/main' into feat/vitrine-vague6-premium-ui
24933c896  docs(mission): trace de gouvernance vague 6 (CHANGELOG + registre de scénarios)
```

## Vérifications déjà exécutées (à ne pas rejouer inutilement)

- `tsc --noEmit` → 0 erreur
- `eslint src --max-warnings 0` → 0 warning
- `jest src/modules/vitrine` → 56 suites / 658 tests, 0 échec
- gardes `check:french-copy`, `check:mojibake`, `check:orphans` → OK
- `check-i18n-diff origin/main HEAD` → aucune chaîne codée en dur nouvelle
- Chromium 1440×900 / 834×1112 / 390×844 → 1 CTA, 0 débordement, fiche
  centrée et fermable (Échap, X)
- `next build` → **exit 137**, cgroup plafonné à 2 Gio (`memory.peak == memory.max`)

## Commandes exactes à exécuter

```bash
# 0. Jeton en variable d'environnement, jamais sur la ligne de commande
export GH_TOKEN='<PAT limité à kitokoh/leopardo-hr>'

cd /path/to/leopardo-hr
git fetch origin --prune

# 1. Vérifier l'identité et les droits d'écriture AVANT toute écriture
curl -sS -H "Authorization: Bearer $GH_TOKEN" https://api.github.com/user \
  | jq -r '.login'
curl -sS -H "Authorization: Bearer $GH_TOKEN" \
  https://api.github.com/repos/kitokoh/leopardo-hr \
  | jq -r '.permissions'

# 2. Contrôler la collaboration (aucun chevauchement attendu sur la vitrine)
curl -sS -H "Authorization: Bearer $GH_TOKEN" \
  "https://api.github.com/repos/kitokoh/leopardo-hr/pulls?state=open&per_page=50" \
  | jq -r '.[] | "\(.number) \(.title)"'
curl -sS -H "Authorization: Bearer $GH_TOKEN" \
  "https://api.github.com/repos/kitokoh/leopardo-hr/issues?state=open&per_page=50" \
  | jq -r '.[] | "\(.number) \(.title)"'

# 3. Se resynchroniser si main a encore avancé
git merge origin/main --no-edit
cd front/web && npx tsc --noEmit && npm run lint \
  && npx jest src/modules/vitrine --maxWorkers=2

# 4. Pousser
git push -u origin feat/vitrine-vague6-premium-ui

# 5. Ouvrir la Pull Request (corps déjà rédigé)
gh pr create \
  --repo kitokoh/leopardo-hr \
  --base main \
  --head feat/vitrine-vague6-premium-ui \
  --title "feat(vitrine): vague 6 — direction artistique premium du héros stage" \
  --body-file docs/mission-leopardo/pull-request-vague6.md

# 6. Suivre la CI
gh pr checks <NUMERO> --repo kitokoh/leopardo-hr --watch

# 7. Fusionner UNIQUEMENT si les 4 contrôles requis sont verts
gh pr merge <NUMERO> --repo kitokoh/leopardo-hr --squash --delete-branch

# 8. Vérifier main propre et verte
git checkout main && git pull --ff-only
git status --short && git log --oneline -3

# 9. Nettoyage du jeton
unset GH_TOKEN
```

## Pièges connus de ce dépôt (rencontrés et documentés aux vagues 5)

- **`Check PR/issue governance`** : la PR doit référencer une issue **fermée**.
  Créer une issue de suivi et ajouter `Closes #<n>` au corps, sinon le check
  échoue sans autre explication.
- **`Governance Gates`** : toute surface utilisateur web modifiée exige une
  entrée dans `CHANGELOG.md` **et** dans
  `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md`. Les deux sont déjà dans ce
  lot ; si `main` avance sur ces fichiers, **conserver les deux entrées** —
  jamais résoudre en écrasant.
- **`validate-and-sync`** : ne jamais éditer un catalogue i18n généré. Ce lot
  n'ajoute aucune clé, donc aucun `sync` n'est nécessaire ; si une clé devait
  être ajoutée, éditer `shared/i18n/locales/*.json` puis lancer
  `sync-web.js` / `sync-mobile.js` / `sync-backend.js`.
- **Fichiers générés en conflit** (`shared/i18n/versions/versions.json`) :
  régénérer par les scripts, jamais arbitrer à la main.
- **Jobs préexistants en échec** : `Analyze leopardo_*`, `Desktop verify *`,
  `Distribute * to Firebase` (conflit `sentry_flutter ^9`, issue #8599). Ils
  échouent déjà sur `main` — ne pas les attribuer à cette PR.
- **`next build`** : ne pas conclure à un échec de code si `exit 137` ; vérifier
  `/sys/fs/cgroup/memory.max` avant de relancer.
