# 04 — Créer des Issues GitHub

> **Quand l'utiliser :** Quand vous avez identifié du travail à faire (bugs, features, améliorations) et que vous voulez les transformer en tickets actionnables dans le backlog.
> **Durée estimée :** Court (5-10 min par issue)
> **Prérequis :** Avoir une idée claire du travail à créer, ou demander à l'agent d'auditer puis de créer

## Instructions

```
Agis en tant que product owner technique pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md pour comprendre les conventions du projet.

Ton objectif est de créer des issues GitHub bien structurées. Pour chaque issue :

1. TITRE : Court et descriptif, préfixé par le type : `fix:`, `feat:`, `docs:`, `ci:`, `refactor:`.
   Exemple : `feat: ajouter l'export CSV des fiches de paie`

2. DESCRIPTION : Utilise ce template :
   ```
   ## Contexte
   [Pourquoi ce travail est nécessaire]

   ## Critères d'acceptation
   - [ ] [Critère mesurable 1]
   - [ ] [Critère mesurable 2]
   - [ ] [Critère mesurable 3]

   ## Fichiers probablement concernés
   - `chemin/vers/fichier1`
   - `chemin/vers/fichier2`

   ## Notes techniques
   [Contraintes, dépendances, points d'attention]
   ```

3. LABELS : Ajoute les labels pertinents :
   - `bug`, `enhancement`, `documentation`, `ci`, `security`
   - `Agent-Ready` si le ticket peut être traité par un agent sans clarification humaine
   - `P0-critical`, `P1-high`, `P2-medium`, `P3-low` pour la priorité

4. COMMANDE : `gh issue create --title "<titre>" --body "<description>" --label "<labels>"`

Si on te donne une liste de tâches en vrac, transforme chaque tâche en issue individuelle bien découpée. Évite les issues fourre-tout qui mélangent plusieurs sujets.

À la fin, affiche un tableau récapitulatif de toutes les issues créées avec leur numéro, titre et labels.
```

## Notes

- Ne jamais créer d'issue sans critères d'acceptation : un agent qui la prendra ne saura pas quand il a fini.
- Préférer des issues petites et ciblées plutôt que des méga-tickets.
- Toujours vérifier qu'une issue similaire n'existe pas déjà : `gh issue list --search "<mots clés>"`.
