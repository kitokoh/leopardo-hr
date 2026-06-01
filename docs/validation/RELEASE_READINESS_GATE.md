# Release Readiness Gate

## Objectif

Ce gate definit le controle generalise de Leopardo RH avant de declarer un lot livrable. Il complete les tests automatises GitHub Actions avec une lecture produit, securite, architecture et exploitation.

## Regles de decision

| Niveau | Condition | Decision |
|---|---|---|
| Go | Checks requis GitHub Actions verts, aucun P0/P1 ouvert, parcours critiques couverts | Merge/deploiement autorise |
| Go conditionnel | GitHub Actions verts, reste P2/P3 documente avec mitigation | Merge possible si le risque est accepte |
| No-Go | P0/P1 ouvert, test critique rouge, fail securite, migration non validee | Pas de merge |
| No-Go produit | API/admin/mobile ne permettent pas le parcours client attendu | Pas de release commerciale |

## Surfaces controlees

### API backend

- Auth employee et super-admin.
- RBAC tenant et plateforme.
- Isolation multi-tenant directe et par chaine FK.
- Paie, pointage, conges, onboarding, privacy.
- OpenAPI valide et publie.
- Audit trail et webhooks.
- Rate limiting sur endpoints sensibles.

### Admin dashboard

- Login et session.
- Navigation protegee.
- Cockpit plateforme.
- Health client, plans, abonnement, support.
- Exports.
- Recrutement.
- Accessibilite minimale Playwright.
- Aucun endpoint mocke quand un contrat API reel existe.

### Mobile Flutter

- Stack Riverpod.
- Login/welcome.
- Attendance principal.
- Historique pointage.
- Modeles offline/sync critiques.
- Contrats API stables.
- Architecture multi-app canonique `front/mobile_apps/` : core, employee, manager/RH et platform admin.
- Gardes runtime, GPS, branding tenant, notifications, workflow contracts et distribution Firebase.

### Web vitrine et kiosk

- Vitrine marketing/client presente et separable de l'admin interne.
- Liens commerciaux et ressources publiques non casses.
- Kiosk ZKTeco present, base API normalisee et routes kiosk documentees.

### Securite

- RBAC route matrix.
- SQL injection audit.
- CSRF/XSS admin audit.
- Secret scan.
- CodeQL.
- OWASP ZAP baseline.
- Chiffrement des donnees sensibles selon plan.

### Operations

- CI/CD par SHA.
- Backup quotidien et restore drill.
- RPO/RTO documentes.
- Rollback.
- Observabilite.
- Runbook incident P1.

## Commandes de validation

```powershell
git fetch origin main --prune
git diff --check
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\check-governance.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\release-readiness.ps1 -Strict
```

GitHub Actions reste la source de verite pour :

- tests Laravel Unit/Feature/Security ;
- coverage backend ;
- Pint/PHPStan diff-gate ;
- admin-dashboard lint/build/Playwright ;
- mobile analyze/test ;
- OpenAPI CI ;
- secret scan ;
- CodeQL.

## Exigence de rapport

Chaque controle generalise doit produire :

- ce qui est livre ;
- ce qui est partiellement livre ;
- ce qui reste a livrer ;
- les commandes executees ;
- les echecs classes selon `environment | dependency | architecture | code | ci` ;
- un score de readiness ;
- les prochains lots recommandes.

Depuis le 2026-06-01, le score local attendu est `22/22` checks sur le gate strict.
