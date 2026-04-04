# LEOPARDO RH — Documentation Technique
## Source de verite conception | Version 4.1.1 | Avril 2026
## Reference programme: v4.1.1

---

## STRUCTURE

```
docs/
├── dossierdeConception/   ← Specs fonctionnelles et techniques
└── PROMPTS_EXECUTION/     ← Prompts pour agents IA
```

---

## SOURCES DE VÉRITÉ ABSOLUES

| Quoi | Fichier |
|------|---------|
| API (82 endpoints) | `dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` |
| ERD v2.0 | `dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` |
| SQL complet | `dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` |
| Seeders | `dossierdeConception/18_schemas_sql/05_SEEDERS_ET_DONNEES_INITIALES.md` |
| Règles métier | `dossierdeConception/05_regles_metier/05_REGLES_METIER.md` |
| RBAC | `dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md` |
| Multitenancy | `dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` |
| i18n | `dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md` |
| Mocks JSON Flutter | `../mobile/assets/mock/` ← dans le dossier mobile, pas ici |

---

## PROMPTS D'EXÉCUTION

| Prompt | Pour |
|--------|------|
| `PROMPTS_EXECUTION/ORCHESTRATION/CONTINUE.md` | Tableau de bord global |
| `PROMPTS_EXECUTION/v2/backend/CC-01_INIT_LARAVEL.md` | Démarrer le backend |
| `PROMPTS_EXECUTION/v2/backend/CC-02_MODULE_AUTH.md` | Module Auth |
| `PROMPTS_EXECUTION/v2/backend/CC-03_A_CC-06_MODULES.md` | Modules métier |
| `PROMPTS_EXECUTION/v2/mobile/JU-01_A_JU-04_FLUTTER.md` | App Flutter |
| `PROMPTS_EXECUTION/v2/frontend/CU-01_ET_AGENTS.md` | Frontend Vue.js |
