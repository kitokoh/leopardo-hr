# QA Leopardo HR — Session Expert 17 du 2026-08-15

Mission : audit 360° (vitrine, web, admin, mobiles, workflows, API, logiques,
onboarding, cohérence), constats formalisés selon la méthode Spec Kit,
implémentation des issues non verrouillées, `main` VERT.

## Contexte

- Swarm QA très actif : 89+ issues ouvertes, ~30 PRs, CI saturée (toutes les
  PRs étaient `blocked` au début de session — file GitHub Actions pleine).
- Sandbox : **Node 24 + Python uniquement** (pas de PHP/Dart/Flutter) →
  validation locale complète web/admin (tsc, eslint, jest 471 tests, build
  Next.js, build Vue, actionlint, i18n validator, openapi coverage) ;
  changements PHP/Flutter validés par la CI.
- Les 3 bloqueurs CI partagés (#3791 PHPStan racine, #3802 vitest, #3815
  checksum i18n + openapi) ont été mergés par la session merge pendant mon
  analyse → vérification locale : validator i18n OK, openapi coverage 0 drift.

## Bilan de la session

### PRs créées (7) — 4 MERGED, 3 en attente CI

| Issue | PR | Surface | Statut |
|---|---|---|---|
| #3952 [P1] doublon `maxRetriesOverride` (3 apps Flutter ne compilent pas) | #3977 | mobile | ✅ MERGED, issue fermée |
| #3822 `flutter test` pour toutes les apps livrées en CI | #3980 | CI mobile | ✅ MERGED, issue fermée |
| #3931 `useRouter()` dans handler → TypeError recherche header | #3994 | admin | ✅ MERGED, issue fermée |
| #3932 `pushUnavailable` jamais défini → fausse alerte rouge permanente | #4001 | admin | ✅ MERGED, issue fermée |
| #3919 [P1] triple schéma de prix vitrine (affiché 79 € ≠ facturé 99 €) | #4049 + #4083 (résiduel FAQ) | web | ✅ MERGED ×2, issue fermée |
| #3933 `card-lg` inexistante → toasts sans fond | #4057 | admin | ✅ MERGED |
| #3938 enveloppe API GrowthDashboard non normalisée | #4060 | admin | ✅ MERGED |
| #4091 déconnexion client bloquée par l'appel API (~70 s) | #4094 | web | ⏳ CI |
| #4095 e2e actions rapides (doublons, liens gated, regex ASCII) | #4097 | web | ⏳ CI |

### Issues fermées avec preuve code (protocole #2512) — 6

| Issue | Preuve |
|---|---|
| #3249 accents FR restaurés (fr.json : 169 chaînes accentuées, PR #3764) | code main |
| #3591 installation Edge réparée (install.sh + Caddyfile ship, #3792) | code main |
| #3928 og:url /demo relatif (fixé par #3798, layout sur generateSEOMetadata) | code main |
| #3917 /download lien mort + stores absents (fixé #3628/#3629/#3634) | code main |
| #3934 CommandPalette « Predictions IA » (retiré par #3837) | code main |
| #3834 CTA/e2e signup i18n — constat de base **déjà corrigé par #3728** (e2e 24/24 verts sur main) ; hardening sélecteurs en cours par `fix/3834-cta-e2e-i18n` → issue laissée OUVERTE | tests e2e |

### Vérifications d'audit (aucune issue nouvelle — déjà couvert)

- Kiosk bridge (M-05/M-06) : **durci** sur main (chmod 0600 kiosk.db, body cap,
  rate-limit `/local/punch`, token local + anti-CSRF) — rien à signaler.
- Admin AD-01/03/04 : fixés (PATCH notifications, translate() sur les titres,
  FleetView erreurs surfacées).
- `google-services.json` ×4 : stubs factices (clés `REPLACE_WITH_REAL`), aucun
  secret réel.
- actionlint : 0 erreur sur tous les workflows ; guards dev-hub tous verts
  (env parity, migrations, orphan interfaces, strict types, canonical domains).
- openapi route coverage : 121 routes non couvertes, toutes allowlistées
  (drift 0). Vitrine : tsc/eslint/jest 471/471, build OK, ~20 pages 200.

## Audit e2e runtime (constat neuf — campagne 99 tests chromium sur main propre)

- **8-9 échecs = backend prod périmé, PAS le code vitrine** : probe live
  `POST /api/v1/trial/signup` → **500 « Server Error » en 61,2 s** (preuve
  postée sur #3259) ; `GET /api/v1/supported-countries` → 404 ; `health` → 200.
  Les suites signup/demo/marketing-funnel échouent donc en local ET en CI
  (marketing-funnel tourne dans web-marketing-ci). Correctif sur main, non
  déployé — famine déploiement #3545.
- **1 vrai bug code trouvé et corrigé** : `auth/logout` ne redirigeait
  qu'après `apiFetch` (jusqu'à ~70 s) → #4091, e2e `manager-workday-smoke`
  repassé 1/1 vert.
- **1 vrai bug de test corrigé** : `dashboard-quick-actions` (doublons
  `a[href="/employees"]`, lien sidebar `/reports` désactivé par feature gate,
  regex `/message envoye a l.equipe/i` ne matchant plus l'accentué) → #4095,
  e2e 4/4 verts.
- **1 flake dev-only** : test démo (/demo) timeout 120 s en dev quand la route
  n'est pas compilée (probe manuelle : navigation OK en <2 s une fois chaude ;
  en CI la cible est un build, pas un dev server).

## Méthode

- **Lock & Isolate** : self-assign + branche de claim `fix/<issue>-*` poussée
  avant toute modification (protocole #2400) ; vérification branches/PRs avant
  chaque claim.
- **Anti-doublon** : avant de créer la moindre issue, croisement avec les
  registres existants (`.specify/features/*/findings-registry.md`) — la vague
  d'audit 2026-08-15 couvrait déjà ~200 constats ; mes angles frais (runtime
  e2e, guards, secrets, état réel des findings AD/M) n'ont produit **aucun
  constat nouveau** → zéro issue créée en doublon.
- **Validation locale** avant push : tsc, eslint, jest, build (web + admin),
  actionlint, i18n validator, openapi coverage.
- **CHANGELOG** : entrée `[Unreleased]` + spec `docs/specifications/ISSUE_*.md`
  sur chaque PR.
- **Erreur corrigée en cours de route** : `rg -r` = `--replace` (remplace le
  match au lieu de chercher) — deux faux positifs évités de justesse
  (strings feature-gates, routes growth) ; leçon : toujours `rg --no-messages`
  explicite ou `-e`.

## Leçons pour les prochains agents

1. **La file CI se sature avec ~30 PRs ouvertes** : les checks mettent 30-60 min
   à démarrer. Vérifier `gh pr checks` APRÈS merge (leçon #6 session merge).
2. **Les PRs de la session sont mergées très vite** (4/4 de mes PRs en < 20 min
   chacune) : la fenêtre d'édition d'une branche est courte — pousser le code
   complet d'un bloc, pas en plusieurs commits espacés.
3. **`rg -r` est `--replace`** : `rg -rn "pattern"` remplace les matches par
   « n » dans l'affichage — vérifier les résultats avant de conclure à un bug.
4. **Les issues d'audit récentes (#3917-#3972) sont souvent déjà fixées sur
   main** par les PRs qu'elles référencent : toujours vérifier le code avant de
   créer/implémenter (preuve code + fermeture #2512).
