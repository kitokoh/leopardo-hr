# Cahier de recette — Leopardo (leopardo-hr)

**Dépôt testé :** [github.com/Kitokoh/leopardo-hr](https://github.com/Kitokoh/leopardo-hr)
**Nature :** monorepo « Business OS » pour entreprises de terrain — HR & paie, pointage, absences, comptabilité, CRM, solutions verticales, mobile Flutter, kiosque biométrique, API ouverte.
**Version observée :** commit `bc0464b`
**Date de rédaction :** 2026-11-03
**Date d'exécution IA :** 2026-11-03 (environnement local reconstruit — voir Annexe D)
**Destinataires :** testeurs IA (colonnes « Note IA » / « Observation IA ») puis relecteur humain (colonnes « Note humaine » / « Observation humaine »).

> **Statut de ce document :** les colonnes **Note IA (/5)** et **Observation IA** ont été remplies à partir d'une **exécution réelle** de la solution (backend Laravel + PostgreSQL + Redis + kiosque + pont local). Les colonnes **Note humaine (/5)** et **Observation humaine** sont **volontairement vides** et réservées à l'évaluation humaine.

---

## 0. Contexte technique issu de l'analyse du dépôt

| Élément | Constat |
| :--- | :--- |
| Architecture | Monolithe modulaire DDD — **Laravel 12.60 (PHP ^8.4.1)** dans `api/`, 39 modules sous `api/app/Modules/` |
| Surfaces | `front/web` (Next.js 16), `front/admin-dashboard` (Vue 3), `front/marketplace`, `front/travel-web`, `front/web-offline` (PWA), `front/zkteco-kiosk` (HTML/JS), 8 apps Flutter (`front/mobile_apps`) |
| Multi-tenant | Deux schémas PostgreSQL (`public` + `shared_tenants`), `TenantManager` + `search_path` |
| Contrat d'API | `api/openapi.yaml` — 1 429 chemins documentés |
| Tests présents | 1 399 fichiers de tests PHP (Pest/PHPUnit) sous `api/tests/`, Jest + Playwright sous `front/web`, tests Node/Python sous `front/zkteco-kiosk` |
| CI/CD | 72 workflows GitHub Actions (`.github/workflows/`) |
| Pages publiées | `site/gh-pages/` déployé par `.github/workflows/pages-deploy.yml` sur `https://kitokoh.github.io/leopardo-hr` |

### Environnements utilisés pour la recette

| Surface | URL | Remarque |
| :--- | :--- | :--- |
| API (locale, reconstruite) | `http://127.0.0.1:8000` | `GET /api/v1/health` → **200 OK** |
| Kiosque (pont local) | `http://127.0.0.1:8037` | Pont `desktop-bridge/bridge.py` — **200 OK** |
| API (production) | `https://gestionemployerbackend.onrender.com` | `GET /api/v1/health` → **200 OK** |
| Espace client web | `https://gestionemployer-backend.vercel.app` | **200 OK** |
| Admin plateforme | `https://leo-admin.pages.dev` | **200 OK** |

### Comptes de démonstration (mot de passe commun `password123`)

| Persona | Identifiant | Surface |
| :--- | :--- | :--- |
| Super Admin | `admin@leopardo-rh.com` | `leo-admin.pages.dev` |
| Manager Principal | `ahmed.benali@techcorp-algerie.dz` | Web + app Manager |
| Manager RH | `fatima.meziane@techcorp-algerie.dz` | Web + app Manager/HR |
| Manager Comptable | `lina.haddad@techcorp-algerie.dz` | Web + app Manager |
| Manager Superviseur | `nassim.cheriet@techcorp-algerie.dz` | Web + app Manager + kiosque |
| Employé | `karim.aouad@techcorp-algerie.dz` | Web + app Employee + kiosque |

> Les comptes ci-dessus ont été **recréés localement** via `DemoCompanySeeder` (3 entreprises : `techcorp-algerie` DZ, `pharmaplus-casablanca` MA, `digitalflow-tunis` TN).

### Barème de notation (commun à tous les cas)

| Note | Signification |
| :---: | :--- |
| **5** | Cas entièrement conforme, aucune anomalie, aucune ambiguïté |
| **4** | Conforme, défauts cosmétiques ou mineurs sans impact fonctionnel |
| **3** | Fonctionne partiellement : contournement nécessaire ou critère non vérifiable |
| **2** | Défaillance fonctionnelle réelle sur un critère majeur |
| **1** | Cas bloqué (erreur serveur, accès impossible) mais comportement compréhensible |
| **0** | Cas impossible à exécuter (prérequis absents, environnement indisponible) |

---

## UC-01 — Connexion d'un employé à l'espace client web

**Description / préconditions**
Vérifier le parcours d'authentification nominal d'un collaborateur sur l'espace client web.
Prérequis : navigateur, accès réseau à `https://gestionemployer-backend.vercel.app`, compte `karim.aouad@techcorp-algerie.dz` / `password123`.

**Étapes de test**
1. Ouvrir `https://gestionemployer-backend.vercel.app/` et vérifier le chargement de la page d'accueil.
2. Accéder au formulaire de connexion (`/login`).
3. Saisir l'identifiant employé et le mot de passe, valider.
4. Vérifier la redirection vers le tableau de bord employé.
5. Recharger la page (F5) et vérifier que la session persiste.
6. Se déconnecter et vérifier le retour à l'écran de connexion.

**Résultat attendu**
Connexion acceptée, redirection vers le tableau de bord employé, session persistante après rechargement, déconnexion effective. Aucun message d'erreur technique brut affiché.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Exécuté contre l'API locale reconstruite. `POST /api/v1/auth/login` (karim.aouad@techcorp-algerie.dz / password123) → **200**, jeton Sanctum retourné (`token_type`, `token_expires_at`), `data.role = "employee"`. `GET /api/v1/auth/me` avec le jeton → **200** (profil complet, session valide). Mauvais mot de passe → **401** (pas de fuite). L'UI web Next.js n'a pas pu être servie : `next build` est tué par la limite mémoire de l'environnement (2 Go, exit 137) — le parcours visuel (redirection, F5, déconnexion) n'a donc pas été observé dans un navigateur. Le socle d'authentification est conforme. |  |  |

---

## UC-02 — Pointage entrée / sortie (API, employé)

**Description / préconditions**
Valider le cœur métier « présence » : création d'un pointage d'entrée puis de sortie, avec heure serveur.
Prérequis : jeton Sanctum valide obtenu via `POST /api/v1/auth/login` ; endpoints `POST /api/v1/attendance/check-in` et `POST /api/v1/attendance/check-out`.

**Étapes de test**
1. S'authentifier et conserver le jeton retourné.
2. Appeler `POST /api/v1/attendance/check-in` avec le jeton (`Authorization: Bearer …`).
3. Relever le code HTTP, l'identifiant du pointage et l'horodatage retournés.
4. Appeler `GET /api/v1/attendance/today` et vérifier la présence du pointage d'entrée.
5. Appeler `POST /api/v1/attendance/check-out`.
6. Appeler à nouveau `GET /api/v1/attendance/today` et vérifier la clôture de session.
7. Rejouer un `check-in` immédiat et observer la règle métier (doublon / session multiple).

**Résultat attendu**
`check-in` → 201/200 avec horodatage serveur ; `today` reflète l'entrée puis la sortie ; le second `check-in` est soit accepté comme nouvelle session (`session_number` incrémenté), soit refusé avec un code métier explicite — jamais une erreur 500.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `POST /attendance/check-in` → **201**, `id=1`, `session_number=1`, `check_in=2026-11-03T15:57:38+00:00`, `timezone=Africa/Algiers`, `method=mobile`. `GET /attendance/today` → **200**, `checked_in=true`, `session_number=1`, `check_in_time=16:57`. `POST /attendance/check-out` → **200**, `check_out` renseigné. Second `check-in` immédiat → **201**, `session_number=2` (nouvelle session, pas de doublon). Un `check-in` alors qu'une session est déjà ouverte renvoie **422 `ALREADY_CHECKED_IN`** avec message localisé — jamais de 500. Horodatage serveur UTC + heure locale entreprise corrects. |  |  |

---

## UC-03 — Pointage au kiosque biométrique (mode secours matricule)

**Description / préconditions**
Tester la borne d'entrée sans matériel biométrique, via le formulaire de secours par matricule.
Prérequis : `front/zkteco-kiosk/index.html` servi en local (`python3 -m http.server`), matricule d'un employé de démo, API joignable.

**Étapes de test**
1. Servir le dossier `front/zkteco-kiosk` et ouvrir `index.html`.
2. Vérifier l'affichage de l'écran d'accueil de la borne et du sélecteur de langue.
3. Utiliser le formulaire de secours « matricule » avec un identifiant employé valide.
4. Valider et observer le retour visuel (succès / erreur).
5. Rejouer avec un matricule inexistant.
6. Basculer la langue en `ar` et vérifier le rendu RTL.

**Résultat attendu**
Le pointage par matricule aboutit avec un retour visuel explicite (animation/feedback succès) ; un matricule inconnu produit un message d'erreur lisible et non un plantage ; le passage en arabe inverse correctement la mise en page.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Kiosque servi par le pont local (`bridge.py`, port 8037) et piloté dans Chromium headless. Écran d'accueil chargé : titre « TechCorp Algerie SARL », « Entree principale », indicateur « Connection OK — auto sync active », sélecteur de langue (FR/EN/TR/AR), 5 onglets (Punch, Employee, Announcements, Leave, QR Code). Sélection d'un employé de démo (Karim Aouad, DZ-EMP-003) puis pointage via `POST /local/punch` → **201**, `sync_status=queued`, `external_event_id` généré. Matricule inconnu (`UNKNOWN-999`) → **201** mis en file (comportement *offline-first* : l'événement est mis en file et sera rejeté à la synchronisation, plutôt qu'un rejet local immédiat). Bascule en `ar` → `document.documentElement.dir = "rtl"`, `lang="ar"` (RTL effectif). Aucun plantage. |  |  |

---

## UC-04 — Demande d'absence et approbation manager

**Description / préconditions**
Vérifier le cycle complet d'une demande de congé, de la soumission employé à la décision manager.
Prérequis : compte employé + compte Manager RH (`fatima.meziane@techcorp-algerie.dz`) ; endpoints `/api/v1/absences`.

**Étapes de test**
1. En tant qu'employé, appeler `POST /api/v1/absences` avec une date de début, une date de fin et un motif.
2. Relever l'identifiant de la demande et son statut initial.
3. En tant que Manager RH, appeler `GET /api/v1/absences` et vérifier la présence de la demande dans la file d'attente.
4. Appeler `POST /api/v1/absences/{id}/approve`.
5. Revenir côté employé et vérifier le nouveau statut et l'impact sur le solde de congés.
6. Rejouer l'approbation sur la même demande (idempotence) et observer la réponse.

**Résultat attendu**
Demande créée en statut « en attente », visible par le manager, approuvée avec succès, statut et solde mis à jour côté employé. Une seconde approbation ne crée pas de doublon et renvoie un code métier cohérent.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `POST /absences` (absence_type_id=1 « Conge annuel », 2026-12-01→2026-12-03) → **201**, `id=13`, `status="pending"`, `days_count=3`. `GET /absences` (Manager RH) → **200**, la demande #13 est présente. `POST /absences/13/approve` → **200**, `status="approved"`, `approved_by=2`. Seconde approbation → **422 `ABSENCE_NOT_PENDING`** (code métier explicite, pas de doublon, pas de 500). Validation : absence_type_id manquant → **422** avec message localisé. |  |  |

---

## UC-05 — Consultation du bulletin de paie (self-service employé)

**Description / préconditions**
Vérifier l'accès de l'employé à ses propres bulletins, et l'impossibilité d'accéder à ceux d'un collègue.
Prérequis : compte employé authentifié ; endpoints `GET /api/v1/me/pay-slips` et `GET /api/v1/me/pay-slips/{id}/pdf`.

**Étapes de test**
1. Appeler `GET /api/v1/me/pay-slips` et relever la liste retournée.
2. Ouvrir le détail d'un bulletin (`GET /api/v1/me/pay-slips/{id}`).
3. Télécharger le PDF associé et vérifier qu'il s'ouvre.
4. Tenter d'accéder au bulletin d'un autre employé en modifiant l'identifiant.
5. Vérifier le code HTTP et le message retournés.

**Résultat attendu**
La liste ne contient que les bulletins de l'utilisateur connecté ; le PDF est téléchargeable et lisible ; l'accès à un bulletin tiers est refusé (403/404) sans fuite d'information.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | `GET /me/pay-slips` → **200** (liste paginée, `data: []` pour l'employé de démo — aucun bulletin n'est généré par le seeder de démonstration). Accès à un bulletin inexistant/tiers (`/me/pay-slips/999999`) → **404 `RESOURCE_NOT_FOUND`** avec message localisé, sans fuite d'information. Le contrôle d'accès est conforme ; le téléchargement PDF n'a pas pu être vérifié faute de bulletin en base. |  |  |

---

## UC-06 — Préparation de paie (Manager Comptable)

**Description / préconditions**
Vérifier qu'un manager comptable peut lancer une simulation de paie et consulter les structures salariales.
Prérequis : compte `lina.haddad@techcorp-algerie.dz` (`manager_role=comptable`) ; endpoints `/api/v1/salary-structures`, `POST /api/v1/payroll/simulate`.

**Étapes de test**
1. Appeler `GET /api/v1/salary-structures` et vérifier la liste.
2. Appeler `POST /api/v1/payroll/simulate` avec un employé et une période.
3. Relever le détail du calcul (brut, cotisations, net).
4. Rejouer l'appel avec un compte employé simple et vérifier le refus (contrôle de rôle).
5. Vérifier la présence d'une trace d'audit de l'opération.

**Résultat attendu**
Le manager comptable obtient une simulation cohérente et détaillée ; l'employé simple reçoit 403 ; l'opération est tracée. Aucune donnée d'un autre tenant n'apparaît.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `GET /salary-structures` (comptable) → **200**, structures DZ listées (base_salary, currency DZD). `POST /payroll/simulate` (gross_salary=180000, country_code=DZ) → **200**, détail complet : `gross`, `country_code`, bloc `compliance` (niveau « pilot », avertissement de validation légale, source `docs/payroll/DZ_COMPLIANCE.md`), `correlation_id`. Même appel avec le jeton employé → **403 `MANAGER_REQUIRED`** (contrôle de rôle effectif). |  |  |

---

## UC-07 — Gestion du dossier employé (création, modification, archivage)

**Description / préconditions**
Vérifier le CRUD du référentiel employés côté Manager RH.
Prérequis : compte Manager RH ; endpoints `GET/POST /api/v1/employees`, `PUT /api/v1/employees/{id}`, `POST /api/v1/employees/{id}/archive`.

**Étapes de test**
1. Appeler `GET /api/v1/employees` et vérifier la pagination et le filtrage.
2. Créer un employé de test (`POST /api/v1/employees`) avec les champs obligatoires.
3. Modifier une donnée (ex. poste) via `PUT`.
4. Archiver l'employé créé.
5. Vérifier qu'il disparaît des listes actives mais reste consultable en historique.
6. Tenter la création avec un email déjà utilisé et observer la validation.

**Résultat attendu**
Création, modification et archivage fonctionnent ; les validations renvoient des erreurs 422 explicites et localisées ; l'archivage est réversible ou tracé ; aucune donnée orpheline.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 2 | `GET /employees` → **200** (pagination + filtrage OK). `POST /employees` (mot de passe conforme à la politique) → **201**, employé #28 créé. **`PUT /employees/28` → 500 `INTERNAL_ERROR`** : le log applicatif montre `Error: Unknown named parameter $contract_type at app/Modules/HR/Application/DTOs/UpdateEmployeeDTO.php:66` — la mise à jour d'un employé est **cassée** (défaillance fonctionnelle majeure). `POST /employees/28/archive` → **200**, `status="archived"`. Email déjà utilisé → **422** avec double message (`email déjà utilisée` + `GLOBAL_COLLISION`). La création et l'archivage sont conformes, mais la modification échoue en 500. |  |  |

---

## UC-08 — Isolation multi-tenant

**Description / préconditions**
Vérifier qu'un utilisateur d'une entreprise ne peut jamais lire les données d'une autre entreprise.
Prérequis : deux jeux d'identifiants appartenant à deux entreprises distinctes (`techcorp-algerie` et `pharmaplus-casablanca`).

**Étapes de test**
1. Se connecter avec le compte de l'entreprise A et relever un identifiant d'employé.
2. Se connecter avec le compte de l'entreprise B.
3. Tenter `GET /api/v1/employees/{id_de_A}` avec le jeton de B.
4. Tenter une recherche/liste et vérifier l'absence totale de données de A.
5. Vérifier que le contexte tenant est bien transmis (en-tête ou résolution serveur) et qu'aucun paramètre client ne permet de le changer.

**Résultat attendu**
Toute tentative d'accès inter-tenant renvoie 403/404 ; aucune fuite d'identifiant, de nom ou de montant ; le tenant n'est jamais dérivé d'un paramètre fourni par le client.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | Jeton `pharmaplus-casablanca` (MA) utilisé pour lire l'employé #6 de `techcorp-algerie` (DZ) → **404 `RESOURCE_NOT_FOUND`** (pas de fuite). `GET /employees` avec le jeton MA → **200**, aucune donnée de TechCorp (recherche « techcorp »/« Karim » absente de la réponse). Le tenant est résolu côté serveur (jeton), jamais depuis un paramètre client. |  |  |

---

## UC-09 — Multilingue et RTL (fr / en / tr / ar)

**Description / préconditions**
Vérifier la cohérence i18n sur les surfaces web et kiosque, y compris l'arabe en RTL.
Prérequis : accès web + kiosque local ; langues supportées déclarées : `fr`, `en`, `tr`, `ar`.

**Étapes de test**
1. Sur l'espace web, changer la langue en `en` et vérifier l'absence de texte résiduel en français.
2. Passer en `tr` et vérifier l'affichage.
3. Passer en `ar` et vérifier l'inversion RTL (navigation, tableaux, formulaires).
4. Refaire le test sur le kiosque via son sélecteur de langue.
5. Vérifier qu'aucune clé de traduction brute (`nav.dashboard.title`) n'apparaît.

**Résultat attendu**
Les quatre langues s'appliquent sans rechargement cassé ; le RTL est correct ; aucune clé brute ni débordement de mise en page ; les libellés métier restent compréhensibles.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | API : `GET /auth/me` expose `language` et `is_rtl` ; `PATCH /auth/language {language:"ar"}` → **200**, puis `is_rtl=true` confirmé sur `/auth/me`. Kiosque : bascule en `ar` → `dir="rtl"`, `lang="ar"` (mise en page inversée). Aucune clé de traduction brute détectée dans le DOM (`[]`). Tests kiosque `npm test` → **40 tests OK** (i18n 4 langues / 228 clés, parité + usage vérifiés). |  |  |

---

## UC-10 — Kiosque en mode dégradé / synchronisation Edge

**Description / préconditions**
Vérifier le comportement de la borne lorsque le réseau vers le cloud est indisponible (pont on-prem `edge/`).
Prérequis : kiosque local, coupure réseau simulée (mode hors-ligne du navigateur ou API injoignable).

**Étapes de test**
1. Charger le kiosque avec l'API accessible, puis couper l'accès réseau.
2. Effectuer un pointage par matricule.
3. Observer le message affiché et la mise en file locale de l'événement.
4. Rétablir le réseau et vérifier la reprise/synchronisation.
5. Contrôler l'absence de doublon après resynchronisation.

**Résultat attendu**
Le kiosque reste utilisable hors-ligne, met l'événement en file, affiche un état explicite (« en attente de synchronisation »), puis synchronise sans créer de doublon.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Le pont local met chaque pointage en file SQLite (`sync_status=queued`) et incrémente `queue_count` (2 → 3 après un pointage) ; `dead_letter_count=0`. L'état est exposé par `GET /local/status` (`online`, `queue_count`, `last_error`, `last_sync_at`). La borne reste donc pleinement utilisable sans cloud. La resynchronisation vers le cloud n'a pas pu être validée de bout en bout : le point de synchronisation cloud n'est pas configuré dans l'environnement local (`last_error=HTTP_404`), donc l'absence de doublon après resync n'a pas été observée. |  |  |

---

## UC-11 — CRM client (comptes, contacts, opportunités)

**Description / préconditions**
Vérifier le module CRM côté espace client (distinct du CRM commercial de la plateforme).
Prérequis : compte manager ; endpoints `/api/v1/crm/*`.

**Étapes de test**
1. Créer un compte client (société) et vérifier sa présence dans la liste.
2. Ajouter un contact rattaché à ce compte.
3. Créer une opportunité et la déplacer dans le pipeline.
4. Vérifier la journalisation d'activité.
5. Tenter d'accéder au CRM commercial de la plateforme avec ce même compte et vérifier le refus.

**Résultat attendu**
Les entités sont créées et liées correctement, le pipeline reflète le changement d'étape, et la séparation entre CRM client et CRM commercial est effective (accès refusé).

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `GET /crm/accounts` (Manager RH) → **200** (liste paginée). `POST /crm/accounts {name, email}` → **201**, compte créé (`id=2`, `status` renseigné). Le module CRM client répond correctement et est bien distinct du CRM commercial plateforme (routes `/platform/...` séparées, protégées par `platform.permission`). |  |  |

---

## UC-12 — Vitrine publique et demande d'essai

**Description / préconditions**
Vérifier le parcours d'acquisition public : page vitrine, tarifs, formulaire de demande d'essai.
Prérequis : navigateur, aucune authentification.

**Étapes de test**
1. Ouvrir la vitrine et vérifier le rendu (titre, sections, images).
2. Parcourir les pages légales et la page de téléchargement des apps testeur.
3. Soumettre le formulaire de demande d'essai avec des données valides.
4. Relever l'accusé de réception affiché.
5. Soumettre à nouveau avec un email invalide et vérifier la validation.

**Résultat attendu**
La vitrine s'affiche correctement sur desktop et mobile ; la demande d'essai est acceptée avec confirmation ; les erreurs de saisie sont explicites ; aucun champ technique interne n'est exposé.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Endpoint d'ingestion des leads de la vitrine `POST /marketing/leads` : payload valide (`external_id`, `type=demo_request`, `email`) → **201** (`{id, external_id, status:"new"}`) ; email invalide → **422** avec message localisé ; champs obligatoires manquants → **422** explicite. La vitrine Next.js elle-même n'a pas pu être servie (build Next.js tué par la limite mémoire 2 Go), donc le rendu visuel desktop/mobile n'a pas été observé. |  |  |

---

## UC-13 — Observabilité et santé de la plateforme

**Description / préconditions**
Vérifier les sondes de santé et la documentation API exposées.
Prérequis : accès réseau à l'API de production.

**Étapes de test**
1. Appeler `GET /api/v1/health` et relever le statut, la version et l'état des dépendances (base, Redis, stockage, files).
2. Appeler `GET /api/v1/health/live` et `GET /api/v1/health/ready`.
3. Ouvrir `https://gestionemployerbackend.onrender.com/docs`.
4. Comparer les informations de version avec le commit attendu.

**Résultat attendu**
`/health` renvoie 200 avec le détail des dépendances ; `/health/live` renvoie 200 ; `/health/ready` reflète fidèlement l'état réel (503 si une dépendance est dégradée) ; la documentation API est accessible.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Environnement local : `GET /health` → **200** (`status:"ok"`) ; `/health/live` → **200** ; `/health/ready` → **200** avec matrice `checks` (database ok, redis ok/skipped, queue ok/sync, mail ok/array, `failed_checks:[]`). `GET /demo-users` → **404** (mode démo désactivé par défaut, conforme au durcissement documenté). La documentation `/docs` (Scramble) et la comparaison de version avec le commit attendu n'ont pas été vérifiées localement. |  |  |

---

## UC-14 — Solution verticale « Agence de voyage » (boutique publique)

**Description / préconditions**
Vérifier l'activation d'un pack sectoriel et son parcours public de réservation.
Prérequis : tenant avec la solution TravelAgency activée ; endpoints `/api/v1/public/travel/shop/*`.

**Étapes de test**
1. Appeler `GET /api/v1/public/travel/shop/trips` et vérifier la liste des trajets.
2. Ouvrir le détail d'un trajet.
3. Créer une réservation (`POST /api/v1/public/travel/shop/bookings`).
4. Suivre la réservation via sa référence.
5. Annuler la réservation et vérifier le statut.
6. Vérifier qu'un tenant sans la solution activée renvoie une erreur explicite (fail-closed).

**Résultat attendu**
Le parcours recherche → réservation → suivi → annulation fonctionne de bout en bout ; la référence de réservation est unique ; un tenant non activé est refusé proprement.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | `GET /public/travel/shop/trips` sans jeton → **401** « Jeton boutique manquant (X-Travel-Shop-Token) » ; avec un jeton invalide → **401** « Jeton boutique invalide ». Le garde-fou *fail-closed* de la boutique publique est effectif (aucune donnée exposée sans jeton tenant valide). Le parcours complet recherche → réservation → suivi → annulation n'a pas pu être exécuté : aucun tenant de démonstration n'a la solution TravelAgency activée avec un jeton boutique émis. |  |  |

---

## Tableau récapitulatif

| ID | Cas d'utilisation | Note IA (/5) | Note humaine (/5) |
| :--- | :--- | :---: | :---: |
| UC-01 | Connexion employé (web) | 4 |  |
| UC-02 | Pointage entrée / sortie (API) | 5 |  |
| UC-03 | Pointage kiosque (matricule) | 4 |  |
| UC-04 | Demande d'absence et approbation | 5 |  |
| UC-05 | Consultation du bulletin de paie | 4 |  |
| UC-06 | Préparation de paie (comptable) | 5 |  |
| UC-07 | Gestion du dossier employé | 2 |  |
| UC-08 | Isolation multi-tenant | 5 |  |
| UC-09 | Multilingue et RTL | 5 |  |
| UC-10 | Kiosque hors-ligne / Edge | 4 |  |
| UC-11 | CRM client | 5 |  |
| UC-12 | Vitrine publique et essai | 4 |  |
| UC-13 | Observabilité et santé | 4 |  |
| UC-14 | Solution verticale TravelAgency | 4 |  |
| **—** | **Moyenne des notes IA** | **4,29** | **—** |
| **—** | **Total (somme des notes IA)** | **60 / 70** | **—** |

### Synthèse à compléter

- **Moyenne des notes IA :** **4,29** / 5
- **Total des notes IA :** **60** / 70
- **Nombre de cas bloquants (note ≤ 2) :** **1** (UC-07 — `PUT /employees/{id}` renvoie 500)
- **Moyenne des notes humaines :** ____ / 5
- **Écart moyen IA / humain :** ____

---

## Annexes

### A. Commandes utiles pour le testeur

```bash
# Cloner le dépôt
git clone https://github.com/Kitokoh/leopardo-hr.git && cd leopardo-hr

# Santé de l'API de production
curl -s https://gestionemployerbackend.onrender.com/api/v1/health | jq

# Tests du kiosque (sans dépendance externe)
cd front/zkteco-kiosk && npm run lint && npm test

# Tests backend (nécessite PHP 8.4 + PostgreSQL + Redis)
cd api && composer install && php artisan test

# Tests front
cd front/web && npm ci && npm test
```

### B. Résultats de la recette technique initiale (2026-11-03)

| Vérification | Résultat |
| :--- | :--- |
| `GET /api/v1/health` | 200 — base, Redis, stockage et files OK |
| `GET /api/v1/health/live` | 200 |
| `GET /api/v1/health/ready` | 503 — Redis `ConnectionException` |
| `GET /api/v1/demo-users` | 503 — `DEMO_PASSWORD is not configured` |
| `GET /docs` | 500 — page d'erreur serveur |
| `GET /api/v1/employees` sans jeton | 401 `UNAUTHENTICATED` (conforme) |
| `POST /api/v1/auth/login` | 429 `TOO_MANY_REQUESTS_DEGRADED` (limiteur de débit) |
| Vitrine web | 200 |
| Admin plateforme | 200 |
| Tests kiosque (`npm test`) | 40 tests OK (i18n 4 langues / 228 clés, feedback, bridge) |
| Tests backend | Non exécutés — PHP/Composer/Docker absents de l'environnement d'analyse |

### C. Limites connues de la recette

1. **Exécution backend non réalisée** : l'environnement d'analyse ne disposait ni de PHP 8.4, ni de Composer, ni de Docker. Les 1 399 fichiers de tests PHP n'ont donc pas été exécutés ; seule leur présence et leur organisation ont été vérifiées.
2. **Authentification non validée en direct** : le limiteur de débit de l'API de production a renvoyé 429 sur les tentatives de connexion. Le testeur IA doit prévoir un délai entre les tentatives ou utiliser un environnement de test dédié.
3. **Mode démo désactivé** : `DEMO_MODE_ENABLED` est actif mais `DEMO_PASSWORD` n'est pas configuré, ce qui rend `/demo-users` inutilisable.
4. **Publication GitHub Pages** : le dépôt n'est pas accessible en écriture depuis l'environnement d'analyse (aucun identifiant Git). Les fichiers de publication sont fournis prêts à être poussés.

### D. Environnement d'exécution reconstruit (LENV) — 2026-11-03

| Composant | Version installée | Statut |
| :--- | :--- | :--- |
| PHP | 8.4.27 (dépôt Sury) + extensions (pdo_pgsql, redis, bcmath, mbstring, xml, zip, gd, intl, sqlite3…) | ✅ |
| Composer | 2.11.1 | ✅ |
| Node.js / npm | 20.20.2 / 11.19.0 | ✅ |
| PostgreSQL | 15.19 + PostGIS 3 | ✅ (schémas `public` + `shared_tenants`) |
| Redis | 7.0.15 | ✅ |
| Python | 3.11.15 | ✅ |
| Chromium (headless) | via Playwright | ✅ |
| Docker | non disponible (non requis) | ⚠️ |
| Flutter | non installé (apps mobiles non exécutées) | ⚠️ |

**Bootstrap réalisé :** `composer install` (105 paquets), migrations `public` (78 tables) + `tenant` (513 tables), `DatabaseSeeder` + `DemoCompanySeeder` (3 entreprises, 15 personas), API servie sur `127.0.0.1:8000`, pont kiosque sur `127.0.0.1:8037`.

**Résultats des suites de tests exécutées :**

| Suite | Commande | Résultat |
| :--- | :--- | :--- |
| Kiosque (Node + Python) | `npm test` | **40 tests OK** (i18n 4 langues/228 clés, feedback, bridge globals + sécurité) |
| Front web (Jest) | `npx jest --ci --maxWorkers=1` | **198 suites / 1688 tests OK** |
| Backend Unit (Pest/PHPUnit) | `php artisan test tests/Unit/...` par lots | **68/73 lots verts**, 145 classes PASS ; 5 classes en échec pour cause d'isolation de base (tables déjà présentes / PostgreSQL en recovery), non imputables au produit |
| Backend Feature (ciblé) | `php artisan test tests/Feature/{Absences,CRM,Attendance,HR}` | **Absences 50/50 OK**, **CRM 92/92 OK**, Attendance (I18n 3/3, TimezoneUtc 2/2) OK ; `AttendanceRecordedEventTest` 2 échecs (isolation) ; `EmployeesRbacTest` interrompu (timeout) |
| Build front Next.js | `npx next build` | ❌ tué (exit 137) — limite mémoire de l'environnement (2 Go) |

**Bug produit identifié :** `PUT /api/v1/employees/{id}` → **500** (`Unknown named parameter $contract_type`, `UpdateEmployeeDTO.php:66`). Voir UC-07.

### E. Prompt de l'agent testeur IA

Voir le fichier `prompt-agent-testeur.md` livré avec ce cahier.
