# PILOTAGE - LEOPARDO RH
# PROGRAM_VERSION = 4.1.79 | 2026-04-27
# CE FICHIER EST LA SOURCE DE VERITE OPERATIONNELLE

## Le projet en une phrase

Leopardo RH est un SaaS RH multi-tenant pour petites structures, avec un coeur deja livre sur `main` et une priorite immediate sur la stabilisation beta et l'alignement documentaire.

## Etat actuel

Date MAJ : 2026-04-27

- Conception : terminee et structuree dans `docs/dossierdeConception/`
- Code : `main` contient deja le MVP livre, l'i18n, les hardenings P0/P1/P2, les modules RH etendus, le module cameras et l'onboarding public
- Phase active : stabilisation beta + gouvernance documentaire + remise a niveau de la documentation API
- Objectif courant : rendre l'etat du produit lisible sans ambiguite et preparer les retours pilotes

## Prochaine action canonique

Le backlog courant et l'ordre de reprise sont maintenus dans :

- `docs/GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md`

Si un developpeur ou un agent demande "qu'est-ce qu'on fait ensuite ?",
il doit lire ce document juste apres celui-ci.

## Regle cle de lecture

Le depot contient trois niveaux documentaires differents :

1. etat reel de `main`
2. vision produit active
3. cible de conception plus large

Ils ne doivent jamais etre confondus.

## Sources de verite actives

Ordre de priorite :

1. `PILOTAGE.md`
2. `docs/GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md`
3. `docs/GESTION_PROJET/GARDE_FOUS.md`
4. `docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
5. `api/routes/api.php`
6. `api/routes/modules/rh.php`
7. `api/routes/modules/cameras.php`
8. tests backend
9. `docs/REFERENTIEL_PRODUIT/`
10. `docs/dossierdeConception/`

En cas de contradiction sur l'etat reel, les routes et les tests priment.

## Ce qui est deja visible sur `main`

### Core

- healthcheck
- auth API
- auth plateforme
- onboarding public par invitation

### RH

- employes
- estimations
- self-service `me/*`
- attendance
- invitations
- kiosque / biometrie
- absences
- salary advances
- payrolls
- departements / positions / sites / schedules
- notifications
- projects / tasks

### Cameras

- CRUD cameras
- stream token
- access tokens
- permissions
- access logs
- viewer public

## Cadrage MVP historique

Le projet a depasse le perimetre MVP historique.

Cela signifie :

- les anciennes listes "hors scope MVP" ne decrivent plus correctement `main`
- elles peuvent servir de garde-fou contre une nouvelle derive
- elles ne doivent pas servir d'inventaire produit

## Priorites courantes

1. Aligner les documents maitres entre eux
2. Remettre `api/openapi.yaml` au niveau de la surface API reelle
3. Consolider les parcours pilotes et traiter les retours prioritaires
4. Garder la reprise simple pour le prochain developpeur

## Documents a lire selon le besoin

### Pour savoir quoi faire ensuite

- `docs/GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md`

### Pour savoir ce qui est reellement implemente

- `docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
- `api/routes/api.php`
- `api/routes/modules/rh.php`
- `api/routes/modules/cameras.php`

### Pour la vision produit active

- `docs/REFERENTIEL_PRODUIT/APV.md`
- `docs/REFERENTIEL_PRODUIT/ROADMAP.md`
- `docs/REFERENTIEL_PRODUIT/STATUTS.md`
- `docs/REFERENTIEL_PRODUIT/COULEURS.md`

### Pour la cible de conception

- `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- `docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql`

## Regles de session

Avant de commencer :

1. verifier la branche et le `git status`
2. lire ce fichier
3. lire `docs/GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md`
4. verifier les routes et tests de la zone touchee

Avant de terminer :

1. mettre a jour `CHANGELOG.md` si une zone critique change
2. mettre a jour ce fichier si l'etat reel ou la priorite courante change
3. mettre a jour `docs/GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md` si l'ordre des priorites change
4. ne jamais utiliser `docs/notes/CONTINUE_v2.md` comme source canonique de reprise
