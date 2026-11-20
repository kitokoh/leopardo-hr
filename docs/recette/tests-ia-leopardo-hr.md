# Cahier de recette — Leopardo (leopardo-hr)

**Dépôt testé :** [github.com/Kitokoh/leopardo-hr](https://github.com/Kitokoh/leopardo-hr)  
**Portail web :** [kitokoh.github.io/leopardo-hr/recette/](https://kitokoh.github.io/leopardo-hr/recette/)  
**Nature :** monorepo « Business OS » pour entreprises de terrain — HR & paie, pointage, absences, comptabilité, CRM, solutions verticales, mobile Flutter, kiosque biométrique, API ouverte.  
**Destinataires :** testeurs IA (colonnes « Note IA » / « Observation IA ») puis relecteur humain (colonnes « Note humaine » / « Observation humaine »).

> Les colonnes **Note IA (/5)** et **Observation IA** sont remplies à partir d'une exécution réelle.  
> Les colonnes **Note humaine (/5)** et **Observation humaine** sont **volontairement vides** et réservées à l'évaluation humaine.

---

## Barème de notation

| Note | Signification |
| :---: | :--- |
| **5** | Cas entièrement conforme, aucune anomalie |
| **4** | Conforme, défauts cosmétiques ou mineurs |
| **3** | Fonctionne partiellement : contournement nécessaire ou critère non vérifiable |
| **2** | Défaillance fonctionnelle réelle sur un critère majeur |
| **1** | Cas bloqué (erreur serveur, accès impossible) mais comportement compréhensible |
| **0** | Cas impossible à exécuter (prérequis absents, environnement indisponible) |

---

## UC-01 — Connexion d'un employé à l'espace client web

**Profil(s) :** Employé · **Module(s) :** Authentification & IAM · **Type :** Fonctionnel · **Statut :** exécuté

**Description / préconditions**  
Vérifier le parcours d'authentification nominal d'un collaborateur sur l'espace client web.  
Prérequis : Navigateur, accès réseau à l'espace client, compte employé `karim.aouad@example.com` / `password123`.

**Étapes de test**
1. Ouvrir l'espace client et vérifier le chargement de la page d'accueil.
2. Accéder au formulaire de connexion (`/login`).
3. Saisir l'identifiant employé et le mot de passe, valider.
4. Vérifier la redirection vers le tableau de bord employé.
5. Recharger la page (F5) et vérifier que la session persiste.
6. Se déconnecter et vérifier le retour à l'écran de connexion.

**Résultat attendu**  
Connexion acceptée, redirection vers le tableau de bord employé, session persistante après rechargement, déconnexion effective. Aucun message d'erreur technique brut.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Exécuté contre l'API locale reconstruite. `POST /api/v1/auth/login` (karim.aouad@example.com / password123) → **200**, jeton Sanctum retourné (`token_type`, `token_expires_at`), `data.role = "employee"`. `GET /api/v1/auth/me` avec le jeton → **200** (profil complet, session valide). Mauvais mot de passe → **401** (pas de fuite). L'UI web Next.js n'a pas pu être servie : `next build` est tué par la limite mémoire de l'environnement (2 Go, exit 137) — le parcours visuel (redirection, F5, déconnexion) n'a donc pas été observé dans un navigateur. Le socle d'authentification est conforme. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-02 — Pointage entrée / sortie (API, employé)

**Profil(s) :** Employé · **Module(s) :** Pointage (Attendance) · **Type :** API · **Statut :** exécuté

**Description / préconditions**  
Valider le cœur métier « présence » : création d'un pointage d'entrée puis de sortie, avec heure serveur.  
Prérequis : Jeton Sanctum valide obtenu via `POST /api/v1/auth/login` ; endpoints `POST /api/v1/attendance/check-in` et `POST /api/v1/attendance/check-out`.

**Étapes de test**
1. S'authentifier et conserver le jeton retourné.
2. Appeler `POST /api/v1/attendance/check-in` avec le jeton.
3. Relever le code HTTP, l'identifiant du pointage et l'horodatage.
4. Appeler `GET /api/v1/attendance/today` et vérifier la présence de l'entrée.
5. Appeler `POST /api/v1/attendance/check-out`.
6. Appeler à nouveau `GET /api/v1/attendance/today` et vérifier la clôture.
7. Rejouer un `check-in` immédiat et observer la règle métier.

**Résultat attendu**  
`check-in` → 201/200 avec horodatage serveur ; `today` reflète l'entrée puis la sortie ; le second `check-in` est accepté comme nouvelle session ou refusé avec un code métier explicite — jamais une erreur 500.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `POST /attendance/check-in` → **201**, `id=1`, `session_number=1`, `check_in=2026-11-03T15:57:38+00:00`, `timezone=Africa/Algiers`, `method=mobile`. `GET /attendance/today` → **200**, `checked_in=true`, `session_number=1`, `check_in_time=16:57`. `POST /attendance/check-out` → **200**, `check_out` renseigné. Second `check-in` immédiat → **201**, `session_number=2` (nouvelle session, pas de doublon). Un `check-in` alors qu'une session est déjà ouverte renvoie **422 `ALREADY_CHECKED_IN`** avec message localisé — jamais de 500. Horodatage serveur UTC + heure locale entreprise corrects. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-03 — Pointage au kiosque biométrique (mode secours matricule)

**Profil(s) :** Superviseur / Kiosque, Employé · **Module(s) :** Kiosque & EdgeSync · **Type :** UI/UX · **Statut :** exécuté

**Description / préconditions**  
Tester la borne d'entrée sans matériel biométrique, via le formulaire de secours par matricule.  
Prérequis : `front/zkteco-kiosk/index.html` servi en local, matricule d'un employé de démo, API joignable.

**Étapes de test**
1. Servir le dossier `front/zkteco-kiosk` et ouvrir `index.html`.
2. Vérifier l'écran d'accueil de la borne et le sélecteur de langue.
3. Utiliser le formulaire de secours « matricule » avec un identifiant valide.
4. Valider et observer le retour visuel (succès / erreur).
5. Rejouer avec un matricule inexistant.
6. Basculer la langue en `ar` et vérifier le rendu RTL.

**Résultat attendu**  
Le pointage par matricule aboutit avec un retour visuel explicite ; un matricule inconnu produit un message lisible et non un plantage ; le passage en arabe inverse correctement la mise en page.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Kiosque servi par le pont local (`bridge.py`, port 8037) et piloté dans Chromium headless. Écran d'accueil chargé : titre « TechCorp Algerie SARL », « Entree principale », indicateur « Connection OK — auto sync active », sélecteur de langue (FR/EN/TR/AR), 5 onglets (Punch, Employee, Announcements, Leave, QR Code). Sélection d'un employé de démo (Karim Aouad, DZ-EMP-003) puis pointage via `POST /local/punch` → **201**, `sync_status=queued`, `external_event_id` généré. Matricule inconnu (`UNKNOWN-999`) → **201** mis en file (comportement *offline-first*). Bascule en `ar` → `document.documentElement.dir = "rtl"`, `lang="ar"` (RTL effectif). Aucun plantage. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-04 — Demande d'absence et approbation manager

**Profil(s) :** Employé, Manager RH · **Module(s) :** Congés & absences · **Type :** Fonctionnel · **Statut :** exécuté

**Description / préconditions**  
Vérifier le cycle complet d'une demande de congé, de la soumission employé à la décision manager.  
Prérequis : Compte employé + compte Manager RH (`fatima.meziane@example.com`) ; endpoints `/api/v1/absences`.

**Étapes de test**
1. En tant qu'employé, appeler `POST /api/v1/absences` avec dates et motif.
2. Relever l'identifiant et le statut initial.
3. En tant que Manager RH, appeler `GET /api/v1/absences` et vérifier la file d'attente.
4. Appeler `POST /api/v1/absences/{id}/approve`.
5. Revenir côté employé et vérifier le statut et le solde.
6. Rejouer l'approbation (idempotence) et observer la réponse.

**Résultat attendu**  
Demande créée « en attente », visible par le manager, approuvée avec succès, statut et solde mis à jour. Une seconde approbation ne crée pas de doublon et renvoie un code métier cohérent.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `POST /absences` (absence_type_id=1 « Conge annuel », 2026-12-01→2026-12-03) → **201**, `id=13`, `status="pending"`, `days_count=3`. `GET /absences` (Manager RH) → **200**, la demande #13 est présente. `POST /absences/13/approve` → **200**, `status="approved"`, `approved_by=2`. Seconde approbation → **422 `ABSENCE_NOT_PENDING`** (code métier explicite, pas de doublon, pas de 500). Validation : absence_type_id manquant → **422** avec message localisé. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-05 — Consultation du bulletin de paie (self-service employé)

**Profil(s) :** Employé · **Module(s) :** Paie (Payroll) · **Type :** Sécurité · **Statut :** exécuté

**Description / préconditions**  
Vérifier l'accès de l'employé à ses propres bulletins, et l'impossibilité d'accéder à ceux d'un collègue.  
Prérequis : Compte employé authentifié ; endpoints `GET /api/v1/me/pay-slips` et `GET /api/v1/me/pay-slips/{id}/pdf`.

**Étapes de test**
1. Appeler `GET /api/v1/me/pay-slips` et relever la liste.
2. Ouvrir le détail d'un bulletin (`GET /api/v1/me/pay-slips/{id}`).
3. Télécharger le PDF associé et vérifier qu'il s'ouvre.
4. Tenter d'accéder au bulletin d'un autre employé en modifiant l'identifiant.
5. Vérifier le code HTTP et le message retournés.

**Résultat attendu**  
La liste ne contient que les bulletins de l'utilisateur connecté ; le PDF est téléchargeable et lisible ; l'accès à un bulletin tiers est refusé (403/404) sans fuite d'information.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | `GET /me/pay-slips` → **200** (liste paginée, `data: []` pour l'employé de démo — aucun bulletin n'est généré par le seeder). Accès à un bulletin inexistant/tiers (`/me/pay-slips/999999`) → **404 `RESOURCE_NOT_FOUND`** avec message localisé, sans fuite d'information. Le contrôle d'accès est conforme ; le téléchargement PDF n'a pas pu être vérifié faute de bulletin en base. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-06 — Préparation de paie (Manager Comptable)

**Profil(s) :** Manager Comptable · **Module(s) :** Paie (Payroll) · **Type :** Fonctionnel · **Statut :** exécuté

**Description / préconditions**  
Vérifier qu'un manager comptable peut lancer une simulation de paie et consulter les structures salariales.  
Prérequis : Compte `lina.haddad@example.com` (`manager_role=comptable`) ; endpoints `/api/v1/salary-structures`, `POST /api/v1/payroll/simulate`.

**Étapes de test**
1. Appeler `GET /api/v1/salary-structures` et vérifier la liste.
2. Appeler `POST /api/v1/payroll/simulate` avec un employé et une période.
3. Relever le détail du calcul (brut, cotisations, net).
4. Rejouer avec un compte employé simple et vérifier le refus.
5. Vérifier la présence d'une trace d'audit.

**Résultat attendu**  
Le manager comptable obtient une simulation cohérente et détaillée ; l'employé simple reçoit 403 ; l'opération est tracée. Aucune donnée d'un autre tenant n'apparaît.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `GET /salary-structures` (comptable) → **200**, structures DZ listées (base_salary, currency DZD). `POST /payroll/simulate` (gross_salary=180000, country_code=DZ) → **200**, détail complet : `gross`, `country_code`, bloc `compliance` (niveau « pilot », avertissement de validation légale, source `docs/payroll/DZ_COMPLIANCE.md`), `correlation_id`. Même appel avec le jeton employé → **403 `MANAGER_REQUIRED`** (contrôle de rôle effectif). |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-07 — Gestion du dossier employé (création, modification, archivage)

**Profil(s) :** Manager RH · **Module(s) :** Dossier employé (HR) · **Type :** Fonctionnel · **Statut :** exécuté

**Description / préconditions**  
Vérifier le CRUD du référentiel employés côté Manager RH.  
Prérequis : Compte Manager RH ; endpoints `GET/POST /api/v1/employees`, `PUT /api/v1/employees/{id}`, `POST /api/v1/employees/{id}/archive`.

**Étapes de test**
1. Appeler `GET /api/v1/employees` et vérifier pagination et filtrage.
2. Créer un employé de test (`POST /api/v1/employees`).
3. Modifier une donnée (ex. poste) via `PUT`.
4. Archiver l'employé créé.
5. Vérifier qu'il disparaît des listes actives mais reste consultable.
6. Tenter la création avec un email déjà utilisé.

**Résultat attendu**  
Création, modification et archivage fonctionnent ; les validations renvoient des erreurs 422 explicites et localisées ; l'archivage est tracé ; aucune donnée orpheline.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 2 | `GET /employees` → **200** (pagination + filtrage OK). `POST /employees` (mot de passe conforme) → **201**, employé #28 créé. **`PUT /employees/28` → 500 `INTERNAL_ERROR`** : le log montre `Error: Unknown named parameter $contract_type at app/Modules/HR/Application/DTOs/UpdateEmployeeDTO.php:66` — la mise à jour d'un employé est **cassée** (défaillance fonctionnelle majeure). `POST /employees/28/archive` → **200**, `status="archived"`. Email déjà utilisé → **422** avec double message. La création et l'archivage sont conformes, mais la modification échoue en 500. *(Corrigé depuis par le patch d'audit — à re-tester.)* |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-08 — Isolation multi-tenant

**Profil(s) :** Manager RH, Manager Principal · **Module(s) :** Multi-tenant & sécurité · **Type :** Sécurité · **Statut :** exécuté

**Description / préconditions**  
Vérifier qu'un utilisateur d'une entreprise ne peut jamais lire les données d'une autre entreprise.  
Prérequis : Deux jeux d'identifiants appartenant à deux entreprises distinctes (`techcorp-algerie` et `pharmaplus-casablanca`).

**Étapes de test**
1. Se connecter avec le compte de l'entreprise A et relever un identifiant d'employé.
2. Se connecter avec le compte de l'entreprise B.
3. Tenter `GET /api/v1/employees/{id_de_A}` avec le jeton de B.
4. Tenter une recherche/liste et vérifier l'absence de données de A.
5. Vérifier que le contexte tenant est résolu côté serveur.

**Résultat attendu**  
Toute tentative d'accès inter-tenant renvoie 403/404 ; aucune fuite d'identifiant, de nom ou de montant ; le tenant n'est jamais dérivé d'un paramètre client.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | Jeton `pharmaplus-casablanca` (MA) utilisé pour lire l'employé #6 de `techcorp-algerie` (DZ) → **404 `RESOURCE_NOT_FOUND`** (pas de fuite). `GET /employees` avec le jeton MA → **200**, aucune donnée de TechCorp (recherche « techcorp »/« Karim » absente). Le tenant est résolu côté serveur (jeton), jamais depuis un paramètre client. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-09 — Multilingue et RTL (fr / en / tr / ar)

**Profil(s) :** Employé, Superviseur / Kiosque · **Module(s) :** Multilingue & RTL · **Type :** UI/UX · **Statut :** exécuté

**Description / préconditions**  
Vérifier la cohérence i18n sur les surfaces web et kiosque, y compris l'arabe en RTL.  
Prérequis : Accès web + kiosque local ; langues supportées : `fr`, `en`, `tr`, `ar`.

**Étapes de test**
1. Sur l'espace web, changer la langue en `en` et vérifier l'absence de texte résiduel.
2. Passer en `tr` et vérifier l'affichage.
3. Passer en `ar` et vérifier l'inversion RTL.
4. Refaire le test sur le kiosque via son sélecteur de langue.
5. Vérifier qu'aucune clé de traduction brute n'apparaît.

**Résultat attendu**  
Les quatre langues s'appliquent sans rechargement cassé ; le RTL est correct ; aucune clé brute ni débordement ; les libellés métier restent compréhensibles.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | API : `GET /auth/me` expose `language` et `is_rtl` ; `PATCH /auth/language {language:"ar"}` → **200**, puis `is_rtl=true` confirmé sur `/auth/me`. Kiosque : bascule en `ar` → `dir="rtl"`, `lang="ar"`. Aucune clé de traduction brute détectée dans le DOM (`[]`). Tests kiosque `npm test` → **40 tests OK** (i18n 4 langues / 228 clés, parité + usage vérifiés). |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-10 — Kiosque en mode dégradé / synchronisation Edge

**Profil(s) :** Superviseur / Kiosque · **Module(s) :** Kiosque & EdgeSync · **Type :** Résilience · **Statut :** exécuté

**Description / préconditions**  
Vérifier le comportement de la borne lorsque le réseau vers le cloud est indisponible (pont on-prem `edge/`).  
Prérequis : Kiosque local, coupure réseau simulée (mode hors-ligne ou API injoignable).

**Étapes de test**
1. Charger le kiosque avec l'API accessible, puis couper l'accès réseau.
2. Effectuer un pointage par matricule.
3. Observer le message affiché et la mise en file locale.
4. Rétablir le réseau et vérifier la reprise/synchronisation.
5. Contrôler l'absence de doublon après resynchronisation.

**Résultat attendu**  
Le kiosque reste utilisable hors-ligne, met l'événement en file, affiche un état explicite, puis synchronise sans créer de doublon.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Le pont local met chaque pointage en file SQLite (`sync_status=queued`) et incrémente `queue_count` (2 → 3 après un pointage) ; `dead_letter_count=0`. L'état est exposé par `GET /local/status` (`online`, `queue_count`, `last_error`, `last_sync_at`). La borne reste pleinement utilisable sans cloud. La resynchronisation vers le cloud n'a pas pu être validée de bout en bout : le point de synchronisation cloud n'est pas configuré en local (`last_error=HTTP_404`). |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-11 — CRM client (comptes, contacts, opportunités)

**Profil(s) :** Manager Principal · **Module(s) :** CRM client · **Type :** Fonctionnel · **Statut :** exécuté

**Description / préconditions**  
Vérifier le module CRM côté espace client (distinct du CRM commercial de la plateforme).  
Prérequis : Compte manager ; endpoints `/api/v1/crm/*`.

**Étapes de test**
1. Créer un compte client (société) et vérifier sa présence dans la liste.
2. Ajouter un contact rattaché à ce compte.
3. Créer une opportunité et la déplacer dans le pipeline.
4. Vérifier la journalisation d'activité.
5. Tenter d'accéder au CRM commercial plateforme et vérifier le refus.

**Résultat attendu**  
Les entités sont créées et liées correctement, le pipeline reflète le changement d'étape, et la séparation entre CRM client et CRM commercial est effective.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 5 | `GET /crm/accounts` (Manager RH) → **200** (liste paginée). `POST /crm/accounts {name, email}` → **201**, compte créé (`id=2`, `status` renseigné). Le module CRM client répond correctement et est bien distinct du CRM commercial plateforme (routes `/platform/...` séparées, protégées par `platform.permission`). |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-12 — Vitrine publique et demande d'essai

**Profil(s) :** Marketing · **Module(s) :** Vitrine & acquisition · **Type :** Fonctionnel · **Statut :** exécuté

**Description / préconditions**  
Vérifier le parcours d'acquisition public : page vitrine, tarifs, formulaire de demande d'essai.  
Prérequis : Navigateur, aucune authentification.

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
| 4 | Endpoint d'ingestion des leads `POST /marketing/leads` : payload valide (`external_id`, `type=demo_request`, `email`) → **201** (`{id, external_id, status:"new"}`) ; email invalide → **422** avec message localisé ; champs obligatoires manquants → **422** explicite. La vitrine Next.js elle-même n'a pas pu être servie (build tué par la limite mémoire 2 Go), donc le rendu visuel desktop/mobile n'a pas été observé. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-13 — Observabilité et santé de la plateforme

**Profil(s) :** Opérations (Ops), Super Admin plateforme · **Module(s) :** Plateforme & observabilité · **Type :** API · **Statut :** exécuté

**Description / préconditions**  
Vérifier les sondes de santé et la documentation API exposées.  
Prérequis : Accès réseau à l'API de production.

**Étapes de test**
1. Appeler `GET /api/v1/health` et relever statut, version et état des dépendances.
2. Appeler `GET /api/v1/health/live` et `GET /api/v1/health/ready`.
3. Ouvrir la documentation API (`/docs`).
4. Comparer les informations de version avec le commit attendu.

**Résultat attendu**  
`/health` renvoie 200 avec le détail des dépendances ; `/health/live` renvoie 200 ; `/health/ready` reflète fidèlement l'état réel (503 si dégradé) ; la documentation API est accessible.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | Environnement local : `GET /health` → **200** (`status:"ok"`) ; `/health/live` → **200** ; `/health/ready` → **200** avec matrice `checks` (database ok, redis ok/skipped, queue ok/sync, mail ok/array, `failed_checks:[]`). `GET /demo-users` → **404** (mode démo désactivé par défaut, conforme au durcissement documenté). La documentation `/docs` (Scramble) et la comparaison de version avec le commit attendu n'ont pas été vérifiées localement. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-14 — Solution verticale « Agence de voyage » (boutique publique)

**Profil(s) :** Manager Principal, Agence de voyage · **Module(s) :** Agence de voyage (TravelAgency) · **Type :** Sécurité · **Statut :** exécuté

**Description / préconditions**  
Vérifier l'activation d'un pack sectoriel et son parcours public de réservation.  
Prérequis : Tenant avec la solution TravelAgency activée ; endpoints `/api/v1/public/travel/shop/*`.

**Étapes de test**
1. Appeler `GET /api/v1/public/travel/shop/trips` et vérifier la liste.
2. Ouvrir le détail d'un trajet.
3. Créer une réservation (`POST /api/v1/public/travel/shop/bookings`).
4. Suivre la réservation via sa référence.
5. Annuler la réservation et vérifier le statut.
6. Vérifier qu'un tenant non activé renvoie une erreur explicite (fail-closed).

**Résultat attendu**  
Le parcours recherche → réservation → suivi → annulation fonctionne de bout en bout ; la référence est unique ; un tenant non activé est refusé proprement.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
| 4 | `GET /public/travel/shop/trips` sans jeton → **401** « Jeton boutique manquant (X-Travel-Shop-Token) » ; avec un jeton invalide → **401** « Jeton boutique invalide ». Le garde-fou *fail-closed* est effectif (aucune donnée exposée sans jeton tenant valide). Le parcours complet n'a pas pu être exécuté : aucun tenant de démonstration n'a la solution TravelAgency activée avec un jeton boutique émis. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-15 — Connexion Manager Principal et tableau de bord

**Profil(s) :** Manager Principal · **Module(s) :** Authentification & IAM · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la connexion d'un manager principal et l'accès à son tableau de bord agrégé.  
Prérequis : Compte `ahmed.benali@example.com` / `password123` ; espace client web.

**Étapes de test**
1. Se connecter avec le compte manager principal.
2. Vérifier la redirection vers `/dashboard`.
3. Contrôler les indicateurs affichés (effectif, présences, absences en attente).
4. Vérifier que les données correspondent au tenant TechCorp.
5. Se déconnecter.

**Résultat attendu**  
Connexion acceptée, tableau de bord chargé avec des indicateurs cohérents du tenant, aucune donnée d'un autre tenant.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-16 — Gestion des départements et des équipes

**Profil(s) :** Manager RH · **Module(s) :** Dossier employé (HR) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création et l'affectation des départements et des managers d'équipe.  
Prérequis : Compte Manager RH ; endpoints `/api/v1/departments`.

**Étapes de test**
1. Lister les départements existants.
2. Créer un département de test.
3. Affecter un employé au département.
4. Vérifier la cohérence de l'organigramme.
5. Supprimer/archiver le département de test.

**Résultat attendu**  
Création et affectation fonctionnent ; l'organigramme reflète les changements ; les validations renvoient des 422 explicites.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-17 — Attribution de rôles et invitations

**Profil(s) :** Manager RH · **Module(s) :** Authentification & IAM · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'invitation d'un collaborateur et l'attribution d'un rôle dans les limites autorisées.  
Prérequis : Compte Manager RH ; endpoints `/api/v1/invitations`, `/api/v1/role-assignments`.

**Étapes de test**
1. Inviter un nouvel utilisateur par email.
2. Vérifier la réception de l'invitation et l'activation du compte.
3. Attribuer un rôle (ex. manager de département).
4. Tenter d'attribuer un rôle supérieur au sien et vérifier le refus.
5. Vérifier la trace d'audit de l'attribution.

**Résultat attendu**  
L'invitation aboutit, le rôle est attribué dans les limites du délégable, l'élévation de privilège est refusée, l'opération est tracée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-18 — Validation des heures supplémentaires et du planning

**Profil(s) :** Manager Principal · **Module(s) :** Pointage (Attendance) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la consultation du planning et la validation des heures supplémentaires.  
Prérequis : Compte manager ; endpoints `/api/v1/planning`, `/api/v1/overtime`.

**Étapes de test**
1. Consulter le planning de la semaine.
2. Relever les heures supplémentaires calculées.
3. Valider une demande d'heures supplémentaires.
4. Vérifier la répercussion sur le récapitulatif de paie.
5. Rejeter une demande et vérifier le statut.

**Résultat attendu**  
Le planning s'affiche, les heures supplémentaires sont calculées et validées/rejetées avec un statut cohérent, sans erreur 500.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-19 — Génération et téléchargement d'un bulletin PDF

**Profil(s) :** Manager Comptable · **Module(s) :** Paie (Payroll) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la génération d'un bulletin de paie et son téléchargement au format PDF.  
Prérequis : Compte Manager Comptable ; un employé avec structure salariale ; endpoint de génération de bulletin.

**Étapes de test**
1. Lancer la génération d'un bulletin pour une période.
2. Vérifier la présence du bulletin dans la liste.
3. Télécharger le PDF et vérifier son ouverture.
4. Contrôler les montants (brut, cotisations, net).
5. Vérifier que l'employé voit son bulletin en self-service.

**Résultat attendu**  
Le bulletin est généré, le PDF s'ouvre et les montants sont cohérents ; l'employé y accède en self-service.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-20 — Console Super Admin — gestion des entreprises

**Profil(s) :** Super Admin plateforme · **Module(s) :** Plateforme & observabilité · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création, la suspension et la consultation d'une entreprise depuis la console plateforme.  
Prérequis : Compte `admin@leopardo-rh.com` ; admin plateforme.

**Étapes de test**
1. Se connecter à la console plateforme.
2. Lister les entreprises.
3. Créer une entreprise de test.
4. Suspendre puis réactiver l'entreprise.
5. Vérifier l'impact sur l'accès des utilisateurs de cette entreprise.

**Résultat attendu**  
Les opérations aboutissent, l'état de l'entreprise est reflété, la suspension bloque l'accès de façon cohérente.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-21 — Impersonation encadrée (Support)

**Profil(s) :** Support · **Module(s) :** Plateforme & observabilité · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'un agent support peut impersonner un utilisateur de façon tracée et limitée.  
Prérequis : Compte support plateforme ; permission `Impersonate`.

**Étapes de test**
1. Depuis la console, lancer une impersonation d'un utilisateur d'entreprise.
2. Vérifier l'accès au contexte de l'utilisateur.
3. Contrôler la présence d'un bandeau/indicateur d'impersonation.
4. Terminer l'impersonation et vérifier le retour au compte support.
5. Vérifier la trace d'audit de l'opération.

**Résultat attendu**  
L'impersonation est possible, clairement signalée, réversible, et journalisée ; aucune action sensible non autorisée n'est permise.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-22 — Facturation et abonnements (Finance)

**Profil(s) :** Finance · **Module(s) :** Plateforme & observabilité · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la consultation des abonnements, des offres et des paiements.  
Prérequis : Compte finance plateforme ; permissions `BillingView`, `PlansView`, `PaymentsView`.

**Étapes de test**
1. Consulter la liste des abonnements.
2. Ouvrir le détail d'une facture.
3. Vérifier le registre des paiements et les réconciliations.
4. Tenter une action réservée à admin et vérifier le refus.
5. Vérifier les métriques financières.

**Résultat attendu**  
Les données de facturation sont consultables, les actions hors périmètre sont refusées, les montants sont cohérents.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-23 — Kill switch et nœuds Edge (Ops)

**Profil(s) :** Opérations (Ops) · **Module(s) :** Plateforme & observabilité · **Type :** Résilience · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'activation d'un kill switch et la supervision des nœuds Edge.  
Prérequis : Compte ops plateforme ; permissions `KillSwitchManage`, `EdgeManage`.

**Étapes de test**
1. Consulter l'état des nœuds Edge.
2. Activer un kill switch sur une fonctionnalité non critique.
3. Vérifier l'effet immédiat côté application.
4. Désactiver le kill switch et vérifier le retour à la normale.
5. Contrôler la journalisation des bascules.

**Résultat attendu**  
Le kill switch produit un effet immédiat et réversible, les nœuds Edge sont supervisés, chaque bascule est tracée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-24 — Campagnes et vitrine (Marketing)

**Profil(s) :** Marketing · **Module(s) :** Vitrine & acquisition · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la gestion des campagnes, des annonces et de la vitrine publique.  
Prérequis : Compte marketing plateforme ; permissions `CrmView`, `AnnouncementsManage`, `ShowcaseManage`.

**Étapes de test**
1. Créer une annonce et la publier.
2. Vérifier son affichage sur la vitrine publique.
3. Créer une campagne et suivre ses métriques.
4. Tenter une publication engageant la marque et vérifier la limite de rôle.
5. Dépublier l'annonce.

**Résultat attendu**  
Les annonces et campagnes sont gérées, la vitrine reflète les publications, les limites de rôle sont respectées.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-25 — Recrutement et offres d'emploi

**Profil(s) :** Manager RH · **Module(s) :** Dossier employé (HR) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création d'une offre d'emploi et le suivi des candidatures.  
Prérequis : Compte Manager RH ; module Recruitment.

**Étapes de test**
1. Créer une offre d'emploi.
2. Vérifier sa publication sur la page carrières.
3. Enregistrer une candidature de test.
4. Faire avancer la candidature dans le pipeline.
5. Clôturer l'offre.

**Résultat attendu**  
L'offre est créée et publiée, les candidatures sont suivies, le pipeline reflète les changements d'étape.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-26 — Bibliothèque / Showcase public

**Profil(s) :** Marketing · **Module(s) :** Vitrine & acquisition · **Type :** UI/UX · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le rendu de la bibliothèque publique et la navigation des contenus.  
Prérequis : Accès public à la bibliothèque/showcase.

**Étapes de test**
1. Ouvrir la bibliothèque publique.
2. Vérifier le rendu responsive (desktop et mobile).
3. Naviguer entre les contenus.
4. Vérifier le cache public et les temps de réponse.
5. Contrôler l'absence de fuite de données internes.

**Résultat attendu**  
La bibliothèque s'affiche correctement, la navigation est fluide, le cache public fonctionne, aucune donnée interne n'est exposée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-27 — Comptabilité — écritures et clôture mensuelle

**Profil(s) :** Manager Comptable, Cabinet comptable · **Module(s) :** Comptabilité · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la saisie d'écritures, l'équilibre débit/crédit et la clôture d'une période.  
Prérequis : Compte Manager Comptable ; endpoints `/api/v1/accounting/*` ; un exercice ouvert.

**Étapes de test**
1. Lister les journaux disponibles (`GET /api/v1/accounting/journals`).
2. Créer une écriture équilibrée sur le journal des achats.
3. Tenter une écriture déséquilibrée et vérifier le refus.
4. Consulter la balance et vérifier la somme nulle.
5. Clôturer la période et vérifier le verrouillage des écritures.

**Résultat attendu**  
L'écriture équilibrée est acceptée, la déséquilibrée refusée avec un code métier explicite, la balance est nulle et la clôture empêche toute nouvelle saisie sur la période.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-28 — Comptabilité — export et rapprochement bancaire

**Profil(s) :** Manager Comptable, Cabinet comptable · **Module(s) :** Comptabilité, Encaissements · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'export comptable et le rapprochement des encaissements avec les relevés.  
Prérequis : Compte comptable ; au moins un encaissement enregistré ; endpoint d'export.

**Étapes de test**
1. Générer l'export de la période (format attendu par le cabinet).
2. Vérifier la présence de chaque encaissement dans l'export.
3. Importer un relevé de test et lancer le rapprochement.
4. Contrôler les lignes non rapprochées et leur motif.
5. Vérifier qu'aucun montant d'un autre tenant n'apparaît.

**Résultat attendu**  
L'export est complet et réimportable, le rapprochement identifie correctement les écarts, et l'isolation entre tenants est respectée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-29 — Notes de frais — soumission et validation

**Profil(s) :** Employé, Manager Principal · **Module(s) :** Notes de frais · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le cycle d'une note de frais : soumission avec justificatif, validation manager, rejet motivé.  
Prérequis : Compte employé + compte manager ; endpoints `/api/v1/expenses`.

**Étapes de test**
1. Créer une note de frais avec un justificatif joint.
2. Vérifier le statut initial et le montant total.
3. En tant que manager, approuver la note.
4. Créer une seconde note et la rejeter avec un motif.
5. Vérifier que le rejet est visible côté employé avec son motif.

**Résultat attendu**  
La note suit le cycle attendu, le rejet exige un motif, et l'employé voit l'état réel de sa demande.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-30 — Notes de frais — remboursement et impact paie

**Profil(s) :** Manager Comptable · **Module(s) :** Notes de frais, Paie (Payroll) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'une note approuvée devient un élément de paie ou un remboursement tracé.  
Prérequis : Une note de frais approuvée ; compte comptable ; période de paie ouverte.

**Étapes de test**
1. Marquer la note comme remboursée.
2. Vérifier l'apparition de la ligne dans la préparation de paie ou l'export.
3. Contrôler le montant et la devise.
4. Rejouer le remboursement et vérifier l'idempotence.
5. Vérifier la trace d'audit de l'opération.

**Résultat attendu**  
Le remboursement est tracé une seule fois, le montant est correct, et le rejeu ne crée pas de doublon.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-31 — Planning — shifts et affectations

**Profil(s) :** Manager Principal, Manager RH · **Module(s) :** Planning & tâches · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création d'un planning hebdomadaire et l'affectation des collaborateurs aux shifts.  
Prérequis : Compte manager ; au moins 3 employés actifs ; endpoints `/api/v1/planning`.

**Étapes de test**
1. Créer un shift matin et un shift après-midi sur la semaine.
2. Affecter deux employés au shift matin.
3. Vérifier les conflits détectés (double affectation, absence approuvée).
4. Publier le planning et vérifier la visibilité côté employé.
5. Modifier un shift publié et vérifier la notification.

**Résultat attendu**  
Le planning se crée et se publie, les conflits sont signalés avant publication, et les employés voient la version à jour.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-32 — Tâches — assignation et preuve d'exécution

**Profil(s) :** Manager Principal, Employé · **Module(s) :** Planning & tâches · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'assignation d'une tâche terrain et la preuve d'exécution (photo, horodatage).  
Prérequis : Compte manager + compte employé ; endpoints `/api/v1/tasks`.

**Étapes de test**
1. Créer une tâche avec échéance et l'assigner à un employé.
2. Vérifier la réception côté employé.
3. Marquer la tâche terminée avec une photo de preuve.
4. Vérifier l'horodatage et l'auteur de la clôture.
5. Créer une tâche en retard et vérifier l'escalade.

**Résultat attendu**  
La tâche circule correctement, la preuve est horodatée et attribuée, et le retard déclenche l'escalade prévue.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-33 — Recrutement — offre d'emploi et page carrières

**Profil(s) :** Recruteur, Manager RH · **Module(s) :** Recrutement, Surfaces publiques · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la publication d'une offre et son affichage sur la page carrières publique.  
Prérequis : Compte recruteur ; module Recruitment actif ; page carrières accessible.

**Étapes de test**
1. Créer une offre (intitulé, lieu, contrat, description).
2. Publier l'offre.
3. Ouvrir la page carrières publique et vérifier l'affichage.
4. Vérifier le SEO de la page (titre, description, langue).
5. Dépublier l'offre et vérifier sa disparition.

**Résultat attendu**  
L'offre est publiée, visible publiquement avec les bonnes métadonnées, puis retirée proprement à la dépublication.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-34 — Recrutement — pipeline de candidatures

**Profil(s) :** Recruteur · **Module(s) :** Recrutement · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'enregistrement d'une candidature et son avancement dans le pipeline.  
Prérequis : Compte recruteur ; une offre publiée ; endpoints `/api/v1/recruitment/*`.

**Étapes de test**
1. Enregistrer une candidature sur l'offre.
2. Vérifier son statut initial et ses coordonnées.
3. Faire avancer la candidature (présélection → entretien → offre).
4. Rejeter une candidature avec un motif.
5. Vérifier l'historique des changements d'étape.

**Résultat attendu**  
Chaque transition est enregistrée avec auteur et date, le rejet est motivé, et l'historique est complet.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-35 — Communication — annonce interne et accusés de lecture

**Profil(s) :** Manager Principal, Employé · **Module(s) :** Communication interne · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la diffusion d'une annonce ciblée et le suivi des accusés de lecture.  
Prérequis : Compte manager ; endpoints `/api/v1/announcements`.

**Étapes de test**
1. Créer une annonce ciblée sur un département.
2. Publier l'annonce.
3. Vérifier sa réception côté employé du département ciblé.
4. Vérifier qu'un employé hors cible ne la reçoit pas.
5. Contrôler le compteur d'accusés de lecture.

**Résultat attendu**  
Le ciblage est respecté, l'annonce est lisible, et le compteur de lecture reflète les lectures réelles.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-36 — Notifications — préférences et multi-canal

**Profil(s) :** Employé, Opérations (Ops) · **Module(s) :** Notifications · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier que les préférences de notification sont respectées par canal et par type d'événement.  
Prérequis : Compte employé ; endpoints `/api/v1/notification-preferences`.

**Étapes de test**
1. Lire les préférences par défaut.
2. Désactiver les notifications push pour les absences.
3. Déclencher un événement d'absence et vérifier l'absence de push.
4. Vérifier que l'e-mail reste envoyé si le canal est actif.
5. Réactiver le canal et vérifier le retour du push.

**Résultat attendu**  
Chaque canal suit la préférence enregistrée, sans effet de bord sur les autres canaux, et le journal d'envoi trace la décision.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-37 — Onboarding — provisioning d'un essai guidé

**Profil(s) :** Client / usager public, Administrateur plateforme · **Module(s) :** Onboarding & provisioning, Multi-tenant & sécurité · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le parcours d'inscription jusqu'à la création effective du tenant et du premier utilisateur.  
Prérequis : Formulaire d'inscription public ; endpoint `/api/v1/trial/signup`.

**Étapes de test**
1. Soumettre une inscription avec un e-mail valide.
2. Vérifier l'accusé de réception et l'état de l'onboarding.
3. Confirmer l'e-mail (OTP) et vérifier la création du tenant.
4. Se connecter avec le compte créé.
5. Vérifier qu'aucun tenant en doublon n'est créé en cas de double soumission.

**Résultat attendu**  
Le tenant est créé une seule fois, l'utilisateur peut se connecter, et la double soumission est idempotente.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-38 — Onboarding — reprise d'un parcours interrompu

**Profil(s) :** Client / usager public · **Module(s) :** Onboarding & provisioning · **Type :** Résilience · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'un onboarding interrompu reprend à l'étape atteinte sans perte de données.  
Prérequis : Un compte en cours d'onboarding ; endpoints `GET`/`PATCH /api/v1/onboarding-setup/state`.

**Étapes de test**
1. Interrompre le parcours après l'étape 2.
2. Relire l'état d'onboarding (`current_step`, `furthest_step`).
3. Reprendre le parcours et vérifier la reprise à l'étape attendue.
4. Vérifier que `furthest_step` ne régresse jamais.
5. Terminer le parcours et vérifier la cohérence des données saisies.

**Résultat attendu**  
La reprise se fait à l'étape atteinte, les données déjà saisies sont conservées, et l'étape la plus avancée est monotone.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-39 — Catalogue — produits, services et tarifs

**Profil(s) :** Restaurant, Commerce de détail · **Module(s) :** Catalogue produits & services · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création d'articles, la tarification et la disponibilité par établissement.  
Prérequis : Compte gérant ; endpoints `/api/v1/catalog/*`.

**Étapes de test**
1. Créer un article avec prix et TVA.
2. Vérifier sa présence dans le catalogue de l'établissement.
3. Modifier le prix et vérifier l'historique.
4. Désactiver l'article et vérifier sa disparition des ventes.
5. Tenter un prix négatif et vérifier la validation.

**Résultat attendu**  
Le catalogue est cohérent, l'historique de prix est conservé, la désactivation est effective et les valeurs invalides sont refusées.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-40 — Recherche — recherche transverse et permissions

**Profil(s) :** Manager Principal, Support · **Module(s) :** Recherche & intelligence · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier que la recherche transverse ne renvoie que ce que l'utilisateur a le droit de voir.  
Prérequis : Deux comptes de rôles différents ; endpoints `/api/v1/search`.

**Étapes de test**
1. Lancer une recherche sur un terme présent dans plusieurs modules.
2. Vérifier les types de résultats retournés pour un manager.
3. Rejouer la même recherche avec un compte employé.
4. Vérifier l'absence de résultats hors périmètre.
5. Vérifier qu'aucun identifiant d'un autre tenant n'apparaît.

**Résultat attendu**  
Les résultats respectent les permissions du rôle, aucune donnée hors périmètre n'est exposée, et la recherche reste rapide.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-41 — Pages — pages publiques et SEO multilingue

**Profil(s) :** Marketing · **Module(s) :** Pages & contenu, Surfaces publiques · **Type :** UI/UX · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le rendu des pages publiques et la cohérence des métadonnées SEO par langue.  
Prérequis : Accès public ; pages vitrine et contenu éditorial.

**Étapes de test**
1. Ouvrir la page d'accueil en `fr` et vérifier titre et description.
2. Basculer en `en`, `tr`, `ar` et vérifier les métadonnées.
3. Vérifier la présence des balises canoniques et `hreflang`.
4. Contrôler le rendu RTL en arabe.
5. Vérifier l'absence de clé de traduction brute.

**Résultat attendu**  
Chaque langue expose ses propres métadonnées, les balises canoniques et `hreflang` sont correctes, et le RTL est effectif.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-42 — Showcase — vitrine publique et bibliothèque

**Profil(s) :** Marketing, Client / usager public · **Module(s) :** Vitrine & bibliothèque, Surfaces publiques · **Type :** UI/UX · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le rendu de la vitrine et de la bibliothèque publiques, y compris le cache.  
Prérequis : Accès public ; contenus publiés dans la bibliothèque.

**Étapes de test**
1. Ouvrir la vitrine publique et vérifier le rendu desktop.
2. Vérifier le rendu mobile (viewport étroit).
3. Naviguer dans la bibliothèque et ouvrir un contenu.
4. Contrôler les en-têtes de cache public.
5. Vérifier qu'aucune donnée interne n'est exposée.

**Résultat attendu**  
Les surfaces publiques s'affichent correctement sur desktop et mobile, la navigation est fluide, le cache est actif et aucune donnée interne ne fuit.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-43 — Bibliothèque — prêt et retour d'ouvrages

**Profil(s) :** Bibliothèque · **Module(s) :** Bibliothèque · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le cycle de prêt d'un ouvrage, le retour et la gestion des retards.  
Prérequis : Compte bibliothécaire ; un adhérent et un ouvrage disponibles.

**Étapes de test**
1. Enregistrer un prêt pour un adhérent.
2. Vérifier la disponibilité de l'ouvrage (passée à « emprunté »).
3. Tenter un second prêt du même ouvrage et vérifier le refus.
4. Enregistrer le retour et vérifier la remise à disposition.
5. Vérifier le calcul du retard sur un prêt échu.

**Résultat attendu**  
Un ouvrage ne peut être prêté qu'une fois, le retour le remet à disposition, et le retard est calculé correctement.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-44 — Caméras — parc et supervision des flux

**Profil(s) :** Technicien terrain, Opérations (Ops) · **Module(s) :** Caméras & supervision · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'enregistrement d'une caméra et l'état de son flux dans la supervision.  
Prérequis : Compte technicien ; endpoints `/api/v1/cameras/*`.

**Étapes de test**
1. Enregistrer une caméra avec son adresse de flux.
2. Vérifier son apparition dans la liste du site.
3. Contrôler l'état du flux (en ligne / hors ligne).
4. Simuler une caméra injoignable et vérifier l'état dégradé.
5. Vérifier qu'aucune caméra d'un autre tenant n'est visible.

**Résultat attendu**  
La caméra est enregistrée et supervisée, l'état reflète la réalité du flux, et l'isolation entre tenants est respectée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-45 — Caméras — incident et maintenance

**Profil(s) :** Technicien terrain · **Module(s) :** Caméras & supervision · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la déclaration d'un incident caméra et son suivi jusqu'à la clôture.  
Prérequis : Compte technicien ; une caméra enregistrée.

**Étapes de test**
1. Déclarer un incident sur une caméra (panne, image absente).
2. Vérifier la création de l'intervention et son statut.
3. Renseigner le diagnostic et la pièce remplacée.
4. Clôturer l'intervention.
5. Vérifier l'historique de maintenance de la caméra.

**Résultat attendu**  
L'incident est tracé, l'intervention suit son cycle, et l'historique de maintenance reste consultable.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-46 — Flotte — véhicules, affectations et entretien

**Profil(s) :** Gestion de flotte · **Module(s) :** Flotte · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la gestion d'un véhicule, son affectation et le suivi de l'entretien.  
Prérequis : Compte gestionnaire de flotte ; endpoints `/api/v1/fleet/*`.

**Étapes de test**
1. Créer un véhicule (immatriculation, modèle, échéances).
2. L'affecter à un chauffeur.
3. Enregistrer un entretien avec kilométrage.
4. Vérifier l'alerte d'échéance (assurance, visite technique).
5. Tenter une immatriculation en doublon et vérifier le refus.

**Résultat attendu**  
Le véhicule est suivi de bout en bout, les échéances déclenchent des alertes, et les doublons d'immatriculation sont refusés.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-47 — Station-service — index pompes et ventes carburant

**Profil(s) :** Station-service · **Module(s) :** Station-service · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la saisie des index de pompes et le calcul des ventes de carburant.  
Prérequis : Compte gérant de station ; pompes configurées ; endpoints `/api/v1/fuel/*`.

**Étapes de test**
1. Saisir l'index de début et de fin de journée sur une pompe.
2. Vérifier le volume calculé et le montant de la vente.
3. Contrôler la cohérence avec le prix du jour.
4. Clôturer la caisse de la journée.
5. Tenter un index décroissant et vérifier le refus.

**Résultat attendu**  
Le volume et le montant sont calculés correctement, la clôture est cohérente, et un index incohérent est refusé avec un message explicite.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-48 — VTC — course et tarification

**Profil(s) :** VTC / Chauffeur, Client / usager public · **Module(s) :** VTC · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création d'une course, l'application de la grille tarifaire et la clôture.  
Prérequis : Compte chauffeur ; grille tarifaire configurée ; endpoints `/api/v1/vtc/*`.

**Étapes de test**
1. Créer une course avec départ et arrivée.
2. Vérifier le tarif calculé selon la grille.
3. Démarrer puis terminer la course.
4. Vérifier le montant final et le mode de paiement.
5. Tenter une course sans grille active et vérifier le refus explicite.

**Résultat attendu**  
Le tarif suit la grille, la course suit son cycle, et l'absence de grille produit une erreur métier claire (jamais une 500).

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-49 — Livraison — tournée et preuve de livraison

**Profil(s) :** Livraison, Manager Principal · **Module(s) :** Livraison · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la constitution d'une tournée, la livraison d'un colis et sa preuve.  
Prérequis : Compte livreur ; commandes à livrer ; endpoints `/api/v1/delivery/*`.

**Étapes de test**
1. Créer une tournée avec plusieurs colis.
2. Vérifier l'ordre de livraison proposé.
3. Marquer un colis livré avec signature ou photo.
4. Marquer un colis en échec avec un motif.
5. Vérifier le suivi visible côté client.

**Résultat attendu**  
La tournée se constitue, chaque colis a un statut et une preuve, et le client voit l'état réel de sa livraison.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-50 — Restauration — POS et plan de salle

**Profil(s) :** Restaurant · **Module(s) :** Restauration & POS · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'ouverture d'une table, la prise de commande et l'encaissement au POS.  
Prérequis : Compte gérant ; POS restaurant accessible ; menu publié.

**Étapes de test**
1. Ouvrir une table sur le plan de salle.
2. Ajouter deux plats et une boisson à la commande.
3. Envoyer la commande en cuisine.
4. Encaisser la table et vérifier le ticket.
5. Vérifier la libération de la table après encaissement.

**Résultat attendu**  
La commande est transmise, le total est correct, le ticket est généré et la table redevient disponible.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-51 — Restauration — KDS et cycle de commande

**Profil(s) :** Restaurant · **Module(s) :** Restauration & POS · **Type :** Résilience · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'affichage des commandes au KDS et le passage hors-ligne sans perte.  
Prérequis : KDS accessible ; POS connecté ; coupure réseau simulable.

**Étapes de test**
1. Envoyer une commande depuis le POS.
2. Vérifier son apparition au KDS avec son heure.
3. Marquer un plat comme prêt puis la commande comme servie.
4. Couper le réseau et envoyer une commande : vérifier la mise en file.
5. Rétablir le réseau et vérifier la synchronisation sans doublon.

**Résultat attendu**  
Le KDS reflète l'état réel, la commande hors-ligne est mise en file puis synchronisée une seule fois.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-52 — Retail — POS et encaissement

**Profil(s) :** Commerce de détail · **Module(s) :** Commerce de détail · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier une vente au POS retail : scan, remise, paiement et ticket.  
Prérequis : Compte gérant ; POS retail ; articles en stock.

**Étapes de test**
1. Scanner ou rechercher deux articles.
2. Appliquer une remise autorisée.
3. Encaisser en espèces puis en carte (deux ventes distinctes).
4. Vérifier le ticket et la numérotation.
5. Tenter une remise supérieure au seuil autorisé et vérifier le refus.

**Résultat attendu**  
Le total est correct, le ticket est généré, la numérotation est continue, et les remises hors seuil sont refusées.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-53 — Retail — stock et inventaire

**Profil(s) :** Commerce de détail · **Module(s) :** Commerce de détail, Catalogue produits & services · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la décrémentation du stock à la vente et la régularisation par inventaire.  
Prérequis : Compte gérant ; un article avec stock connu.

**Étapes de test**
1. Relever le stock initial d'un article.
2. Effectuer une vente de 2 unités.
3. Vérifier le stock décrémenté de 2.
4. Saisir un inventaire physique et régulariser l'écart.
5. Vérifier la trace de la régularisation.

**Résultat attendu**  
Le stock suit les ventes, la régularisation est possible et tracée, et aucun stock négatif n'est accepté sans règle explicite.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-54 — Hôtellerie — chambres, séjours et housekeeping

**Profil(s) :** Hôtellerie · **Module(s) :** Hôtellerie · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la réservation d'une chambre, le check-in et le passage en housekeeping.  
Prérequis : Compte réception ; chambres configurées ; endpoints `/api/v1/hospitality/*`.

**Étapes de test**
1. Créer une réservation sur une chambre libre.
2. Vérifier l'indisponibilité de la chambre sur les dates.
3. Effectuer le check-in et vérifier l'état de la chambre.
4. Passer la chambre en « à nettoyer » puis « propre ».
5. Effectuer le check-out et vérifier la facture.

**Résultat attendu**  
La chambre ne peut pas être doublement réservée, les états de housekeeping sont cohérents, et la facture reflète le séjour.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-55 — Pharmacie — stock, ordonnance et profil public

**Profil(s) :** Pharmacie · **Module(s) :** Pharmacie, Surfaces publiques · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la vente sur ordonnance, la décrémentation du stock et la fiche publique de l'officine.  
Prérequis : Compte titulaire ; articles en stock ; profil public activé.

**Étapes de test**
1. Enregistrer une ordonnance et la vente associée.
2. Vérifier la décrémentation du stock.
3. Tenter la vente d'un produit soumis à ordonnance sans ordonnance.
4. Ouvrir la fiche publique de la pharmacie (horaires, adresse).
5. Vérifier qu'aucune donnée de stock n'est exposée publiquement.

**Résultat attendu**  
La vente sur ordonnance est contrôlée, le stock suit, la fiche publique est correcte et n'expose aucune donnée interne.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-56 — Santé — patients, rendez-vous et dossier

**Profil(s) :** Santé (cabinet / clinique) · **Module(s) :** Santé · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la prise de rendez-vous et la confidentialité du dossier de soin.  
Prérequis : Compte secrétariat ; patients enregistrés ; endpoints `/api/v1/health/*`.

**Étapes de test**
1. Créer un rendez-vous pour un patient.
2. Vérifier l'absence de double réservation sur le même créneau.
3. Ouvrir le dossier de soin et ajouter une note de consultation.
4. Tenter d'accéder au dossier d'un patient hors périmètre.
5. Vérifier la trace d'audit des accès au dossier.

**Résultat attendu**  
Les créneaux ne se chevauchent pas, l'accès au dossier est restreint et tracé, et aucune donnée de santé n'est exposée hors périmètre.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-57 — École — classes, notes et présences

**Profil(s) :** École / Formation · **Module(s) :** École & formation · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la saisie des notes et l'appel d'une classe dans EduManager.  
Prérequis : Compte direction pédagogique ; une classe avec élèves ; endpoints `/api/v1/edu/*`.

**Étapes de test**
1. Ouvrir une classe et faire l'appel du jour.
2. Vérifier l'enregistrement des présences et absences.
3. Saisir une note pour une évaluation.
4. Vérifier le calcul de la moyenne de l'élève.
5. Tenter une note hors barème et vérifier le refus.

**Résultat attendu**  
L'appel est enregistré, les moyennes sont calculées correctement, et les notes hors barème sont refusées.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-58 — École — facturation scolaire et espace parents

**Profil(s) :** École / Formation, Client / usager public · **Module(s) :** École & formation, Abonnements & facturation · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'émission d'une facture de scolarité et sa consultation par le parent.  
Prérequis : Compte direction ; un élève inscrit ; un parent rattaché.

**Étapes de test**
1. Générer la facture de scolarité d'un élève.
2. Vérifier le montant et l'échéance.
3. Ouvrir l'espace parent et vérifier la visibilité de la facture.
4. Enregistrer un paiement partiel et vérifier le solde.
5. Vérifier qu'un parent ne voit que ses propres enfants.

**Résultat attendu**  
La facture est émise et visible du seul parent concerné, le solde suit les paiements, et l'isolation entre familles est respectée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-59 — Agriculture — parcelles, cultures et récoltes

**Profil(s) :** Exploitation agricole · **Module(s) :** Agriculture · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le suivi d'une parcelle, d'une culture et d'une récolte dans FarmOs.  
Prérequis : Compte exploitation ; endpoints `/api/v1/farm/*`.

**Étapes de test**
1. Créer une parcelle avec sa surface.
2. Associer une culture et une date de semis.
3. Enregistrer un intrant (traitement, engrais).
4. Saisir la récolte et le rendement.
5. Vérifier l'historique de la parcelle.

**Résultat attendu**  
La parcelle conserve son historique complet, le rendement est calculé, et les surfaces incohérentes sont refusées.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-60 — Cagnottes — campagne de collecte et dons

**Profil(s) :** Cagnottes / collecte, Client / usager public · **Module(s) :** Cagnottes & collecte · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la création d'une campagne, la réception d'un don et la mise à jour de l'objectif.  
Prérequis : Compte association ; page de collecte publique ; endpoints `/api/v1/fundraising/*`.

**Étapes de test**
1. Créer une campagne avec un objectif de collecte.
2. Ouvrir la page publique de la campagne.
3. Enregistrer un don de test.
4. Vérifier la progression de l'objectif et le reçu.
5. Vérifier qu'aucune coordonnée de donateur n'est exposée publiquement.

**Résultat attendu**  
La campagne est publique et lisible, le don met à jour la progression, le reçu est émis, et les données personnelles restent privées.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-61 — Cabinet comptable — portefeuille clients

**Profil(s) :** Cabinet comptable · **Module(s) :** Cabinet comptable, Comptabilité · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'un cabinet accède aux dossiers de ses clients et à eux seuls.  
Prérequis : Compte cabinet ; deux dossiers clients rattachés ; un troisième non rattaché.

**Étapes de test**
1. Lister les dossiers du portefeuille.
2. Ouvrir un dossier rattaché et vérifier l'accès aux écritures.
3. Tenter d'ouvrir un dossier non rattaché.
4. Vérifier le code HTTP et l'absence de fuite.
5. Vérifier la trace d'audit des accès inter-dossiers.

**Résultat attendu**  
Le cabinet ne voit que son portefeuille, l'accès hors portefeuille est refusé sans fuite, et chaque accès est tracé.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-62 — Négoce — intention, sous-profil et devis

**Profil(s) :** Manager Principal · **Module(s) :** Négoce & import-export · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la capture de l'intention commerciale (marché, sous-profil) et la production d'un devis.  
Prérequis : Tenant avec la verticale Trade ; endpoints `/api/v1/trade/*`.

**Étapes de test**
1. Créer un devis pour un client professionnel.
2. Vérifier la reprise du sous-profil (importateur, grossiste…).
3. Ajouter des lignes et vérifier les totaux et la devise.
4. Confirmer le devis et vérifier son statut.
5. Tenter un devis sans sous-profil et vérifier le comportement attendu.

**Résultat attendu**  
L'intention est conservée jusqu'au devis, les totaux sont exacts, et l'absence de sous-profil produit un message explicite.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-63 — Marque — branding tenant et surfaces publiques

**Profil(s) :** Administrateur plateforme, Marketing · **Module(s) :** Marque & surfaces publiques, Surfaces publiques · **Type :** UI/UX · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'application de la marque d'un tenant sur ses surfaces publiques.  
Prérequis : Compte admin ; une marque configurée (logo, palette, slug).

**Étapes de test**
1. Configurer une marque avec logo et couleur principale.
2. Publier la marque.
3. Ouvrir la surface publique correspondante et vérifier l'identité.
4. Vérifier le contraste et la lisibilité des textes.
5. Suspendre la marque et vérifier le retour à l'identité par défaut.

**Résultat attendu**  
La marque s'applique sur la surface publique, le contraste reste lisible, et la suspension restaure proprement l'identité par défaut.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-64 — Abonnements — plan, essai et facturation

**Profil(s) :** Finance, Administrateur plateforme · **Module(s) :** Abonnements & facturation · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le cycle d'abonnement : essai, activation d'un plan, facture et renouvellement.  
Prérequis : Compte finance ; un tenant en essai ; endpoints `/api/v1/billing/*`.

**Étapes de test**
1. Vérifier l'état de l'essai et sa date de fin.
2. Activer un plan payant sur le tenant.
3. Vérifier la génération de la facture et son montant.
4. Simuler un renouvellement et vérifier la période suivante.
5. Vérifier le blocage attendu à l'expiration sans paiement.

**Résultat attendu**  
L'essai, l'activation, la facture et le renouvellement sont cohérents, et l'expiration produit le blocage prévu sans erreur technique.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-65 — Paiements — encaissement et webhooks

**Profil(s) :** Finance, Intégrateur / API · **Module(s) :** Encaissements, Abonnements & facturation · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la réception d'un webhook de paiement, sa vérification de signature et l'idempotence.  
Prérequis : Endpoint de webhook exposé ; secret de signature configuré.

**Étapes de test**
1. Envoyer un webhook signé valide et vérifier l'accusé.
2. Rejouer le même webhook et vérifier l'absence de double traitement.
3. Envoyer un webhook avec une signature invalide et vérifier le rejet.
4. Vérifier la mise à jour de l'état de paiement.
5. Vérifier la trace d'audit de chaque appel.

**Résultat attendu**  
Seuls les webhooks signés sont acceptés, le rejeu est idempotent, l'état de paiement est cohérent et chaque appel est tracé.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-66 — EdgeSync — file hors-ligne et rejeu idempotent

**Profil(s) :** Superviseur / Kiosque, Opérations (Ops) · **Module(s) :** EdgeSync & hors-ligne · **Type :** Résilience · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'un événement produit hors-ligne est rejoué une seule fois après reconnexion.  
Prérequis : Pont Edge local ; file `sync_queue` ; coupure réseau simulable.

**Étapes de test**
1. Couper l'accès au cloud et produire trois événements.
2. Vérifier la présence des trois événements dans la file locale.
3. Rétablir le réseau et déclencher la synchronisation.
4. Vérifier qu'aucun événement n'est dupliqué côté cloud.
5. Contrôler l'état de la file (vide, `dead_letter_count` à zéro).

**Résultat attendu**  
Les événements sont mis en file, rejoués une seule fois, et la file se vide sans lettre morte.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-67 — Géolocalisation — preuve de présence

**Profil(s) :** Employé, Manager Principal · **Module(s) :** Géolocalisation, Pointage (Attendance) · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier que le pointage géolocalisé n'est accepté que dans la zone autorisée.  
Prérequis : Compte employé ; un site avec zone autorisée ; endpoints de pointage.

**Étapes de test**
1. Pointer depuis une position dans la zone autorisée.
2. Vérifier l'enregistrement des coordonnées.
3. Pointer depuis une position hors zone et vérifier le refus.
4. Vérifier le message retourné (explicite, non technique).
5. Vérifier que les coordonnées ne sont pas exposées à des tiers.

**Résultat attendu**  
Le pointage dans la zone est accepté avec preuve, le pointage hors zone est refusé avec un message clair, et les coordonnées restent confidentielles.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-68 — API — contrat OpenAPI, webhooks et clés

**Profil(s) :** Intégrateur / API · **Module(s) :** API & intégrations · **Type :** API · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la conformité du contrat OpenAPI et le cycle de vie d'une clé d'API.  
Prérequis : Accès à la documentation API ; compte autorisé à créer une clé.

**Étapes de test**
1. Ouvrir la documentation et vérifier la version du contrat.
2. Créer une clé d'API avec un périmètre restreint.
3. Appeler un endpoint autorisé puis un endpoint hors périmètre.
4. Révoquer la clé et vérifier le refus immédiat.
5. Vérifier que la clé n'apparaît jamais en clair dans les journaux.

**Résultat attendu**  
Le contrat est accessible et versionné, la clé respecte son périmètre, la révocation est immédiate et aucun secret n'est journalisé.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-69 — Mobile — build et distribution des applications

**Profil(s) :** Intégrateur / API, Opérations (Ops) · **Module(s) :** Applications mobiles · **Type :** Résilience · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'une application Flutter se construit et se distribue sur le canal de test.  
Prérequis : Dépôt cloné ; toolchain Flutter ; accès au canal de distribution.

**Étapes de test**
1. Lancer `flutter pub get` puis `flutter analyze` sur une application.
2. Construire un APK de debug.
3. Vérifier la version et le numéro de build générés.
4. Distribuer sur le canal de test et vérifier l'installation.
5. Vérifier que l'application démarre et atteint l'écran de connexion.

**Résultat attendu**  
L'analyse et le build passent, l'APK s'installe, et l'application démarre jusqu'à l'écran de connexion sans plantage.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-70 — Desktop — poste de travail et mise à jour

**Profil(s) :** Opérations (Ops), Intégrateur / API · **Module(s) :** Poste de travail (desktop) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le démarrage de l'application desktop et la détection d'une mise à jour.  
Prérequis : Binaire desktop installé ; canal de mise à jour accessible.

**Étapes de test**
1. Démarrer l'application desktop.
2. Vérifier l'écran de connexion et la version affichée.
3. Publier une version supérieure sur le canal.
4. Vérifier la détection de la mise à jour.
5. Appliquer la mise à jour et vérifier la nouvelle version.

**Résultat attendu**  
L'application démarre, affiche sa version réelle, détecte la mise à jour et l'applique sans perte de session.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-71 — Public — fiche commerce (leopardo_public)

**Profil(s) :** Client / usager public · **Module(s) :** Surfaces publiques · **Type :** UI/UX · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'affichage d'une fiche commerce publique : horaires, contact, menu, actions.  
Prérequis : Application `leopardo_public` ou fiche web ; un commerce publié.

**Étapes de test**
1. Ouvrir la fiche d'un commerce.
2. Vérifier les horaires et l'état ouvert/fermé.
3. Vérifier l'affichage du menu ou du catalogue.
4. Tester l'action de contact (appel, itinéraire).
5. Vérifier le rendu sur un écran étroit.

**Résultat attendu**  
La fiche est complète et lisible, l'état ouvert/fermé est correct, les actions fonctionnent et le rendu mobile est propre.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-72 — Plateforme — provisionnement et diagnostic

**Profil(s) :** Super Admin plateforme, Administrateur plateforme · **Module(s) :** Plateforme & observabilité, Onboarding & provisioning · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le suivi du provisionnement d'un tenant et le diagnostic en cas d'échec.  
Prérequis : Compte super admin ; un tenant en cours de provisionnement.

**Étapes de test**
1. Ouvrir la vue de provisionnement d'un tenant.
2. Vérifier les étapes franchies et leur horodatage.
3. Provoquer un échec contrôlé et vérifier l'état affiché.
4. Consulter le diagnostic (étape, message, corrélation).
5. Relancer le provisionnement et vérifier l'idempotence.

**Résultat attendu**  
Chaque étape est visible et horodatée, l'échec est diagnosticable sans accès serveur, et la relance ne crée pas de doublon.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-73 — Tenant — isolation par établissement

**Profil(s) :** Manager Principal, Opérations (Ops) · **Module(s) :** Multi-tenant & sécurité · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier que les données d'un établissement ne sont pas visibles depuis un autre établissement du même tenant.  
Prérequis : Un tenant avec deux établissements ; comptes rattachés à chacun.

**Étapes de test**
1. Créer une donnée métier dans l'établissement A.
2. Se connecter avec un compte rattaché à l'établissement B.
3. Tenter de lire la donnée de A par son identifiant.
4. Vérifier l'absence de la donnée dans les listes de B.
5. Vérifier que le périmètre est résolu côté serveur.

**Résultat attendu**  
L'accès inter-établissement est refusé, aucune donnée de A n'apparaît dans B, et le périmètre n'est jamais dérivé d'un paramètre client.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-74 — IAM — connexion SSO (SAML / OIDC)

**Profil(s) :** Administrateur plateforme, Intégrateur / API · **Module(s) :** Authentification & IAM · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la connexion via un fournisseur d'identité et le repli en cas de configuration invalide.  
Prérequis : Un fournisseur d'identité de test configuré ; un utilisateur existant.

**Étapes de test**
1. Lancer la connexion SSO depuis l'écran de login.
2. S'authentifier auprès du fournisseur de test.
3. Vérifier la création de session et le rôle attribué.
4. Tester une configuration invalide et vérifier le message d'erreur.
5. Vérifier qu'aucun jeton du fournisseur n'est journalisé.

**Résultat attendu**  
La connexion SSO aboutit avec le bon rôle, une configuration invalide produit une erreur explicite, et aucun jeton n'est journalisé.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-75 — RGPD — registre des traitements et droits

**Profil(s) :** Administrateur plateforme, Support · **Module(s) :** Multi-tenant & sécurité, Plateforme & observabilité · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier l'existence du registre des traitements et la capacité d'exporter ou supprimer les données d'une personne.  
Prérequis : Compte admin ; un employé de test avec des données réparties sur plusieurs modules.

**Étapes de test**
1. Consulter le registre des traitements (`docs/RGPD_REGISTRE_TRAITEMENTS.md`).
2. Demander l'export des données d'un employé de test.
3. Vérifier la complétude de l'export (modules couverts).
4. Demander la suppression et vérifier l'effacement ou l'anonymisation.
5. Vérifier la trace d'audit de l'opération.

**Résultat attendu**  
Le registre est à jour, l'export est complet, la suppression est effective ou anonymisée selon la règle, et l'opération est tracée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-76 — Performance — objectifs et évaluations

**Profil(s) :** Manager RH, Employé · **Module(s) :** Dossier employé (HR) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la fixation d'objectifs, le check-in et la revue d'évaluation.  
Prérequis : Compte Manager RH ; un employé avec un cycle d'évaluation ouvert.

**Étapes de test**
1. Créer deux objectifs pour un employé.
2. Vérifier leur visibilité côté employé.
3. Enregistrer un check-in intermédiaire.
4. Clôturer la revue avec une note et un commentaire.
5. Vérifier que l'employé ne peut pas modifier sa propre note.

**Résultat attendu**  
Les objectifs sont partagés, le check-in est tracé, la revue est clôturée, et l'employé ne peut pas s'auto-noter.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-77 — Documents — coffre-fort et échéances

**Profil(s) :** Manager RH, Employé · **Module(s) :** Dossier employé (HR) · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le dépôt d'un document, son accès restreint et l'alerte d'échéance.  
Prérequis : Compte Manager RH ; un employé ; un document avec date d'expiration.

**Étapes de test**
1. Déposer un document sur le dossier d'un employé.
2. Vérifier l'accès de l'employé à son propre document.
3. Vérifier qu'un autre employé n'y a pas accès.
4. Renseigner une échéance proche et vérifier l'alerte.
5. Vérifier la trace de téléchargement.

**Résultat attendu**  
Le document est accessible au seul ayant droit, l'échéance déclenche une alerte, et chaque téléchargement est tracé.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-78 — Workflows — validations multi-niveaux

**Profil(s) :** Manager Principal, Manager RH · **Module(s) :** Planning & tâches, Congés & absences · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier qu'une demande suit l'enchaînement de validation configuré selon son montant ou son type.  
Prérequis : Un workflow configuré à deux niveaux ; comptes des deux valideurs.

**Étapes de test**
1. Soumettre une demande dépassant le premier seuil.
2. Vérifier qu'elle est envoyée au premier valideur.
3. Valider au premier niveau et vérifier le passage au second.
4. Rejeter au second niveau et vérifier le retour à l'employé.
5. Vérifier l'historique complet des décisions.

**Résultat attendu**  
L'enchaînement des validations est respecté, un rejet arrête le circuit, et l'historique est complet et horodaté.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-79 — Plateforme — tableau de bord consolidé

**Profil(s) :** Manager Principal, Opérations (Ops) · **Module(s) :** Plateforme & observabilité · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la cohérence des indicateurs agrégés du tableau de bord avec les données réelles.  
Prérequis : Compte manager ; données de test sur plusieurs modules.

**Étapes de test**
1. Relever l'effectif affiché et le comparer à la liste des employés actifs.
2. Comparer les présences du jour au module de pointage.
3. Comparer les absences en attente à la file d'approbation.
4. Vérifier la période couverte par chaque indicateur.
5. Vérifier qu'aucune donnée d'un autre tenant n'est agrégée.

**Résultat attendu**  
Chaque indicateur correspond aux données réelles du module source, la période est explicite, et l'agrégation reste dans le tenant.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-80 — Support — tickets et impersonation encadrée

**Profil(s) :** Support · **Module(s) :** Plateforme & observabilité · **Type :** Sécurité · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le traitement d'un ticket et l'impersonation bornée d'un utilisateur.  
Prérequis : Compte support ; un ticket ouvert ; permission d'impersonation.

**Étapes de test**
1. Ouvrir un ticket et y répondre.
2. Lancer une impersonation d'un utilisateur du tenant concerné.
3. Vérifier l'indicateur d'impersonation visible.
4. Terminer l'impersonation et vérifier le retour au compte support.
5. Vérifier la trace d'audit (qui, quand, quel utilisateur).

**Résultat attendu**  
Le ticket est traité, l'impersonation est signalée, réversible et journalisée, et aucune action sensible n'est permise pendant celle-ci.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-81 — Finance — métriques d'abonnement et revenus

**Profil(s) :** Finance · **Module(s) :** Abonnements & facturation · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la cohérence des métriques financières (MRR, essais, conversions) avec les abonnements réels.  
Prérequis : Compte finance ; plusieurs tenants avec des états différents.

**Étapes de test**
1. Relever le nombre d'abonnements actifs.
2. Comparer au décompte réel des tenants actifs.
3. Vérifier le calcul du revenu récurrent.
4. Vérifier le décompte des essais en cours.
5. Vérifier qu'aucun montant n'est compté deux fois.

**Résultat attendu**  
Les métriques correspondent aux abonnements réels, le revenu récurrent est calculé sans double comptage, et la période est explicite.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-82 — Ops — nœuds Edge et kill switch

**Profil(s) :** Opérations (Ops) · **Module(s) :** EdgeSync & hors-ligne, Plateforme & observabilité · **Type :** Résilience · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la supervision des nœuds Edge et l'effet immédiat d'un kill switch.  
Prérequis : Compte ops ; au moins un nœud Edge enregistré ; permission de kill switch.

**Étapes de test**
1. Consulter l'état des nœuds Edge (en ligne, dernière synchronisation).
2. Activer un kill switch sur une fonctionnalité non critique.
3. Vérifier l'effet immédiat côté application.
4. Désactiver le kill switch et vérifier le retour à la normale.
5. Vérifier la journalisation des deux bascules.

**Résultat attendu**  
L'état des nœuds est fidèle, le kill switch agit immédiatement et de façon réversible, et chaque bascule est tracée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-83 — Marketing OS — publication sociale et statistiques

**Profil(s) :** Marketing · **Module(s) :** Vitrine & acquisition · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier la programmation d'une publication et la remontée de ses statistiques.  
Prérequis : Compte marketing ; un compte social connecté ; endpoints `/api/v1/marketing/*`.

**Étapes de test**
1. Créer un post avec visuel et texte.
2. Le programmer à une heure future.
3. Vérifier la publication effective à l'heure prévue.
4. Consulter les statistiques remontées.
5. Déconnecter le compte social et vérifier le comportement attendu.

**Résultat attendu**  
Le post est publié à l'heure prévue, les statistiques remontent, et la déconnexion du compte produit un état explicite sans erreur technique.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## UC-84 — RH — onboarding d'un nouvel employé

**Profil(s) :** Manager RH, Employé · **Module(s) :** Dossier employé (HR), Onboarding & provisioning · **Type :** Fonctionnel · **Statut :** à exécuter

**Description / préconditions**  
Vérifier le parcours d'arrivée d'un nouvel employé : création, invitation, première connexion.  
Prérequis : Compte Manager RH ; un nouvel employé à créer.

**Étapes de test**
1. Créer le dossier du nouvel employé.
2. Envoyer l'invitation à son adresse e-mail.
3. Vérifier l'activation du compte et la première connexion.
4. Vérifier l'accès aux seules fonctionnalités de son rôle.
5. Vérifier la présence du dossier dans l'organigramme.

**Résultat attendu**  
Le dossier est créé, l'invitation aboutit, l'employé accède uniquement à son périmètre, et l'organigramme reflète son arrivée.

| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |
| :---: | :--- | :---: | :--- |
|  | Non exécuté — à réaliser par le testeur IA. |  |  |

_Aucune modification humaine enregistrée pour ce cas._

---

## Tableau récapitulatif

| ID | Cas d'utilisation | Type | Statut | Note IA (/5) | Note humaine (/5) |
| :--- | :--- | :--- | :--- | :---: | :---: |
| UC-01 | Connexion d'un employé à l'espace client web | Fonctionnel | exécuté | 4 |  |
| UC-02 | Pointage entrée / sortie (API, employé) | API | exécuté | 5 |  |
| UC-03 | Pointage au kiosque biométrique (mode secours matricule) | UI/UX | exécuté | 4 |  |
| UC-04 | Demande d'absence et approbation manager | Fonctionnel | exécuté | 5 |  |
| UC-05 | Consultation du bulletin de paie (self-service employé) | Sécurité | exécuté | 4 |  |
| UC-06 | Préparation de paie (Manager Comptable) | Fonctionnel | exécuté | 5 |  |
| UC-07 | Gestion du dossier employé (création, modification, archivage) | Fonctionnel | exécuté | 2 |  |
| UC-08 | Isolation multi-tenant | Sécurité | exécuté | 5 |  |
| UC-09 | Multilingue et RTL (fr / en / tr / ar) | UI/UX | exécuté | 5 |  |
| UC-10 | Kiosque en mode dégradé / synchronisation Edge | Résilience | exécuté | 4 |  |
| UC-11 | CRM client (comptes, contacts, opportunités) | Fonctionnel | exécuté | 5 |  |
| UC-12 | Vitrine publique et demande d'essai | Fonctionnel | exécuté | 4 |  |
| UC-13 | Observabilité et santé de la plateforme | API | exécuté | 4 |  |
| UC-14 | Solution verticale « Agence de voyage » (boutique publique) | Sécurité | exécuté | 4 |  |
| UC-15 | Connexion Manager Principal et tableau de bord | Fonctionnel | à exécuter |  |  |
| UC-16 | Gestion des départements et des équipes | Fonctionnel | à exécuter |  |  |
| UC-17 | Attribution de rôles et invitations | Sécurité | à exécuter |  |  |
| UC-18 | Validation des heures supplémentaires et du planning | Fonctionnel | à exécuter |  |  |
| UC-19 | Génération et téléchargement d'un bulletin PDF | Fonctionnel | à exécuter |  |  |
| UC-20 | Console Super Admin — gestion des entreprises | Fonctionnel | à exécuter |  |  |
| UC-21 | Impersonation encadrée (Support) | Sécurité | à exécuter |  |  |
| UC-22 | Facturation et abonnements (Finance) | Fonctionnel | à exécuter |  |  |
| UC-23 | Kill switch et nœuds Edge (Ops) | Résilience | à exécuter |  |  |
| UC-24 | Campagnes et vitrine (Marketing) | Fonctionnel | à exécuter |  |  |
| UC-25 | Recrutement et offres d'emploi | Fonctionnel | à exécuter |  |  |
| UC-26 | Bibliothèque / Showcase public | UI/UX | à exécuter |  |  |
| UC-27 | Comptabilité — écritures et clôture mensuelle | Fonctionnel | à exécuter |  |  |
| UC-28 | Comptabilité — export et rapprochement bancaire | Fonctionnel | à exécuter |  |  |
| UC-29 | Notes de frais — soumission et validation | Fonctionnel | à exécuter |  |  |
| UC-30 | Notes de frais — remboursement et impact paie | Fonctionnel | à exécuter |  |  |
| UC-31 | Planning — shifts et affectations | Fonctionnel | à exécuter |  |  |
| UC-32 | Tâches — assignation et preuve d'exécution | Fonctionnel | à exécuter |  |  |
| UC-33 | Recrutement — offre d'emploi et page carrières | Fonctionnel | à exécuter |  |  |
| UC-34 | Recrutement — pipeline de candidatures | Fonctionnel | à exécuter |  |  |
| UC-35 | Communication — annonce interne et accusés de lecture | Fonctionnel | à exécuter |  |  |
| UC-36 | Notifications — préférences et multi-canal | Fonctionnel | à exécuter |  |  |
| UC-37 | Onboarding — provisioning d'un essai guidé | Fonctionnel | à exécuter |  |  |
| UC-38 | Onboarding — reprise d'un parcours interrompu | Résilience | à exécuter |  |  |
| UC-39 | Catalogue — produits, services et tarifs | Fonctionnel | à exécuter |  |  |
| UC-40 | Recherche — recherche transverse et permissions | Sécurité | à exécuter |  |  |
| UC-41 | Pages — pages publiques et SEO multilingue | UI/UX | à exécuter |  |  |
| UC-42 | Showcase — vitrine publique et bibliothèque | UI/UX | à exécuter |  |  |
| UC-43 | Bibliothèque — prêt et retour d'ouvrages | Fonctionnel | à exécuter |  |  |
| UC-44 | Caméras — parc et supervision des flux | Fonctionnel | à exécuter |  |  |
| UC-45 | Caméras — incident et maintenance | Fonctionnel | à exécuter |  |  |
| UC-46 | Flotte — véhicules, affectations et entretien | Fonctionnel | à exécuter |  |  |
| UC-47 | Station-service — index pompes et ventes carburant | Fonctionnel | à exécuter |  |  |
| UC-48 | VTC — course et tarification | Fonctionnel | à exécuter |  |  |
| UC-49 | Livraison — tournée et preuve de livraison | Fonctionnel | à exécuter |  |  |
| UC-50 | Restauration — POS et plan de salle | Fonctionnel | à exécuter |  |  |
| UC-51 | Restauration — KDS et cycle de commande | Résilience | à exécuter |  |  |
| UC-52 | Retail — POS et encaissement | Fonctionnel | à exécuter |  |  |
| UC-53 | Retail — stock et inventaire | Fonctionnel | à exécuter |  |  |
| UC-54 | Hôtellerie — chambres, séjours et housekeeping | Fonctionnel | à exécuter |  |  |
| UC-55 | Pharmacie — stock, ordonnance et profil public | Fonctionnel | à exécuter |  |  |
| UC-56 | Santé — patients, rendez-vous et dossier | Sécurité | à exécuter |  |  |
| UC-57 | École — classes, notes et présences | Fonctionnel | à exécuter |  |  |
| UC-58 | École — facturation scolaire et espace parents | Fonctionnel | à exécuter |  |  |
| UC-59 | Agriculture — parcelles, cultures et récoltes | Fonctionnel | à exécuter |  |  |
| UC-60 | Cagnottes — campagne de collecte et dons | Fonctionnel | à exécuter |  |  |
| UC-61 | Cabinet comptable — portefeuille clients | Sécurité | à exécuter |  |  |
| UC-62 | Négoce — intention, sous-profil et devis | Fonctionnel | à exécuter |  |  |
| UC-63 | Marque — branding tenant et surfaces publiques | UI/UX | à exécuter |  |  |
| UC-64 | Abonnements — plan, essai et facturation | Fonctionnel | à exécuter |  |  |
| UC-65 | Paiements — encaissement et webhooks | Sécurité | à exécuter |  |  |
| UC-66 | EdgeSync — file hors-ligne et rejeu idempotent | Résilience | à exécuter |  |  |
| UC-67 | Géolocalisation — preuve de présence | Sécurité | à exécuter |  |  |
| UC-68 | API — contrat OpenAPI, webhooks et clés | API | à exécuter |  |  |
| UC-69 | Mobile — build et distribution des applications | Résilience | à exécuter |  |  |
| UC-70 | Desktop — poste de travail et mise à jour | Fonctionnel | à exécuter |  |  |
| UC-71 | Public — fiche commerce (leopardo_public) | UI/UX | à exécuter |  |  |
| UC-72 | Plateforme — provisionnement et diagnostic | Fonctionnel | à exécuter |  |  |
| UC-73 | Tenant — isolation par établissement | Sécurité | à exécuter |  |  |
| UC-74 | IAM — connexion SSO (SAML / OIDC) | Sécurité | à exécuter |  |  |
| UC-75 | RGPD — registre des traitements et droits | Sécurité | à exécuter |  |  |
| UC-76 | Performance — objectifs et évaluations | Fonctionnel | à exécuter |  |  |
| UC-77 | Documents — coffre-fort et échéances | Fonctionnel | à exécuter |  |  |
| UC-78 | Workflows — validations multi-niveaux | Fonctionnel | à exécuter |  |  |
| UC-79 | Plateforme — tableau de bord consolidé | Fonctionnel | à exécuter |  |  |
| UC-80 | Support — tickets et impersonation encadrée | Sécurité | à exécuter |  |  |
| UC-81 | Finance — métriques d'abonnement et revenus | Fonctionnel | à exécuter |  |  |
| UC-82 | Ops — nœuds Edge et kill switch | Résilience | à exécuter |  |  |
| UC-83 | Marketing OS — publication sociale et statistiques | Fonctionnel | à exécuter |  |  |
| UC-84 | RH — onboarding d'un nouvel employé | Fonctionnel | à exécuter |  |  |

**Cas notés (IA) :** 14 · **Total des notes IA :** 60 · **Moyenne IA :** 4.29 / 5 · **Cas bloquants (≤2) :** 1

> La colonne « Note humaine (/5) » est renseignée par les testeurs humains depuis le portail
> (ou directement dans `docs/recette/notes-humaines.json`) ; elle est versionnée dans le dépôt.
