# Solution Restaurant — Questionnaire de pré-qualification (« Je suis restaurateur »)

> Statut : **squelette v1** (branche `feat/restaurant-solution-survey`)
> Base : main vérifiée le 2026-09-01

## Objectif

Un prospect déclare son profil (« je suis restaurateur »), répond à un mini-questionnaire, et le système lui **propose automatiquement le pack Leopardo** adapté (apps mobiles, modules, kiosque, nœud Edge). Il coche ce dont il a besoin et **télécharge** (QR / APK / commande d'installation Edge / guide).

Le tout **sans aucune API payante** : règles pures en PHP côté serveur, libs open source côté front (`qrcode`, framer-motion, déjà en deps).

## Architecture

```text
Vitrine web (/restaurant)                     API Laravel
┌──────────────────────────────┐             ┌──────────────────────────────────────┐
│ RestaurantSolutionWizard     │  GET  /solutions                    │ SolutionSurveyController │
│  (Next.js, client)           │──────────▶│  (Core\Solutions\Interfaces)          │
│                              │  GET  /solutions/restaurant/survey  │   ├── SolutionCatalogue  │
│  questions ──► réponses      │──────────▶│   └── SolutionSurveyRegistry           │
│  pack suggéré (coché)        │  POST /solutions/restaurant/survey  │   └── SolutionSurveyEngine │
│  QR + liens + Edge + guide   │◀─────────│        (règles pures PHP)               │
└──────────────────────────────┘             └──────────────────────────────────────┘
```

### Couches

| Brique | Emplacement | Rôle |
|---|---|---|
| Contrat survey | `api/app/Core/Solutions/Survey/Contracts/SolutionSurvey.php` | Interface question/catalogue/règles |
| Registre | `api/app/Core/Solutions/Survey/SolutionSurveyRegistry.php` | Allowlist serveur, fail-closed (miroir de `SolutionCatalogue`) |
| Moteur | `api/app/Core/Solutions/Survey/SolutionSurveyEngine.php` | Évalue les réponses → packages triés par priorité + raisons |
| Manifest | `api/app/Modules/Restaurant/Domain/Solution/RestaurantManifest.php` | Le « pack » de la solution (modules requis/optionnels) |
| Survey | `api/app/Modules/Restaurant/Domain/Survey/RestaurantSurvey.php` | Questions + catalogue de packages + règles |
| Provider | `api/app/Modules/Restaurant/Providers/RestaurantServiceProvider.php` | Enregistre manifest + survey (avec garde `bound()`) |
| Controller | `api/app/Core/Solutions/Interfaces/Api/V1/SolutionSurveyController.php` | Endpoints publics (index / questions / suggest) |
| Routes | `api/routes/modules/solutions.php` | Publiques, `throttle:10,1` |
| Front | `front/web/src/modules/vitrine/lib/solution-survey.ts` + `components/RestaurantSolutionWizard.tsx` | Wizard vitrine + page `(landing)/restaurant/page.tsx` |

## Endpoints

| Méthode | URL | Corps | Réponse |
|---|---|---|---|
| GET | `/api/v1/solutions` | — | `{ data: [{ code, name, description, maturity }] }` |
| GET | `/api/v1/solutions/{code}/survey` | — | `{ data: { code, questions, packages } }` |
| POST | `/api/v1/solutions/{code}/survey` | `{ answers: { clé_question: valeur } }` | `{ data: { code, packages: [{ key, type, label_key, reason_key, priority, app?, download? }], total } }` |
| GET | `/api/v1/solutions/{code}/pack?packages=k1,k2` | — | PDF A4 du pack (dompdf, i18n `solutions.*`) |

## Ajouter une nouvelle solution (ex. FuelStation)

1. `SolutionManifest` (déjà fait pour FuelStation) : `Modules/FuelStation/Domain/Solution/…`.
2. `SolutionSurvey` : `Modules/FuelStation/Domain/Survey/…` — questions, catalogue, règles.
3. Enregistrer les deux dans le provider du module (patterns ci-dessus).

Le moteur, le registre et les endpoints sont **génériques** : aucun changement nécessaire dans `Core/Solutions`.

## Choix assumés (v1)

- **Moteur déterministe, zéro IA** : une ligne de règle = `{package, priority, when, reason_key}`. Un modèle (Ollama self-host) pourrait plus tard réordonner les packages sans changer le contrat.
- **Modules « futur » déjà au catalogue** (stock, delivery, pos, reservations) : le front peut les afficher, le manifest ne les active pas côté serveur.
- **Label_i18n clés** : `label_key`/`reason_key` résolus par `SOLUTION_LABELS` côté front (fr/en complets, tr/ar → en). Chemin production : catalogue central `/i18n/catalog`.
- **Sans inscription** : endpoints publics (pré-qualification). L'activation réelle d'une solution par tenant passe par l'existant `SolutionActivator` (feature flags) au moment du provisioning.

## Activation de la solution sur un tenant (#6693)

### Activation automatique à l'inscription

Le signup self-service (`POST /api/v1/trial/signup`) accepte un champ optionnel
`solution` (ex. `restaurant`), validé **fail-closed** contre l'allowlist du
`SolutionCatalogue` (code inconnu → 422, jamais de résolution dynamique).

Au moment du provisioning (vérification OTP, `VerifyTrialSignup`), la solution
demandée est activée dans la transaction du tenant :

1. les **modules transversaux requis** par le manifest sont activés
   (`Company::setFeature`, ex. `attendance`, `documents`, `notifications`) ;
2. `SolutionActivator::activate()` pose le feature flag `restaurant` et trace
   `solution.activated` (audit).

Un échec d'activation est loggé (`trial.signup.solution_activation_failed`) et
**ne fait jamais échouer** la création du compte : l'ops peut ré-activer via la
commande console ou le dashboard (voir ci-dessous).

### Activation manuelle (ops / super-admin)

- **Console** : `php artisan leopardo:solution:activate {company} restaurant`
  (idempotente ; refuse un code inconnu ou un tenant dont les modules requis
  sont inactifs — `SolutionMissingDependencyException`).
- **Dashboard super-admin** : édition de la société (`/platform/companies/{id}/edit`),
  toggles `features` par module connu (`Company::KNOWN_MODULES` — `restaurant`
  y est déclaré), puis sauvegarde.

## Budget zéro — dépendances

- Backend : aucune nouvelle dépendance (PHP pur).
- Front : `qrcode` et `framer-motion` déjà présents dans `front/web/package.json`.
- Distribution APK : GitHub Releases ou Firebase App Distribution (free tier) — liens branchés via `mobileDownloadTarget`.
- Edge : commande `install.sh` existante (serveur Render).

## À faire (prochaines étapes)

- [x] Rendu PDF du pack (dompdf) — `GET /solutions/{code}/pack`, i18n serveur `solutions.*`
- [x] Traductions tr/ar des labels (ou branchement `/i18n/catalog`) — #6691
- [x] Persistance des leads (réponses) via le webhook `MarketingLeadController` existant — #6692
- [x] Activation tenant post-inscription via `SolutionActivator` — #6693
- [x] Écran admin (Vue) de pilotage des surveys (stats de conversion) — #6694
