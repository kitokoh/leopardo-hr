# Mission DevOps / PM / QA — Scénario client « propriétaire d'école »

**Date :** 2026-09-14
**Dépôt :** `kitokoh/leopardo-hr` (branche `main`, clone local, commit de base `main` @ 2026-09-14)
**Environnement :** sandbox Linux — stack complète montée localement (API + Web Client + Web Admin)
**Rôle tenu :** DevOps (installation), PM (périmètre / parcours), Testeur (exécution réelle navigateur + API)

---

## 1. Résumé exécutif

Le scénario demandé — *un propriétaire d'école crée son compte entreprise, reçoit un code, l'active, et obtient un espace de travail avec RH, comptabilité, assistant IA, gestion scolaire et suivi de véhicules* — **fonctionne pour sa moitié « provisioning » et échoue pour sa moitié « mise en route »**.

| Étape du parcours | État | Preuve |
|---|---|---|
| Création du compte (self-service) | ✅ OK | `POST /api/v1/trial/signup` → 200 |
| Réception du code à 6 chiffres | ✅ OK | e-mail rendu (mailer `log`, code `591572`) |
| Activation / provisioning du tenant | ✅ OK | `POST /trial/verify` → 201, tenant `groupe-scolaire-les-palmiers` créé |
| Solution scolaire (`edumanager`) active | ✅ OK | `features.edumanager = true`, écran EduManager accessible |
| **Outils RH + compta choisis à l'inscription** | ✅ **corrigé** (2e passe) | `metadata.modules` complet, `accounting = true` |
| **Récupération du mot de passe / reprise du parcours** | ✅ **corrigé** (2e passe) | `/trial/status` → `ready`, `set-password` → 200 |
| **Module école : créer une année / matière / classe** | ❌ **500** | `SQLSTATE 42703` colonnes inexistantes |
| **Générer un bulletin** | ❌ **403** | ability `create` absente de la policy |
| Parents d'élèves (responsables légaux) | ✅ **corrigé** (2e passe) | CRUD + rattachement + **portail parents fonctionnel** |
| Cartes d'élèves | ❌ absent | aucune notion dans le module |
| Comptabilité (module) | ⚠️ répond mais désactivé par défaut | auto-activation possible (`POST /company/modules/accounting/activate` → 200) |
| Flotte / véhicules | ⚠️ API OK, **aucun écran client** | véhicule créé (201) ; position/trajet exigent Traccar |
| Assistant IA | ⚠️ non exposé au client | `LEO_AI` désactivé ; « AI Chat » réservé au super-admin |

**Deux bugs bloquants ont été corrigés et vérifiés de bout en bout** (correctif local, **non poussé** — voir §7).

---

## 2. Environnement monté (DevOps)

Le sandbox n'avait ni PHP, ni base de données, ni Docker. Stack installée et opérationnelle :

| Brique | Version | Détail |
|---|---|---|
| PHP | **8.4.25** | PPA `ondrej/php` (les dépôts Ubuntu ne fournissent que 8.1 ; le projet exige `^8.4.1`) |
| Composer | 2.10.3 | `composer install` OK |
| PostgreSQL | 14.24 | base `leopardo` + `leopardo_test` (suite de tests) |
| Node / npm | 24.18 / 11.16 | |
| Laravel | **12.69.1** | `php artisan leopardo:migrate --fresh --seed --demo` → `Leopardo migrate : OK` |

**Services lancés et vérifiés :**

```
API          http://127.0.0.1:8000    → /api/v1/health = 200
             {"status":"ok","version":"4.24.0","environment":"local",
              "checks":{"database":{"ok":true,"latency_ms":11}, ...}}

Web Client   http://localhost:3000     (Next.js — vitrine + espace client)
Web Admin    http://localhost:3001     (Vue/Vite — console plateforme v4.24.0)
```

Le **Web Client** et le **Web Admin** ont été installés et pilotés dans un vrai navigateur (pas seulement en API) : connexion réelle du compte école, navigation dans les modules, reproduction des erreurs en tant qu'utilisateur.

---

## 3. Parcours client exécuté, étape par étape

### 3.1 Inscription self-service (le « je clique sur créer un compte »)

```http
POST /api/v1/trial/signup
{
  "email":"direction@groupescolaire-palmiers.dz",
  "company":"Groupe Scolaire Les Palmiers",
  "first_name":"Yacine","last_name":"Belkacem",
  "role":"founder","employees":"51-200","country":"DZ","plan":"pilot",
  "locale":"fr","company_type":"company","requestedWorkflow":"self_service",
  "solutions":["edumanager"],
  "modules":["employees","attendance","absences","contracts","payroll","reports","accounting"],
  "source":"mission-test-ecole"
}
→ 200 {"success":true,"message":"Code de vérification envoyé."}
```

### 3.2 Réception et saisie du code

Le mail part réellement (mailer `log` en local) : code **591572** (valable 30 min).

```http
POST /api/v1/trial/verify {"email":"…","code":"591572"}
→ 201 {"success":true,"message":"Votre espace Leopardo est prêt !",
       "data":{"token":"2|XZAtB0yL…",                ← auto-connexion
               "company":{"name":"Groupe Scolaire Les Palmiers",
                          "slug":"groupe-scolaire-les-palmiers"},
               "trial":{"days":14,"ends_at":"2026-09-28T14:14:21+00:00"}}}
```

### 3.3 Connexion et état réel de l'espace

Connexion web réussie (`/dashboard`), puis navigation : **le module Scolarité est bien présent**.

