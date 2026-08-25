# Feature Specification: Multi-devises + taux de change (issue #5270)

**Feature Branch**: `mod/accounting/5270-multi-currency`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Issue #5270 [P2][billing] — « Comptabilité 100 % — multi-devises + taux de change (facturation) ». Facturer en devise autre que celle de l'entreprise (devise contact, taux, affichage HT/TVA/TTC convertis). Le socle data (#5221, mergé) pose déjà les champs `currency`/`exchange_rate` sur `accounting_documents`, `currency` sur `accounting_contacts` et `currency` sur `accounting_settings` (COMPTABILITE_CONCEPTION.md §4).

## Décision

1. **Registre de devises supportées** : union des devises du registre `CountryDefaults` (DZD, MAD, TND, XOF, XAF, EUR, TRY, GBP, USD, CAD) — même source de vérité que `UpdateAccountingSettingsRequest` (règle #5270, centralisée). `AccountingCurrencies` (Domain/Support) : `supported()`, `isSupported()`, `normalize()`.
2. **Devise par contact (défaut : entreprise)** : validation `Rule::in(AccountingCurrencies::supported())` sur `StoreContactRequest`/`UpdateContactRequest` ; à la création sans devise fournie, défaut résolu par chaîne de priorité : `AccountingSettings.currency` → devise pays entreprise (`CountryDefaults`). L'update ne surcharge jamais une devise existante (nullable conservé).
3. **Chaîne de résolution documentaire** : devise du document = devise du contact → devise settings → devise pays entreprise (`AccountingCurrencyResolver`). Les champs `currency`/`exchange_rate` du document existent déjà ; la création REST des documents reste le périmètre de #5226/#5352.
4. **Conversion HT/TVA/TTC** : `DocumentCurrencyConverter` (Application/Actions) :
   - **Sémantique du taux** : `exchange_rate` = valeur de 1 unité de la devise du document dans la devise de référence (multiplication). Taux = 1 (identité) si devises identiques.
   - **Ordre de calcul TVA** : la TVA est toujours calculée dans la devise du document (montants de la ligne), puis les totaux (HT, TVA, TTC) sont convertis dans la devise de référence — jamais de taux appliqué avant le calcul de TVA.
   - **Arrondis documentés** : `PHP_ROUND_HALF_UP`, 2 décimales pour tout montant monétaire exposé (devise document et devise de référence) ; précision interne 4 décimales ; taux accepté avec jusqu'à 6 décimales significatives, strictement positif.
   - **Sources de taux** : `CurrencyRateProviderInterface` (contrat) — implémentation par défaut `ManualCurrencyRateProvider` (taux fourni à l'appel, cf. `document.exchange_rate`) ; une source externe (ex. BCE/ECB) s'intègre par une implémentation du même contrat (injectée via le provider du module), documentée dans la spec et le code. Aucun appel réseau dans la v1 (hors périmètre #5272, pas de dépendance API clé).
5. **Endpoint utilitaire** `POST /accounting/currency/convert` (RBAC `comptable`/`principal`, même middleware que contacts) : expose le contrat de conversion pour les frontends (affichage HT/TVA/TTC convertis, wizard) — réponse `{amount, from_currency, to_currency, rate, converted_amount, rounding, decimals}`.
6. **Aucune migration** : les colonnes existent déjà (socle #5221). Aucune chaîne UI nouvelle (0 chaîne hardcodée ; messages de validation via le catalogue existant).

## User Scenarios & Testing

### User Story 1 — Devise par contact (Priority: P1)
1. **Given** une entreprise DZ (settings `currency=DZD`), **When** création d'un contact sans `currency`, **Then** `currency=DZD` (défaut entreprise).
2. **Given** une entreprise MA sans settings persistés, **When** création d'un contact sans `currency`, **Then** `currency=MAD` (défaut pays).
3. **Given** un payload `currency=EUR`, **Then** le contact est créé avec `EUR` (devise supportée).
4. **Given** `currency=ZZZ` (hors registre), **Then** 422 validation.
5. **Given** un contact avec devise `EUR`, **When** PUT sans `currency`, **Then** la devise reste `EUR` (pas de surcharge).

### User Story 2 — Conversion (Priority: P1)
1. **Given** 100,00 EUR → DZD, taux 1,05 (EUR→DZD), **Then** 105,00 DZD (2 décimales, half-up).
2. **Given** 19,99 EUR taux 1,007, **Then** 20,13 DZD (arrondi half-up, pas 20,12).
3. **Given** devises identiques (DZD → DZD), **Then** taux = 1, montant inchangé (identité, sans provider).
4. **Given** TVA 19 % sur 100 HT en devise document, taux 1,05, **Then** HT converti = 105,00 ; TVA = 19,00 dans la devise document puis 19,95 convertie ; TTC = 124,95 (TVA calculée en devise document, jamais convertie avant calcul).
5. **Given** un taux 0 ou négatif, **Then** 422.
6. **Given** un provider externe fake, **Then** la conversion utilise le taux du provider (source `external`).

### User Story 3 — Totaux devise document + devise de référence (Priority: P2)
1. **Given** un `AccountingDocument` (currency EUR, subtotal_ht 1000, tax_amount 190, total_ttc 1190), **When** `convertTotals` vers DZD (settings) taux 1,05, **Then** devise document = EUR (1000/190/1190) et devise référence = DZD (1050/199,50/1249,50).
2. **Given** document en devise identique à la référence, **Then** conversion identité (taux 1).

## Hors périmètre
- Création REST des documents + totaux au store : #5226 / #5352 (le convertisseur est consommable dès qu'ils mergent).
- Taux de change automatiques en temps réel (API externe) : interface fournie, implémentation réseau v1 non requise (pas de clé API, CI hors-ligne).
- Multi-devises des lignes (prix unitaires en devises différentes au sein d'un document) : non-objectif v1 (conception §4 : « pas de multi-devises complexe v1 »).
- Passerelle de paiement en ligne : #5272 (décision fondateur requise).
