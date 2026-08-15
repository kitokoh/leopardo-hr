# Mini-spécification — Issue #3861

## Objectif

Localiser la coquille de la console admin (super-admin) : `document.title` et les libellés de Header suivaient la locale des données mais pas celle de l'interface (FR en dur).

## Correction

1. **Router** : les 25 `meta.title` FR en dur → clés catalogue `navigation.*` (login, logout, dashboard, analytics, globe, users, companies, companyDetail, subscriptions, support, supportTickets, crm, system, contributions, taxBrackets, legalRates, training, fleet, chat, webhooks, exports, growth, edge, account, notFound). `afterEach` traduit déjà via `translate(localeStore.current, …)` — les clés manquaient. Toast `requiresTenant` traduit aussi (`navigation.tenantOnly`).
2. **Header.vue** : Connecté / Mode secours (polling) / Push non configuré / Déconnecté / Rechercher / Notifications / Aucune notification / Alertes critiques / Niveau → `$t('shell.*')`.
3. **Catalogue SOURCE** `shared/i18n/locales/*.json` (fr/en/tr/ar) enrichi + cibles régénérées (`sync-web.js` → admin + web client, `sync-backend.js` → versions.json). `I18N_VALIDATION_OK`.

## Critères d'acceptation

1. `document.title` suit la locale active (fr/en/tr/ar).
2. Header sans littéral FR.
3. Lint, build Vite, `check-i18n-diff.js` et validate-and-sync verts.

## Trace Spec Kit

Issue : #3861
Branche : `fix/3861-admin-shell-i18n`
Date : 2026-08-15
