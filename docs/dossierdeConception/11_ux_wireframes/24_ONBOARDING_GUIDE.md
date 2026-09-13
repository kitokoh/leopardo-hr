# ONBOARDING GUIDÉ — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## DESCRIPTION

L'onboarding guidé est le **premier écran que voit un client après inscription**.
Il conditionne directement le taux de conversion Trial → Payant.
Il s'affiche automatiquement si `company.metadata.onboarding_completed !== true`
(persisté côté serveur depuis #7262 ; l'ancien `company_settings.onboarding_completed`
n'a jamais existé côté API — voir « SOURCE DE VÉRITÉ » ci-dessous).

---

## SOURCE DE VÉRITÉ DE LA PROGRESSION (#7300)

> **Décision (2026-09-13).** Trois surfaces exposaient trois progressions
> différentes pour le **même tenant au même instant** — assistant client 100 %,
> moteur calculé 38 %, back-office admin 40 % — parce que trois modèles
> coexistaient sans hiérarchie. La divergence a été corrigée en désignant une
> source unique, et non en recalant les chiffres à la main.

| Surface | Rôle | Source de la progression |
| :--- | :--- | :--- |
| `GET /onboarding-setup/checklist` | **SOURCE DE VÉRITÉ** — checklist pilotée par l'utilisateur | table `onboarding_steps` (10 étapes) |
| `GET /onboarding/checklist` | **DÉPRÉCIÉ** — moteur d'observation (8 prédicats serveur) | relaie la source de vérité ; ses faits restent sous `observed` |
| `GET /platform/companies/{id}/health` → `adoption.onboarding` | back-office / CSM | relaie la source de vérité ; l'usage réel est sous `observed` |

Règles qui en découlent :

- **« Onboarding » et « adoption » sont deux notions distinctes** et ne doivent
  jamais être affichées l'une pour l'autre : l'onboarding est ce que le client
  *déclare avoir configuré* ; l'adoption est ce que le serveur *observe* (équipe
  active, bases de paie, géofence, premier pointage).
- Une seule implémentation du calcul :
  `App\Modules\Onboarding\Application\Services\OnboardingProgressReader`.
  Les surfaces ci-dessus la consomment ; aucune ne recalcule la sienne.
- `progress_percent` compte les étapes `completed` **et** `skipped` ;
  `go_live_ready` exige que **toutes** les étapes `required` soient `completed`.
- Une société sans étape seedée renvoie `initialized: false` : l'affichage doit
  montrer « non initialisé », jamais 0 % (qui se lirait « mauvais élève »).
- Verrou : `api/tests/Feature/Onboarding/OnboardingProgressAlignmentTest.php`
  échoue si une surface réintroduit une échelle parallèle.

---

## LES 10 ÉTAPES CANONIQUES (`SeedDefaultSteps`)

L'ancienne description « 4 étapes obligatoires » ne correspond plus au produit.
La checklist réelle compte **10 étapes**, dont 5 obligatoires :

| # | `step_key` | Obligatoire | Condition serveur (`StepCompletionGuard`) |
| --- | :--- | :--- | :--- |
| 1 | `company_info` | oui | déclarative |
| 2 | `first_department` | oui | une ligne `Department` existe |
| 3 | `first_employee` | oui | `Employee::count() > 1` (le manager compte) |
| 4 | `first_attendance` | oui | déclarative |
| 5 | `invite_manager` | non | une invitation existe |
| 6 | `configure_schedules` | oui | déclarative |
| 7 | `first_report` | non | déclarative |
| 8 | `configure_payroll` | non | un employé a `salary_base` ou `hourly_rate` > 0 |
| 9 | `install_kiosk` | non | une borne `active` existe |
| 10 | `activate_geofence` | non | `metadata.attendance_geofence` avec rayon > 0 |