```
Nav client : /edu-manager /dashboard /payroll /accounting /billing
             /absences /attendance /reports /employees

Écran EduManager :
  « SYSTÈME LEOPARDO — EduManager
    Pilotage scolaire : campus, années, classes, élèves, admissions,
    évaluations et bulletins. »
  Campus 1 · Classes 0 · Élèves 0 · Admissions 0
  Accès rapides : Gérer les campus / classes / élèves · Évaluations & notes
                  · Bulletins · Années scolaires · Matières
```

État du tenant en base :

```json
features = {"documents": true, "edumanager": true, "notifications": true}
metadata = {"provisioned_by": "self_service_trial", "employees_range": "51-200"}
```

👉 **Les 7 outils horizontaux demandés à l'inscription n'ont pas été appliqués** (voir anomalie A).

### 3.4 Mise en route de l'école — parcours complet rejoué après correctif

```
campus              → 201   (« Campus Alger Centre »)
année scolaire      → 201   (« 2026-2027 »)
matières            → 201   (Mathématiques coef. 4, Français coef. 3)
classe              → 201   (« 5ème AP - A », niveau 5AP, capacité 30)
3 élèves            → 201   (STU-0001/0002/0003)
3 inscriptions      → 201
évaluation          → 201   (« Composition T1 », examen, coef. 2, /20)
3 notes + publication → 201/200
BULLETIN            → 201 → validé → publié
frais de scolarité  → 201   (« Frais de scolarité T1 », 45 000 DZD)

Dashboard école : campuses 1 · academic_years 1 · classes 1 · students 3
                  · assessments 1 · report_cards 1 · fees 1
```

### 3.5 Flotte / véhicules (test API)

```
POST /api/v1/vehicles {"plate_number":"16-1234-114","make":"Toyota",
                       "model":"Coaster","year":2022,"type":"bus",
                       "fuel_type":"diesel"}          → 201
GET /vehicles, /vehicle-trips, /vehicle-alerts,
    /vehicle-maintenance                             → 200
GET /vehicles/1/position                             → 404
    {"message":"No tracker linked to this vehicle."}
```

---

## 4. Anomalies trouvées

### ✅ A — P1 — **[CORRIGÉ]** Les outils choisis à l'inscription étaient silencieusement ignorés

**Symptôme.** Le prospect coche « Comptabilité » (et d'autres outils) : l'espace est créé **sans** ces outils. Aucun message, aucune erreur.

**Preuve directe.**

```
company_requests.signup_payload =
  {… "modules":["employees","attendance","absences","contracts","payroll",
                "reports","accounting"],
      "company_type":"company", "solutions":["edumanager"] …}

companies.features  = {"documents":true,"edumanager":true,"notifications":true}
companies.metadata  = {"provisioned_by":"self_service_trial",
                       "employees_range":"51-200"}     ← AUCUN "modules"
```

**Cause racine.** Deux chemins de provisioning existent :

| Chemin | Fichier | `solutions` | `modules` | `company_type` |
|---|---|---|---|---|
| Guidé / démo | `ProvisionGuidedTrial` | ✅ | ✅ `resolveModuleSelection()` → `metadata.modules` + miroir `features` | ✅ |
| **Self-service (celui du client)** | `VerifyTrialSignup` | ✅ | ❌ **0 occurrence de `'modules'`** | ❌ **0 occurrence de `company_type`** |

`POST /trial/signup` **valide** `modules[]` et `company_type` (allowlists `Company::HORIZONTAL_TOOLS`), les **stocke** dans `signup_payload`… puis `VerifyTrialSignup::provisionTrialCompany()` ne les lit jamais.

**Contournement vérifié côté client** (par module) : `POST /api/v1/company/modules/accounting/activate` → 200, et `metadata.modules = {"accounting": true}` apparaît. Le client peut donc se rattraper **outil par outil**, mais pas via son inscription.

**✅ CORRIGÉ (2e passe).** La règle de sélection est extraite dans un service partagé
`App\Modules\Billing\Application\Services\HorizontalToolSelection` (classe pure)
et **branchée sur les deux chemins de provisioning**. Preuve après correctif :

```
companies.metadata.modules = {"crm": false, "payroll": true, "reports": true,
  "absences": true, "showcase": false, "training": false, "contracts": true,
  "employees": true, "marketing": false, "accounting": true, "attendance": true}
companies.features        = {"accounting": true, "crm": false, ..., "edumanager": true}
GET /auth/me → .data.features.accounting = true
```

---

### ✅ B — P1 — **[CORRIGÉ]** Le parcours self-service était un cul-de-sac si l'auto-connexion échoue

**Symptôme.** `POST /trial/verify` renvoie `next_steps.login = "Connectez-vous avec votre email et le mot de passe **ci-dessus**."` — or **aucun mot de passe n'est renvoyé** dans la réponse (`temp_password` est utilisé en interne pour l'auto-login, jamais exposé).

Aggravant : le code **absorbe** l'échec d'auto-connexion (`trial.verify.autologin_failed` → `token: null`) et prévoit explicitement un « repli sur l'écran de connexion classique ». Ce repli est **impossible** : le prospect n'a jamais choisi de mot de passe.

**Preuve.**

```
public.trial_provisionings (nb lignes) = 0      ← la ligne n'est jamais créée
                                            en self-service
POST /api/v1/trial/set-password   → 404 PROVISIONING_TOKEN_INVALID
GET  /api/v1/trial/status         → 404 PROVISIONING_TOKEN_INVALID
```

