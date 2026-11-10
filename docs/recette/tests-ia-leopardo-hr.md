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

---

## UC-14 — Solution verticale « Agence de voyage » (boutique publique)

**Profil(s) :** Manager Principal · **Module(s) :** Agence de voyage (TravelAgency) · **Type :** Sécurité · **Statut :** exécuté

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

**Cas notés (IA) :** 14 · **Total des notes IA :** 60 · **Moyenne IA :** 4.29 / 5 · **Cas bloquants (≤2) :** 1

> La colonne « Note humaine (/5) » est renseignée par les testeurs humains depuis le portail
> (ou directement dans `docs/recette/notes-humaines.json`) ; elle est versionnée dans le dépôt.
