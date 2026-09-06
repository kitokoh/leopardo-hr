# Spécification — Catalogue produits B2B (BC-28 CATALOG)

- **Statut :** validée par le fondateur le 2026-09-06 (commentaire EPIC #6877, exception FREEZE-SCOPE-60J accordée) — document canonique pour l'implémentation
- **BC :** BC-28 CATALOG (nouveau — inscription au registre MAT-001 via #6879)
- **Issues liées :** EPIC #6877 — programme #6878 → #6891
- **Références :** registre MAT-001, `AGENTS.md`, `.specify/constitution.md`, patterns publics existants (`throttle:shop-public`), CRM client BC-11 (leads)

---

## 1. Vision

Un tenant **producteur / fournisseur** (responsable d'usine, atelier, grossiste…) expose son **catalogue produits** aux acheteurs professionnels (B2B) : fiches produits publiques, galerie, caractéristiques, prix indicatif — et les acheteurs peuvent **demander un devis / être contactés**. Le tout dans le même produit, sans portail séparé, sans exposer de donnée interne.

## 2. Cas d'usage v1

| # | Rôle | Action | Résultat |
|---|---|---|---|
| US1 | Tenant admin / responsable usine | Créer catégories + produits (fiche, prix indicatif, photos) | Catalogue draft |
| US2 | Tenant admin | Publier le catalogue / un produit | Visible publiquement sur `/public/catalog/{slug}` |
| US3 | Acheteur B2B (public, sans compte) | Consulter catalogue + fiches produits | Liste/filtres par catégorie, fiche détaillée |
| US4 | Acheteur B2B | « Demander un devis » (quantité, société, email, message) | Lead créé côté tenant (BC-11) + accusé + consentement |
| US5 | Tenant admin | Suivre les demandes (statuts, notes) + export CSV | Back-office demandes |
| US6 | Tenant admin | Dépublier / modifier un produit | Mise à jour + invalidation cache |

## 3. Placement & frontières

- **Nouveau BC-28** structuré DDD (Domain/Models…/Providers) sous `api/app/Modules/Catalog` (nom de module à confirmer à l'inscription MAT-001).
- **Public vs privé strictement séparés** (mêmes règles que BC-27) : DTO public dédié, routes isolées (`throttle:shop-public`), **0 donnée interne** (stocks réels, marges, fournisseurs, employés, company_id).
- **Leads → BC-11 CRM** : la soumission d'une demande crée un lead chez le tenant via le mécanisme d'intégration propre (événement/contrat — pas d'import cross-BC direct). Le back-office « demandes » est une vue BC-28 sur ces leads (ou délègue à l'UI CRM existante si plus simple — décision d'implémentation documentée).
- Dépendances : BC-02 TENANT, BC-01 PLATFORM (feature flags), BC-11 CRM (leads), BC-13 COMMS (notifications), BC-20 DOCUMENTS (médias), BC-08 ACCOUNTING / BC-21 BILLING (facturation **phase 2**), BC-26 DELIVERY (expédition **phase 2**), BC-27 SHOWCASE (composant « produits » optionnel).

## 4. Décisions d'architecture actées

1. **v1 = catalogue + génération de leads** ; **pas de paiement en ligne** (phase 2 : facturation BC-08/BC-21, expédition BC-26).
2. **Prix indicatifs en minor units + devise ISO** (XOF, XAF, DZD, MAD, EUR, USD…) — jamais de flottants ; pas de taux de change automatique v1.
3. **Aucun compte acheteur v1** (formulaire public + consentement) — compte/marketplace = phase ultérieure.
4. URL v1 : `/public/catalog/{companySlug}` + `/public/catalog/{companySlug}/products/{productSlug}` ; réutilisable **dans une vitrine BC-27** via un composant « produits » (#6891).

## 5. Modèle de domaine (v1)

```
catalog_categories
  id, company_id, name, slug, parent_id nullable, position, created/updated

catalog_products
  id, company_id, category_id FK nullable, name, slug, description,
  price_minor int (indicatif) + currency char(3) + unit (pièce/kg/tonne/m/h/lot…),
  status: draft|published, meta JSON (attributs clé/valeur, specs),
  main_image_id + gallery (médias), published_at, created/updated
```

- Migrations **tenant**, idempotentes (conventions §2.6). Modèles dans `Domain/Models`, structure DDD complète (validator CI).

## 6. API

**Privée (auth + Policies — gestion réservée au tenant admin / responsable)**
- CRUD catégories et produits (verbes #4930) ; recherche/filtres tenant ; slug auto unique ; validation (nom requis, `price_minor ≥ 0`, devise ISO).
- Publication/dépublication (Actions dédiées) ; médias (upload produits) ; statuts.

**Publique (isolée, throttle dédié, cache)**
- `GET /public/catalog/{companySlug}` → catégories + produits publiés (DTO public : nom, ref, prix indicatif, unité, photos — **pas** de stocks/marges/champs internes).
- `GET /public/catalog/{companySlug}/products/{productSlug}` → fiche (galerie, specs, CTA devis).
- `POST /public/catalog/{companySlug}/inquiries` → demande de devis (validation, honeypot, rate limit, **consentement RGPD**) → lead BC-11 + notif tenant + accusé acheteur.
- Cache Redis TTL ; invalidation à la publication ; 404 propre ; `noindex` hors publié.

## 7. Flux de demande de devis (point critique)

1. L'acheteur remplit le formulaire (produit, quantité, société, email, message, consentement).
2. Anti-spam : honeypot + rate limit ; validation stricte (email).
3. Création **lead BC-11** rattaché au tenant (source `b2b_catalog`), avec les données acheteur **minimisées**.
4. Notification tenant (BC-13) + accusé de réception à l'acheteur (sans engagement).
5. Back-office tenant : liste, statuts (`nouveau → contacté → devis envoyé → clos/perdu`), notes internes, export CSV.
6. Conservation/droit d'effacement conformes registre RGPD (§9).

## 8. Devises & unités

- Stockage `price_minor` (int) + `currency` (ISO 4217) ; affichage formaté (intl) ; liste de devises restreinte aux zones produits (XOF/XAF/DZD/MAD/EUR/USD…) configurable.
- Unité : pièce, kg, tonne, m, m², m³, heure, jour, lot… (libre borné).
- **Aucun paiement, aucun taux de change automatique en v1.**

## 9. RGPD (acheteurs & fournisseurs)

- Données acheteur (devis) : minimisation, finalité « réponse à une demande de devis », durée de conservation bornée, droit d'effacement (canal tenant), mentions sur le formulaire.
- Aucune donnée fournisseur interne en public (stocks, marges, effectifs) — revue DTO + tests de non-fuite.
- Entrée registre RGPD (`docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` ou registre dédié) à la livraison.

## 10. SEO & i18n

- Fiches indexables (meta dynamiques, OG image produit), `sitemap.xml` produits publiés, canonical, SSR.
- v1 : libellés FR (+ EN de base) ; les contenus produits peuvent être multilingues (structure par locale) — catalogue complet multilingue = phase 2. Garde PA2-I18N-007.

## 11. Hors périmètre v1 & phases suivantes

- Hors v1 : paiement en ligne, compte acheteur, devis PDF formel, marketplace multi-vendeurs, taux de change, catalogue multilingue complet, place de marché.
- Phase 2 : facturation (BC-08/BC-21), expédition (BC-26), devis PDF, intégration vitrine BC-27 (#6891), catalogue multilingue.

## 12. Séquencement & issues

| Étape | Contenu | Issues |
|---|---|---|
| Spec | Ce document | #6878 |
| Registre | MAT-001 BC-28 + CODEOWNERS + docs | #6879 |
| Socle | Domaine + feature flag + tables + Policies | #6880 |
| API privée | CRUD catégories/produits | #6881 |
| Public | Catalogue public isolé + cache (P0) | #6882 |
| Fiche | Page produit publique (galerie, specs, CTA) | #6883 |
| Leads | Formulaire devis → CRM BC-11 (P0) | #6884 |
| Back-office | Demandes tenant + export CSV | #6885 |
| Devises | Minor units + unités (pas de paiement) | #6886 |
| Médias | Photos produits | #6887 |
| SEO | Meta/sitemap/SSR | #6888 |
| RGPD | Données acheteurs/fournisseurs | #6889 |
| E2E | Parcours complet Playwright | #6890 |
| Vitrine | Composant « produits » BC-27 (optionnel) | #6891 |

Ordre : #6878 → #6879 → #6880 → #6881 → #6882 → #6884 (leads, critique) → reste en parallèle ; #6891 dépend de BC-27.

## 13. Risques

| Risque | Mitigation |
|---|---|
| Fuite données internes (stocks/marges) en public | DTO public dédié + tests de non-fuite + revue RGPD |
| Spam / abus formulaire | Honeypot + rate limits + validation stricte |
| Erreurs monétaires | Minor units exclusivement, tests de formatage |
| Leads perdus / non traités | Statuts + notification tenant + back-office dédié |
| Chevauchement BC-27/BC-28 | Frontière claire : BC-27 = vitrine (pages/contenu), BC-28 = produits/devis ; liaison par composant optionnel |

## 14. Critères de qualité

- Tests : migrations idempotentes ; RBAC (qui gère le catalogue) + isolation tenant ; CRUD produits ; routes publiques sans fuite ; flux devis → lead complet ; e2e (#6890).
- PHPStan strict L8 0 sur delta ; Pint ; validator Module Structure ; CHANGELOG ; i18n.
