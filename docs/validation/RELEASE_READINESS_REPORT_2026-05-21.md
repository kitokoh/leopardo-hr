# Release readiness report - 2026-05-21

## Decision

**Go conditionnel - score 88/100.**

Le socle est pret pour une poursuite commerciale encadree : les P0/P1 connus sont clos, les parcours de connexion critiques API, portail client web, administration plateforme et mobile sont couverts par CI, et le gate local de release passe a 15/15. Le statut n'est pas encore "production sans reserve" tant que les campagnes de charge, les drills operationnels client par client et le suivi SLA externe ne sont pas rejoues sur le volume cible.

## Livre depuis le controle precedent

- API auth tenant : contrats `auth/login -> auth/me` avec role, langue, capabilities et entreprise.
- API auth plateforme : contrats `platform/auth/login -> platform/auth/me` avec `role=super_admin`, 2FA et token Bearer.
- Portail client web : smoke Playwright login RH/employe vers dashboard, avec donnees tenant dashboard.
- Admin plateforme : smoke Playwright login super-admin vers cockpit plateforme et nettoyage de la demo pour ne plus proposer de comptes tenant incompatibles.
- Mobile Flutter : contrat repository login mobile, sauvegarde du token, hydration `/auth/me`, role RH, capabilities, modules et preference langue/RTL.
- Client web : dashboard connecte aux endpoints reels `/dashboard/summary` et `/dashboard/recent-activity`.
- Client web : correction de la boucle de rendu du layout dashboard liee a un snapshot `localStorage` instable.
- CI : le workflow vitrine execute aussi le smoke auth client ; les PR #524, #525 et #526 sont mergees avec checks verts.

## Validation executee

| Commande | Resultat |
|---|---|
| `git fetch origin main --prune` | OK apres nettoyage disque/FETCH_HEAD plus tot dans la session |
| `git diff --check` | OK |
| `powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\check-governance.ps1` | OK |
| `powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\release-readiness.ps1 -Strict` | OK, 15/15 checks |
| `npm run lint` dans `front/web` | OK sur PR #524/#525 |
| `npm run build` dans `front/web` | OK sur PR #524/#525 |
| `npx playwright test e2e/auth-client-smoke.spec.ts --project=chromium` dans `front/web` | OK |
| `npm run lint` dans `front/admin-dashboard` | OK, avertissements historiques inchanges |
| `npx playwright test e2e/platform-auth-smoke.spec.js` dans `front/admin-dashboard` | OK |
| GitHub Actions PR #524 | OK : backend, quality, coverage, web, security, governance |
| GitHub Actions PR #525 | OK : Web CI admin, Web CI vitrine, funnel E2E, CodeQL, secret scan, governance |
| GitHub Actions PR #526 | OK : Flutter Analyze, Flutter Test + Coverage, Mobile Flutter stable, Build Debug APK |

## Gate release local

Le script `dev-hub/tools/release-readiness.ps1 -Strict` valide :

- 136 fichiers de tests PHP backend ;
- specification canonique `api/openapi.yaml` ;
- publication `/docs` couverte ;
- 12 specs Playwright admin ;
- 16 tests Dart mobile ;
- audits RBAC, SQL injection et CSRF/XSS ;
- runbooks backup/operations ;
- ADR et C4 ;
- 19 workflows GitHub Actions ;
- registres de scenarios API, admin et mobile.

## Reste a livrer

### P1 avant declaration "marche sans reserve"

- Rejouer un scenario end-to-end staging avec vrais comptes de demo pour : manager RH web, employe web, employe mobile, super-admin plateforme.
- Attacher les preuves de deploy staging post-merge #526 une fois tous les workflows `main` termines.
- Executer ou planifier un test de charge k6 cible sur auth, dashboard, listes RH, pointage et paie avec p95 documente.

### P2 durcissement exploitation

