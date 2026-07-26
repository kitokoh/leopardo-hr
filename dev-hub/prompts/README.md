# 📋 Prompts Opérationnels — Leopardo RH

Ce dossier contient des **prompts prêts à l'emploi** pour piloter les agents IA sur le projet.

> **Règle d'or :** Chaque fichier = une mission claire et actionnable. Ce n'est PAS un dossier de planification. Ne jamais y ajouter de documents d'audit, de réflexion ou de compte-rendu.

## Comment utiliser

1. Choisissez le prompt par son **numéro** (ex: "exécute le prompt 01")
2. Copiez/collez le contenu de la section **Instructions** à l'agent
3. L'agent fait le travail de bout en bout

## Index des Prompts

| # | Fichier | Mission | Durée |
|---|---------|---------|-------|
| 01 | `01_DRAIN_BACKLOG.md` | Vider tous les tickets GitHub un par un | Long |
| 02 | `02_GUARDIAN_AUDIT.md` | Audit gardien global du projet | Moyen |
| 03 | `03_FIX_CI_RED.md` | Réparer les checks CI en échec | Court |
| 04 | `04_CREATE_ISSUES.md` | Créer de nouvelles issues GitHub | Court |
| 05 | `05_AUDIT_API.md` | Audit complet du backend Laravel | Moyen |
| 06 | `06_AUDIT_ADMIN.md` | Audit du dashboard admin (Vue.js) | Moyen |
| 07 | `07_AUDIT_WEB.md` | Audit de la vitrine Next.js | Moyen |
| 08 | `08_AUDIT_MOBILE.md` | Audit des apps Flutter (employee/manager/admin) | Long |
| 09 | `09_AUDIT_CI_DOCS.md` | Audit CI/CD, workflows et documentation | Moyen |
| 10 | `10_DEPLOY_CHECKLIST.md` | Vérification pré-déploiement production | Court |
| 11 | `11_NEW_MODULE_SCAFFOLD.md` | Créer un nouveau module métier complet | Long |

## Règles de contribution

- **Numérotation :** Toujours préfixer par un numéro à 2 chiffres (`12_NOM.md`)
- **Format :** Respecter le template (Quand l'utiliser / Prérequis / Instructions / Notes)
- **Pas de prose :** Un prompt doit être exécutable, pas explicatif
- **Mettre à jour cet index** à chaque ajout
