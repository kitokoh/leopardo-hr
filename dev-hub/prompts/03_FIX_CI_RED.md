# 03 — Réparer les CI Rouges

> **Quand l'utiliser :** Quand un ou plusieurs workflows GitHub Actions échouent sur `main` ou sur des PRs ouvertes.
> **Durée estimée :** Court (15-30 min par fix)
> **Prérequis :** Avoir des checks en échec visibles

## Instructions

```
Agis en tant que spécialiste CI/CD pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md (section "Stratégie CI rapide").

Ton objectif est de remettre tous les checks CI au vert. Voici ton cycle :

1. DIAGNOSTIQUER : Exécute `gh run list --branch main --limit 5` pour voir l'état CI de main. Puis pour chaque PR ouverte : `gh pr list --state open` suivi de `gh pr checks <numero>`.

2. ANALYSER : Pour chaque check rouge, lis le log d'erreur : `gh run view <run-id> --log-failed`. Ne lis QUE les erreurs, pas tout le log.

3. CORRIGER : Identifie l'erreur exacte, corrige le fichier concerné, commit et push.

4. VÉRIFIER : Attends que la CI repasse, vérifie avec `gh pr checks`. Si toujours rouge, répète l'étape 2-3.

5. BOUCLER : Passe au check rouge suivant.

Règles importantes :
- Ne jamais désactiver un check pour le faire passer au vert
- Ne jamais ignorer un test en échec avec @skip ou @ignore
- Préférer `gh run view --log-failed` plutôt que de deviner l'erreur
- Si un check échoue à cause d'un flaky test (test instable), le signaler dans une issue dédiée
- Chaque PR de fix doit contenir `Closes #<issue>` si une issue existe pour ce problème CI

Continue jusqu'à ce que tous les checks soient verts sur main et sur toutes les PRs ouvertes.
```

## Notes

- La stratégie AGENTS.md privilégie GitHub Actions comme source de vérité plutôt que les checks locaux Windows.
- `npx tsc --strict --noEmit` est acceptable localement pour vérifier rapidement une erreur TypeScript.
