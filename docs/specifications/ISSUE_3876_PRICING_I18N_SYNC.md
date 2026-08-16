# ISSUE_3876 (volet i18n) — validate-and-sync rouge : pricing partagé incohérent

**Statut**: Fixed (PR `fix/3876-pricing-i18n-sync`) · **Priorité**: P0/P1 · **Module**: i18n/CI

## Constat

Le workflow `i18n-enterprise.yml` (validate-and-sync) échoue sur main :
1. `shared/i18n/locales/{fr,en,tr,ar}.json` — `seo.pricing.description` citait
   Starter/Business/Free (décision superseded par #3919/#4049/#4083 :
   **Pilot 29 / Operations 99 / Enterprise sur devis**, aligné PlanSeeder) ;
2. ARB mobiles (`leopardo_core/lib/l10n/app_*.arb`) manquaient 19 clés/locale
   (`navigationLogin`, `shell*`, …) — jamais resynchronisés depuis l'ajout des
   clés dans le catalogue partagé ;
3. `admin-dashboard fr.json` portait une clé `webhooks` DUPLIQUÉE (ligne 65 +
   ligne 794) — normalisée par le sync (JSON.parse garde la dernière).

## Correctif

- `shared/i18n/locales/*.json` : description pricing alignée Pilot/Operations.
- Régénération complète : `sync-mobile.js`, `sync-web.js`, `sync-backend.js`
  → ARB, locales web/admin, `api/lang`, `versions.json`.
- Vérifié : `validate.js` OK, séquence workflow idempotente
  (`git diff --exit-code` stable après re-run).