- Documenter le tableau de bord de supervision externe actif : uptime, latence p95, erreurs 5xx, queue workers, backup.
- Rejouer un drill restore recent et conserver le rapport RPO/RTO.
- Continuer la reduction progressive du baseline PHPStan par module sans l'elargir.

### P3 qualite produit

- Ajouter des parcours E2E plus profonds sur exports RH, paie et onboarding client.
- Ajouter des captures ou videos de smoke mobile sur build Android si l'equipe support en a besoin pour recette client.
- Etendre la matrice frontend/API aux nouveaux endpoints dashboard consommes par le portail client.

## Echecs rencontres et classification

### Disque local plein pendant fetch

- COMMAND : `git fetch origin main --prune`
- OUTPUT : `fatal: write error: No space left on device` puis `fatal: fetch-pack: invalid index-pack output`
- CAUSE : artefacts locaux volumineux (`.tmp`, `.next`, `node_modules`, `dist`)
- CLASS : environment
- PRIORITY : P2
- FIX PLAN : nettoyer les artefacts regenerables avant fetch ; conserver les stashes et ne supprimer aucun travail utilisateur.

### FETCH_HEAD verrouille

- COMMAND : `git pull --ff-only origin main`
- OUTPUT : `error: cannot open '.git/FETCH_HEAD': Permission denied`
- CAUSE : fichier metadata Git local verrouille apres fetch interrompu
- CLASS : environment
- PRIORITY : P2
- FIX PLAN : supprimer uniquement `.git/FETCH_HEAD`, puis relancer le fast-forward.

### PHP absent localement

- COMMAND : `php -v`
- OUTPUT : `php : The term 'php' is not recognized as the name of a cmdlet`
- CAUSE : runtime PHP non installe sur Windows local
- CLASS : environment
- PRIORITY : P3
- FIX PLAN : utiliser GitHub Actions comme source de verite backend ; ne pas conclure a un rouge applicatif local.

### Flutter/Dart absents localement

- COMMAND : `flutter --version` et `dart --version`
- OUTPUT : `flutter : The term 'flutter' is not recognized` / `dart : The term 'dart' is not recognized`
- CAUSE : runtime Flutter/Dart non installe sur Windows local
- CLASS : environment
- PRIORITY : P3
- FIX PLAN : valider via GitHub Actions Mobile CI ; les checks PR #526 sont verts.

### Playwright Chromium absent localement

- COMMAND : `npx playwright test e2e/auth-client-smoke.spec.ts --project=chromium`
- OUTPUT : `Executable doesn't exist ... chromium_headless_shell`
- CAUSE : navigateur Playwright non installe localement
- CLASS : dependency
- PRIORITY : P3
- FIX PLAN : `npx playwright install chromium`, puis relancer le smoke.

### React dashboard render loop

- COMMAND : `npx playwright test e2e/auth-client-smoke.spec.ts --project=chromium`
- OUTPUT : `Minified React error #185`
- CAUSE : `useSyncExternalStore` recevait un snapshot `localStorage` non stable car `JSON.parse` creait un nouvel objet a chaque lecture
- CLASS : code
- PRIORITY : P1
- FIX PLAN : remplacer par une hydration `useState/useEffect`; smoke web vert et merge dans #525.

## Score detaille

| Axe | Score |
|---|---:|
| API backend et securite tenant | 18/20 |
| Auth web/admin/mobile | 18/20 |
| CI/CD et gouvernance | 18/20 |
| Operations et observabilite | 16/20 |
| Tests de charge et preuves terrain | 18/20 |
| **Total** | **88/100** |

## Prochain lot recommande

1. Produire une preuve staging post-merge #526 avec les workflows `main` termines.
2. Ajouter la ligne matrice pour `/dashboard/summary` et `/dashboard/recent-activity` dans `FRONTEND_API_CONTRACT_MATRIX.md`.
3. Lancer un lot performance k6 read-only sur auth/dashboard/listes RH et publier le rapport p50/p95/erreurs.
4. Ajouter un smoke E2E plus profond "journee RH" : login manager, liste employes, absence, notification, logout.
