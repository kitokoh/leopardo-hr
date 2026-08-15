# Registre des manquements — Session QA Expert 7 2026-08-15

> Session de test de la plateforme Leopardo RH (repo kitokoh/leopardo-hr).
> Mission : tester la vitrine, le web, l'admin, les mobiles, les workflows, les APIs, les
> logiques, l'onboarding et la cohérence — tout manquement → spec + tasks + issues (méthode
> Spec Kit) puis implémentation. Anti-doublon (#2400) : constats déjà couverts par les vagues
> QA existantes (#2600–#3434) exclus ou référencés ; une seule branche par issue.

## A. Vérifications runtime effectuées (prod live)

- [x] **Vitrine `leopardo-rh.com` → DOWN** : `getent hosts leopardo-rh.com` = NO DNS ;
      Google DNS (`dns.google/resolve`) → **Status 3 NXDOMAIN** pour `leopardo-rh.com`,
      `www.leopardo-rh.com` (A + NS). Aucun enregistrement DNS → la vitrine entière est
      injoignable à son domaine canonique. (constat nouveau — cf. #2632 build Vercel failure,
      #3251 divergence sitemap)
- [x] API Render live (`gestionemployerbackend.onrender.com`, version **4.23.5**) :
      `/api/v1/health` 200 · `/api/v1/health/live` 200 · `/api/v1/i18n/catalog/fr` **500** ·
      `/api/v1/supported-countries` **404** · `/api/v1/trial/status` **404** ·
      `/api/v1/api-explorer` **404** · `/api/v1/dashboard/kpi` 302 (auth) ·
      POST `/api/v1/auth/login` demo → **401 INVALID_CREDENTIALS** (demo mode off, cf. #2646).
      → déploiement Render obsolète vs main (#2627/#2632, déjà tracés — non dupliqué).
- [x] Admin `leo-admin.pages.dev` : **200** (login servable).

## B. Findings — Audit statique main (SHA au début de session : 4d66521c)

| ID | Sév | Constat | Preuve | Statut |
|----|-----|---------|--------|--------|
| E7-01 | P1 | Vitrine DOWN : `leopardo-rh.com` NXDOMAIN (A/NS vides) — 0 visiteur possible | Google DNS Status 3 | Ouvert → issue |
| E7-02 | P2 | `front/web/package.json` — dépendance `next` vs `react` versions incohérentes à vérifier au build | `npm run build` | À vérifier |
| E7-03 | P3 | (réservé — complété au fil de l'audit) | — | — |

## C. Actions session (branches/PRs)

- [ ] Branche `docs/qa-expert7-session-2026-08-15` : spec + findings-registry + tasks → PR.
- [ ] Issues implémentées : voir `tasks.md` (branches `fix/<issue>-*`).

## D. Décisions & constats post-session

_(complété en fin de session)_
