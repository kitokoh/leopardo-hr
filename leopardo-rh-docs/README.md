# 🐆 LEOPARDO RH — DOSSIER DE COMPLÉTION
# Conception portée à 100% | Mars 2026

---

## CE QUE CONTIENT CE DOSSIER

Ce dossier complète les 8% manquants identifiés dans l'audit de conception.
Après intégration, la conception est à **100%** et le code peut démarrer.

---

## STRUCTURE

```
leopardo-completion/
│
├── 01_API_CONTRATS_COMPLETS/
│   └── 02_API_CONTRATS_COMPLET.md     ← 70/70 endpoints (remplace l'ancien à 42%)
│
├── 02_MODELES_DART/
│   └── 20_MODELES_DART_COMPLET.md     ← 9 classes Dart prêtes à copier dans Flutter
│
├── 03_MOCK_JSON/
│   ├── README_INTEGRATION_FLUTTER.md  ← Guide d'intégration dans Flutter
│   ├── mock_auth_login.json           ← POST /auth/login
│   ├── mock_auth_me.json              ← GET /auth/me
│   ├── mock_attendance_today_A_not_checked.json  ← GET /attendance/today (data: null)
│   ├── mock_attendance_today_B_checked_in.json   ← GET /attendance/today (avec log)
│   ├── mock_attendance_history.json   ← GET /attendance (30 jours)
│   ├── mock_absences.json             ← GET /absences (4 absences)
│   ├── mock_tasks.json                ← GET /tasks (4 tâches)
│   ├── mock_payroll.json              ← GET /payroll (6 bulletins)
│   └── mock_notifications.json        ← GET /notifications (5 notifs)
│
├── 04_CICD_ET_CONFIG/
│   ├── tests.yml                      ← GitHub Actions CI (backend + Flutter)
│   ├── deploy.yml                     ← GitHub Actions CD (o2switch SSH)
│   ├── .env.example                   ← Variables d'environnement complètes
│   ├── nginx-api.conf                 ← Config Nginx pour l'API
│   └── leopardo-horizon.supervisor.conf ← Config Supervisor pour Horizon
│
├── 05_PROMPTS_EXECUTION/
│   ├── backend/
│   │   ├── CC-01_INIT_LARAVEL.md      ← Pour Claude Code : init Laravel
│   │   ├── CC-02_MODULE_AUTH.md       ← Pour Claude Code : module Auth
│   │   └── CC-03_A_CC-06_MODULES.md   ← Pour Claude Code : modules métier
│   ├── mobile/
│   │   └── JU-01_A_JU-04_FLUTTER.md   ← Pour Jules : tous les écrans Flutter
│   └── frontend/
│       └── CU-01_ET_AGENTS.md         ← Pour Cursor, v0.dev, Manus
│
└── 06_ORCHESTRATION/
    └── ORCHESTRATION_MAITRE.md        ← Source de vérité — état du projet
```

---

## COMMENT UTILISER CE DOSSIER

### 1. Intégrer dans le monorepo
```bash
# Copier les fichiers dans la bonne structure
cp -r leopardo-completion/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md \
      docs/dossierdeConception/02_API_CONTRATS_COMPLET.md

cp -r leopardo-completion/02_MODELES_DART/20_MODELES_DART_COMPLET.md \
      docs/dossierdeConception/20_MODELES_DART_COMPLET.md

cp -r leopardo-completion/03_MOCK_JSON/ \
      mobile/assets/mock/

cp leopardo-completion/04_CICD_ET_CONFIG/tests.yml \
   .github/workflows/tests.yml

cp leopardo-completion/04_CICD_ET_CONFIG/deploy.yml \
   .github/workflows/deploy.yml

cp leopardo-completion/04_CICD_ET_CONFIG/.env.example \
   api/.env.example

cp leopardo-completion/06_ORCHESTRATION/ORCHESTRATION_MAITRE.md \
   ORCHESTRATION_MAITRE.md
```

### 2. Démarrer le code
- Donner `CC-01_INIT_LARAVEL.md` à **Claude Code** → backend
- Donner `JU-01_A_JU-04_FLUTTER.md` à **Jules** → mobile (en parallèle)
- Mettre à jour `ORCHESTRATION_MAITRE.md` après chaque tâche

### 3. Ordre des premières tâches
1. Toi (humain) : Firebase, domaine, comptes stores, secrets GitHub
2. Manus : Vérifier les mock JSON (MA-01)
3. Claude Code : CC-01 (init Laravel)
4. Jules : JU-01 (init Flutter) — EN PARALLÈLE avec Claude Code

---

## NOTES IMPORTANTES

- `02_API_CONTRATS_COMPLET.md` est la **SOURCE DE VÉRITÉ** partagée entre tous les agents
- `ORCHESTRATION_MAITRE.md` doit être mis à jour après chaque tâche accomplie
- Les mock JSON permettent à Jules de développer SANS attendre le backend
- Les fichiers CI/CD sont prêts — il suffit de les copier dans `.github/workflows/`
