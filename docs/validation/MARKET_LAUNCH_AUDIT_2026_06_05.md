# Market launch audit - 2026-06-05

## Decision

**Go pilote payant controle. No-go scale massif sans recette device et packaging final.**

## Observations

- Le depot est techniquement avance: API, mobile multi-app, platform admin, docs, CI, OpenAPI, readiness.
- Le marche 2026 favorise consolidation, automatisation, analytics, experience employe et IA gouvernee.
- Le positionnement doit eviter "SIRH complet" et pousser "Mobile-First Company OS".
- Le dernier bug concret trouve pendant cet audit est le bouton demo Platform Admin mobile: il remplissait `admin` au lieu de `password123`.

## Corrections realisees dans ce lot

- `front/mobile_apps/leopardo_platform_admin/lib/src/features/auth/platform_login_screen.dart`: compte demo corrige avec `password123`.
- `dev-hub/tools/validate-mobile-plan29.ps1`: garde CI contre retour du mauvais mot de passe.
- `front/mobile_apps/leopardo_platform_admin/README.md`: credentials demo documentes.
- Nouveau dossier go-to-market 2026.
- Nouveau dossier contexte IA.
- Nouveau Plan 70 avec 72 actions.

## Validation API Render

Commande smoke PowerShell:

- `GET https://gestionemployerbackend.onrender.com/api/v1/demo-users`
- `POST https://gestionemployerbackend.onrender.com/api/v1/platform/auth/login`
- `GET https://gestionemployerbackend.onrender.com/api/v1/platform/auth/me`

Resultat:

- demo-users: `200`
- platform login: `200`
- token recu: oui
- platform auth me: `200`
- compte: `admin@leopardo-rh.com`
- `two_fa_enabled=false`

## Score

| Axe | Score |
|---|---:|
| Technique backend/API | 9/10 |
| Mobile runtime | 8.5/10 |
| Platform admin demo | 9/10 apres correction code |
| Go-to-market clarity | 8/10 apres nouveau dossier |
| Preuves terrain | 6/10 |
| Scale readiness | 7.5/10 |

Score global: **82/100 pour lancement pilote payant**, **pas encore production massive sans reserve**.

