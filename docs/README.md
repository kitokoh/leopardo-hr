# LEOPARDO RH - Documentation Technique
## Version documentaire 4.1.5 | Avril 2026
## Reference programme : `PILOTAGE.md` fait foi

---

## Structure

```text
docs/
|-- REFERENTIEL_PRODUIT/ Documents canoniques courts (APV, roadmap, statuts, couleurs, audit)
|-- STRATEGIE_COMMERCIALE/ GTM, CRM, scripts commerciaux et plan d'action
|-- infra/               References infra et operationnel courant
|-- validation/          Referentiels QA par module
|-- dossierdeConception/ Conception detaillee cible, structuree par domaine
|-- GESTION_PROJET/      Runbooks, audits, alignements, pilotage d'execution
`-- PROMPTS_EXECUTION/   Prompts et filieres d'execution
```

---

## Lire d'abord

### 1. Etat reel de `main`

- `GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
- `GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`

### 2. Surface API effectivement exposee

- `../api/routes/api.php`
- `../api/routes/modules/rh.php`
- `../api/routes/modules/cameras.php`
- `../api/README.md`

### 3. Cible produit / conception

- `REFERENTIEL_PRODUIT/APV.md`
- `REFERENTIEL_PRODUIT/ROADMAP.md`
- `REFERENTIEL_PRODUIT/STATUTS.md`
- `REFERENTIEL_PRODUIT/COULEURS.md`
- `dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- `dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- `dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- `dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md`
- `dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md`
- `dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md`

---

## Regle de lecture importante

Les documents sous `dossierdeConception/` decrivent majoritairement la cible produit.
Ils ne doivent pas etre consideres comme un reflet automatique de l'etat exact de `main`.

Pour toute question de coherence doc/code :

1. verifier les routes Laravel et les controllers
2. verifier les migrations et tests
3. utiliser `GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
4. ensuite seulement consulter la spec cible pour les modules non encore livres

---

## Statut des sources

| Sujet | Fichier | Statut conseille |
|------|---------|------------------|
| Etat reel API/backend sur `main` | `GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md` | canonique pour l'etat courant |
| Contrat API cible | `dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | cible produit, pas garantie d'implementation complete |
| APV / architecture produit | `REFERENTIEL_PRODUIT/APV.md` | canonique pour la vision produit active |
| Roadmap produit | `REFERENTIEL_PRODUIT/ROADMAP.md` | canonique pour l'ordre d'execution et les phases |
| ERD | `dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` | cible structurelle |
| SQL complet | `dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` | reference schema cible |
| Regles metier | `dossierdeConception/05_regles_metier/05_REGLES_METIER.md` | reference fonctionnelle, a confronter au code |
| Validation locale backend | `GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` | canonique pour l'execution locale |
| Architecture infra courante | `infra/01_etat_courant/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md` | canonique pour l'operationnel infra |

---

## Prompts d'execution

| Prompt | Usage |
|--------|-------|
| `PROMPTS_EXECUTION/v3/MVP-0*_*.md` | filiere active MVP |
| `PROMPTS_EXECUTION/v2/backend/CC-*.md` | legacy, lecture seule |
| `PROMPTS_EXECUTION/v2/mobile/JU-*.md` | legacy, lecture seule |
| `PROMPTS_EXECUTION/v2/frontend/CU-*.md` | legacy, lecture seule |
