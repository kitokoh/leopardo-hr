# LEOPARDO RH - Documentation Technique
## Source de verite conception | Version 4.1.5 | Avril 2026
## Reference programme: v4.1.5 (`PILOTAGE.md` fait foi)

---

## STRUCTURE

```
docs/
├── infra/                 <- References infra non canoniques + cadrage
├── validation/            <- Referentiels QA / validation par module
├── dossierdeConception/   <- Specs fonctionnelles et techniques
└── PROMPTS_EXECUTION/     <- Prompts pour agents IA
```

---

## SOURCES DE VERITE ABSOLUES

| Quoi | Fichier |
|------|---------|
| API (82 endpoints) | `dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` |
| ERD v2.0 | `dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` |
| SQL complet | `dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` |
| Seeders | `dossierdeConception/18_schemas_sql/05_SEEDERS_ET_DONNEES_INITIALES.md` |
| Regles metier | `dossierdeConception/05_regles_metier/05_REGLES_METIER.md` |
| RBAC | `dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md` |
| Multitenancy | `dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` |
| i18n | `dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md` |
| Mocks JSON Flutter | `../mobile/assets/mock/` <- dans le dossier mobile, pas ici |

---

## DOCUMENTS DE REFERENCE NON CANONIQUES

| Type | Fichier | Statut |
|------|---------|--------|
| Architecture infra actuelle | `infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md` | Reference principale de l'etat courant Render / Neon / healthcheck |
| Architecture infra historique | `infra/Leopardo_RH_Architecture_Deploiement.pdf` | Archive de vision et de projection, non utilisee seule pour l'operationnel courant |
| Alignement infra | `infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md` | Note d'alignement operationnelle entre le PDF et l'etat reel du depot |
| Validation module pointage | `validation/Leopardo_RH_Pointage_Validation_Finale.pdf` | Referentiel QA canonique du module pointage |

Lire aussi :

- `infra/README.md`
- `infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- `infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md`
- `GESTION_PROJET/TICKETS_ALIGNEMENT_DOC_INFRA_2026-04-25.md`
- `validation/README.md`

---

## PROMPTS D'EXECUTION

| Prompt | Pour |
|--------|------|
| `PROMPTS_EXECUTION/v3/MVP-0*_*.md` | Filiere **ACTIVE** (MVP) |
| `PROMPTS_EXECUTION/v2/backend/CC-*.md` | Legacy (lecture seule - ne pas executer) |
| `PROMPTS_EXECUTION/v2/mobile/JU-*.md` | Legacy (lecture seule - ne pas executer) |
| `PROMPTS_EXECUTION/v2/frontend/CU-*.md` | Legacy (lecture seule - ne pas executer) |
