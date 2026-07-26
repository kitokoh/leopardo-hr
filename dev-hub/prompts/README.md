# 📋 Prompts Opérationnels — Leopardo RH

Ce dossier contient des **prompts prêts à l'emploi** pour piloter les agents IA sur le projet.

> **Règle d'or :** Chaque fichier = une mission claire et actionnable. Ce n'est PAS un dossier de planification. Ne jamais y ajouter de documents d'audit, de réflexion ou de compte-rendu.

## 🚀 Démarrage rapide

1. **Nouvel agent ?** → Commence par lire `00_AGENT_QUICK_CARD.md` (2 min)
2. **On t'a donné un numéro ?** → Ouvre le fichier correspondant et exécute les instructions
3. **Pas de consigne précise ?** → Exécute le prompt `01` pour vider le backlog

## Index des Prompts

| # | Fichier | Mission | Durée |
|---|---------|---------|-------|
| 00 | `00_AGENT_QUICK_CARD.md` | 🚨 Carte de référence rapide (LIRE EN PREMIER) | 2 min |
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
| 12 | `12_MERGE_ALL_TO_MAIN.md` | Merger toutes les branches, garder main vert | Moyen |
| 13 | `13_REGRESSION_GUARD.md` | Traquer les régressions et patterns interdits | Moyen |
| 14 | `14_ONBOARDING_AGENT.md` | Onboarding d'un nouvel agent (premier prompt) | Court |

## Comment utiliser

**Option A — Donner le numéro :**
> "Exécute le prompt 01"

**Option B — Donner le fichier :**
> "Lis dev-hub/prompts/01_DRAIN_BACKLOG.md et exécute-le"

**Option C — Enchaîner :**
> "Exécute le prompt 14 (onboarding), puis le prompt 01 (backlog)"

## Règles de contribution

- **Numérotation :** Toujours préfixer par un numéro à 2 chiffres (`15_NOM.md`)
- **Format :** Respecter le template (Quand l'utiliser / Prérequis / Instructions / Notes)
- **Pas de prose :** Un prompt doit être exécutable, pas explicatif
- **Mettre à jour cet index** à chaque ajout
- **Mettre à jour la carte rapide** (`00_AGENT_QUICK_CARD.md`) si un nouveau prompt est ajouté
