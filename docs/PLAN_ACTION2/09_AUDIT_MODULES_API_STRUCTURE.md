# Audit modules API — structure et hygiene DDD — 2026-07-19

Statut: complete pour publication
Auteur: audit interne KiloClaw (agent), a la demande de kitokoh, contexte demo testeurs/developpeurs (securite stricte hors perimetre de cet audit — voir `docs/security/AUDIT_API_2026-07-19.md` et `08_AUDIT_ARCHITECTURE_TECH.md` pour ce volet)
Perimetre: `api/app/Modules/*` (19 modules), respect de `CONVENTIONS.md` et `api/ARCHITECTURE.md`, garde CI `module-structure-check`.

Ce document complete `08_AUDIT_ARCHITECTURE_TECH.md` (RBAC, dependances composer, moteur paie, secrets) avec un audit structurel module par module : conformite au schema DDD documente, code mort issu de migrations inachevees, doublons de policies. Methode : scripts de verification (arborescence, namespaces PSR-4, imports croises, `declare(strict_types=1)`, controllers non references par les routes) plutot qu'analyse statique PHPStan (pas de runtime PHP/Composer disponible dans l'environnement d'audit) — a rejouer en CI reelle pour validation finale.

---

## 1. Le garde CI `module-structure-check` ne couvre que 16 modules sur 19

> Mise a jour 2026-07-19 (post-verification) : entre la premiere passe de cet audit et sa publication, le module `Marketing` a ete corrige en amont (Phase 3 mergee sur `main`, commit `e495311`/`911465a`) — `Marketing/Interfaces/` existe desormais et `architecture-check.yml` inclut `Marketing` dans sa boucle. Le constat ci-dessous est donc **partiellement resolu** ; il reste neanmoins **`SmartAttendance` et `EdgeSync`**, toujours absents de la boucle CI au moment de cette publication.

`.github/workflows/architecture-check.yml` boucle sur une liste codee en dur : `HR Payroll Attendance Planning Recruitment Cabinet Fleet Billing Cameras Absence Expense Growth Platform Onboarding Training Notification Marketing`. Il manque toujours `SmartAttendance` et `EdgeSync`, qui existent bien sur `app/Modules/` et sont enregistres dans `bootstrap/providers.php`. Consequence directe, une anomalie structurelle reelle n'est jamais detectee :

- `app/Modules/EdgeSync/Infrastructure/` **manquant**. Le module a `database/`, `Notifications/`, `Jobs/`, `routes/` a la place — hors du schema DDD documente (`Domain/Application/Infrastructure/Interfaces/Providers`).

## 2. Code mort — controllers dupliques jamais branches sur les routes

Recherche systematique (controller par controller vs contenu de `routes/` + `app/Modules/*/routes/`) : plusieurs controllers complets existent en double dans deux modules pour la meme fonctionnalite, residu de migrations DDD inachevees ou l'ancien emplacement legacy reste cable et la nouvelle copie "propre" n'est jamais executee.

| Controller orphelin (jamais route) | Copie active reellement cablee |
|---|---|
| `App\Modules\Training\Interfaces\Api\V1\Controllers\TrainingController` | `App\Modules\HR\Interfaces\Api\V1\Controllers\TrainingController` (`routes/modules/hr_extended.php`) |
| `App\Modules\Onboarding\Interfaces\Api\V1\Controllers\OnboardingQrController` | `App\Modules\HR\Interfaces\Api\V1\Controllers\OnboardingQrController` (`routes/modules/rh.php`) |
| `App\Modules\Planning\Interfaces\Api\V1\ExpenseClaimController` | `App\Modules\Expense\Interfaces\Api\V1\Controllers\ExpenseClaimController` (`routes/modules/expense.php`) |
| `App\Modules\Billing\Interfaces\Api\V1\EstimationController` | `App\Modules\Payroll\Interfaces\Api\V1\EstimationController` (`routes/modules/rh.php`) |

Risque concret pour la demo : un testeur/dev qui corrige un bug dans le controller du module "logique" (celui qui porte le bon nom de module d'apres l'architecture documentee) ne voit **aucun effet**, car le trafic reel passe par l'autre copie. Le module `Training` va plus loin : il a 3 Actions + 1 controller complets, mais zero route ne les utilise — 100% du trafic reel transite encore par le controller legacy sous `HR`.

## 3. Policies enregistrees deux fois, avec une incoherence reelle sur `Invoice`

`app/Providers/AppServiceProvider.php::boot()` et `app/Providers/AuthServiceProvider.php::boot()` enregistrent chacun des `Gate::policy()` pour un sous-ensemble largement recoupant de modeles (`Employee`, `AttendanceLog`, `Evaluation`, `Subscription`, `PayrollRun`/`PaySlip`, `JobPosting`/`Applicant`, `TrainingCourse`, `Vehicle`). La plupart du temps la meme Policy est assignee dans les deux fichiers (redondant mais inoffensif). **Une divergence reelle existe sur `Invoice`** :

