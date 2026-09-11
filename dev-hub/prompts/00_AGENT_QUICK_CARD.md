# 🚨 Carte de Référence Rapide — Agent Leopardo RH

> **Lis cette carte en 2 minutes avant de toucher au code. C'est le résumé vital de AGENTS.md.**

---

## 📜 CADRE DE PROTOCOLES (P01-P07) — où trouver la règle qui s'applique

| Question | Protocole |
|---|---|
| Peut-on mettre en marché ? | P01 — `docs/PROTOCOLES/P01_VALIDATION_MARCHE.md` |
| Comment un nouvel agent démarre ? | P02 — `P02_ONBOARDING_AGENT.md` |
| Quels mots pour présenter le produit ? | P03 — `P03_VITRINE_PRESENTATION.md` (source : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`) |
| Créer / déléguer une issue, capitaliser ? | P04 — `P04_TACHES_ISSUES_EXPERIENCE.md` |
| Tokens/couleurs, quelle charte ? | P05 — `P05_DESIGN_HARMONISE.md` |
| Sortir un `.exe` / une app macOS ? | P06 — `P06_DESKTOP_DISTRIBUTION.md` |
| Un doute sur dev vs prod ? | P07 — `P07_ARCHITECTURE_DEV_PROD.md` |

État & travaux ouverts : `docs/PROTOCOLES/REGISTRE_PROTOCOLES.md`. Revue mensuelle : dernier jour ouvré.

---

## ❌ INTERDIT (ne fais JAMAIS ça)

| Règle | Pourquoi |
|-------|----------|
| Créer des tickets ou coder un nouveau module sans fichier de spécification `docs/specifications/` | La conception doit être validée avant l'exécution |
| Créer des fichiers dans `docs/PLAN_ACTION2/` ou `docs/PLAN_ACTION/` | Archivés. Le backlog est sur GitHub Issues |
| Lire les dossiers `docs/archive/` pour chercher du travail | Obsolètes. Utilise `gh issue list` |
| Merger une PR avec des checks CI rouges | Main doit rester vert |
| Contourner / désactiver une garde CI « pour avancer » (validation sautée, check local neutralisé) | Toute garde inadaptée se modifie par **PR dédiée**, jamais en local ; contourner = régression silencieuse → ouvrir une issue RETEX immédiate (règle RET-1, triage 2026-09-09) |
| Force push sur `main` | Destructif et irréversible |
| Créer une PR sans `Closes #<numero>` dans la description | L'issue ne se fermera pas automatiquement |
| Travailler sur une issue déjà assignée à quelqu'un | Vérifie les assignés d'abord |
| Utiliser `apiClient.dio.*` dans le mobile (sauf `dio.download`) | Utilise `requestWithRetry` |
| Caster `response.data['data'] as List` dans le mobile | Utilise `extractDataList()` |
| Mettre un `await` avant `runApp()` dans `main.dart` | Cause page noire/grise |
| Utiliser `.withOpacity()` dans Flutter | Déprécié → `.withValues(alpha: X)` |
| Utiliser `dd()` ou `dump()` dans le code PHP | Debug oublié en production |
| Mettre un mot de passe dans `/signup` vitrine | C'est un essai guidé, pas une inscription |
| Utiliser les anciennes cartes `rounded-lg bg-white shadow` dans l'admin | Utilise les tokens `glass-*` |
| Accéder à `FirebaseMessaging.instance` sans `Firebase.initializeApp()` protégé | Crash assuré |

---

## ✅ OBLIGATOIRE (fais TOUJOURS ça)

| Règle | Comment |
|-------|---------|
| Lire cette carte avant de coder | Tu es en train de le faire ✓ |
| Se synchroniser avec main distant | `git fetch origin main; git checkout main; git pull` |
| Vérifier les stashes existants | `git stash list` — ne jamais les perdre |
| S'assigner l'issue avant de coder | `gh issue edit <N> --add-assignee "@me"` |
| Inclure `Closes #<N>` dans la description de PR | Fermeture automatique de l'issue |
| Ouvrir une issue RETEX **immédiate** si une garde a été contournée (RET-1) | Le contournement sans issue = régression silencieuse pour les agents suivants |
| Ajouter une entrée CHANGELOG.md | Pour tout changement de comportement, migration, CI |
| Vérifier la CI après push | `gh pr checks <N>` — doit être vert |
| Utiliser `requestWithRetry` + `extractDataList/extractDataMap` | Pattern mobile obligatoire |
| Garder `StartupGate` comme premier widget | Anti page noire mobile |
| Utiliser les tokens premium `glass-*` dans l'admin | Design system v4.16.250+ |

---

## 🏗️ Architecture en 30 secondes

```
gestionemployer/
├── api/                          # Backend Laravel (PHP 8.4, PostgreSQL multi-tenant)
│   ├── app/Modules/              # Modules métier (HR, Marketing, ...)
│   ├── routes/api.php            # Routes API
│   └── openapi.yaml              # Contrat API
├── front/
│   ├── admin-dashboard/          # Dashboard admin (Vue.js) — tokens glass-*
│   ├── web/                      # Vitrine commerciale (Next.js) — SEO, i18n FR/EN/TR/AR
│   ├── mobile_apps/
│   │   ├── leopardo_core/        # Noyau partagé Flutter
│   │   ├── leopardo_employee/    # App employé
│   │   ├── leopardo_manager/     # App manager
│   │   ├── leopardo_platform_admin/ # App super-admin
│   │   └── leopardo_marketing/   # App marketing
│   └── zkteco-kiosk/             # Kiosk biométrique (HTML offline-first)
├── dev-hub/
│   ├── prompts/                  # 📋 Prompts opérationnels (ce dossier)
│   └── tools/                    # Scripts de validation CI
├── docs/
│   ├── CONTEXT/                  # Contexte rapide pour nouveaux agents
│   ├── archive/                  # ⛔ NE PAS LIRE pour chercher du travail
│   └── PLAN_ACTION2/             # ⛔ Redirige vers GitHub Issues (README.md seulement)
├── AGENTS.md                     # Règles complètes du projet
└── CHANGELOG.md                  # Journal des versions
```

---

## 🔄 Cycle de travail standard

```
1. gh issue list → choisir un ticket non assigné
2. gh issue edit <N> --add-assignee "@me"
3. git checkout -b fix/<N>-<slug>  (nommage canonique : le nom de branche est le verrou anti-doublon, #2400)
4. Coder, tester
5. git commit -m "fix: description (Closes #<N>)"   # type unique : feat|fix|docs|test|ci|refactor|perf|chore (pas de type composé)
6. git push -u origin HEAD
7. gh pr create --title "..." --body "Closes #<N>"
8. gh pr checks <PR> → attendre le vert
9. gh pr merge <PR> --merge --delete-branch
10. git checkout main; git pull → ticket suivant
```

---

## 📋 Index des Prompts Disponibles

| # | Mission rapide |
|---|----------------|
| 01 | Vider tout le backlog |
| 02 | Audit gardien global |
| 03 | Réparer CI rouge |
| 04 | Créer des issues |
| 05 | Audit API backend |
| 06 | Audit admin dashboard |
| 07 | Audit vitrine web |
| 08 | Audit mobile Flutter |
| 09 | Audit CI/CD et docs |
| 10 | Checklist pré-déploiement |
| 11 | Scaffolder un nouveau module |
| 12 | Merger toutes les branches |
| 13 | Garde anti-régression |
| 14 | Onboarding nouvel agent |
| 15 | Audit / refonte design UI |
| 16 | Fin de session — contrat de sortie |
