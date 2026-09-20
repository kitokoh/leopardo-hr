# Session QA Expert 11 — 2026-08-15 (audit 360° + implémentation)

**Agent** : qa-expert11 (session 2026-08-15) — audit 360° (spec-kit), consolidation, implémentation.
**Spec-kit** : `.specify/features/qa-expert11-2026-08-15/` (spec.md, plan.md, tasks.md).
**Issues ouvertes** : #3706, #3707, #3708 (+ contribution #3719, #2605, #3334 partiel).

## Constats d'audit vérifiés en live

### F1 — Domaine API canonique introuvable (issue #3706, P1)
- Vérification DNS/HTTP le 2026-08-15 : seuls `gestionemployerbackend.onrender.com`
  (API, v4.23.5) et `gestionemployer-backend.vercel.app` (vitrine, HTTP 200) sont
  joignables. Tous les `*.leopardo-rh.com` / `*.leopardo.app` → NXDOMAIN (#3452).
- Docs, `.env.example`, READMEs, Postman, workflows pointaient dans tous les sens.
- **Fix (PR #3716)** : `docs/ops/DOMAINS.md` (registre live/target machine-checkable),
  `dev-hub/tools/check-canonical-domains.sh` (garde anti-drift), `api/.env.example`
  réaligné sur les domaines joignables, step CI dans le job hygiene-guards.

### F2 — Régression b0630dd5 sur .env.example (issue #3707, P1)
- `APP_VERSION` repassé 4.23.5, `MAIL_BOUNCE_WEBHOOK_SECRET=` supprimé (fail-closed 503),
  commentaire `EDGE_LICENSE_PUBLIC_KEY` perdu.
- **Résolu sur main** par d'autres agents (0ba544ea, f01f694d) — issue fermée avec
  preuve code (gardes `check-app-version-sync.sh` + `check-env-example-parity.sh` vertes).

### F3 — Gardes dev-hub mortes (issue #3708, P2)
- `check-app-version-sync.sh`, `check-env-example-parity.sh`, `check-migration-basename-collisions.sh`,
  `check-country-catalog.sh` n'étaient appelées par AUCUN workflow → dérive silencieuse.
- **Fix (PR #3713)** : job `hygiene-guards` dans `architecture-check.yml` (4 gardes +
  canonical-domains). Doublon PR #3714 fermé (protocole #2400), spec ISSUE_3708.md intégrée.

### F4 — Collision de basenames de migrations sur main
- `check-migration-basename-collisions.sh` échouait sur main (préfixes 000004/000006/000001
  dupliqués — agents parallèles). **Résolu par un autre agent** (renumérotation 000008-000010).

### F5 — web-offline PWA appelle /api/edge/* (issue #3719, P2)
- `page.tsx` → `/api/edge/health` (inexistant) + `/api/edge/sync` (inexistant) → 404 permanent ;
  contrat health `{node_id,pending_sync}` jamais retourné ; `sw.js` précache `/index.html` (404).
- **Fix (PR #3749)** : health → `/api/v1/edge/health`, sync désactivé honnêtement,
  champs absents → `—`, `Promise.allSettled` sur le précache.

## Implémentation

| PR | Sujet | Statut |
|---|---|---|
| #3709 | docs spec-kit session (issues #3706-3708) | mergée |
| #3713 | CI hygiene-guards (Closes #3708) | ouverte |
| #3716 | registre domaines canoniques (Closes #3706) | ouverte |
| #3749 | web-offline edge UI (Closes #3719) | ouverte |
| #3764 | i18n /about, /careers, /faq (Closes #2605) | ouverte |

## Opérations de consolidation (Phase 2)

- **21 branches de PR réalignées sur main** (merges + résolution conflits i18n JSON
  « union clés, branche gagne ») — la plupart mergées ensuite par l'orchestrateur.
- Duplicatas fermés : PR #3714 → #3713 (hygiène CI).
- Issue #3707 fermée avec preuve code ; issue #2605 implémentée.
- Garde `check-canonical-domains.sh` testée : 0 erreur sur main.

## Notes pour les prochaines sessions

- CI GitHub Actions en saturation (67+ runs queued, 0 in_progress pendant ~25 min le
  2026-08-15) : ne pas re-pusher en boucle, poller avant merge. Contexte #3545.
- Le merge-robot « tmp-merge » met à jour les branches de PR avec origin/main —
  faire `git fetch` + `git reset --hard origin/<branche>` avant de travailler dessus.
- `git reset --hard` détruit les fichiers non commités : commiter immédiatement.
