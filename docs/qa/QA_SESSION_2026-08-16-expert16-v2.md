# QA Leopardo RH — Session expert #16 (suite) du 2026-08-16

Poursuite de la mission QA : implémentation du backlog restant (spec-kit,
anti-doublon #2400, `Closes #N`).

## Issues implémentées et mergées (7)

| Issue | PR | Surface | Fix |
|---|---|---|---|
| #3965 | #4134 | edge | Dockerfile standalone : PWA web-offline **buildée** dans l'image (stage pwa-build, out/ → /app/public/edge-web/) |
| #3966 | #4137 | edge | docker-entrypoint : échecs visibles (exit 1 + état /data, logs scheduler sur volume) |
| #3972 | #4136 | edge | docker-compose : images `${EDGE_VERSION:-1.0.0}`, Caddy pinné par digest multi-arch |
| #3958 | #4135 | mobile | Smart Attendance : mode picker via GoRouter (route /smart-attendance/mode) |
| #3862 | #4133 | mobile | zone_enter : file d'attente bornée + réenvoi au tick (plus de catch muet) |
| #4102 | #4144 | mobile/CI | validateur contrats inclut leopardo_core (/device-tokens) ; contrat manager → read-all |
| #3876 | #4146 | i18n/CI | **validate-and-sync remis au vert** : pricing partagé aligné sur #3919 (Pilot/Operations), ARB resynchronisés (+19 clés/locale), clé webhooks dédoublonnée |

## Clôture avec preuve
- #3843 (vitest dans les tests web) : déjà corrigé par #3802/#3836 — fermée avec
  preuve grep (0 import vitest sur main).

## Leçons nouvelles
1. **validate-and-sync local** : rejouer la séquence exacte du workflow
   (validate → sync-mobile → sync-web → sync-backend → git diff --exit-code) ;
   vérifier l'idempotence (re-run → même drift).
2. **Clé JSON dupliquée** (webhooks ×2 dans admin fr.json) : JSON.parse garde la
   dernière → le sync « normalise » en une seule occurrence (drift bénin mais
   révélateur d'éditions manuelles des fichiers générés).
3. **Décision plans** : #3919/#4049/#4083 ont tranché Pilot 29 / Operations 99 /
   Enterprise sur devis (aligné PlanSeeder) — le catalogue partagé devait suivre.
4. Le drift composer.lock (#4110) signalé sur #4104 est en réalité sur main —
   à vérifier avec `composer validate` une fois l'outillage PHP local installé.
