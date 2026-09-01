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
| GET | `/api/v1/solutions/{code}/pack?packages=k1,k2` | — | PDF A4 du pack (dompdf, i18n `solutions.*` ×4) |
| POST | `/api/forms/solution-survey` (vitrine Next) | `{ email, consent: true, data: { solution, answers, packages } }` | Lead persiste via PA2-MKT-007 (`marketing_leads`, type `solution_survey`) |

## Capture du lead (#6692)

Le wizard (étape téléchargement) propose « Recevez votre pack par email » : email + **consentement marketing explicite** (obligatoire, horodaté dans `payload.consented_at`). La route Next `front/web/src/app/api/forms/solution-survey/route.ts` valide (zod, rate-limit) puis persiste via `captureMarketingLead()` → `POST /marketing/leads` (secret partagé, idempotent) avec le pack sélectionné dans `payload`.

**RGPD** : aucun lead sans consentement ; le registre `docs/RGPD_REGISTRE_TRAITEMENTS.md` doit référencer ce traitement (follow-up).

## Activation d'une solution sur un tenant (#6693)

La commande existante active une solution par tenant (idempotente, auditée, fail-closed) :

```bash
php artisan leopardo:solution:activate {company_uuid} restaurant [--actor={employee_id}]
```

L'activation **à l'inscription** (provisioning tenant → `SolutionActivator`) reste à câbler (issue #6693).

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

## Budget zéro — dépendances

- Backend : aucune nouvelle dépendance (PHP pur).
- Front : `qrcode` et `framer-motion` déjà présents dans `front/web/package.json`.
- Distribution APK : GitHub Releases ou Firebase App Distribution (free tier) — liens branchés via `mobileDownloadTarget`.
- Edge : commande `install.sh` existante (serveur Render).

## À faire (prochaines étapes)

- [x] Rendu PDF du pack (dompdf) — `GET /solutions/{code}/pack`, i18n serveur `solutions.*` ×4 (fr/en/tr/ar)
- [x] Labels ×4 du wizard (SOLUTION_LABELS tr/ar + garde PA2-I18N-014) — #6691
- [x] Capture des leads (email + consentement RGPD) via `POST /api/forms/solution-survey` → `marketing_leads` (type `solution_survey`) — #6692
- [ ] Branchement `/i18n/catalog` pour les messages de réponse des routes forms
- [ ] Activation tenant post-inscription via `SolutionActivator` (commande `leopardo:solution:activate` documentée, wiring signup à faire) — #6693
- [ ] Écran admin (Vue) de pilotage des surveys (stats de conversion) — #6694
