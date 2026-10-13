# EQUITY-INTEREST — Manifestation d'intérêt « Prendre des parts »

> **Statut** : v1 (cette livraison) — capture + suivi. Paiement de parts : hors
> scope (démarche juridique propre, traitée en direct par le propriétaire).
> **Demandeur** : propriétaire de la plateforme (session 2026-10-13).
> **Discrétion** : entrée volontairement discrète — lien en **footer** de la
> vitrine uniquement, aucune carte home, aucun bandeau.

## 1. Cas d'usage

Le propriétaire souhaite ouvrir le capital de l'entreprise : un visiteur de la
vitrine (prospect, partenaire, investisseur individuel) doit pouvoir
**manifester son intérêt à prendre des parts** de l'entreprise, sans
engagement, en quelques champs. Le propriétaire retrouve ces intentions dans
l'espace **admin plateforme** et traite le suivi en direct (contact,
term-sheet, closing — hors plateforme).

Stratégie retenue :

- **Positionnement** : « Devenir actionnaire » — manifestation d'intérêt
  **non contraignante**, ce n'est **pas** une offre publique de titres. La
  page le dit explicitement (conformité + honnêteté).
- **Ton** : sobre, institutionnel. Pas de compteur, pas d'urgence artificielle.
- **Montants** : tranches indicatives (pas de montant libre — évite la
  collecte de données fantaisistes et facilite le tri) :
  `< 1 k€`, `1–10 k€`, `10–50 k€`, `50 k€+`, « à discuter ».
- **Traçabilité** : consentement explicite à être recontacté (RGPD), locale
  du visiteur, source (`vitrine:/investir`), horodatage.

## 2. Acteurs

| Acteur | Rôle |
|---|---|
| Visiteur vitrine | Lit la page `/investir`, soumet le formulaire |
| Propriétaire / admin plateforme | Consulte et qualifie les intentions dans l'admin |

Aucune dimension tenant : c'est une donnée **plateforme** (schema `public`),
comme les annuaires fundraising — déclarée dans
`dev-hub/governance/tenant-scope-exceptions.json`.

## 3. Domaine

### Table `equity_interests` (migration `public`, idempotente)

| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `full_name` | string(120) | requis |
| `email` | string(190) | requis, index |
| `phone` | string(40) nullable | optionnel |
| `country` | string(2) nullable | code ISO si fourni |
| `amount_bracket` | string(20) | enum : `lt_1k`, `1k_10k`, `10k_50k`, `gt_50k`, `discuss` |
| `message` | text nullable | 2000 max |
| `locale` | string(5) | fr/en/ar/tr au dépôt |
| `source` | string(60) | défaut `vitrine:/investir` |
| `consent` | boolean | requis true — recontact |
| `status` | string(20) | `new` → `contacted` → `qualified` → `closed` (index) |
| `created_at` / `updated_at` | timestamps | |

Anti-double-soumission : unicité souple (`email`, `created_at` fenêtre 24 h)
gérée en action (pas de contrainte DB — un investisseur peut re-manifester un
intérêt plus tard), honeypot + throttle IP comme les autres surfaces publiques.

### Énumérations

- `EquityInterestStatus` : `new`, `contacted`, `qualified`, `closed`
  (transitions libres avant → arrière autorisées : le suivi humain n'est pas
  linéaire ; on journalise juste `updated_at`).
- `EquityAmountBracket` : `lt_1k`, `1k_10k`, `10k_50k`, `gt_50k`, `discuss`.

## 4. API

### 4.1 Capture publique — `POST /api/v1/public/equity-interests`

- Middleware : `throttle:shop-public` (pattern cagnottes publiques).
- Validation : `full_name` requis ≤120, `email` requis email ≤190,
  `phone` ≤40, `country` 2 lettres, `amount_bracket` in enum,
  `message` ≤2000, `consent` accepted, `website` honeypot (present → 200
  silencieux, rien en base).
- Réponse : `201 { data: { reference, status } }` — `reference` =
  `EQ-<id base32>` non énumérable, affichée au visiteur (« conservez cette
  référence »). Aucun endpoint de relecture publique : pas de fuite.
- Échec validation : `422` + codes erreur traduits ×4 locales.

### 4.2 Suivi admin plateforme

Sous le groupe existant `auth:super_admin_api` + `throttle:platform-sensitive`,
préfixe `/platform` :

