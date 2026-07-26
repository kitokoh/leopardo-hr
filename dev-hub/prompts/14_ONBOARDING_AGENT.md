# 14 — Onboarding Agent (Première Chose à Faire)

> **Quand l'utiliser :** Quand un nouvel agent arrive sur le projet pour la première fois. C'est le PREMIER prompt à lui donner avant tout travail.
> **Durée estimée :** Court (5-10 min)
> **Prérequis :** Aucun

## Instructions

```
Tu viens d'arriver sur le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Avant de coder quoi que ce soit, fais ces vérifications dans cet ordre exact :

1. LIS LES RÈGLES :
   - Lis dev-hub/prompts/00_AGENT_QUICK_CARD.md (carte de référence rapide, 2 min)
   - Si tu as besoin de détails, consulte AGENTS.md (complet mais long)

2. SYNCHRONISE-TOI :
   - `git fetch origin main`
   - `git checkout main`
   - `git pull`
   - `git stash list` (ne JAMAIS perdre les stashes existants)
   - `git branch` (vérifie qu'il n'y a pas de branches orphelines)

3. COMPRENDS L'ÉTAT DU PROJET :
   - `gh pr list --state open` (PRs en cours)
   - `gh issue list --state open --limit 10` (tickets à faire)
   - `gh run list --branch main --limit 3` (santé CI)

4. IDENTIFIE TON TRAVAIL :
   - Si on t'a donné un numéro de prompt (ex: "exécute le prompt 01"), lis le fichier correspondant dans dev-hub/prompts/ et exécute-le.
   - Si on t'a donné un numéro d'issue, lis-la avec `gh issue view <numero>` et implémente-la.
   - Si on ne t'a rien donné de précis, exécute le prompt 01 (DRAIN_BACKLOG) pour prendre le prochain ticket disponible.

5. AVANT CHAQUE COMMIT, vérifie :
   - [ ] Ta PR contient `Closes #<numero>` dans sa description
   - [ ] Tu n'as pas créé de fichiers dans docs/PLAN_ACTION2/ (interdit)
   - [ ] Tu n'as pas utilisé de patterns interdits (voir carte rapide)
   - [ ] Tu as ajouté une entrée dans CHANGELOG.md si c'est un changement de comportement

Bon courage !
```

## Notes

- Ce prompt est conçu pour qu'un agent soit opérationnel en 5 minutes.
- La carte rapide (00_AGENT_QUICK_CARD.md) est le résumé condensé de AGENTS.md.
