# RELEASE READINESS — Leopardo RH — 2026-08-14

> Issue #1902 — Préparation de la release post-vague multi-pays (v4.25.0 cible).
> Méthode : `dev-hub/tools/release-readiness.ps1 -Strict` (équivalent bash
> exécuté dans cet environnement, sans PowerShell). Le script a été **mis à
> jour** au préalable : 5 chemins référençaient l'ancienne architecture
> (front/mobile → front/mobile_apps, mobile-ci.yml → mobile-apps-ci.yml,
> DEPLOYMENT_GUIDE.md déplacé sous docs/deployment/, Plan 72 archivé) —
> les 5 échecs étaient des faux positifs de chemins obsolètes, pas des
> régressions réelles.

## Résultat de la batterie de checks

**28/28 PASS** (script `release-readiness.ps1` corrigé + équivalent bash).

| Domaine | Check | Statut |
|---|---|---|
| Repository | Remote main synced | ✅ |
| Backend API | Laravel app, tests (≥100), OpenAPI, Swagger UI | ✅ |
| Admin Dashboard | package, E2E Playwright (≥10) | ✅ |
| Mobile | Flutter launch apps (mobile_apps), tests (≥20) | ✅ |
| Mobile Apps | core/employee/manager/platform_admin + guards + CI/Firebase | ✅ |
| Web Vitrine / Kiosk | front/web + zkteco-kiosk | ✅ |
| Security / Ops / Archi | audits, runbooks, ADR/C4 | ✅ |
| CI/CD | workflows présents (36), core (tests/web/mobile-apps/openapi) | ✅ |
| Governance | scénarios, rapports Plan 67, contrat frontend/API, launch workflows, code quality | ✅ |
| Ecosystem | open-core/marketplace | ✅ |

## État de la CI sur main (2026-08-14, ~14:20 UTC)

| Check | État | Note |
|---|---|---|
| Deploy / Deploy Staging / E2E Playwright / OWASP / Secret Scan | 🟢 | |
| Admin Pages Deploy Guard | 🔴 | **Externe** : CF Pages `leo-admin.pages.dev` sert toujours l'ancien CSP (`upgrade-insecure-requests`). Repo correct (#1783). **#1834 rouverte par le resp** — action dashboard CF requise ou migration du déploiement en workflow GitHub (cf. runbook #1914). |
| Route → OpenAPI coverage guard | 🔴 sur main | Gap hérité #1867 (`v1/supported-countries`) + #1817 (`/me/pay-slips/{param}/document`) — **#1971** ; corrigé par la PR #1969 (à merger). |
| Workers Builds: gestionemploye | 🔴 (externe, non requis) | Voir #1914 + runbook CF Workers. |

## Vague multi-pays 2026-08-14 — livraisons sur main

Résolveur unique (#1868), jours fériés (#1811), calendrier islamique (#1812),
workflow validation taux (#1813), admin barèmes/cotisations (#1814/#1815),
jours travaillés réels (#1816), archivage bulletins (#1817), régularisations
(#1818), conformité DZ (#1819), infra Afrique (#1820), pilotes
CM/CI/SN/GA/CG/BF/ML (#1821/#1824/#1825/#1827/#1829), golden tests
CM/CI/SN (#1822/#1826/#1828), déclarations CNSS/IPRES/DAS (#1823/#1830),
contrat de calcul (#1869), matrice d'isolation (#1870).

## Reste à faire avant tag v4.25.0

- [ ] **Merge de la file en cours** : PR #1966 (CI Payroll), #1967 (double déduction congés), #1969 (contrat de calcul) — 0 échec en cours, file GitHub Actions saturée (#1903).
- [ ] **Validation experte des taux « pilot »** : CM/CI/SN/GA/CG/BF/ML — un pays non validé ne bloque pas la release s'il est étiqueté `pilot` et masqué des tenants de production (issues #1939/#1912/#1904). **Action requise : expert-comptable local.**
- [ ] **Décision produit** : pays CEDEAO/CEMAC exposés aux tenants de production ou gated par feature flag.
- [ ] **CF Pages admin** : redéploiement ou migration workflow (#1834, externe).
- [ ] CHANGELOG [Unreleased] consolidé + bump PROGRAM_VERSION 4.24.0 → 4.25.0 + `api/config/app.php` + `GET /api/v1/health`.
- [ ] Tag `v4.25.0` via le workflow Release (jamais de tag manuel).

## Conclusion

La **structure et la gouvernance de release sont prêtes (28/28)**. Deux
signaux CI rouges sur main sont **externes au code** (CF Pages, CF Workers) et
un gap OpenAPI sera résolu par le merge de la PR #1969. La décision de tag
**v4.25.0** reste conditionnée à la validation experte des taux pilot et au
choix produit d'exposition multi-pays — voir les issues #1904/#1912/#1939/#1902.