- `AppServiceProvider::boot()` → `Gate::policy(Invoice::class, BillingPolicy::class)`
- `AuthServiceProvider::boot()` → `Gate::policy(Invoice::class, InvoicePolicy::class)`

L'ordre de boot des deux providers dans `bootstrap/providers.php` determine silencieusement laquelle des deux Policy s'applique reellement sur `Invoice` — sans erreur, sans log, un comportement d'autorisation qui depend d'un ordre d'enregistrement plutot que d'une decision explicite. C'est le genre de bug latent qui peut resister longtemps a un debug ("j'ai corrige la policy et rien ne change" si on modifie celle qui perd).

## 4. Controllers epais / deficit d'Actions au-dela des 4 modules deja notes dans `ARCHITECTURE.md`

`api/ARCHITECTURE.md` note deja "Application layer : enrichir les Actions dans Growth, Platform, Onboarding, Training — trop peu d'Actions, controllers trop epais". Le comptage Actions/Controllers par module montre que ce constat s'applique aussi, voire plus severement, a des modules non listes :

| Module | Actions | Controllers | Constat |
|---|---|---|---|
| Cameras | 0 | 6 | Aucune Action — logique entierement dans controllers/services |
| EdgeSync | 0 | 3 | Idem |
| HR | 4 | 28 | Ratio le plus deficitaire en volume absolu |
| Payroll | 3 | 17 | Idem |
| Billing | 2 | 10 | `SelfServiceTrialController` (531 lignes) fait 16 appels directs `::create()/::where()/DB::` sans passer par une Action |
| Platform | 3 | 15 | Deja note dans `ARCHITECTURE.md` |

## 5. Adoption inegale de `declare(strict_types=1)`

`CONVENTIONS.md` impose `declare(strict_types=1)` partout. Les modules les plus recents (`Expense`, `Training`, `SmartAttendance`, `Marketing`) sont a 100% conformes — la regle est connue et appliquee sur le code neuf. Elle n'a en revanche jamais ete retrofittee sur le code plus ancien : `HR` (46/77 fichiers sans, 60%), `Cameras` (20/26, 77%), `Attendance` (24/42, 57%), `Payroll` (30/71, 42%). Risque faible (typage strict absent, pas un bug en soi) mais ecart mesurable et facile a corriger mecaniquement.

---

## 6. Backlog d'action — nouveaux tickets PLAN_ACTION2

Format identique a `02_BACKLOG_ATOMIQUE.md` / `08_AUDIT_ARCHITECTURE_TECH.md`. Copie faite dans `02_BACKLOG_ATOMIQUE.md` et `03_GITHUB_PROJECT_IMPORT.csv` dans ce meme commit.

### Architecture — P1/P2

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ARCH-006 | P1 | Etendre `module-structure-check` a SmartAttendance/EdgeSync (Marketing deja fait) | CI, `.github/workflows/architecture-check.yml` | boucle generee depuis `app/Modules/*` (pas de liste codee en dur) ; statut de `EdgeSync/Infrastructure` tranche (mise en conformite ou derogation documentee dans `ARCHITECTURE.md`) |
| PA2-ARCH-007 | P1 | Supprimer les controllers dupliques jamais routes | API | `Training/TrainingController`, `Onboarding/OnboardingQrController` migres et le doublon HR supprime ; `Planning/ExpenseClaimController` et `Billing/EstimationController` supprimes (aucune migration en cours) ; garde CI detectant un controller jamais reference dans `routes/` |
| PA2-ARCH-008 | P1 | Point d'enregistrement unique pour les Gate::policy | API/Providers | plus qu'un seul provider enregistre chaque policy (recommande : par ServiceProvider de module) ; divergence `Invoice` -> `BillingPolicy` vs `InvoicePolicy` tranchee explicitement ; test unitaire verifiant l'absence de double enregistrement |
| PA2-ARCH-009 | P2 | Retrofit `declare(strict_types=1)` sur modules anciens | API | HR/Payroll/Attendance/Cameras a 100% ; garde CI incremental refusant tout nouveau fichier sans la directive |

---

## 7. Recapitulatif executif

| Domaine | Etat | Severite |
|---|---|---|
| Garde CI structure de modules | Ne couvre que 16/19 modules (Marketing corrige entre-temps), 1 anomalie residuelle (EdgeSync/Infrastructure) non detectee | Faible-moyen |
| Code mort (controllers dupliques non routes) | 4 controllers orphelins confirmes, dont 1 module (Training) entierement non branche | Moyen |
| Policies dupliquees entre providers | Divergence reelle sur `Invoice` (BillingPolicy vs InvoicePolicy) | Moyen |
| Controllers epais / deficit Actions | Confirme au-dela des 4 modules deja notes dans `ARCHITECTURE.md` | Faible-moyen (dette, non bloquant demo) |
| `declare(strict_types=1)` | Bien applique sur code neuf, absent sur ~40-60% du code ancien | Faible |
| Namespaces PSR-4 | Conformes partout (0 anomalie hors fichiers sans namespace legitimes : routes/migrations) | OK |
