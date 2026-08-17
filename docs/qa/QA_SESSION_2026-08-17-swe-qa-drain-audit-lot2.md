# QA Session — 2026-08-17 (SWE/QA · merge drain · audit 360° · portail Lot 2)

> Session multi-agents : drain des branches ouvertes, audit 360° live + code, implémentation des findings et du backlog produit.

## 1. Merge drain

| PR | Contenu | Statut |
|----|---------|--------|
| #4743 | env.example parity rate limits | ✅ mergée |
| #4744 | URLs prod fail-closed (smoke réparé, fallbacks legacy supprimés) | ✅ mergée (rebasée) |
| #4745 | dédup E2E/ZAP par SHA de déploiement | ✅ mergée (rebasée) |
| #4746 | fleet/exports routes + double toast + auth i18n + CI URLs | ✅ mergée (rebase + conflits i18n/LoginView/workflows résolus) |
| #4747 | tests Feature verts (user_employee_links, anti-SSRF, isolation 404) | ✅ mergée — rebase corrigé : la branche portait un état stale de `layout.tsx`/`seo.ts` qui aurait **réverté le fix #4612** (template de titre localisé) |

**21 branches stale supprimées** (contenu vérifié déjà sur main via rebase-probe : `git rebase origin/main` → « patch contents already upstream » ou diff résiduel = CHANGELOG dupliqué uniquement) : `fix/4555`, `fix/4579` ×2, `fix/4609`, `fix/4610` ×2, `fix/4611`, `fix/4612`, `fix/4613` ×2, `fix/4621` ×2, `fix/4630-4631`, `fix/4667`, `fix/4677`, `fix/4683`, `fix/4705-4706-4709`, `fix/env-example-rate-limit-parity`, `neo/ops-hardening-tyutq`, `docs/qa-*` ×2.

**Doublons fermés** (protocole #2400) : #4763 → #4760 ; #4847 → implémentation complète du swarm déjà sur main (`changelogReleasesFr/En/Tr/Ar`).

## 2. Implémentations livrées

- **PR #4766 (mergée)** — SEO racine ×4 : keywords/og:image alt par locale, JsonLd Organisation ×4, `PRICING_CURRENCY` piloté par `currency.ts` ; code mort vitrine supprimé (`lib/monitoring.ts` 503 lignes, `HeroSection()` export mort + orphelins, `PLAN_CONFIG.features/employeeLimit`). tsc 0, eslint 0, 452 tests verts.
- **PR #4773 (mergée)** — 15 classes DDD mortes supprimées (3 actions fantômes, 7 mails jamais envoyés, SentryPerformanceMiddleware, FeatureService, ProcessWebhook + 2 DTOs) + 7 blocs baseline PHPStan nettoyés. `SendBulkNotificationsJob` conservé (testé, candidat câblage).
- **PR #4850 (mergée)** — guard PA2-I18N-014 : faux positifs corrigés (`translate(` reconnu, `https:`/`wss:` techniques). Probe diff validée (vraie chaîne FR toujours flaggée).
- **PR #4872 (ouverte)** — portail client Lot 2 (#4574/#4871) : 40 clés CopyTree ×4 locales (billing/contracts/absences/attendance/social/social-marketing), 6 pages débranchées des littéraux FR, statuts/filtres/erreurs/confirmations localisés. Garde i18n verte, tsc/eslint 0, 455 tests.

## 3. Audit 360° (Phase 1) — findings et sort

| Finding | Issue | Sort |
|---------|-------|------|
| `/changelog` contenu 100 % FR ×4 malgré #4610/#4675 « closes » (fix #4673 n'a câblé que l'appel ; `getChangelogReleases(_locale)` ignorait la locale — preuves live + code) | #4610 réouverte | ✅ implémenté par le swarm (×4 complet) |
| CSP admin en `report-only` (jamais enforced) | #4804 | ✅ traité (swarm) |
| `shared/i18n/versions/versions.json` jamais gardé (drift silencieux) | #4805 | ✅ traité (syncs idempotents #4838) |
| web-offline PWA : « Hors ligne »/« Erreur de connexion » FR | #4806 | ✅ traité (#4829, ui-copy ×4) |
| `/api/v1/demo-users` → 404 live (parcours démo super-admin KO) | #2646 (existant) | preuve live ajoutée |

**Live** : API v4.24.0 saine (DB/Redis/queue/storage), vitrine 200 partout, hreflang/canonical/og:locale OK, guides SSR riches, trial/signup 422 rapide (plus de 500), i18n catalog parité 851×4. Régression main `seo-locale.test.ts` (titre EN « Changelog » 9 car.) trouvée puis corrigée sur main.

## 4. Leçons opérationnelles

1. **Les branches « fix/`N`-slug » orphelines sont souvent des doublants squash-mergés** : vérifier par rebase-probe avant suppression, pas par `--no-merged`.
2. **Le guard PA2-I18N-014** ignore `/vitrine/data/`, `.test.`, `/i18n/locales/` ; les catalogues dérivés (admin/web/mobile) sont **générés** depuis `shared/i18n/` — ne jamais les éditer à la main sans lancer `shared/i18n/sync/*.js` (validate-and-sync).
3. **`getChangelogReleases`** : toujours vérifier que la locale est réellement consommée (un paramètre `_locale` = correctif inachevé).
4. **Éditions automatisées multi-fichiers** : vérifier `wc -l`/`git diff --stat` après chaque fichier (cf. memory `automated-edit-truncation`).

## 5. Reste ouvert (backlog)

#4574 Lots 3-4 (pages restantes + garde CI anti-régression), #3245 (refactor self-service), #4842/#3885 (OpenAPI drift), mobile #4194/#4409/#4843, ops #2646/#3452/#3765, #4101 (E2E admin).
