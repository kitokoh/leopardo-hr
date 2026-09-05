# 12 — Merger Toutes les Branches au Main

> **Quand l'utiliser :** Quand il y a des PRs ouvertes à fusionner dans main. L'agent résout les conflits, vérifie la CI, merge proprement, et nettoie les branches mortes.
> **Durée estimée :** Moyen (dépend du nombre de PRs et conflits)
> **Prérequis :** Avoir des PRs ouvertes
## Instructions
```
Agis en tant que release manager pour le projet Leopardo RH situé dans leopardo-hr (racine du dépôt, dossier courant).
Commence par lire AGENTS.md. Ton objectif est de fusionner proprement TOUTES les PRs ouvertes dans main, en gardant main vert et stable.
ÉTAPE 1 — INVENTAIRE
- Exécute `gh pr list --state open --json number,title,headRefName,mergeable` pour lister les PRs.
- Pour chaque PR, vérifie les checks CI : `gh pr checks <numero>`.
- Classe les PRs par ordre de merge (les plus simples/indépendantes d'abord, celles avec conflits en dernier).
ÉTAPE 2 — MERGE (pour chaque PR, dans l'ordre)
a) Vérifie que les checks CI sont verts. Si rouges :
   - Lis le log d'erreur : `gh run view <run-id> --log-failed`
   - Corrige l'erreur, push, attends que la CI repasse
   - Ne merge JAMAIS une PR avec des checks rouges
b) Si la PR a des conflits de merge :
   - `gh pr checkout <numero>`
   - `git fetch origin main; git rebase origin/main` (mode canonique des protocoles CRM/BC)
   - Résous les conflits manuellement en gardant les deux changements quand c'est possible
   - `git add -A; git rebase --continue`
   - `git push --force-with-lease origin HEAD`
   - Attends que la CI repasse au vert
c) Merge la PR : `gh pr merge <numero> --merge --delete-branch`
   - **Jamais `--admin`** : `main` est protégée (`enforce_admins=true`, garde #2011) ;
     un bypass désactiverait la protection et contredit AGENTS.md (incident #2011, 2026-08-14).
d) Après chaque merge, mets à jour ton main local :
   - `git checkout main; git pull`
ÉTAPE 3 — VÉRIFICATION POST-MERGE
Après avoir mergé TOUTES les PRs :
- `gh run list --branch main --limit 3` — vérifie que main est vert
- Si main est rouge après les merges, c'est ta responsabilité de le réparer IMMÉDIATEMENT
- `git branch | ForEach-Object { if ($_ -notmatch 'main') { git branch -D $_.Trim() } }` — nettoie les branches locales
- `gh pr list --state open` — doit retourner une liste vide
ÉTAPE 4 — RAPPORT
Produis un rapport final :
- Nombre de PRs mergées
- Conflits résolus (lesquels, comment)
- État final de main (vert/rouge)
- Branches nettoyées
RÈGLES STRICTES :
- Ne JAMAIS merger une PR avec des checks requis en échec
- Ne JAMAIS désactiver un check pour forcer un merge
- Ne JAMAIS utiliser `--admin` ni bypasser la protection (#2011)
- Ne JAMAIS faire un force push sur main
- Toujours merger dans l'ordre : docs → ci → fix → feat (les moins risquées d'abord)
- Si un merge casse main, reverter immédiatement avec `git revert`
## Notes
- L'ordre de merge est important : les PRs docs/ci ont moins de risque de conflit que les PRs feat.
- Sur PowerShell, utiliser `;` au lieu de `&&` pour chaîner les commandes.
- `main` est protégée (`enforce_admins=true`) : **aucun bypass n'est possible ni souhaité** — les merges passent par les checks requis (5) ; ne jamais merger une PR rouge.
Agis en tant que release manager pour le projet Leopardo RH situé dans leopardo-hr (racine du dépôt git).
