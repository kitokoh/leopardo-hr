# Release readiness report - 2026-06-01

## Decision

**Go conditionnel - score 91/100.**

Le depot est maintenant auditable par surface avant marketing : API, employee mobile, manager/RH mobile, platform admin mobile, vitrine web et kiosk ont chacun une preuve, un garde ou un workflow CI associe. La condition restante avant declaration "Go sans reserve" est operationnelle : rejouer une recette terrain avec vrais appareils/testeurs et verifier les workflows `main` post-merge de chaque lot.

## Validation executee

| Commande / preuve | Resultat |
|---|---|
| `powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/release-readiness.ps1 -Strict` | OK, 24/24 checks |
| `dev-hub/tools/validate-frontend-api-contract-governance.ps1` | OK, matrice/API/mobile/OpenAPI CI relies |
| `dev-hub/tools/validate-open-core-boundaries.ps1` | OK, bornes open core/marketplace documentees |
| `dev-hub/tools/validate-mobile-runtime-smoke.ps1` | Couvert par Mobile Apps CI |
| `dev-hub/tools/validate-mobile-location-readiness.ps1` | Couvert par Mobile Apps CI |
| `dev-hub/tools/validate-mobile-tenant-branding.ps1` | Couvert par Mobile Apps CI |
| `dev-hub/tools/validate-mobile-notification-production-proof.ps1` | Couvert par Mobile Apps CI |
| `dev-hub/tools/validate-mobile-workflow-contracts.ps1` | Couvert par Mobile Apps CI |
| PR #668 | Backend, coverage, OpenAPI, mobile apps analyze/build, security et Vercel verts avant merge |

## Score par profil

| Profil / surface | Score | Preuves actuelles | Risque restant |
|---|---:|---|---|
| Employee mobile | 92/100 | Runtime anti-page noire, auth, pointage GPS/timezone, notifications, branding tenant, build debug CI | Recette sur vrai device Android/iOS a rejouer a chaque distribution Firebase |
| Manager/RH mobile | 91/100 | Equipe/taches/pointage/corrections, notifications manager, horaires, branding, build debug CI | Verifier charge des listes equipe sur tenants volumineux |
| Platform admin mobile | 88/100 | Login super-admin, creation client, fiche client, 2FA, build debug CI | Push super-admin non branche volontairement tant qu'un contrat public dedie n'existe pas |
| API publique / partenaires | 92/100 | OpenAPI canonique, `/docs`, `/api-explorer`, contrats push/device-token, tests backend/coverage | Portail developpeur sandbox/tokens partenaires reste un lot futur |
| Vitrine web / portail client | 86/100 | `front/web` present, CI dediee, liens marketing canoniques documentes | Continuer SEO contenu, preuves Lighthouse et parcours essai gratuit |
| Kiosk / ZKTeco | 84/100 | Front kiosk present, API base normalisee, scenarios kiosk documentes | Recette device physique et mode offline a rejouer avec materiel client |
| Operations / CI/CD | 93/100 | 23 workflows, backup/restore, OpenAPI CI, secret scan, CodeQL, queues/Redis runbooks | Monitoring externe SLA et alerting incident P1 a verifier en prod |

## Risques classes

| Priorite | Risque | Mitigation |
|---|---|---|
| P1 | Recette mobile Firebase sur vrais appareils encore necessaire apres chaque merge mobile | Garder `mobile-distribute.yml`, nommage APK par app et readback Firebase ; documenter les retours testeurs par version |
| P1 | Super-admin push non implemente cote backend public | Ne pas reutiliser les routes tenant `/device-tokens`; creer plus tard `platform_device_tokens` et endpoints `/platform/device-tokens` |
| P2 | Tests de charge k6 encore read-only et limites | Etendre progressivement auth/dashboard/employees/attendance/payroll avec seuils p95 |
| P2 | Kiosk necessite validation materiel | Preparer session avec device ZKTeco et QR punch en environnement staging |
| P3 | Portail developpeur premium incomplet | Plan futur : API Explorer authentifie, sandbox tokens, webhooks exemples et SDK |

## Actions post-lancement recommandees

1. Rejouer une recette terrain employee/manager/platform admin avec les APK Firebase generes depuis `main`.
2. Publier un rapport k6 p50/p95 sur auth, dashboard, employees, attendance et payroll.
3. Ajouter le contrat push super-admin dedie si la plateforme exige des alertes natives cote administration.
4. Ajouter un rapport Lighthouse/SEO pour la vitrine avant achat du domaine principal.
5. Rejouer le kiosk sur materiel reel avant promesse commerciale kiosk.

## Conclusion

Le niveau produit est suffisant pour une mise en marche controlee et une campagne marketing progressive. Le statut reste **Go conditionnel** parce que les preuves terrain device/kiosk et le stress test volume cible doivent encore etre executes hors CI.
