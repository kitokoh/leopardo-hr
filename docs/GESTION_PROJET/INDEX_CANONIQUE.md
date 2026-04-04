# INDEX CANONIQUE - LEOPARDO RH
# Version 4.1.3 | 04 Avril 2026
# Objectif: eliminer toute ambiguite sur "quel fichier fait foi"

---

## SOURCE DE VERITE (SEULE REFERENCE ACTIVE)

Ordre de priorite absolu :
1. `ORCHESTRATION_MAITRE.md`
2. `docs/dossierdeConception/*`
3. `docs/PROMPTS_EXECUTION/v2/*`
4. `docs/GESTION_PROJET/*`
5. Archives historiques (lecture seule)

---

## FICHIERS ACTIFS OBLIGATOIRES

- Vision globale: `ORCHESTRATION_MAITRE.md`
- Roadmap: `08_FEUILLE_DE_ROUTE.md`
- API: `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- ERD: `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- SQL: `docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql`
- Regles metier: `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- Securite/RBAC: `docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md`
- Multitenancy: `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md`
- Changelog: `CHANGELOG.md`
- Journal racine: `JOURNAL_RACINE.md`

---

## FICHIERS ARCHIVES (INTERDITS POUR IMPLEMENTATION)

- `AUDIT_COMPLET_MANQUES.md`
- `docs/notes/*`
- Tout document de patch historique non reference par `ORCHESTRATION_MAITRE.md`

Regle :
- Ces fichiers servent a la tracabilite uniquement.
- Aucun ticket de dev ne peut etre derive depuis ces archives.

---

## CHECKLIST PRE-DEV (OBLIGATOIRE)

Avant toute implementation :
- Valider la tache dans `docs/GESTION_PROJET/BACKLOG_PHASE1_UNIQUE.md`
- Verifier alignement API + ERD + SQL + regles metier
- Verifier scope phase active (Phase 1 actuellement)
