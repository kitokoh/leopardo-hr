# Conception — Verticale Leopardo International Trade OS (BC-36)

> Date : 2026-10-14 · Statut : **conception pour implémentation v1** · Épic : mission « Leopardo International Trade OS »
> Références : audit `docs/audits/TRADE_OS_AUDIT_2026-10-14.md` · directive de mission owner (validation explicite du périmètre, règle d'or AGENTS.md satisfaite par le présent document)
> Blueprints suivis : verticale VTC (BC-35) et TravelAgency (BC-24) pour l'enregistrement ; Accounting pour devis/pro forma ; CRM pour les contreparties.

---

## 1. Vision

La verticale **Trade** donne aux professionnels du commerce (import/export ET B2B domestique) un espace cohérent dans Leopardo Business OS : sourcing, fournisseurs, clients, demandes de prix (RFQ), offres commerciales, coûts logistiques, documents, commandes et suivi des transactions.

Principes directeurs :

1. **Un noyau commercial partagé**, des vues métier distinctes — l'acheteur (importateur/grossiste) et le vendeur (exportateur/grossiste) manipulent les MÊMES objets (`TradeRfq`, `TradeQuote`, `TradeOrder`, `TradeDeal`…) avec des directions opposées. Une même entreprise peut importer certains produits et en exporter d'autres : **aucun enfermement dans un rôle métier irréversible** (le profil trade porte des *directions*, pas un rôle exclusif).
2. **Réutilisation maximale du socle** (classification A/B/C/D de l'audit §11) — rien n'est dupliqué : catalogue de solutions, provisioning, RBAC, CRM (contreparties), Accounting (pro forma), fichiers privés, audit, notifications.
3. **Honnêteté fonctionnelle** : aucune intégration externe non configurée n'est présentée comme active ; aucune estimation douanière n'est présentée comme une décision officielle ; l'IA ne fixe ni taux ni prix.
4. **Progressivité** : architecture prête pour les espaces prestataires (transporteurs, transitaires, dédouanement, inspection) et la marketplace publique, sans les construire en v1.

## 2. Classification architecturale

- **BC-36 TRADE** (nouveau bounded context — registre `dev-hub/governance/bounded-context-registry.json` + label GitHub `BC-36 TRADE`).
- **Module** : `api/app/Modules/Trade/` (structure DDD complète, template `api/stubs/module-template/`).
- **Code solution** : `trade` — feature flag `companies.features.trade`, clé `ModuleRegistry` (kind `solution`, défaut `false`, killable, exposable), manifest `TradeManifest`, industrie nouvelle case `SolutionIndustry::InternationalTrade = 'international_trade'` (registre fermé, extension par case — précédent `Mobility`).
- **Routes** : `api/routes/modules/trade.php` sous `/api/v1/trade/*`, groupe `[throttle:api, auth:sanctum, token.refresh, tenant, throttle:api-plan, module.trade]` ; gate `EnsureTradeModuleMiddleware` (alias `module.trade`, pattern VTC).
- **Rôles de verticale** (deny-by-default) : `TradeRoleResolver` + `EnsureTradeRoleMiddleware` (alias `trade.role:<rôle>`), calqués sur VTC ; permissions de manifest installées aux `principal` par `SolutionPermissionInstaller`.

| Permission manifest | Rôle métier | Portée |
|---|---|---|
| `trade.admin` | Admin trade | paramétrage profil/catalogue, tout le module |
| `trade.purchase` | Acheteur | RFQ, invitations, réceptions, commandes d'achat |
| `trade.sales` | Vendeur | offres, clients, commandes de vente, pro forma |
| `trade.logistics` | Logisticien | transport, documents, douane, coûts |
| `trade.reports` | Analyste | tableaux de bord et exports |

Résolution (comme VTC) : managers `principal`/`manager` → tous rôles trade ; employé → rôles via grants `employee_module_grants` portant les codes ci-dessus ; rien sinon. Les rôles trade **ne remplacent jamais** le RBAC plateforme (`manager_role`, policies, `PlatformPermission`) — ce sont des capacités métier de verticale.

## 3. Parcours d'entrée « Trade » (vitrine + onboarding)

### 3.1 Vitrine (front/web)

- **Point d'entrée « Trade » sur la première page** : carte dans `VerticalsSection` (×4 locales) + entrée du menu `Packs` de la `Navbar` — surface additive minimale (PR #8472 concurrente sur ces fichiers : insertion en blocs distincts, rebase si besoin).
- **Page hub `/packs/trade`** (data `pack-pages.ts` ×4 locales, servie par la route dynamique existante) présentant **deux choix** :
  - **International** → `/packs/trade#international` : questionnaire progressif (survey `trade`, §3.3) : importer/exporter/les deux, pays d'établissement, marchés visés, activité & catégories. CTA → `/signup?pack=trade`.
  - **Domestique** → deux sous-choix :
    - **Grossiste** (B2B domestique) → `/signup?pack=trade-grossiste` (active `trade`, profil `domestic_scope=true` posé à l'onboarding espace).
    - **Vente au détail** → renvoi vers la verticale **Retail existante** (`/signup?pack=retail` — slug ajouté, solution `retail` déjà enregistrée) : la vente locale au détail = POS/e-commerce Retail, pas de duplication.
- **Anti-over-promise** (garde `public-promises-guard.yml`) : copy factuelle — « aide à la préparation documentaire », « estimation indicative » — jamais « dédouanement automatique », « taux officiels », « fournisseurs vérifiés ».

### 3.2 Mapping signup (existant, extension)

`front/web/src/modules/vitrine/lib/pack-signup.ts` (fail-closed) : ajout de slugs → codes SolutionCatalogue :

| Slug pack | Solutions[] | Profil |
|---|---|---|
| `trade` | `['trade']` | international (défaut) |
| `trade-grossiste` | `['trade']` | domestique (onboarding espace) |
| `retail` | `['retail']` | vente au détail domestique |

Le backend valide déjà `solutions.*` contre le catalogue (422) — aucune modification du contrôleur trial. Le provisioning est celui d'**origine existante** (`VerifyTrialSignup`, verrou anti double-provisioning, `metadata.vertical='trade'`) : **aucun tenant ni compte dupliqué** — un utilisateur déjà provisionné active `trade` depuis `/modules` (self-service existant) ou la console plateforme.

### 3.3 Survey « Trade » (point d'extension Core prévu)

`TradeSurvey` implémente `App\Core\Solutions\Survey\Contracts\SolutionSurvey` (enregistré au `SolutionSurveyRegistry` comme `RestaurantSurvey`) → endpoints publics existants `GET|POST /api/v1/solutions/trade/survey`. Questions v1 : direction (import/export/les deux), pays de l'entreprise, marchés cibles (multi), secteur/catégories, volume estimé. Réponses stockées dans `solution_survey_responses` (existant) — aucune table nouvelle.

### 3.4 Onboarding espace (post-activation, progressif)

`TradeProfile` créé **paresseusement et idempotemment** :

- `GET /api/v1/trade/onboarding/state` → état (profil existant ? étapes restantes : direction, pays/marchés, catégories, premier produit).
- `POST /api/v1/trade/onboarding/profile` → upsert idempotent (direction(s), pays établissement, marchés servis, catégories, devise/Incoterm par défaut) — validation stricte, réponse = profil complet.

Le choix « les deux » (import ET export) est naturel : `import_enabled` + `export_enabled` sont deux booléens indépendants. Le parcours grossiste domestique pose `domestic_scope=true` (marché = pays d'établissement) tout en réutilisant le même cycle commercial (§5).

## 4. Modèle de données (noyau commercial partagé)

Toutes les tables sont **tenant** (`shared_tenants`), avec `company_id` UUID + trait `BelongsToCompany` (fail-closed, anti-spoof) — **aucune exception** à `tenant-scope-exceptions.json`. Montants en **minor units entières** (`bigint`, jamais de float — leçon TravelQuote), devises `char(3)` ISO 4217 validées contre le registre Accounting. Pays = codes ISO 3166-1 alpha-2 validés contre `CountryDefaults` (21 pays supportés v1 — refus explicite hors liste, extensible).

### 4.1 Profil & catalogue

- **`trade_company_profiles`** (1:1 company, unique) : `import_enabled`, `export_enabled`, `domestic_scope`, `hq_country`, `markets_served jsonb`, `product_categories jsonb`, `default_currency`, `default_incoterm` (nullable), `capabilities jsonb` (capacités déclaratives : production, MOQ types, certifications — texte libre structuré), `onboarding_completed_at`.
- **`trade_products`** : `name`, `description`, `category`, `hs_code` (nullable, format 6–10 chiffres validé), `origin_country`, `unit` (pièce/kg/t/m³/L/carton…), `moq_quantity`, `indicative_unit_price_minor` (nullable), `currency`, `specs jsonb` (attributs clé/valeur), `status active|archived`.

### 4.2 Contreparties (extension CRM par satellite — classification B)

Le CRM (`crm_accounts`/`crm_contacts`) reste le référentiel clients/prospects. La verticale ajoute :

- **`trade_counterparties`** : `crm_account_id` (FK, unique par tenant), `roles jsonb` ⊆ `[buyer, supplier, manufacturer, wholesaler, carrier, forwarder, customs_broker, inspector]`, `country`, `verification_status none|pending|verified|rejected` (jamais « verified » sans source — v1 : saisie manuelle tracée), `capabilities jsonb`, `notes`.

→ Un fournisseur/client est donc un compte CRM enrichi de son rôle trade ; la timeline CRM et la dédup restent utilisables. **Aucune modification du module CRM** (satellite dans Trade, isolation préservée).

### 4.3 Cycle commercial

- **`trade_deals`** (dossier de transaction — conteneur) : `reference` (TRD-…), `direction import|export|domestic_purchase|domestic_sale`, `trade_counterparty_id` (nullable à l'ouverture), `title`, `status open|won|lost|cancelled`, `currency`, `opened_by_employee_id`, `metadata jsonb`.
- **`trade_rfqs`** (demandes de prix émises) : `reference` (RFQ-…), `trade_deal_id` (nullable), `title`, `status draft|open|closed|awarded|cancelled`, `destination_country`, `destination_port`, `incoterm` (nullable), `budget_minor` (nullable), `budget_currency`, `deadline_at`, `notes`.
- **`trade_rfq_lines`** : `trade_product_id` (nullable — saisie libre permise), `description`, `specs jsonb`, `quantity decimal(14,3)`, `unit`, `target_unit_price_minor` (nullable).
- **`trade_rfq_invitations`** (diffusion multi-fournisseurs) : `trade_rfq_id`, `trade_counterparty_id`, `channel internal_note|email`, `status pending|sent|viewed|responded|declined|expired`, `message`, `sent_at`, `responded_at`, `response_token_hash` (nullable — lien de réponse sécurisé, SHA-256, usage unique, expiration ; pattern invitation onboarding).
- **`trade_quotes`** (réponses ET offres proactives — un seul objet) : `direction incoming|outgoing`, `trade_rfq_id` + `trade_rfq_invitation_id` (nullables — null = offre proactive), `trade_deal_id` (nullable), `trade_counterparty_id`, `reference` (QTE-…), `version int`, `supersedes_id` (self-FK — versioning), `status draft|submitted|under_review|negotiating|accepted|rejected|expired|withdrawn`, `currency`, `incoterm`, `validity_until`, `lead_time_days`, `payment_terms`, `subtotal_minor`, `total_minor` (**figés serveur**, recalculés des lignes — jamais acceptés du client), `notes`, `submitted_at`.
- **`trade_quote_lines`** : `trade_product_id` (nullable), `description`, `quantity`, `unit`, `unit_price_minor`, `moq_quantity` (nullable), `lead_time_days` (nullable), `line_total_minor` (calculé).
- **`trade_orders`** : `trade_deal_id`, `trade_quote_id` (nullable — source), `direction purchase|sale`, `reference` (ORD-…), `status draft|confirmed|preparing|ready|shipped|delivered|closed|cancelled`, `currency`, `incoterm`, `total_minor` (figé), `ordered_at`, `expected_delivery_at`, `payment_terms`, `notes`.
- **`trade_shipments`** : `trade_order_id`, `mode sea|air|road|rail|multimodal`, `carrier_name`, `tracking_reference`, `status pending|booked|in_transit|arrived|in_customs|delivered|exception`, `origin`, `destination`, `eta`, `notes`.

**Machines à états** (enums typés + transitions autorisées en `Domain/Services`, tests exhaustifs) — extraits : RFQ `draft→open→(awarded|closed|cancelled)`, `open→closed` ; Quote `draft→submitted→under_review→negotiating→(accepted|rejected|withdrawn)`, `submitted→expired` (job ou à la lecture), `accepted` terminal (→ order) ; Order `draft→confirmed→preparing→ready→shipped→delivered→closed`, annulation permise avant `shipped`. Toute transition illégale = 422 `INVALID_STATE_TRANSITION` ; transitions auditées (`trade_events` + `Auditable`).

**Pro forma / facture** (classification B) : une quote `accepted` peut générer un document comptable **pro forma** via un contrat partagé nouveau `App\Shared\Contracts\Accounting\TradeDocumentBridgeContract` (implémenté côté Accounting si faisable sans modification intrusive — sinon v1 : `trade_documents` de type `proforma_invoice` uploadé + marqueur `accounting_document_id` nullable câblé quand le pont existe). Décision d'implémentation tranchée au lot TRADE-004 : **priorité au pont par contrat Shared** (jamais d'import direct `Modules\Accounting` depuis Trade — garde Module Structure).

### 4.4 Transport & prestataires

- **`trade_transport_requests`** : `reference` (TTR-…), `trade_deal_id`/`trade_order_id` (nullables), `origin_country/city/port`, `destination_country/city/port`, `mode`, `cargo_description`, `weight_kg`, `volume_m3`, `containers jsonb`, `incoterm`, `pickup_date`, `status draft|sent|quoted|selected|expired|cancelled`.
- **`trade_transport_quotes`** : `trade_transport_request_id`, `trade_counterparty_id` (rôle `carrier|forwarder`), `price_minor`, `currency`, `included_fees jsonb`, `excluded_fees jsonb`, `transit_time_days`, `validity_until`, `status received|accepted|declined|expired`, `notes`.

→ Architecture « espaces prestataires » : les prestataires v1 sont des **contreparties CRM** recevant demandes et réponses capturées (manuellement ou par lien sécurisé) — le modèle `trade_counterparties.roles` et les objets transport ci-dessus accueillent déjà transporteurs/transitaires/dédouaneurs/inspecteurs ; un espace prestataire authentifié reste possible ultérieurement **sans refonte** (les objets sont déjà orientés contrepartie).

### 4.5 Coûts, douane, documents, conversation, événements

- **`trade_cost_estimates`** (coût total rendu) : `reference` (LCE-…), liens nullables `trade_deal_id|trade_quote_id|trade_order_id`, `origin_country`, `destination_country`, `incoterm`, `goods_value_minor`, `goods_currency`, `display_currency`, `fx_rate decimal(18,8)` (nullable), `fx_rate_date`, `fx_source` (`manual|<provider>` — tracé), `total_minor` (calculé), `status draft|final`, `notes`.
- **`trade_cost_estimate_lines`** : `type` ∈ `[purchase_price, inland_freight_origin, export_fees, international_freight, insurance, handling, port_fees, customs_duty, taxes, customs_broker_fee, inland_freight_destination, other]`, `label`, `amount_minor`, `currency`, `converted_amount_minor` (nullable si FX indisponible), `source confirmed|estimated|user_input|unavailable`, `note`.
- **`trade_customs_cases`** (aide à la préparation — **jamais** décision officielle) : liens nullables deal/order, `product_description`, `hs_code` (nullable), `origin_country`, `destination_country`, `status incomplete|ready`, `required_documents jsonb` (checklist **déterministe** : règles pays/HS saisies + documents de base par type d'opération), `duty_rate_percent` (nullable — source tracée `duty_source manual|provider`), `duty_amount_minor` (calculé si base+rate connus), `taxes jsonb`, `missing_info jsonb` (champs manquants listés automatiquement), `disclaimer_acknowledged_at` (obligatoire avant export/partage), `notes`. Mention légale systématique dans les réponses API : estimation indicative, ne constitue ni déclaration ni garantie de conformité.
- **`trade_documents`** : liens nullables `trade_deal_id|trade_order_id|trade_quote_id|trade_rfq_id`, `type` ∈ `[proforma_invoice, commercial_invoice, packing_list, bill_of_lading, airway_bill, certificate_of_origin, certificate_of_conformity, insurance_certificate, customs_declaration, contract, other]`, `title`, `disk` (défaut `private`), `path`, `original_name`, `mime_type`, `size_bytes`, `status draft|final|signed`, `uploaded_by_employee_id`. Accès : 404 anti-énumération cross-tenant (pattern Cabinet), download streamé après autorisation `trade.role`, upload validé (mime/taille ≤ 15 Mo, extension).
- **`trade_messages`** (conversation rattachée au dossier) : `trade_deal_id`, `employee_id` (auteur), `direction internal|outbound|inbound`, `body`, `attachments jsonb` (chemins disque `private`), `sent_via` (nullable). L'envoi e-mail réel passe par le mailer existant (template Blade `emails/trade/*`) — v1 journalise, n'envoie que si configuré.
- **`trade_events`** (timeline métier) : `subject_type` ∈ `[deal, rfq, quote, order, shipment, transport_request, cost_estimate, customs_case, document]`, `subject_id`, `type` (ex. `rfq.sent`, `quote.accepted`…), `payload jsonb`, `employee_id` (nullable = système). Écrits dans les actions applicatives (même transaction que la transition).

## 5. Domestique : grossiste & détaillant

- **Grossiste domestique** = même verticale `trade`, profil `domestic_scope=true` : catalogue, clients pro (CRM + contreparties), demandes de prix, offres, commandes (`direction domestic_purchase|domestic_sale`), livraison éventuelle (intégration Delivery possible ultérieurement via contrat Shared — hors v1). Formalités douanières et Incoterms masqués côté UI quand `domestic_scope` (le backend les accepte mais l'onboarding ne les demande pas).
- **Détaillant** = solution **Retail existante** (POS, stock, e-commerce « Leopardo Marché »). Le hub Trade y renvoie — **zéro duplication**.

Le cycle commun grossiste↔exportateur (clients, offres, commandes) est donc **le même code** ; seuls diffèrent marché desservi, règles logistiques et formalités (activées par le profil, pas par un fork de code).

## 6. API (extraits — contrat complet dans `api/openapi.yaml`)

Préfixe `/api/v1/trade`, groupe de middlewares canonique (§2), réponses JSON `{data|error, message}`, codes d'erreur du registre `App\Shared\Enums\ApiError` quand applicables.

| Ressource | Endpoints (verbes convention #4930) | Rôle min |
|---|---|---|
| Onboarding | `GET /onboarding/state` · `POST /onboarding/profile` | authentifié tenant (module trade) |
| Profil | `GET /profile` · `PUT /profile` | lecture : tout rôle trade · écriture : `admin` |
| Catalogue | `GET|POST /products` · `GET|PUT|DELETE /products/{id}` · `POST /products/{id}/archive` | `purchase`/`sales` (lecture) · `admin` (écriture) |
| Contreparties | `GET|POST /counterparties` · `GET|PUT /counterparties/{id}` · `POST /counterparties/{id}/verify` | `purchase`/`sales` · verify : `admin` |
| Deals | `GET|POST /deals` · `GET|PUT /deals/{id}` · `POST /deals/{id}/close` · `GET /deals/{id}/timeline` | `purchase`/`sales` |
| RFQ | `GET|POST /rfqs` · `GET|PUT /rfqs/{id}` · `POST /rfqs/{id}/publish|close|cancel|award` · `POST /rfqs/{id}/invitations` · `POST /rfqs/{id}/invitations/{inv}/send|cancel` | `purchase` |
| Quotes | `GET|POST /quotes` · `GET|PUT /quotes/{id}` · `POST /quotes/{id}/submit|accept|reject|withdraw|revise` · `GET /quotes/compare?rfq=` | `purchase` (incoming) · `sales` (outgoing) |
| Orders | `GET|POST /orders` (depuis quote acceptée) · `GET /orders/{id}` · `POST /orders/{id}/confirm|prepare|ready|ship|deliver|close|cancel` · `POST /orders/{id}/shipments` · `GET /orders/{id}/shipments` | `purchase`/`sales` selon direction · `logistics` (shipments) |
| Transport | `GET|POST /transport-requests` · `POST /transport-requests/{id}/send|cancel|select` · `POST /transport-requests/{id}/quotes` · `GET /transport-requests/{id}/quotes` | `logistics` |
| Coûts | `GET|POST /cost-estimates` · `GET|PUT /cost-estimates/{id}` · `POST /cost-estimates/{id}/finalize` · `GET /cost-estimates/{id}/breakdown` | `logistics` · lecture `purchase`/`sales` |
| Douane | `GET|POST /customs-cases` · `PUT /customs-cases/{id}` · `POST /customs-cases/{id}/acknowledge-disclaimer` | `logistics` |
| Documents | `GET|POST /documents` (upload multipart) · `GET /documents/{id}` · `GET /documents/{id}/download` · `DELETE /documents/{id}` | rôle du dossier parent + `logistics` |
| Messages | `GET|POST /deals/{id}/messages` | rôle du deal |
| Référentiels | `GET /meta/incoterms` · `GET /meta/currencies` · `GET /meta/countries` · `GET /meta/integrations` (statuts adaptateurs, cf. §8) | authentifié tenant (module trade) |

Lien de réponse fournisseur (public, sans auth) : `GET|POST /api/v1/public/trade/rfq-response/{token}` — token SHA-256 à usage lié, expiration, throttle strict, **aucune donnée au-delà de l'invitation** (pattern `cabinet/shared/{token}`).

## 7. Moteur de coût total rendu & Incoterms (déterministe)

- `App\Modules\Trade\Domain\Enums\TradeIncoterm` : 11 Incoterms 2020 (`EXW FCA FAS FOB CFR CIF CPT CIP DAP DPU DDP`) + **carte de responsabilité** (quel type de coût incombe à l'acheteur selon l'Incoterm) — table statique versionnée, testée unitairement (source : règles ICC 2020, commentée dans le code).
- `TradeLandedCostCalculator` : service pur — entrées lignes typées + FX (`rate`, `date`, `source`) → totaux par devise d'affichage, **math entière** (minor units), lignes `unavailable` exclues du total mais listées ; `coverage` (part des postes confirmés/estimés/saisis/indisponibles) exposée. Aucun appel réseau : **déterministe et testé** (Unit + Feature).
- **Comparaison de quotes** (`TradeQuoteComparisonService`) : normalisation vers un périmètre comparable UNIQUEMENT si devise (FX connu) + Incoterm + postes inclus sont documentés ; sinon chaque quote est marquée `non comparable — périmètre de coût différent` (jamais de comparaison silencieusement fausse — exigence mission §7).
- FX v1 : **saisi par l'utilisateur** (rate + date + source `manual`) ou via provider externe **si configuré** (§8) ; jamais inventé. Conversion inverse gérée (leçon `TravelCurrencyConverter`).

## 8. Adaptateurs d'API externes (v1)

Contrats dans le module (`Domain\Contracts`), implémentations `Infrastructure\Services`, enregistrement config-driven (`config/services.php` clés `trade_fx`, `trade_tariff`, `trade_verification` — **jamais de secret en dur**, doc `.env.example`, garde `ci-secrets-doc-guard`).

| Capacité | Contrat | Implémentations v1 | État réel affiché |
|---|---|---|---|
| Taux de change | `TradeExchangeRateProvider` | `ManualTradeExchangeRateProvider` (saisie) · `HttpTradeExchangeRateProvider` (squelette : base_url+key, timeout 5 s, cache 12 h, erreurs explicites) | `manual` opérationnel · HTTP `unavailable` tant que non configuré |
| Tarifs/droits de douane | `TradeTariffInfoProvider` | `NullTradeTariffInfoProvider` (fail-open explicite) | `unavailable` (aucune source souscrite — décision métier) |
| Vérification d'entreprises | `TradeCompanyVerificationProvider` | `NullTradeCompanyVerificationProvider` | `unavailable` |
| Cotations transporteur / tracking | `TradeCarrierQuoteProvider` | `NullTradeCarrierQuoteProvider` | `unavailable` (cycle interne livré) |

Exigences communes : timeout, exceptions typées `TradeProviderUnavailableException`, limitation via cache, logs **sans secrets ni données sensibles**, `GET /trade/meta/integrations` expose `{key, configured, available, reason}` par adaptateur — consommé par le dashboard (badge « non configuré ») et le superadmin (§9). **Aucune donnée fictive ne remplace une API réelle.**

## 9. Superadmin (extension de l'existant — pas de seconde console)

- **Backend** (`Modules/Platform`, groupe `/platform` existant, `platform.permission`) : `PlatformTradeController` —
  - `GET /platform/trade/overview` : nb de companies avec flag `trade`, compteurs d'objets (via wrappers `crossTenantForPlatformAdmin` — lecture agrégée **sans contenu privé** : counts uniquement), répartition directions.
  - `GET /platform/trade/integrations` : statut des 4 adaptateurs (§8) par plateforme (config présente ? disponible ?) — jamais de valeur de secret.
  - Activation/désactivation : **déjà possible** via `PlatformCompanyFeatureController` existant (clé `trade` au ModuleRegistry) — rien à recréer.
  - Permission réutilisée : `companies.view` (lecture) — aucune nouvelle case `PlatformPermission` en v1.
- **Front** (`admin-dashboard`) : entrée navigation `Trade` (groupe `modules-clients`, permission `companies.view`) + vue `src/views/trade/TradeOverview.vue` (KPIs + tableau intégrations) consommant les endpoints ci-dessus (client axios `services/trade.js`).
- **Séparation stricte** : aucun accès aux documents/messages/prix des tenants (pas d'endpoint de contenu), actions sensibles inchangées (impersonation auditée existante).

## 10. Sécurité & qualité (plan)

- Isolation : `BelongsToCompany` partout ; tests d'isolation dédiés `tests/Feature/Security/TradeTenantIsolationTest.php` (matrice : 2 companies × chaque ressource — lecture/écriture/téléchargement croisés = 404).
- Autorisation : middleware `module.trade` + `trade.role` sur chaque groupe ; policies par ressource (garde cross-tenant première ligne, pattern `EmployeePolicy`) ; écriture des totaux **serveur-only** ; anti-spoof `company_id` natif du trait.
- Fichiers : disque `private`, validation mime/taille, download autorisé, suppression auditée.
- Tokens publics (réponse RFQ, partage) : SHA-256 hashés, expirés, usage borné, throttle dédié, idempotence des traitements.
- Validation : FormRequests par endpoint ; transitions d'état centralisées ; montants entiers ; enums stricts.
- Journalisation : `Auditable` sur tous les modèles + `trade_events` métier ; pas de PII ni secrets dans les logs.
- IA : aucune en v1 (§12) — le câblage futur utilisera `PendingActionStore` (jamais d'écriture IA sans confirmation).
- PHPStan : niveau max sur fichiers ajoutés (diff-gate) + niveau 8 strict : code typé strict (generics collections, `list<…>`, unions nullables), zéro `mixed`.

## 11. Tests (plan par lot)

- **Unit** : machines à états RFQ/Quote/Order/Shipment/Transport, `TradeLandedCostCalculator` (dont lignes unavailable, FX inverse), carte Incoterms, `TradeQuoteComparisonService` (périmètres hétérogènes → non comparable), providers null/manual, validation HS code.
- **Feature** : ≥1 test par endpoint (CONVENTIONS §3.1) — CRUD, transitions (autorisées/refusées), RBAC par rôle (autorisé/refusé), onboarding idempotent (double POST → même profil, pas de doublon), upload/download documents (404 cross-tenant), lien public token (expiré/invalide/valide), meta/integrations (non configuré ≠ opérationnel).
- **Sécurité** : `TradeTenantIsolationTest` (matrice complète), spoof `company_id` (écrasé + warning), enumeration tokens.
- **Non-régression** : `TenantModelIsolationTest`, tests Solutions/Onboarding/Feature registry, `php artisan test` ciblé par lot puis suite étendue avant PR ; front : `lint` + `typecheck` + Jest ciblés ; gardes `check-migration-basename-collisions.sh`, `check-duplicate-schema-create.py`, `check-module-isolation.sh`, i18n sync, OpenAPI (lint + couverture + miroir régénéré).

## 12. Hors scope v1 (explicitement reporté)

Marketplace publique mondiale & moteur de recherche fournisseurs (mission §6) · espaces prestataires authentifiés (architecture prête §4.4) · signatures électroniques · paiements intégrés aux commandes · outils IA Trade (le socle Core/AI + ToolRegistry permet l'ajout ultérieur avec confirmation humaine) · providers externes souscrits (FX HTTP, tarifs douaniers, vérification — interfaces livrées, activation = configuration) · app mobile dédiée · intégration Delivery (livraison locale grossiste).

## 13. Découpage en issues (ordre & dépendances)

> Préfixe `[TRADE-nnn]`, label `BC-36 TRADE`, branche unique `bc/bc-36-trade-os` (protocole lot BC), un commit par issue, une PR fermant toutes les issues.

| # | Issue | Dépendances | Contenu (critères détaillés dans l'issue) |
|---|---|---|---|
| 001 | Socle verticale : spec/audit + BC-36 + manifest + registres + gate + routes | — | docs (déjà sur branche), BC registry, `TradeManifest`, `SolutionIndustry`, ModuleRegistry+flags+KNOWN_MODULES, provider, middleware, `/trade/ping`, CHANGELOG |
| 002 | Profil trade + catalogue produits + onboarding espace | 001 | `trade_company_profiles`, `trade_products`, API §6, `TradeSurvey`, permissions manifest installées, tests isolation+idempotence |
| 003 | Contreparties (satellite CRM) + RFQ + invitations + réponses | 002 | `trade_counterparties`, `trade_rfqs(+lines+invitations)`, machines à états, lien public token, tests |
| 004 | Quotes (offres) + versioning + négociation + commandes + shipments | 003 | `trade_quotes(+lines)`, `trade_orders`, `trade_shipments`, totaux serveur, comparaison §7, pont pro forma (contrat Shared si faisable) |
| 005 | Deals + messages + documents + événements | 003 (004 pour liens order/quote) | `trade_deals`, `trade_messages`, `trade_documents`, `trade_events`, timeline |
| 006 | Transport : demandes + cotations prestataires | 003 | `trade_transport_requests(+quotes)`, cycle complet |
| 007 | Coût rendu + Incoterms + FX + aide douane | 004 | calculator, `trade_cost_estimates(+lines)`, `trade_customs_cases`, adaptateurs §8, `meta/integrations` |
| 008 | Vitrine : entrée Trade + hub International/Domestique + signup slugs + i18n | 001 (copy) | VerticalsSection, Navbar, pack-pages, pack-signup, survey UI, locales ×4 + sync |
| 009 | Dashboard /trade (espaces acheteur/vendeur) | 003–007 | client-features, proxy, pages (sourcing, ventes, commandes, transport, coûts, catalogue, paramètres), composants |
| 010 | Superadmin : overview + intégrations + section console | 007 (statuts) | PlatformTradeController, navigation+vue admin-dashboard |
| 011 | Finalisation : OpenAPI, CHANGELOG, durcissement sécurité, docs | tous | openapi.yaml + miroir/SDK, RBAC matrix doc, isolation matrix complète, README module |

**Regroupement CI** : commits poussés par grappes cohérentes (001–002 socle, 003–005 commercial, 006–007 logistique/coûts, 008–010 fronts, 011 finalisation) pour limiter les runs (leçon saturation #6928).

## 14. Risques & mitigations

| Risque | Mitigation |
|---|---|
| Conflit PR #8472 (vitrine) / #8470 (dashboard) | Surface additive minimale sur fichiers partagés ; rebase rapide ; `strict: true` impose de toute façon une branche à jour au merge |
| Désync feature flags (historique #7220…) | Les 3 points modifiés dans le MÊME commit (ModuleRegistry + feature-flags.php + KNOWN_MODULES) + test de parité registry |
| Détournement « douane automatique » | Copy et API verrouillées : disclaimers, statuts `unavailable`, garde public-promises respecté |
| Volume (20 tables, ~30 endpoints) | Découpage TRADE-001…011, tests au fil de l'eau, CI verte à chaque grappe |
| PHPStan level max sur gros volume neuf | Typage strict dès l'écriture, exécution locale `phpstan` (PHP 8.4 installé) avant push |
| CHANGELOG 514 Ko | Entrées courtes uniquement |

## 15. Critères d'acceptation globaux (traçabilité mission §12)

1. Entrée Trade → choix International/Domestique : TRADE-008.
2. International → Importateur/Exportateur/les deux : TRADE-002 (profil) + 008 (parcours).
3. Domestique → Grossiste/Vente au détail : TRADE-008 (+ retail existant).
4. Provisioning existant, sans doublon : TRADE-001/002 (tests idempotence).
5. Permissions distinguant utilisateurs/entreprises : TRADE-001/002 (+ tests RBAC chaque lot).
6. Objets commerciaux partagés : TRADE-003…007.
7. Transitions/autorisations RFQ/quotes/orders : tests Feature TRADE-003/004.
8. APIs indisponibles jamais présentées actives : TRADE-007 (`meta/integrations`) + 009 (badges) + garde promises.
9. Isolation inter-entreprises démontrée : `TradeTenantIsolationTest` (TRADE-011, initiée dès TRADE-002).
10. Compatibilité migrations/fonctionnement : gardes migrations + suite non-régression par grappe.
11. CI requise verte sur branche + PR : vérifié avant merge (4 checks).
12. Merge sans contournement : protection `strict` + `enforce_admins`, aucun force-merge.
13. `main` vérifié après fusion : checks HEAD + état dépôt rapportés.
