# QA Session — Expert SWE/QA 2026-08-16 : Audit 360°, Merge Drain & Implémentations

> Session : 2026-08-16 | Agent : expert SWE/QA | Repo : kitokoh/leopardo-hr
> Mandat : Phase 0 (vérification audits) → Phase 1 (audit 360°) → Phase 2 (dette/merge drain)
> → Phase 3 (implémentation des findings).

---

## Phase 0 — Vérification des audits antérieurs (✅)

Rapport complet : `docs/qa/QA_SESSION_2026-08-16-expert-swe-qa-VERIFICATION.md` (PR #4486).

Verdict : les audits expert18/19 (36/38 issues closes) et agent-360 (#4395→#4417) étaient
**implémentés mais non mergés** — 73 PRs ouvertes en état behind/blocked/dirty. Le goulot
n'était pas l'implémentation mais le **merge drain**.

## Phase 1 — Audit 360° (✅)

4 audits parallèles (API Laravel, vitrine Next.js, admin Vue 3, mobile Flutter + CI) menés
par sous-agents sur le code, chaque finding vérifié dans le code.

**37 nouvelles issues créées** (#4494 → #4530), format spec-kit
(`## Constat` / `## Cause racine` / `## Fix attendu` / `## Critères d'acceptation`, labels
`QA` + `qa-audit-2026-08-16` + surface/type) :

| Surface | P1 | P2 | P3 |
|---|---|---|---|
| API | — | 4 (trustProxies, reset oracle, fillable, orgchart) | 5 |
| Web | — | 2 (checkout i18n) | 6 |
| Admin | — | 1 (creds super-admin en clair) | 7 |
| Mobile/CI | 1 (manifest INTERNET) | 8 | 3 |

Sondes live (2026-08-16) : `gestionemployerbackend.onrender.com/api/v1/health` 200 (legacy
toujours en prod) ; `leopardo.vercel.app` sert « Marketing Automatizado con IA » (site d'une
autre entreprise) ; `leopardo-rh.com` NXDOMAIN → confirment #2812/#2813/#3452/#3765-3771.

## Phase 2 — Merge drain & dette (🔄 en cours)

### Merge-forward
- **70 branches** de PRs ouvertes mises à jour avec `main` (merge-forward + push), dont
  conflits résolus : `fix/4380-checkout-annual-badge` (annualDiscount), `fix/4401-sitemap`
  (withAlternates + gating blog), `fix/4322-4327-web-hygiene` (garde ancre docs#intro),
  `api/lang/*/errors.php` (unions de clés i18n ×5 branches).
- **8 PRs mergées** en vague 1 : #4290, #4350, #4351, #4362, #4363, #4437, #4442, #4456.
- **Poller de merge automatique** actif : merge (squash) + suppression de branche dès que les
  5 checks requis sont verts (CI saturée, famine #3545 — la file est le goulot).

### Doublons résolus (protocole anti-doublon #2400)
- #4463 → clos (canonique #4462, approche `??=`), #4469 → clos (canonique #4470),
  #4481 → clos (canonique #4483), #4355 + #4466 → clos (superseded par #4362 mergé),
  issue #4294 → close (fix vérifié sur main).

### Issues sans PR implémentées (Phase 2d/3)
| PR | Issues | Sujet |
|---|---|---|
| #4531 | #4519 | manifest marketing : permission INTERNET (P1) |
| #4532 | #4503/#4504/#4507 | checkout i18n ×4 + redirect Stripe ?lang |
| #4533 | #4505 | metadata /checkout/success dédiée ×4 |
| #4534 | #4523/#4522/#4527 | CI mobile : dart format strict, artefacts, paths |
| #4535 | #4511 | LoginView admin : creds super-admin retirées du bundle |
| #4536 | #4513 | clé i18n shell.tenantOnly (toast tenant) |
| #4538 | #4512 | WebhooksView : events chargés du backend |
| #4539 | #4515 | checkAuth : session conservée sur erreurs transitoires |
| #4540 | #4524/#4530 | plus de fallback API legacy en dur + debug→local |
| #4541 | #4506 | LegalPageShell : ?lang persisté dans l'URL |
| #4542 | #4510 | Navbar mobile : aria-expanded/haspopup |
| #4543 | #4499/#4498 | recentActivity clamp + throttle /activate |

## Phase 3 — Implémentation des findings (✅ partiel, 12 PRs)

Voir tableau ci-dessus. Validation locale : `tsc --noEmit`, `eslint --max-warnings 0`,
`vite build` (admin), YAML parse (workflows). Backend PHP validé par CI (sandbox sans PHP).

## Reste à faire (handoff)

1. **Surveiller le poller** jusqu'à épuisement des ~65 PRs restantes (CI saturée).
2. Ops prod (accès Render/Vercel requis) : #2812/#2813/#2906/#3452/#3765-3771/#3879/#4461.
3. Chantiers i18n longs : #4194/#4303/#4305/#4330/#4410/#2755.
4. Mobile : #3910/#3912/#4304/#4378/#4409.
5. API : #3245/#3885/#4101/#2601 ; métier : #1912.
6. Nouveaux findings non encore implémentés : #4494 (trustProxies — décision ops),
   #4495 (reset oracle), #4496 (fillable), #4497 (orgchart authz), #4500 (employeeBalance),
   #4501 (catalog throttle), #4502 (cache pays), #4508 (seo mort), #4509 (canonical guides),
   #4514 (requiresTenant), #4516 (préfixe /v1/), #4517 (dates), #4518 (AnalyticsView),
   #4525 (UIBackgroundModes iOS), #4526 (elif mort), #4528 (FutureBuilders), #4529 (dead core).