| Route | Permission | Rôle |
|---|---|---|
| `GET /platform/equity-interests?status=&q=&page=` | `crm.view` | liste paginée + compteurs par statut |
| `PATCH /platform/equity-interests/{id}` | `crm.view` | transition de statut (+ note interne v2) |
| `GET /platform/equity-interests/export` | `crm.view` | export CSV (mêmes filtres) |

Permissions réutilisées (`crm.view`) : c'est du suivi de leads investisseurs,
même famille que le pipeline CRM — aucune permission nouvelle à seeder.

## 5. Vitrine (`front/web`)

- **Page** `/investir` (`app/(landing)/investir/page.tsx` + client) :
  hero sobre, 3 blocs (pourquoi nous, ce que signifie manifester son intérêt,
  étapes : manifester → échange → décision), formulaire (nom, email, téléphone
  optionnel, pays, tranche, message, consentement), états succès/erreur,
  honeypot invisible. SEO : metadata propre, `robots` index OK (page publique
  assumée, simplement non mise en avant).
- **Footer** : 4ᵉ lien de la colonne « Légal » → `/investir`
  (`getFooterHref(3,3)`), libellés ×4 locales
  (`vitrine-locale.ts`) : « Devenir actionnaire » / « Become a shareholder » /
  ar / tr. Test `footer-links.test.ts` complété.
- **i18n** : groupe `invest` dans `shared/i18n/locales/*.json` ×4, propagé par
  `sync-web.js` (web vitrine) ; checksums `versions.json` recalculés.

## 6. Admin plateforme (`front/admin-dashboard`)

- **Écran** `views/equity/EquityInterestsView.vue` : tableau (date, nom,
  email, téléphone, tranche, statut, message), filtre par statut, recherche
  email/nom, changement de statut inline, bouton export CSV, compteurs.
- **Route** `/equity-interests` + **entrée nav** groupe « Entreprises
  clientes » après « CRM pipeline », permission `crm.view`, icône
  `BanknotesIcon`, titleKey `navigation.equityInterests` ×4 locales.
- Client HTTP : `api.get('/platform/equity-interests', …)` existant.

## 7. Sécurité & conformité

- Fail-closed : aucune donnée bancaire, aucun paiement — simple formulaire.
- Anti-spam : honeypot + throttle IP `shop-public` + validation stricte.
- RGPD : consentement explicite, finalité affichée (« être recontacté au
  sujet d'une prise de participation »), pas de revente, export admin limité
  aux admins plateforme authentifiés (super admin), purge manuelle possible
  (delete admin v2).
- Anti-énumération : aucune lecture publique ; références non séquentielles.

## 8. Tests

- **API Feature** `EquityInterestApiTest` : création OK (201 + reference),
  validation 422 (email, consent, bracket), honeypot silencieux, throttle,
  liste admin paginée + filtre statut + recherche, transition de statut,
  export CSV, garde RBAC (non-admin 401/403).
- **Vitrine Jest** `invest-page.test.tsx` : rendu sections ×4 locales,
  soumission (mock apiFetch), états succès/erreur, honeypot rempli = pas
  d'appel API ; `footer-links.test.ts` : `getFooterHref(3,3) === '/investir'`.
- **Parité i18n** : validate.js vert (4 locales, clé `invest.*`).

## 9. Tâches (mapping livraison)

| ID | Tâche | Statut |
|---|---|---|
| EQ-010 | Migration publique `equity_interests` + exception gouvernance | à faire |
| EQ-020 | Enums + modèle + action de capture (honeypot, doublon 24 h) | à faire |
| EQ-030 | Endpoint public + validation traduite ×4 + OpenAPI | à faire |
| EQ-040 | Endpoints admin plateforme (liste/statut/export) | à faire |
| EQ-050 | Page vitrine `/investir` + formulaire + i18n ×4 | à faire |
| EQ-055 | Lien footer « Devenir actionnaire » ×4 + test | à faire |
| EQ-060 | Écran admin suivi + nav + i18n ×4 | à faire |
| EQ-070 | Tests API Feature + vitrine Jest | à faire |
| EQ-080 | Registre scénarios de test + CHANGELOG | à faire |

## 10. Hors scope (v2+)

- Data-room investisseurs, upload de documents, signature électronique.
- Paiement/émission de titres en ligne (cadre juridique préalable requis).
- Note interne admin + assignation + purge RGPD en self-service.
