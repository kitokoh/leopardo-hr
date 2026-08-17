# QA Session — 2026-08-17 — swe-qa-b (SWE/QA mission multi-phase)

> Contexte : mission 3 phases (drain issues/PRs existants → audit 360° → implémentation),
> repo `kitokoh/leopardo-hr`, protocole Lock & Isolate multi-agents (#2400).

## 1. Drain des PRs ouvertes (pré-phase)

| PR | Contenu | Rôle | Statut |
|----|---------|------|--------|
| #4743 | env-example parity rate limits | shepherding | **merged** |
| #4744 | URLs prod fail-closed (#4720/#4721) | merge main dans branche + validation | **merged** |
| #4745 | dedup runs E2E/ZAP | suivi | **merged** |
| #4746 | admin fleet/exports + auth i18n + double toast | conflits résolus (union locales ×4, workflows canoniques post-#4744), body `Closes #4710,#4712,#4713` | merged (par la vague) |
| #4747 | tests Feature cassés sur main (#4748) | **validé localement 22 tests / 130 assertions**, adopté rebase agent | **merged** |

## 2. PRs ouvertes par cet agent

| PR | Issue(s) | Contenu | Statut |
|----|----------|---------|--------|
| #4779 | #4762 | ARB leopardo_core resynchronisés + l10n régénéré (Flutter 3.47) | **merged** |
| #4782 | #4780 | sync_service.dart `_mode` → `_currentMode` (leopardo_core ne compilait pas) | **merged** |
| #4786 | #4612 (complément) | régression seo-locale.test.ts sur main (titres courts post-#4755) | **merged** |
| #4857 | #4574 (batch 1) | portail client : dashboard/contracts/training/social-marketing localisés ×4 | open, CI |
| #4777 | #4704/#4702/#4612/#4707 | lot web i18n/seo — **fermée** (doublon #2400 : PRs canoniques #4756/#4765/#4778/#4766) | closed (dup) |
| #4848 | #4803 | checkout EUR — **fermée** (implémenté sur main par un autre agent, #4803 clos) | closed (dup) |

## 3. Issues créées

| Issue | Type | Résolution |
|-------|------|-----------|
| #4749 | doublon #4748 | close avec renvoi |
| #4762 | P1 CI — ARB désynchronisés (validate-and-sync rouge sur main) | close — implémenté (#4779 puis fix idempotence #4838) |
| #4780 | P1 mobile — `_mode` undefined (leopardo_core ne compile pas) | close — PR #4782 |
| #4803 | P2 web ux — EUR en dur + ternaire jours/gün/أيام/days dans le checkout | close — implémenté sur main (currencyLabel/trialDaysUnit) |
| #4574 | P2 web — portail client localisé (claim) | batch 1 livré (PR #4857) |

## 4. Audit 360° (Phase 1) — surfaces vérifiées

- **Vitrine live** : routes 200 sur 22 pages vérifiées, sitemap 48 URLs, hreflang OK, déploiement Vercel auto par merge (lag ~1 commit, titres dupliqués transitoires #2813 déjà suivi).
- **API prod** : health OK (v4.24.0, DB/Redis/queue/storage verts), shapes d'erreur cohérents (`RESOURCE_NOT_FOUND` + `localized_message`), `/trial/signup` répond 422 validé en <1s (#3259 résolu).
- **Admin** : login/fleet/exports 200.
- **Check externe Cloudflare** #4216 : toujours échec (infra, hors code).
- **Nouveaux findings** : checkout EUR (→ #4803), validate-and-sync ARB (→ #4762), leopardo_core analyze rouge (→ #4780).
- **Flutter** : `/opt/flutter-new/flutter/bin/flutter` 3.47 (pas `/opt/flutter-new/bin`).

## 5. Leçons (process)

1. **Committer avant rebase/stash** — les changements non commités sont perdus/emmêlés dans les stashes (2 incidents, récupérés).
2. `git stash pop` sur index conflictuel → UU ; récup = `checkout HEAD -- <fichiers>` + add.
3. **check-i18n-diff.js** : fichiers exempts = `/vitrine/data/`, `/vitrine/lib/content.ts`, `/vitrine/lib/seo.ts`, `/vitrine/lib/seo-metadata.ts` — y placer les dictionnaires.
4. Pas de commentaire JSX `{/* */}` entre attributs JSX (parser TS KO) — le mettre avant la balise.
5. Toujours `git fetch` avant merge/push (branches bougent toutes les 2-3 min).
6. Backticks dans les corps de PR → casser la shell ; écrire le payload via fichier.
