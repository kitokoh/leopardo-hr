# ALIGNEMENT DOCUMENTATION / IMPLEMENTATION MAIN
## Date de reference : 2026-04-26
## Branche de reference analysee : `origin/main`

---

## Resume executif

La documentation du depot melange aujourd'hui deux niveaux :

- la cible produit complete
- l'etat reel de l'implementation sur `main`

Conclusion : la documentation n'est pas globalement coherente avec l'implementation actuelle si on la lit comme un contrat executable immediat.

En revanche, elle reste utile si on la lit comme une cible de conception.

---

## Regle de verite pour l'etat courant

Pour savoir ce qui est vraiment livre sur `main`, utiliser dans cet ordre :

1. `api/routes/api.php`
2. `api/routes/modules/rh.php`
3. `api/routes/modules/cameras.php`
4. les controllers `api/app/Http/Controllers/Api/V1/`
5. les tests backend

Le fichier `api/openapi.yaml` et le dossier `docs/dossierdeConception/` ne doivent pas etre lus seuls comme preuve d'implementation complete.

---

## Ce qui est effectivement implemente sur `main`

### Core API

- `GET /api/v1/health`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `PATCH /api/v1/auth/profile`
- `POST /api/v1/auth/change-password`
- `POST /api/v1/auth/logout`

### RH module

- CRUD employes de base
- endpoints d'estimation employee
- self-service `me/*`
- `attendance/check-in`
- `attendance/check-out`
- `attendance/today`
- `attendance`
- invitations
- demandes d'enrollement biometrique
- register kiosk
- roster / punch / sync kiosk

### Cameras module

- CRUD cameras
- test RTSP
- stream token
- access tokens
- permissions
- access logs
- verify token interne
- viewer public

### Platform / super-admin

- `POST /api/v1/platform/auth/login`
- `GET /api/v1/platform/auth/me`
- `POST /api/v1/platform/auth/logout`
- `GET /api/v1/platform/companies`
- `POST /api/v1/platform/companies`

---

## Ecarts documentaires majeurs

### 1. README racine

Ancien probleme :

- liens morts vers `docs/GESTION_PROJET/INDEX_CANONIQUE.md`
- liens morts vers `docs/GESTION_PROJET/EXECUTION_BLOCKERS_AND_NEXT.md`

Impact :

- confusion immediate a l'entree du depot

### 2. Contrat API surdocumente par rapport au code

Le document `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
decrit une plateforme beaucoup plus large que celle exposee par `main`.

Exemples documentes mais non livres comme surface API complete dans `main` :

- `public/register`
- `auth/forgot-password`
- `auth/reset-password`
- `auth/device/fcm`
- `absences/*`
- `absence-types/*`
- `advances/*`
- `departments/*`
- `positions/*`
- `schedules/*`
- `sites/*`
- `settings/*`
- `notifications/*`
- `reports/*`
- une grande partie de `admin/*`
- plusieurs endpoints attendance de correction / QR / webhook documentes sous d'autres formes

### 3. Formats de reponse divergents

Exemples :

- `POST /auth/login` documente avec token dans `data`, alors que le code renvoie `data` + `token` au niveau racine
- `POST /auth/logout` documente avec `message`, alors que le code renvoie `status`
- archivage employe documente comme `DELETE /employees/{id}`, alors que l'implementation expose `POST /employees/{employee}/archive`
- `attendance/today` documente avec `data/context`, alors que le code renvoie `mode` + `item` ou `items`

### 4. Codes HTTP divergents

Exemple explicite :

- `ALREADY_CHECKED_IN` est documente en `409`
- l'implementation renvoie `422`

---

## Ce qui reste coherent

- la doc de demarrage backend est globalement exploitable
- le runbook local Docker est coherent avec l'approche d'equipe
- la structure multitenant et le decoupage modulaire sont visibles dans le code
- plusieurs tables cibles existent deja en migration, meme si les endpoints associes ne sont pas encore exposes

---

## Decision documentaire recommandee

Adopter desormais trois statuts explicites :

- `Etat courant` : ce qui est reellement expose dans `main`
- `Cible validee` : spec produit approuvee mais pas encore entierement livree
- `Archive / legacy` : anciens supports ou prompts non executables

---

## Plan d'action clair

### Priorite 1 - securiser la lecture du depot

1. garder le README racine minimal et exact
2. faire de ce document le point d'entree pour toute revue doc/code
3. conserver `docs/dossierdeConception/` comme cible, pas comme preuve d'implementation

### Priorite 2 - remettre l'API a niveau documentaire

1. ajouter dans `api/openapi.yaml` uniquement les endpoints reellement exposes
2. deplacer les endpoints non livres dans un document `API cible`
3. aligner chemins, payloads, codes HTTP et formats de reponse sur les controllers reels

### Priorite 3 - eviter les regressions

1. verifier en CI les liens Markdown
2. verifier que tout endpoint documente comme "implante" existe bien dans `route:list`
3. imposer une mention explicite `cible` ou `main` sur les gros documents de contrat

### Priorite 4 - reprendre module par module

Ordre conseille :

1. auth
2. employees
3. attendance
4. kiosks / biometrics
5. platform
6. cameras
7. seulement ensuite absences, advances, payroll, notifications, reports, settings

---

## Usage conseille pour mobile et web

Avant d'implementer un ecran ou un client API :

1. verifier que la route existe dans Laravel
2. verifier le serializer du controller
3. verifier les erreurs renvoyees dans `bootstrap/app.php`
4. verifier ensuite la doc cible pour les evolutions non encore livrees
