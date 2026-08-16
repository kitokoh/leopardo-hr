# QA Session — Vérification des audits antérieurs (2026-08-16)

> Session : 2026-08-16 | Agent : expert SWE/QA (agent session ghp_…) | Repo : kitokoh/leopardo-hr
> Objet : **Phase 0** — vérifier que les recommandations des audits précédents ont
> réellement été implémentées, et dresser l'état du backlog ouvert, AVANT de lancer
> un nouvel audit 360°.

---

## 1. Méthode

- Lecture des rapports de sessions QA précédentes (`docs/qa/QA_SESSION_2026-08-15*.md`,
  `QA_SESSION_2026-08-16-*.md`, rapports expert18/expert19/expert20/expert21/agent-360).
- Croisement automatique avec l'API GitHub : état de chaque issue référencée
  (`state`), PR ouverte associée (`Closes #N` dans le body), état de merge
  (`mergeable_state`), checks requis (`check-runs`).
- Snapshot stable du backlog au moment de la vérification (le repo est **en
  activité concurrente** — plusieurs agents travaillent en parallèle ; le
  snapshot est daté).

## 2. Verdict global

| Indicateur | Valeur (snapshot) |
|---|---|
| Issues ouvertes (hors PR) | **73** |
| PR ouvertes | **73** |
| Issues avec PR d'implémentation ouverte | **45** |
| Issues SANS PR d'implémentation | **28** |
| PR `behind` (branche derrière main) | ~55 |
| PR `blocked` (checks requis non verts / queue CI saturée) | ~10 |
| PR `dirty` (conflit avec main) | ~6 |

**Constat principal** : les recommandations des audits récents ont été
**implémentées en grande majorité**, mais **presque aucune PR n'a été mergée** :
le goulot d'étranglement n'est pas l'implémentation, c'est **le merge drain**.
73 branches `fix/*` attendent une mise à jour avec main puis un merge.

## 3. Vérification audit par audit

### 3.1 Audit expert18 / expert19 (2026-08-16) — issues #4169 → #4206

38 issues créées. **36 closes** ✅ | **2 encore ouvertes** ⚠️

| Issue | Sujet | État |
|---|---|---|
| #4169 → #4206 (36 issues) | CSV injection, routes tenant 401, Renderer DomainException, Kiosk, lang SSR, /branding, /docs, /careers, /offline, /integrations, App Store links, impersonation token, composants morts, Zkteco cross-tenant, i18n API/admin/mobile, openapi, CTA plan=free, checkout FR… | ✅ closes (PRs mergées ou fermées avec preuve) |
| **#4194** | Mobile — ~1650 chaînes FR hardcodées (leopardo_employee/manager/hr) | ⚠️ **OUVERTE**, sans PR (chantier long, dépend de #4194 → #4303) |
| **#4196** | Vitrine — pages docs + modules 100 % FR | ⚠️ **OUVERTE**, PR **#4290** ouverte (`fix/4196-module-pages-i18n`, lot 1 « employes ») — implémenté mais non mergé |

### 3.2 Audit agent-360 (2026-08-16) — issues #4395 → #4417

23 issues créées (21 issues + 2 doublons). **4 closes** ✅ | **19 ouvertes** ⚠️
→ **Mais 18 des 19 ont une PR d'implémentation ouverte** :

| Issue | Sujet | PR ouverte |
|---|---|---|
| #4395 | Trial signup i18n (P1) | #4441 |
| #4396 | Batch i18n v4 résiduel | #4444 |
| #4397 | Scanner i18n-debt surface leopardo_hr | #4418 |
| #4398 | FK cross-tenant validation | #4442 |
| #4400 | hreflang fr auto-référence | #4445 |
| #4401 | Sitemap ?lang fantômes | #4450 |
| #4403 | JSON-LD Enterprise sans price | #4440 |
| #4404 | Plan Enterprise AR typo showsCurrency | #4429 |
| #4405 | Cluster résiduel vitrine | #4452 |
| #4406 | SyncService Edge sans timeout | #4462 **et** #4463 (doublon) |
| #4407 | Upload Cabinet sans try/catch | #4469 **et** #4470 (doublon) |
| #4408 | ApiClient core l10n | #4471 |
| #4411 | Edge SQLite jamais provisionné (P1) | #4458 |
| #4412 | APP_URL absent workers Render | #4424 |
| #4413 | bootstrap.sh cassé | #4425 |
| #4414 | 72/75 jobs sans timeout | #4455 |
| #4417 | Ref mortes PLAN_ACTION2 | #4427 |
| #4409 | Cluster mort/i18n mobile | ❌ **sans PR** (P3) |
| #4410 | ~39 fichiers Vue FR hardcodés | ❌ **sans PR** (P2) |

