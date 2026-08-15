# Feature Specification: Session QA Expert 2026-08-15 — manquements résiduels (docs/outils/lien web)

**Feature Branch**: `fix/qa-expert-session-2026-08-15`
**Created**: 2026-08-15 | **Status**: Draft
**Input**: Session de test expert complète (vitrine, web, admin, mobile, API, workflows, logiques, onboarding, cohérence) — 2026-08-15. Après mapping anti-doublon sur les 184 issues ouvertes du wave `qa-audit-2026-08-15`, **4 manquements ne sont couverts par aucune issue existante** et font l'objet de cette spec.

## User Scenarios & Testing

### User Story 1 — La collection Postman reflète l'API réelle (Priority: P1)

La collection `postman/leopardo_hr.postman_collection.json` ne contient que 2 requêtes (`POST /auth/login`, `GET /employees`) alors que l'API expose 706 routes. Un intégrateur qui découvre l'API via Postman n'a aucune visibilité sur le contrat réel.

**Pourquoi P1** : la collection Postman est un artefact de découverte livré au public ; une collection à 0,3 % de couverture est trompeuse pour les intégrateurs et les testeurs.

**Test indépendant** : `jq '.item | length' postman/leopardo_hr.postman_collection.json` retourne un nombre de requêtes ≥ 50 (au minimum les endpoints publics + auth + un CRUD par module), chaque requête pointant vers une route existante dans `api/routes/`.

**Acceptance Scenarios**:
1. **Given** la collection Postman, **When** on compte les requêtes, **Then** la collection couvre au minimum les endpoints publics (`/health`, `/auth/login`, `/auth/register`, `/platform/auth/login`, `/demo-users`, `/i18n/catalog`, `/onboarding/*`) + un CRUD par module tenant.
2. **Given** chaque requête de la collection, **When** on la joue contre `https://gestionemployerbackend.onrender.com/api/v1`, **Then** elle renvoie 2xx/4xx documentés (pas de 404 de route).
3. **Given** la variable `baseUrl` de la collection, **When** on l'inspecte, **Then** elle pointe vers `https://gestionemployerbackend.onrender.com/api/v1` (inchangée).

### User Story 2 — api/CHANGELOG.md reflète les versions publiées (Priority: P2)

`api/CHANGELOG.md` s'arrête à `[4.21.0] - 2026-07-01` alors que le CHANGELOG racine couvre 4.22.x, 4.23.x et 4.24.0. Toute relecture du changelog API donne une image fausse des correctifs livrés.

**Pourquoi P2** : la gouvernance exige CHANGELOG à jour dans chaque PR ; un changelog API périmé de 3 versions rompt la traçabilité des correctifs backend.

**Test indépendant** : comparer la dernière section de `api/CHANGELOG.md` avec `CHANGELOG.md` (racine) — la version max doit correspondre (4.24.0).

**Acceptance Scenarios**:
1. **Given** `api/CHANGELOG.md`, **When** on lit la première section publiée, **Then** elle est `[4.24.0]` ou plus récente.
2. **Given** les versions 4.22.0 → 4.24.0 du CHANGELOG racine, **When** on consulte api/CHANGELOG.md, **Then** chaque version majeure est représentée (au minimum un en-tête par version).
3. **Given** une nouvelle PR touchant `api/`, **When** elle modifie le CHANGELOG racine, **Then** elle met à jour aussi `api/CHANGELOG.md` (règle documentée dans le fichier).

### User Story 3 — .env.example sans doublon (Priority: P3)

`api/.env.example` contient `BIOMETRIC_RETENTION_MONTHS` deux fois (l.342 et l.402). Un doublon rend la maintenance ambiguë (deux valeurs candidates pour la même clé) et peut masquer une dérive de config.

**Pourquoi P3** : hygiène de configuration ; aucun impact runtime (le dernier gagne), mais source de confusion documentaire.

**Test indépendant** : `grep -c '^BIOMETRIC_RETENTION_MONTHS=' api/.env.example` retourne 1.

**Acceptance Scenarios**:
1. **Given** `api/.env.example`, **When** on compte les occurrences de chaque clé, **Then** aucune clé n'apparaît plus d'une fois.
2. **Given** la clé `BIOMETRIC_RETENTION_MONTHS`, **When** on vérifie sa présence, **Then** elle est présente une seule fois avec la valeur et le commentaire pertinents.

### User Story 4 — Plus de lien mort vers X/Twitter (Priority: P2)

Le footer de la vitrine (`front/web/src/modules/vitrine/components/Footer.tsx:8`) pointe vers `https://x.com/leopardo_hr` qui renvoie **404** (compte inexistant). Chaque visiteur qui clique le lien social principal tombe sur une page d'erreur. La spec #2608 (wave) prévoit d'ajouter ce même compte mort dans le JSON-LD `sameAs` — il faut corriger la source de vérité avant.

**Pourquoi P2** : lien social mort sur toutes les pages publiques + risque de propager l'URL morte dans le schema.org (`sameAs`) prévu par #2608.

**Test indépendant** : `curl -s -o /dev/null -w '%{http_code}' -L https://x.com/leopardo_hr` retourne 200 après correction (compte rétabli) OU le lien est retiré/remplacé (GitHub `https://github.com/kitokoh/leopardo-hr`).

**Acceptance Scenarios**:
1. **Given** le footer de la vitrine, **When** on clique le lien social X/Twitter, **Then** le lien pointe vers un compte existant (200) ou est retiré.
2. **Given** `structured-data.ts` / `seo.ts` (`sameAs`), **When** le compte X est mort, **Then** le `sameAs` n'inclut pas l'URL morte (coordination avec #2608).
3. **Given** la correction, **When** on build la vitrine, **Then** lint + build verts.

## Requirements

### Functional Requirements
- **FR-001** : La collection Postman expose ≥ 50 requêtes couvrant endpoints publics + auth + CRUD représentatif par module, toutes routées vers des routes existantes.
- **FR-002** : `api/CHANGELOG.md` a une section pour 4.22.0, 4.23.0 et 4.24.0 (au minimum les en-têtes, avec les correctifs majeurs reportés depuis le CHANGELOG racine).
- **FR-003** : `.env.example` ne contient aucune clé dupliquée.
- **FR-004** : Le footer vitrine ne contient aucun lien social mort ; `sameAs` JSON-LD ne référence que des profils existants.

## Success Criteria
- **SC-001** : Les 4 corrections sont livrées dans une PR unique `fix/qa-expert-session-2026-08-15`, CI verte, `Closes #N` (issues créées pour chaque manquement).
- **SC-002** : Aucune régression : build web OK, lint OK, test unitaire `.env.example` OK.

## Assumptions
- La collection Postman est régénérée manuellement (pas d'outil de génération automatique existant dans le repo — vérifier `dev-hub/tools/`).
- Le compte X/Twitter `@leopardo_hr` est considéré abandonné ; la décision de le retirer ou de le remplacer est prise dans cette spec (retrait + lien GitHub).