La table n'est alimentée que par `ProvisionDemoTenantJob`. Conséquence : **ni reprise du parcours, ni définition de mot de passe, ni suivi de statut** pour un prospect self-service.

Aggravant découvert ensuite : **la vitrine implémente déjà tout ce chemin** (`provisioning_token` en `sessionStorage`, proxies `/api/forms/trial-status` et `/api/forms/trial-password`, écran de reprise). C'est donc un **contrat front/back rompu sur la seule branche self-service**.

**✅ CORRIGÉ (2e passe).** `POST /trial/signup` crée la ligne `trial_provisionings`
(status `pending`) et renvoie `data.provisioning_token` ; `VerifyTrialSignup` la passe
à `ready` avec `company_id` + `provisioned_at`. Preuve :

```
signup            → 200  data.provisioning_token = 64 caractères
GET /trial/status → 200  {"status":"pending","company_id":null}
verify (OTP)      → 201
GET /trial/status → 200  {"status":"ready","login_url":"/auth/login","password_set":false}
POST /trial/set-password → 200 « Mot de passe enregistré »
POST /auth/login (mot de passe choisi) → 200
```

---

### 🔴 C — P0 — Le module école ne se met pas en route (dérive de schéma)

**Symptôme (reproduit dans l'interface, en tant qu'utilisateur).** Sur « Années scolaires → Nouvelle année → Enregistrer » :

> **« Une erreur est survenue. »**

**Preuve API + stack trace.**

```
POST /edu-manager/academic-years → 500 INTERNAL_ERROR
  SQLSTATE[42703]: Undefined column: column "created_by"
                    of relation "edu_academic_years" does not exist

POST /edu-manager/subjects       → 500
  SQLSTATE[42703]: column "campus_id" of relation "edu_subjects" does not exist
```

**Cause racine.** Le module EDU a été livré par **plusieurs jeux de migrations déclarant les mêmes tables avec des colonnes divergentes** :

```
2026_08_30_0002xx_5819_create_edu_*  (série fine)      ← EXÉCUTÉE EN PREMIER
2026_08_30_0007xx_5819_create_edu_*  (série groupée)   ← neutralisée (schemaTableExists)
2026_08_30_0015xx_5819_create_edu_*  (série groupée)   ← neutralisée
2026_08_31_0002xx_5819_create_edu_*  (révision récente) ← neutralisée
```

Toutes les versions postérieures sont neutralisées par la garde d'idempotence `schemaTableExists()`. **La première migration fige donc le schéma**, contrairement à l'intention « la plus récente gagne ».

**Ampleur mesurée par script** (colonnes déclarées par la **révision la plus récente** de chaque table vs colonnes réellement présentes sur une base fraîche, schéma `shared_tenants`) :

| Table | Colonnes absentes |
|---|---|
| `edu_academic_years` | `created_by`, `notes` |
| `edu_classes` | `campus_id`, `code`, `level`, `teacher_id`, `created_by` |
| `edu_subjects` | `campus_id`, `default_coefficient`, `created_by` |
| `edu_teacher_subjects` | `class_id`, `status`, `created_by` |
| `edu_attendance_corrections` | `attendance_id` |
| `edu_admissions` | **15 colonnes** : `applicant_first_name`, `applicant_last_name`, `applicant_email`, `applicant_phone`, `applicant_birth_date`, `applied_at`, `campus_id`, `source`, `external_id`, `crm_contact_id`, `consent_contact`, `consented_at`, `converted_at`, `notes`, `created_by` |

→ **6 tables, 29 colonnes**.

**Impact mesuré sur la suite de tests du dépôt** (même protocole avant/après, base de test recréée à chaque fois pour forcer la re-migration) :

```
php artisan test tests/Feature/EduManager/

Avant (main)                152 échecs / 125 succès
Après (correctif §7)         95 échecs / 182 succès
                            → 57 tests réparés
```

⚠️ **Piège à connaître pour reproduire** : le harnais `RefreshTenantDatabase` est **étatique** (issue #6948) — il ne re-migre que si 8 tables sentinelles sont absentes (`canonicalSchemaReady()`). **Ajouter une migration tenant puis relancer les tests ne l'applique pas** : il faut `DROP DATABASE leopardo_test` au préalable, sinon on mesure un résultat faussement inchangé. C'est ce qui s'est produit au premier essai de cette mission.

### Le reste du trou : des **renommages jamais appliqués**

Les 95 échecs restants ne sont plus des colonnes manquantes mais des **divergences de nom et de nullabilité** entre les deux générations de schéma — invisibles tant qu'on ne compare pas code ↔ base :

| Table | Schéma réel (génération A) | Ce que le code/les tests attendent (génération B) |
|---|---|---|
| `edu_report_cards` | `period` (NOT NULL) | **`period_label`** (`ReportCardService`, `EduReportCardTest`, `EduRbacMatrixTest` → 12 échecs) |
| `edu_assessments` | `type` | **`assessment_type`** (3 échecs) |
| `edu_admissions` | `applicant_*` **NOT NULL** | le code n'alimente pas ces colonnes à la création → 25 violations NOT NULL |
| `edu_attendance_records` | `attendance_record_id` NOT NULL | insertion sans cette clé |

**Décision d'ingénierie assumée** : je n'ai **pas** appliqué ces renommages/assouplissements de contraintes. Une migration destructive (rename/drop de NOT NULL) sur des tables de production doit être arbitrée par le propriétaire (quelle génération est canonique ?), et la règle du dépôt impose une spec validée avant ce type de changement. Ils sont listés en §8 comme P0-bis.

> Note : le garde du dépôt `dev-hub/tools/check-migration-basename-collisions.sh` répond `✅ Aucune collision` — il compare des **basenames/préfixes**, pas les **tables créées**. La classe de défaut lui échappe entièrement.

---

### 🔴 D — P0 — Aucun bulletin ne peut être généré (403 pour tout le monde)

**Symptôme.**

```
POST /edu-manager/report-cards/generate (directeur, manager_role=principal)
→ 403 FORBIDDEN « Vous n'avez pas les droits pour cette action. »
```

**Cause racine.**

```php
// EduReportCardController::generate()
$this->authorize('create', EduReportCard::class);

// EduReportCardPolicy — méthodes définies :
viewAny() · view() · validate() · publish()
// → AUCUNE méthode create(), aucun before()
```

Une ability non déclarée est **refusée par le Gate Laravel**. La génération de bulletins — fonction cœur d'un établissement et explicitement demandée — était donc **inatteignable par construction**, pour tous les rôles.

**Pourquoi la CI est verte (trou de couverture).** Les tests de bulletins (`EduReportCardTest`, `EduReportCardServiceTest`) appellent **le service directement** (`$this->service()->generate(...)`) — jamais la route HTTP. Même famille de trou que l'incident #7356 (« les trois outils de l'IA n'avaient aucun test d'exécution »).

