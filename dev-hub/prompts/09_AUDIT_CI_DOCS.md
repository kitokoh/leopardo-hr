# 09 — Audit CI/CD et Documentation

> **Quand l'utiliser :** Pour auditer les workflows GitHub Actions, la documentation technique, les scripts de validation, et la cohérence globale du projet.
> **Durée estimée :** Moyen (30 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant qu'auditeur DevOps et documentation pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md (section "Stratégie CI rapide" et workflows).

Audite CI/CD et documentation sur ces 8 axes :

1. WORKFLOWS : Liste tous les fichiers dans .github/workflows/. Pour chaque workflow :
   - Quel est son trigger (push, PR, schedule) ?
   - Quels paths filtres utilise-t-il ?
   - Est-il actif et utile ?
   - Son dernier run est-il vert ? (`gh run list --workflow <fichier> --limit 1`)
   Identifie les workflows redondants, obsolètes ou trop larges.

2. FILTRES PATHS : Vérifie que web-ci.yml ne se déclenche QUE pour admin-dashboard/**, que web-marketing-ci.yml ne se déclenche QUE pour web/**. Pas de CI inutile sur des PRs backend quand seul un doc change.

3. SCRIPTS DE VALIDATION : Liste dev-hub/tools/. Vérifie que chaque script est documenté, fonctionnel, et référencé quelque part (AGENTS.md ou un workflow).

4. GITHUB ACTIONS TEMPLATES : Vérifie .github/ISSUE_TEMPLATE/ et .github/PULL_REQUEST_TEMPLATE.md. Le template PR doit inclure un champ pour `Closes #`.

5. DOCUMENTATION TECHNIQUE : Vérifie docs/CONTEXT/, docs/GUIDES/, docs/GESTION_PROJET/. Identifie les docs obsolètes, les liens cassés internes, les informations contradictoires avec AGENTS.md.

6. CHANGELOG : Vérifie que CHANGELOG.md est à jour avec les derniers merges. Chaque entrée doit avoir une date et un numéro de version.

7. AGENTS.MD : Vérifie la cohérence interne d'AGENTS.md. Pas de sections contradictoires, pas de références à des fichiers supprimés, pas d'instructions obsolètes.

8. DOSSIER ARCHIVE : Vérifie que docs/archive/ contient bien les anciens plans et que docs/PLAN_ACTION2/ ne contient QUE le README.md de redirection. Aucun agent ne doit être tenté de lire les archives pour trouver du travail.

Produis un rapport avec 🔴🟡🟢 et crée des issues pour les 🔴.
```

## Notes

- Les workflows web (admin + vitrine) doivent rester séparés avec des filtres paths stricts.
- Le dépôt a deux surfaces frontend distinctes : admin-dashboard/ (Vue.js) et web/ (Next.js).
- Les anciens dossiers PLAN_ACTION/ et PLAN_ACTION2/ sont archivés. La gestion se fait via GitHub Issues.