Les étapes **déclaratives** n'ont pas de prédicat serveur : elles sont validées
sur la seule action de l'utilisateur. Les autres renvoient
`422 ONBOARDING_STEP_NOT_DONE` tant que la donnée réelle n'existe pas — c'est
volontaire (#7261) : `go_live_ready` doit mesurer un démarrage réel.

---

## API — Endpoints onboarding

> **Mise à jour #4929 (2026-08-17) — contrat réel.** L'ancien contrat
> (`GET /onboarding/status`, `POST /onboarding/complete-step`,
> `POST /onboarding/skip`, colonnes `company_settings.onboarding_step_*`)
> **n'a jamais été implémenté** et est retiré de cette documentation. Le
> contrat canonique, consommé par le wizard web (Next.js) et les apps
> mobiles Flutter, est piloté par la table `onboarding_steps` (10 étapes) :

### GET /onboarding-setup/checklist
Retourne la checklist pilotée par la table `onboarding_steps` (seedée au
provisioning, seed paresseux si absente). Shape canonique :
`data{ completed_steps, total_steps, progress_percent, progress, go_live_ready, next_actions, steps }`.

```json
{
  "data": {
    "completed_steps": 0,
    "total_steps": 10,
    "progress_percent": 0,
    "progress": 0,
    "go_live_ready": false,
    "next_actions": [
      { "key": "company_info", "label": "Renseigner les informations entreprise" }
    ],
    "steps": [
      { "step_key": "company_info", "title": "Renseigner les informations entreprise", "order": 1, "required": true, "status": "pending" }
    ]
  }
}
```

### PATCH /onboarding-setup/{stepKey}/complete
Marque une étape comme complétée (manager uniquement). Résilient : si la
société n'a aucune étape seedée, le seed est déclenché avant la résolution
(#4929). Une clé inconnue répond 404.

### PATCH /onboarding-setup/{stepKey}/skip
Saute une étape **optionnelle** (`required=false`). Une étape requise répond
422.

### GET /onboarding/checklist — DÉPRÉCIÉ (lecture seule)
Checklist calculée de « go-live readiness » (8 étapes auto-détectées) :
`company_created, manager_active, employees_added, employees_active,
payroll_ready, geofence_configured, biometrics_ready, kiosk_connected`.
Conservée pour les clients existants ; le contrat canonique est
`/onboarding-setup/checklist`.

---

## LOGIQUE BACKEND

```php
// #4929 : la source de vérité est la table onboarding_steps (10 étapes)
// seedée par SeedDefaultSteps (action canonique) :
//   company_info, first_department, first_employee, first_attendance,
//   invite_manager, configure_schedules, first_report, configure_payroll,
//   install_kiosk, activate_geofence
// Appelée au provisioning (CompanyProvisioningService) + paresseusement par
// GET/PATCH /onboarding-setup/*. Les anciens champs
// company_settings.onboarding_step_{1..4}_done N'EXISTENT PAS (jamais
// migrés) — ne pas les utiliser.

// AUTO-COMPLETION de la checklist calculée (8 étapes, DÉPRÉCIÉE) :
// company_created : company existe
// manager_active  : manager principal actif
// employees_added : Employee::count() >= 2
// employees_active: comptes employés activés
// payroll_ready   : bases de paie renseignées
// geofence_configured / biometrics_ready / kiosk_connected
```

## COMPOSANT RÉEL — `OnboardingWizard.tsx` (web) / mobile Flutter

> **Correction #7300.** La section précédente décrivait un composant **Vue.js**
> (`OnboardingWizard.vue`, propriétés `onboarding.completed`,
> `trial_days_remaining`, « 4 étapes rapides ») qui **n'existe pas** : le
> composant réel est **React** et vit dans `front/web/src/modules/onboarding/`.
> Un développeur suivant l'ancienne spec implémentait un contrat mort.

- **Composant** : `front/web/src/modules/onboarding/components/OnboardingWizard.tsx`
  (assistant client, modale), consommé par `front/web/src/app/(dashboard)/layout.tsx`.
- **Mobile** : `front/mobile_apps/leopardo_core/lib/features/onboarding/` — même
  endpoint canonique `/onboarding-setup/checklist`.
- **Données** : `GET /onboarding-setup/checklist` pour la progression et les
  étapes ; `PATCH /onboarding-setup/{stepKey}/complete|skip` pour agir.
  Le wizard lit en plus `GET /onboarding/checklist` (déprécié) **uniquement**
  pour `employees_count` (badge Quick Start) et l'auto-complétion d'étapes dont
  la condition serveur est déjà vraie.
- **Libellés** : catalogue i18n partagé (`onboarding.*`, ×4 langues), jamais de
  littéral dans le composant (garde PA2-I18N-014).
- **Fin de parcours** : le serveur persiste `company.metadata.onboarding_completed`
  (#7262) ; le client ne décide plus seul qu'il a terminé.

## DÉCLENCHEMENT

> **Correction #7300.** L'extrait Inertia/PHP ci-dessous décrivait un contrôleur
> et une table (`CompanySetting`, `OnboardingService::getStatus()`) qui
> **n'existent pas** — le web n'utilise pas Inertia et aucune colonne
> `onboarding_completed` ne vit dans une table dédiée.

Le déclenchement réel :

1. `front/web/src/app/(dashboard)/layout.tsx` ouvre l'assistant si
   `user.company.metadata.onboarding_completed !== true`
   (champ renvoyé par `/auth/me`, `EmployeeResource`).
2. Le serveur est **seul juge** de la fin de parcours : dès que toutes les
   étapes sont `completed` ou `skipped`, `OnboardingStepController` persiste
   `company.metadata.onboarding_completed = true` (+ `onboarding_completed_at`),
   de façon idempotente (#7262). Aucun appareil ne peut donc « croire » avoir
   terminé à la place du serveur.
3. Une pastille « Reprendre l'onboarding » reste accessible dans la barre
   latérale tant que le drapeau serveur est absent.

## EMAILS AUTOMATIQUES (séquence Trial)

| Déclencheur | Email envoyé |
|-------------|-------------|
| J+0 (inscription) | Bienvenue + lien onboarding |
| J+1 (si étape 1 non faite) | "Ajoutez votre premier employé en 2 min" |
| J+7 (si onboarding incomplet) | "Astuce : configurez le pointage mobile" |
| J+12 | "Votre trial se termine dans 2 jours" |
| J+14 (expiration) | "Votre compte est suspendu — vos données sont conservées 30 jours" |

---

## MODE QUICK START (< 15 employes)

### Detection a l'inscription
```
Question : "Combien d'employes avez-vous ?"
- Moins de 15  -> onboarding.mode = quickstart
- 15 a 50      -> onboarding.mode = standard
- Plus de 50   -> onboarding.mode = enterprise
```

### Parcours quickstart (3 etapes)
```
Etape 1 : Ajouter un employe minimal (prenom, nom, taux journalier/horaire)
Etape 2 : Installer mobile et realiser un pointage test
Etape 3 : Activer le suivi "ce que je dois aujourd'hui"
```

### Regle de fallback
- Si `onboarding.mode = quickstart`, appliquer automatiquement un planning par defaut `08:00-17:00`, `Lun-Sam`.
- Le passage vers onboarding standard reste possible a tout moment depuis Parametres.
