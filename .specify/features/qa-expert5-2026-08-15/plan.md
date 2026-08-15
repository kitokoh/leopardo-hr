# Plan: QA Expert #5 — Test exhaustif plateforme (2026-08-15)

**Input**: spec.md

## Stratégie

1. **Audit** (terminé) : 4 subagents experts (api/web/admin/mobile) + tests live HTTP (Render/Vercel/CF Pages). 62 nouveaux constats, tous dédupliqués contre les issues/branches existantes (#2400).
2. **Tickets** (terminé) : 62 issues GitHub créées (#3231-#3294), label `qa-expert5-2026-08-15`, format `[QA][Px][surface]`.
3. **Implémentation** : par vagues P1 → P2 → P3, chaque correctif en branche `fix/<issue>-<slug>` + PR avec `Closes #N` + CHANGELOG. Priorité aux P1 sécurité (IDOR, SSRF, messages bruts) et aux parcours mobiles cassés (#3282, #3283).
4. **En parallèle** : campagne de merge des 22 PRs ouvertes (dont les waves QA précédentes) — vérifier les checks, merger les vertes, réparer/fermer les rouges (#3111, #3125, #3128).
5. **Garde** : main doit rester vert (5 checks requis) ; vérifier les runs post-merge avant d'annoncer.

## Ordre d'exécution conseillé

| Vague | Contenu | Issues |
|---|---|---|
| 0 | Merge campaign PRs vertes + réparation des PRs cassées | — |
| 1 | P1 API (company_id, policy, per_page, messages bruts) | #3231 #3232 #3234 #3235 |
| 2 | P1 mobile (GoRoutes manager, 405 read-all) | #3282 #3283 |
| 3 | P1 admin (WebhooksView, UsersView, realtime, i18n) | #3267 #3268 #3269 #3270 |
| 4 | P1 vitrine (preuve sociale, tarifs, FR-only) | #3246 #3247 #3248 |
| 5 | P2 quick wins (throttle, Log, FR API, sitemap, ancres…) | #3236 #3237 #3241 #3252 #3253 #3255… |
| 6 | P3 cleanup | restantes |

## Risques

- **CI saturée** : 22 PRs × ~20 workflows — merger par vagues, prioriser les vertes, éviter de créer des PRs concurrentes sur les mêmes fichiers.
- **Régression par branches périmées** (leçon 2026-08-15) : avant merge d'une vieille branche, vérifier `git diff origin/main...HEAD` sur les fichiers partagés.
- **Anti-doublon** : un seul `fix/<issue>-*` par issue ; vérifier les branches avant de coder.