---

### ✅ E — P1 — **[CORRIGÉ]** Les parents d'élèves ne pouvaient pas être créés

Routes existantes :

```
GET  /edu-manager/guardians/me
GET  /edu-manager/guardians/me/students
GET  /edu-manager/guardians/me/students/{student}/presences
GET  /edu-manager/guardians/me/students/{student}/report-cards
POST /edu-manager/guardians/access-links          ← exige un guardian_id DÉJÀ existant
POST /edu-manager/guardians/access-links/redeem
```

Il n'existe **aucune route** pour créer un `edu_guardians` ni pour le rattacher à un élève (`edu_student_guardians`), et **aucune référence** à « guardian / parent / tuteur » dans les écrans `edu-manager` du Web Client. Seul le seeder `EduManagerPilotSeeder` insère des responsables légaux (en direct en base).

→ Le **portail parents** (`/guardian-portal`, déjà développé) est inatteignable par le produit.

**✅ CORRIGÉ (2e passe)** — routes ajoutées :
`GET|POST /edu-manager/guardians`, `POST /edu-manager/students/{student}/guardians`
(idempotent, drapeaux `can_view_grades` / `can_receive_notifications`),
`DELETE /edu-manager/students/{student}/guardians/{guardian}`, avec validation
bornée au tenant (aucun `company_id` accepté depuis la requête).

### 🔴 E-bis — P0 — Le portail parents appelait des routes qui n'existent pas

**Symptôme.** Même avec un lien d'accès valide, l'écran `/guardian-portal` restait en erreur : le parent recevait toujours « lien invalide ».

**Preuve.** Contrat front vs backend :

| Appelé par la vitrine livrée | Exposé par le backend |
|---|---|
| `POST /edu-manager/guardian-portal/access-links/{token}/consume` | ❌ **inexistant** (0 occurrence) |
| `POST /edu-manager/guardians/{guardian}/access-links` | ❌ inexistant (`guardian_id` attendu **en body**) |
| — | `POST /edu-manager/guardians/access-links/redeem` (token en body) |

Deux surfaces développées séparément, jamais raccordées.

**✅ CORRIGÉ (2e passe).** Ajout de la route de consommation **hors du groupe
authentifié** (le parent n'a pas de session : le contexte tenant est résolu depuis
le lien, jamais depuis la requête — même modèle que `/onboarding/invitation/{token}`),
avec la forme de réponse attendue par l'écran. Preuve en live :

```
POST /guardians/1/access-links                     → 201
POST /guardian-portal/access-links/{token}/consume → 200
   data.guardian  = {id, first_name, last_name, students[]}
   data.children  = [{id, display_name, relationship_code, can_view_grades:true,
                      presence:{today_status,last_30_days,recorded_days},
                      report_cards:[{id, period:"term1", published_at}]}]
re-consommation du même lien                       → 410
jeton inconnu                                      → 404
```

---

### 🟠 F — P1 — Les cartes d'élèves n'existent pas

Aucune notion de `student_card` / `id_card` / badge élève dans le module EDU (API et écrans). La seule occurrence de « carte » dans l'UI scolaire est « cartes d'accès » (cartes du tableau de bord, sans rapport). Les employés, eux, ont `badge_number` et l'enrôlement biométrique — pas les élèves.

---

### 🟠 G — P1 — L'onboarding est générique RH, hors sujet pour une école

Le wizard bloquant (10 étapes) est **le même pour tous les métiers** :

```
Informations entreprise · Premier employé · Premier département ·
Premier pointage · Configurer les horaires · Configurer la paie ·
Activer la géolocalisation · Premier rapport · Inviter un gestionnaire ·
Installer un kiosque
```

Rien sur : créer un campus, ouvrir une année scolaire, importer les élèves, affecter les enseignants, préparer les bulletins, encaisser les frais. Même constat dans la console plateforme, où les « actions prioritaires » du tenant école sont : *« Planifier une session de démarrage **pointage** »*, *« Configurer une **zone de pointage** pour prouver la présence terrain »*.