### 3.3 Audit expert20 (2026-08-16) — 34 issues (#4380 → #4448)

Issues implémentées par PR ouverte mais non mergée : #4380→#4387, #4381→#4384,
#4383→#4390, #4393→#4420, #4394→#4456, #4419→#4421, #4433→#4434, #4446→#4457,
#4447→#4449, #4448→#4460, #4467→#4472, #4468→#4475, #4476→#4481/#4483 (doublon),
#4327→#4477, #4323→#4480, #4329→#4431, #4334→#4364, #4341→#4373, #4343/4345→#4363,
#4344→#4372, #4292→#4365, #4293→#4353, #4294→#4355/#4362/#4466, #4296→#4354/#4443,
#4297→#4357, #4298→#4352, #4299→#4369, #4300→#4368, #4301→#4358, #4302→#4351,
#4306→#4350, #4307→#4308, #4310→#4362, #4316→#4356, #4318→#4459, #4322→#4367,
#4328→#4347, #4393→#4420, #4405→#4452, #4411→#4458, #4412→#4424…

→ **La très grande majorité du backlog « audit » est donc implémentée mais en
attente de merge** (état `behind` : main a avancé).

## 4. Les 28 issues SANS implémentation (vrais restes à faire)

### Ops / production (nécessitent accès Render/Vercel — hors code)
#2646 (demo login KO prod), #2812 (déployer API ≥ main), #2813 (déployer vitrine),
#2906 (activer blog), #3259 (trial signup 500 prod), #3452 (DNS NXDOMAIN),
#3765/#3766/#3767 (epic stabilisation prod), #3771 (smoke test prod),
#3879 (trial 500 prod), #4216 (check externe Workers Builds).

### Chantiers i18n / refactoring longs (P2/P3, mécaniques mais volumineux)
#2755 (8 983 chaînes), #4194 (~1 650 chaînes mobile), #4303 (résiduel 113 chaînes),
#4305 (15 vues admin FR), #4330 (FR partiels admin), #4410 (39 fichiers Vue),
#2601 (dédup leopardo_hr/manager).

### Mobile / Flutter
#3910 (app marketing 2 écrans), #3912 (platform_admin 7 écrans), #4304 (deep links),
#4378 (migration AGP 9), #4409 (cluster mort/i18n).

### API / divers
#1912 (Sénégal — validation expert-comptable, métier), #3245 (logique self-service
dupliquée MeController vs Estimation), #3885 (OpenAPI drift 192 routes),
#4101 (10 parcours E2E admin ignorés).

## 5. Conclusion & décision de séquence

1. **Phase 2 d'abord** : débloquer le merge drain (73 PRs). Mettre à jour chaque
   branche avec main, attendre CI, merger les PRs vertes. Traiter les doublons :
   #4462/#4463, #4469/#4470, #4481/#4483 (garder la PR canonique).
2. **Ensuite implémenter** les issues sans PR, par ordre de valeur : #4304 (deep
   links mobile), #4378 (AGP9), #4410/#4330/#4305 (admin i18n), #3245 (API dedup)…
3. **Phase 1** : audit 360° frais sur les surfaces (code + runtime staging) pour
   créer de nouvelles issues spec-kit si des failles subsistent.
4. **Phase 3** : implémenter les findings du nouvel audit.

> ⚠️ Avertissement : snapshot daté. Le repo étant en activité concurrente, les
> compteurs peuvent évoluer entre deux lectures (constaté : fermetures en cours
> #4323, #4324, #4327 pendant la vérification).
