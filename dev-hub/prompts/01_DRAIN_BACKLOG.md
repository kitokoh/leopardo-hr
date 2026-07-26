# 01 — Vider le Backlog (Drain All Tickets)

> **Quand l'utiliser :** Quand il y a plusieurs issues GitHub ouvertes et que vous voulez qu'un agent les traite toutes, une par une, sans intervention humaine.
> **Durée estimée :** Long (dépend du nombre de tickets)
> **Prérequis :** Être sur `main` à jour (`git pull`), avoir des issues ouvertes non assignées

## Instructions

```
Agis en tant que développeur autonome pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md pour comprendre les règles du projet.

Ton objectif est de vider le backlog en traitant, de bout en bout et l'un après l'autre, tous les tickets (GitHub Issues) actuellement ouverts et non assignés.

Voici ton cycle de travail strict :

1. CHERCHER : Exécute `gh issue list --state open --json number,title,labels,assignees` pour lister les issues. Sélectionne la première issue ouverte sans assigné. Si la liste est vide, arrête-toi et fais un rapport final de tes accomplissements.

2. PRENDRE : Assigne-toi l'issue avec `gh issue edit <numero> --add-assignee "@me"`.

3. COMPRENDRE : Lis la description complète avec `gh issue view <numero>`. Analyse les critères d'acceptation.

4. CODER : Crée une branche `fix/issue-<numero>` ou `feat/issue-<numero>`, implémente la solution.

5. LIVRER : Commit, push, puis crée une PR. La description de la PR DOIT contenir `Closes #<numero>` pour fermer l'issue automatiquement.

6. VÉRIFIER : Vérifie la CI via `gh pr checks <numero_pr>`. Si c'est rouge, corrige et re-push. Si c'est vert, merge la PR avec `gh pr merge <numero_pr> --merge --delete-branch`.

7. BOUCLER : Retourne à l'étape 1 pour le ticket suivant.

Ne t'arrête pas entre les tickets. Ne demande pas de validation humaine sauf blocage technique majeur. Continue jusqu'à épuisement total des tickets.
```

## Notes

- Si un ticket est trop vague (pas de critères d'acceptation), l'agent peut le marquer avec un commentaire et passer au suivant.
- Les tickets avec le label `Agent-Ready` sont prioritaires.
- Penser à vérifier `git stash list` avant de commencer (règle AGENTS.md).