→ Le parcours « Mon métier = Scolarité » existe (badge présent, écran EduManager dédié) mais **l'accompagnement de démarrage n'a pas été adapté** à la verticale.

---

### 🟠 H — P1 — La flotte n'a pas d'écran côté client

- **API** : complète et fonctionnelle (`/vehicles`, `/vehicle-trips`, `/vehicle-alerts`, `/vehicle-maintenance`, `/vehicles/{id}/position`, `/vehicles/{id}/trips`) — un bus a été créé avec succès (201).
- **Web Client** : **aucune page véhicule/flotte** (`front/web/src/app/**/*vehicle*` → ∅) et **aucune entrée de navigation**. Le premier écran « Flotte » de la plateforme (`FleetView.vue`, `/fleet`) est réservé au **super-admin**.
- **Itinéraires** : `/vehicles/{id}/position` → **404 « No tracker linked to this vehicle. »** ; les trajets ne peuvent être créés que par synchronisation Traccar (`POST /tracking/sync-trips`) — **pas de création manuelle**.
- **Prérequis** : `TRACCAR_URL` (défaut `http://localhost:8082`) + `TRACCAR_API_TOKEN`. Le suivi d'itinéraire **exige donc un serveur Traccar** (dépôt `kitokoh/traccar` disponible) — à déployer et raccorder avant toute promesse client.

---

### 🟠 I — P1 — L'assistant IA n'est pas accessible au client

- Les routes existent (`/ai/chat`, `/ai/agent/run`, `/ai/tools` — 22 outils), et `GET /api/v1/ai/tools` répond.
- Mais : `LEO_AI` est **désactivé** sur le tenant créé, `ai_cloud_allowed` aussi (visible dans « Modules & plan » de l'admin).
- **Aucun composant d'assistant IA dans le Web Client** (`front/web/src` : la seule occurrence de `leo_ai` est un commentaire dans `i18n.ts`). L'écran « AI Chat » est **super-admin uniquement**.
- En local, `AI_LLM_DRIVER=fake` : utile pour vérifier le câblage, pas pour une démonstration réelle.

→ Pour honorer la promesse « un assistant IA pour l'aider », il faut (1) activer `AI_ENABLED` + `leo_ai` + `ai_cloud_allowed`, (2) fournir une clé LLM, (3) **exposer une surface client** (aujourd'hui inexistante).

---

### 🟡 J — P2 — Petits écarts constatés au passage

| # | Constat | Détail |
|---|---|---|
| J1 | Statut non retourné à la création | `POST /edu-manager/subjects` sans `status` renvoie `"status": null` alors que le défaut en base est `active` (le modèle ne relit pas la valeur par défaut). |
| J2 | Colonne historique divergente | `edu_classes.grade_level` (schéma) vs `level` (modèle, requête, écrans) — `EduClassTest` continue d'échouer sur cette assertion. |
| J3 | Message d'erreur générique | L'UI affiche « Une erreur est survenue. » sans code ni action — indiagosticable pour le client. |
| J4 | `status` du tenant | Le tenant créé est en `status: trial`/RISK HIGH avec `ONBOARDING 0%` — correct, mais les KPI sont calculés sur des critères RH (voir G). |

---

## 5. Couverture du besoin client

