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

## 3. Domaine — réutilisation du canal leads (`marketing_leads`)

**Décision d'implémentation (revue en cours de livraison)** : pas de table
dédiée. La plateforme possède déjà un canal de capture publique éprouvé —
`marketing_leads` (schema public, serveur-à-serveur vitrine → API avec
secret partagé, idempotence persistée, anti-spam, alertes) — avec suivi
admin existant (`crm.view`). La manifestation d'intérêt est un **lead**,
pas une donnée métier tenant : on réutilise le canal (pattern
`solution_survey` #6692).

- `type = 'equity_interest'` — ajouté à la contrainte CHECK
  `marketing_leads_type_check` (migration publique `2026_10_13_000100_8440`),
  à `MarketingLead::TYPE_*`, à la validation du endpoint de capture et à la
  liste `TYPES` de `PlatformMarketingLeadController`.
- Détail du formulaire dans `payload` (jsonb) : `name`, `phone` (optionnel),
  `amount_bracket` (`lt_1k|1k_10k|10k_50k|gt_50k|discuss`), `message`,
  `consent: true`.
- Colonnes natives réutilisées : `email`, `locale`, `country`, `page`
  (`/investir`), `source` (`equity_form`), `ip`, `referrer`, `status`
  (`new → contacted → qualified → converted | rejected`), `note`.
- Anti-double-soumission : idempotence par hash du payload (registre
  `WebhookEventRegistry`) + throttle IP vitrine + honeypot — déjà en place.

## 4. API

### 4.1 Capture — vitrine server-to-server (canal existant)

- `POST /api/forms/equity` (Next.js vitrine) : validation zod stricte
  (nom 2..120, email, téléphone optionnel, pays 2 lettres, tranche en liste
  fermée, message ≤2000, consentement obligatoire), anti-spam (honeypot +
  origine + rate-limit IP), sanitisation, puis `captureMarketingLead()` →
  `POST /api/v1/marketing/leads` avec `type: 'equity_interest'` et le
  détail en `payload` (secret partagé `MARKETING_LEAD_WEBHOOK_TOKEN`,
  idempotence par hash, persistance confirmée ou continuée en tâche de
  fond — compromis #7301).
- Réponse vitrine : `201 { success, data: { id, persisted } }`. Aucun
  endpoint de relecture publique : pas de fuite ni d'énumération.

### 4.2 Suivi admin plateforme

Groupes existants `auth:super_admin_api`, préfixes `/platform` et `/admin`
(alias SPA) :

| Route | Permission | Rôle |
|---|---|---|
| `GET /platform/marketing/leads?type=equity_interest&status=&search=&page=` | `crm.view` | liste paginée ; `meta.type_status_counts` = compteurs par statut **scopés au type** (ajout #8440 — `status_counts` global inchangé, contrat #7595 préservé) |
| `PATCH /platform/marketing/leads/{id}` | `crm.view` | qualification : statut (`new/contacted/qualified/converted/rejected`) + note interne (nouveau endpoint #8440) |

Export CSV : non livré en v1 (le volume attendu est faible ; la liste +
filtres suffisent — reporté en v2 sans bloquer).

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
- **i18n** : contenu de page en ligne ×4 (pattern `/contact`), plus deux clés
  partagées — `consent.formNoticeEquity` (mention RGPD sous le formulaire)
  et `forms.validation.required` (messages zod) — propagées par
  `sync-web.js` ; checksums `versions.json` recalculés.

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

- **API Feature** `EquityInterestLeadApiTest` : capture du type
  `equity_interest` (201 + payload jsonb conservé), rejet d'un type inconnu
  (422), filtre admin par type + compteurs scopés (`type_status_counts`) vs
  contrat global intact, transitions de statut + note, 422/404, RBAC
  fail-closed (401 sans compte, 403 rôle support).
- **Vitrine Jest** `validation.test.ts` : `equityFormSchema` ×4 locales
  (tranche liste fermée, consentement obligatoire, messages localisés) ;
  `footer-links.test.ts` : `getFooterHref(3,3) === '/investir'`.
- **Parité i18n** : sync + validate.js verts (clés `consent.formNoticeEquity`
  et `forms.validation.required` ×4).

## 9. Tâches (mapping livraison)

| ID | Tâche | Statut |
|---|---|---|
| EQ-010 | Migration publique : type `equity_interest` dans la CHECK `marketing_leads` | ✅ |
| EQ-020 | Capture lead (constante modèle, validation endpoint, type TS vitrine) | ✅ |
| EQ-030 | Route vitrine `POST /api/forms/equity` (zod, antispam, sanitisation) | ✅ |
| EQ-040 | Suivi admin : compteurs scopés type + `PATCH` statut/note (routes `/platform` + alias `/admin`) | ✅ |
| EQ-050 | Page vitrine `/investir` + formulaire + SEO ×4 | ✅ |
| EQ-055 | Lien footer « Devenir actionnaire » ×4 + test | ✅ |
| EQ-060 | Écran admin `EquityInterestsView` + nav + i18n ×4 | ✅ |
| EQ-070 | Tests API Feature + vitrine Jest | ✅ |
| EQ-080 | Registre scénarios de test + CHANGELOG | ✅ |
| EQ-090 | Export CSV admin + purge RGPD self-service | v2 |

## 10. Hors scope (v2+)

- Data-room investisseurs, upload de documents, signature électronique.
- Paiement/émission de titres en ligne (cadre juridique préalable requis).
- Note interne admin + assignation + purge RGPD en self-service.
