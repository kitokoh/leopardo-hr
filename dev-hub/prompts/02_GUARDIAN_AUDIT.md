# 02 — Audit Gardien Global

> **Quand l'utiliser :** Pour faire un état des lieux complet du projet et détecter les dérives, incohérences ou régressions avant qu'elles ne s'accumulent.
> **Durée estimée :** Moyen (30-60 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant que gardien du projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md et CHANGELOG.md pour comprendre l'état actuel.

Fais un audit gardien complet en vérifiant ces 7 axes :

1. SANTÉ GIT : `git status`, `git stash list`, branches locales orphelines, divergence avec origin/main.

2. PRs OUVERTES : `gh pr list --state open` — pour chaque PR, vérifie les checks CI (`gh pr checks <numero>`). Signale les PRs en échec ou abandonnées.

3. ISSUES EN DÉRIVE : `gh issue list --state open --limit 50` — identifie les issues sans assigné, sans label, ou ouvertes depuis plus de 7 jours sans activité.

4. CI/CD : `gh run list --branch main --limit 5` — vérifie que le dernier run sur main est vert. Si rouge, identifie la cause.

5. COHÉRENCE ARCHITECTURE : Vérifie que les conventions sont respectées (pas de fichiers dans docs/PLAN_ACTION2/ hors README.md, pas de hardcoded URLs, pas de TODO critiques non ticketés).

6. SÉCURITÉ : Vérifie Dependabot alerts, `composer audit` côté API, `npm audit` côté web.

7. DOCUMENTATION : Vérifie que AGENTS.md, CHANGELOG.md et dev-hub/prompts/README.md sont à jour.

Produis un rapport structuré avec :
- 🟢 Ce qui va bien
- 🟡 Points d'attention
- 🔴 Problèmes critiques à corriger immédiatement

Pour chaque problème 🔴, crée automatiquement une issue GitHub avec un titre clair et des critères d'acceptation.
```

## Notes

- Idéal à exécuter une fois par semaine ou avant chaque déploiement.
- Le rapport doit rester factuel, pas de prose inutile.