| Besoin exprimé | État réel | Où |
|---|---|---|
| Compte entreprise + code + activation | ✅ | `/signup`, `/trial/signup`, `/trial/verify`, `/auth/activate/[token]` |
| Outils RH | ✅ | `/employees`, `/attendance`, `/absences`, `/contracts`, `/payroll`, `/reports` |
| Pointage / kiosque | ✅ | `/attendance`, `front/zkteco-kiosk` |
| Outils compta | ⚠️ présent, **désactivé par défaut** | `/accounting/*` (chart, documents, journal, TVA, bilan, rapprochement bancaire) |
| Paie multi-pays | ✅ | `/payroll-runs/*` (+ déclarations CNAS/CNSS/IPRES/CNPS) |
| Assistant IA | ⚠️ **non exposé au client** | API `/ai/*` ; UI super-admin seulement |
| Inscriptions (admissions) | ✅ API | `/edu-manager/admissions` (+ conversion, campagnes marketing) |
| Enseignants | ⚠️ partiel | via employés + `classes/{class}/teachers`, `teacher-subjects` (pas d'écran « enseignants » dédié) |
| Surveillants | ✅ | `manager_role = superviseur` (socle RH) |
| Élèves | ✅ | `/edu-manager/students` |
| Cartes d'élèves | ❌ **absent** | — |
| Bulletins | ❌ **403** (corrigé) | `/edu-manager/report-cards/generate` |
| Parents d'élèves | ❌ **non créables** | portail existant, provisioning absent |
| Véhicules + suivi itinéraire | ⚠️ API OK, **pas d'écran client**, Traccar requis | `/vehicles`, `/vehicle-trips` ; `TRACCAR_URL` |

---

## 6. Ce qui fonctionne vraiment bien

Pour être juste, l'audit a aussi montré une base solide :

- **Multi-tenant réel** (schéma `shared_tenants`, `company_id` partout, isolation vérifiée par les tests) et **provisioning self-service en < 1 s** (tenant + manager + auto-login).
- **Module EDU très complet côté domaine** : campus, années, matières, classes, élèves, responsables légaux, admissions, présences, évaluations/notes versionnées, bulletins, frais, imports/exports, portail enseignant, portail parents, rapports (capacité, effectifs, présence, résultats), RGPD (`privacy-export`, `anonymize`).
- **Plateforme d'administration mature** : santé par tenant, actions prioritaires, onglets de modules à bascule, abonnements, audit.
- **Gouvernance de dépôt exemplaire** (constitution, protocoles, gardes CI, CHANGELOG) — la difficulté n'est pas là, mais dans le **désalignement entre branches fusionnées**, que les gardes actuels ne détectent pas.

---

## 7. Correctifs livrés (local, **non poussé**)

Branche locale : **`fix/edu-schema-drift`** (basée sur `main`, aucun push, aucune PR ouverte).

| Fichier | Nature |
|---|---|
| `api/database/migrations/tenant/2026_09_14_000002_5819_repair_edu_schema_drift.php` | **Nouveau.** Migration **additive, gardée, idempotente** (helpers F-17 `schemaTableExists` / `schemaHasColumn` / `resolveTableSchema`) qui aligne **10 tables EDU** sur la génération de schéma la plus récente : ajout des colonnes attendues par le code + assouplissement (`DROP NOT NULL` en SQL brut, jamais de `change()` qui risquerait une conversion de type) des contraintes que le code ne peut pas satisfaire. Aucune colonne supprimée, aucun renommage. No-op là où tout existe → **sûr en production**. |
| `api/app/Modules/EduManager/Domain/Policies/EduReportCardPolicy.php` | **Modifié.** Ajout de l'ability `create` (périmètre `isManager`) → débloque la génération des bulletins. |
| `api/app/Modules/Billing/Application/Services/HorizontalToolSelection.php` | **Nouveau.** Service partagé (classe pure) de la sélection d'outils horizontaux + miroir `features`, extrait de `ProvisionGuidedTrial` et branché sur le chemin self-service. |
| `api/app/Modules/Billing/Application/Actions/ProvisionGuidedTrial.php` | **Modifié.** Délègue au service partagé (suppression des méthodes privées dupliquées). |
| `api/app/Modules/Billing/Application/Actions/VerifyTrialSignup.php` | **Modifié.** Applique `modules` / `company_type` du `signup_payload` au tenant provisionné ; passe la ligne `trial_provisionings` à `ready`. |
| `api/app/Modules/Billing/Interfaces/Api/V1/Controllers/SelfServiceTrialController.php` | **Modifié.** Crée la ligne de suivi au signup et renvoie `data.provisioning_token` (contrat déjà attendu par la vitrine). |
| `api/app/Modules/EduManager/Interfaces/Api/V1/Controllers/EduGuardianController.php` | **Nouveau.** CRUD des responsables légaux + rattachement/détachement élève. |
| `api/app/Modules/EduManager/Interfaces/Api/V1/Requests/StoreEduGuardianRequest.php` / `LinkEduStudentGuardianRequest.php` | **Nouveaux.** Validation bornée au tenant (allowlists + `Rule::exists`). |
| `api/app/Modules/EduManager/Interfaces/Api/V1/Controllers/EduGuardianPortalController.php` | **Modifié.** Ajout de `consume()` (route publique du portail parents, contrat de la vitrine) et `issueLinkForGuardian()`. |
| `api/routes/modules/edu_manager.php` | **Modifié.** Routes parents (CRUD, rattachement, émission par chemin) + groupe **public** `throttle:20,1` pour la consommation du lien. |
| `api/tests/Feature/EduManager/EduGuardianCrudTest.php` | **Nouveau.** 3 cas : création + rattachement + émission de lien ; RBAC employé → 403 ; isolation cross-tenant (élève et responsable). |
| `CHANGELOG.md` | Entrées sous `[Unreleased]` (convention du dépôt). |

Garde migrations du dépôt relancé après ajout : `✅ Aucune collision de migrations`.

**Preuve de bout en bout après correctif** : parcours école complet rejoué (§3.4) — année, matières, classe, élèves, inscriptions, évaluation, notes, **bulletin généré → validé → publié**, frais — tout passe ; l'erreur « Une erreur est survenue. » a disparu du parcours année scolaire.

**Impact mesuré sur la suite du dépôt** : `tests/Feature/EduManager/` passe de **152 échecs / 125 succès** (main) à **95 échecs / 182 succès** avec ce correctif, à protocole identique (base de test recréée avant chaque mesure).

> ⚠️ **Piège de reproduction** : ne pas oublier `DROP DATABASE leopardo_test` — le harnais `RefreshTenantDatabase` étant étatique (#6948), une nouvelle migration tenant **n'est pas appliquée** si le schéma canonique est déjà en place, et la mesure paraît inchangée.

### Ce que le correctif ne fait pas (volontairement)

Les **95 échecs restants** relèvent de renommages/contraintes non arbitrés (`period` vs `period_label`, `type` vs `assessment_type`, `applicant_*` NOT NULL) — voir §4 C et §8 P0-bis. Aucun correctif destructif n'a été appliqué sans décision du propriétaire.

---

## 8. Recommandations priorisées

**P0 — avant toute démo client**
1. **Fusionner le correctif de schéma EDU** (§7) puis **rejouer la suite `tests/Feature/EduManager/` sur `main`** — elle doit redevenir verte.
2. **P0-bis — Arbitrer la génération de schéma canonique** (décision propriétaire requise) : `edu_report_cards.period` **ou** `period_label` ? `edu_assessments.type` **ou** `assessment_type` ? Et faut-il lever les `NOT NULL` sur `edu_admissions.applicant_*` / `edu_attendance_records.attendance_record_id` (le code ne les alimente pas) ? Une fois tranché, écrire la migration de renommage/assouplissement correspondante — c'est ce qui reste entre 95 et 0 échec.
3. **Traiter la dérive de schéma à la racine** : les 4 jeux de migrations EDU dupliqués doivent être consolidés (un seul jeu canonique), et le garde `check-migration-basename-collisions.sh` **étendu** pour comparer les **tables/colonnes créées**, pas seulement les noms de fichiers. Vérifier au passage `fuel_station`, `restaurant`, `travelagency` : la même maladie a pu s'y produire.
4. **Fermer les trous de couverture** : un test HTTP (pas seulement service) par route de création EDU — au minimum `POST /edu-manager/report-cards/generate` (aujourd'hui 403 silencieux).
5. **Corriger le harnais de test** ou le documenter explicitement : `RefreshTenantDatabase` étatique (#6948) fait croire à tort qu'une nouvelle migration tenant est sans effet ; au minimum un garde « migration en attente » dans `canonicalSchemaReady()`.

**P1 — pour que le client travaille réellement**
6. **Honorer `modules[]` au provisioning self-service** : faire lire `modules` + `company_type` par `VerifyTrialSignup` (réutiliser la logique de `ProvisionGuidedTrial::resolveModuleSelection`). Sinon, à défaut, l'UI d'inscription ne doit plus **afficher** des cases non appliquées.
7. **Réparer le parcours self-service** : créer la ligne `trial_provisionings` au verify (ou exposer un moyen de définir son mot de passe), pour que `/trial/set-password` et `/trial/status` fonctionnent — condition d'un parcours autonome.
8. **Parents d'élèves** : route + écran de création du responsable légal et de rattachement à l'élève, puis émission du lien d'accès (le portail est déjà écrit).
9. **Flotte côté client** : décider entre un écran flotte dans le Web Client ou l'assumer côté admin ; **déployer Traccar** (`TRACCAR_URL`/`TRACCAR_API_TOKEN`) sans quoi « suivre l'itinéraire » n'est pas livrable.
10. **Onboarding par verticale** : un parcours « école » (campus → année → classe → élèves → enseignants → bulletins → frais) au lieu du parcours RH générique.
11. **Assistant IA** : ouvrir `AI_ENABLED`/`leo_ai`/`ai_cloud_allowed`, fournir une clé LLM, et **exposer une surface client**.

**P2 — qualité**
12. Cartes d'élèves (fonctionnalité à spécifier ; la règle du dépôt impose une spec dans `docs/specifications/` **avant** les tickets).
13. Messages d'erreur actionnables (code + cause) au lieu de « Une erreur est survenue. ».
14. Aligner `edu_classes.grade_level` / `level` (et réaligner `EduClassTest`).

---
## 9. Sécurité — à faire sans attendre

Les jetons transmis (GitHub, Vercel prod/dev, Render prod/dev, Cloudflare prod/dev) ont été utilisés **en lecture pour le diagnostic** et stockés dans un fichier local restreint :

```
/home/user/.workspace/.secrets/creds.env   (chmod 600)
```

Ils n'ont **pas** été écrits dans un dépôt, un commit, un rapport ou un service tiers. **Deux points d'attention :**

1. **Aucune action d'écriture n'a été faite sur les environnements hébergés** : pas de déploiement, pas de migration, pas de modification de configuration sur Vercel / Render / Cloudflare, ni en dev ni en prod. Toute la validation a été faite **en local**.
2. **À révoquer dès la fin de la mission** (comme prévu) : les 6 jetons ci-dessus, et surtout le jeton GitHub `ghp_…` qui porte des droits d'écriture sur le compte `kitokoh`. À noter : le dépôt `kitokoh/leopardo-hr` est **public**.

**Aucun secret n'a été détecté dans le clone** (`.secrets.baseline` présent, `.env` ignoré par git).

---

## 10. Reproduire l'environnement

```bash
# 1. Dépendances système (PHP 8.4 exige le PPA : Ubuntu ne fournit que 8.1)
sudo add-apt-repository -y ppa:ondrej/php && sudo apt-get update
sudo apt-get install -y php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml \
     php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl postgresql unzip

# 2. Base
sudo -u postgres psql -c "CREATE USER leopardo WITH PASSWORD 'secret' SUPERUSER;"
sudo -u postgres psql -c "CREATE DATABASE leopardo OWNER leopardo;"
sudo -u postgres psql -d leopardo -c "CREATE EXTENSION IF NOT EXISTS pgcrypto;"

# 3. API
cd api && cp .env.example .env
#    APP_ENV=local · APP_DEBUG=true · DB_HOST=127.0.0.1 · SESSION_DRIVER=file
#    CACHE_STORE=file · MAIL_MAILER=log · DEMO_MODE_ENABLED=true
composer install && php artisan key:generate --force
php artisan leopardo:migrate --fresh --seed --demo
php artisan serve --host=127.0.0.1 --port=8000

# 4. Web Client (:3000) et Web Admin (:3001)
cd front/web            && cp .env.local.example .env.local && npm install && npm run dev
cd front/admin-dashboard && cp .env.example .env.local      && npm install && npm run dev
```

Comptes utiles : `admin@leopardo-rh.com` / `password123` (super-admin), `ahmed.benali@techcorp-algerie.dz` / `password123` (manager principal), compte école créé par ce test : `direction@groupescolaire-palmiers.dz`.

---

*Rapport produit par l'agent DevOps/PM/QA. Toutes les affirmations sont vérifiées par exécution réelle (API + navigateur) sur la base fraîche `leopardo:migrate --fresh --seed --demo`, version API `4.24.0`.*

---

## 11. Deuxième passe (PM) — correctifs produit et nouvelle classe de défaut

Après la première passe, j'ai pris la suite en PM avec un objectif mesurable :
**rendre l'environnement du client réellement utilisable**, prouvé par le parcours
réel **et** par la suite de tests du dépôt.

### 11.1 Suite de tests EDU : 152 → 61 échecs

Mesuré sur base de test recréée à chaque fois (protocole identique) :

| Étape | Échecs | Succès |
|---|---|---|
| `main` (référence) | **152** | 125 |
| + migration de réparation du schéma | 95 | 182 |
| + contraintes (statuts d'inscription, `type` d'évaluation) | 76 | 204 |
| + `edu_students.metadata` jsonb→text | 68 | 212 |
| + **routes `fee-types`** | **61** | **219** |

**Et la suite ne se bloque plus** : elle tournait indéfiniment (voir 11.3).

### 11.2 Quatre chaînons produit manquants (corrigés)

| # | Défaut | Impact client | Correctif |
|---|---|---|---|
| A | Outils cochés à l'inscription ignorés en self-service | « Comptabilité » cochée → espace sans compta | service partagé `HorizontalToolSelection` branché sur les 2 chemins |
| B | Aucune ligne `trial_provisionings` en self-service | `/trial/status` et `set-password` = 404 ; mot de passe impossible à définir | ligne créée au signup + passée à `ready` au verify |
| E | Parents non créables + **portail parents sur des routes inexistantes** | Portail parents inatteignable | CRUD responsables légaux + route `consume` publique + émission par chemin |
| F-bis | Types de frais scolaires sans route (modèle + requête écrits) | Tarifs (scolarité, cantine…) impossibles à créer | `EduFeeTypeController` + `GET|POST /fee-types` |

### 11.3 Classe de défaut systémique : **un effet de bord qui empoisonne la transaction**

Trouvé en cherchant pourquoi `tests/Feature/SelfServiceTrialTest.php` **ne se terminait
jamais** (blocage infini, reproduit **sur `main`**, indépendant de tout changement) :

```
PostgreSQL : ERROR: relation "platform_outbox_events" does not exist
          → ERROR: current transaction is aborted, commands ignored until end of transaction block
          → connexion laissée « idle in transaction (aborted) » (verrous conservés)
          → le seeding du test suivant se bloque sur : insert into "public"."plans"
```

Trois causes cumulées :

1. **Table non résolue** : `VerifyTrialSignup::execute()` fait `SET search_path TO public`
   avant de provisionner, or `platform_outbox_events` vit dans `shared_tenants` — le
   publisher la requêtait sans qualifier le schéma.
2. **Événement plateforme perdu** : l'erreur était absorbée par un `catch` PHP
   (l'intention #6958 est bonne) — mais **un `catch` PHP ne rattrape pas une transaction
   PostgreSQL avortée**. Résultat : `CompanyCreated` **jamais persisté** dans l'outbox
   (18 occurrences dans les logs de la session), audit et consommation asynchrone perdus.
3. **Échec hors savepoint** : le pre-SELECT de déduplication s'exécutait HORS transaction
   imbriquée ; en test (transaction englobante `RefreshDatabase`), il abortait la
   transaction du test → cascade + verrous → **blocage de la suite**.

**Correctif** : résolution **explicite** du schéma (garde F-17, avec repli
`information_schema` borné à `shared_tenants`/`public` — `resolveTableSchema()` filtre
sur `search_path` et renvoyait `null` ici) **et** publication entière dans un
**savepoint**. Vérifié : la table est résolue même sous `search_path=public`, l'événement
est persisté (`platform.company.created`), l'avertissement disparaît, et
`SelfServiceTrialTest` passe de « blocage infini » à 13/14 en 7 s (l'unique échec restant
est **pré-existant** : reproduit à l'identique sur `main`).

> **Leçon transverse** (les trois mêmes causes se répètent dans ce dépôt) :
> 1. une erreur SQL **avorte la transaction** — `try/catch` PHP ≠ rollback ;
> 2. une table doit être **qualifiée par son schéma** dès qu'un appelant peut changer le
>    `search_path` ;
> 3. **deux surfaces développées séparément finissent par ne plus se parler** (front ↔
>    back, route ↔ contrôleur, modèle ↔ migration) : c'est la cause de 100 % des défauts
>    de cette mission.

### 11.4 Ce qui reste (inventaire, non traité)

La génération « B » du module EDU a été fusionnée **incomplètement**. Endpoints attendus
par les tests et par les écrans, toujours **absents** :

| Endpoint | Constat |
|---|---|
| `GET /edu-manager/fee-accounting-entries` | attendu par `EduFeeTest` (5 cas) |
| `POST /edu-manager/fees/{fee}/charges` | idem |
| `PUT|DELETE /edu-manager/fee-types/{id}` | CRUD partiel (création/lecture livrées) |
| noms de contraintes | 5 tests attendent `edu_classes_year_company_fk` alors que la base porte `edu_classes_academic_year_company_fk` (comportement correct, **nom** divergent) |

Et les faiblesses de test à traiter : `SelfServiceTrialTest::test_verify_locks_email_after_five_failed_attempts`
échoue sur `main` (attend `400`, reçoit `429`), et plusieurs tests appellent un **service**
au lieu de la **route** (trou de couverture qui a laissé passer le 403 des bulletins).
