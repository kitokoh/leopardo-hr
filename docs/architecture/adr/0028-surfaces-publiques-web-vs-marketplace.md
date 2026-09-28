# ADR 0028 — Surfaces publiques : front/web (mono-tenant) vs front/marketplace (agrégation cross-tenant)

## Statut

Proposée — **validation owner requise**

**Date** : 2026-09-28
**Décideurs** : owner (validation finale) — proposition rédigée par l'agent Zentor
**Liens** : issue #8226 (BOS-051, programme Business OS Block 3) · analyse : `docs/architecture/business-os/08_ARCHITECTURE_CHALLENGE.md` §10 · borne parente : ADR-0004 (open core / marketplace) · plumbing mutualisé : BOS-050 (#8208)

---

## Contexte

Trois surfaces publiques de commerce coexistent sans règle écrite, et toute nouvelle verticale publique hérite de l'ambiguïté.

### État mesuré (preuves code)

**`front/web`** porte aujourd'hui **trois** implémentations du parcours commande publique, plus les surfaces SEO et **l'administration vendeur** :

| Route | Type | Rôle |
|---|---|---|
| `shop/page.tsx` | Mono-tenant à jeton | Commande restaurant complète (menu → panier → commande idempotente → paiement → suivi), tenant résolu par `?token=` (`X-Restaurant-Shop-Token`) |
| `restaurants/[slug]/page.tsx` | Mono-tenant SEO (SSR, JSON-LD) | Fiche restaurant + parcours transactionnel (`RestaurantOrderPanel`) |
| `restaurants/page.tsx` | **Agrégation** SEO (SSR) | Annuaire public cross-tenant (recherche q/ville/cuisine, géoloc) |
| `stay/[slug]/page.tsx` | Mono-tenant SEO (SSR, JSON-LD) | Fiche hébergement + réservation idempotente (`StayBookingPanel`) |
| `vitrine/[slug]/page.tsx` | Mono-tenant SEO (SSR) | Site vitrine tenant (BC-27) |
| `order/page.tsx` | Mono-tenant | Suivi de commande restaurant (référence + jeton) |
| `(dashboard)/commerce/boutique` | Console | **Admin vendeur marketplace** (opt-in, fiche publique, produits, commandes — #7810) |

**`front/marketplace`** est une **app Next.js indépendante** (projet Vercel dédié, `vercel.json` propre, déploiement limité à `main`/`staging`) portant un 4ᵉ parcours, **multi-vendeurs** : accueil, `/produits`, `/produits/[id]`, `/boutiques`, `/boutiques/[slug]`, `/panier` (localStorage, groupé par boutique), `/commande` (checkout invité, **1 commande par boutique**), `/confirmation`, `/suivi` — soit **11 pages réelles** (le README n'en documente que 8 : `/compte`, `/compte/commandes`, `/compte/favoris`, `/paiement/retour` ne sont pas listées — écart à corriger). Elle consomme `public/market/*` (retail cross-tenant) avec un guard acheteur dédié (`market.buyer`) et un compte acheteur **cross-tenant** — capacité que `front/web` ne possède pas.

**Aucun lien applicatif entre les deux apps** : aucune URL de la marketplace n'est référencée dans `front/web` ; seules la donnée et le pattern API (`throttle:shop-public`, DTO publics fail-closed, idempotence invitée) les relient.

**Côté API, il existe déjà deux « marketplaces » publiques** : retail `public/market/*` (consommée par `front/marketplace`) et travel `public/travel/marketplace/*` (consommée par `front/travel-web`, épic #7736) — plus le webhook entrant des apps de livraison (`restaurant/marketplace/{provider}/webhooks`, RESTO-806, autre sens du mot).

### Borne existante (ADR-0004)

ADR-0004 (Acceptée) classe la « vitrine commerciale » en enterprise-only et définit la marketplace comme un modèle d'**extensions par contrats** (plugins, scopes, webhooks signés). Elle **ne tranche pas** le partage entre les deux apps publiques du dépôt — c'est l'objet de la présente décision, qui s'y subordonne.

---

## Décision

**Règle par type de surface : `front/web` = propriété publique d'UN tenant ; `front/marketplace` = agrégation cross-tenant. Une nouvelle verticale publique = au plus une surface de chaque type, sur le plumbing mutualisé.**

1. **`front/web` — surfaces mono-tenant.** Tout ce qui est la propriété publique d'un seul tenant : vitrine (`vitrine/[slug]`), fiches établissement SEO (`restaurants/[slug]`, `stay/[slug]`…), commande/réservation/suivi mono-tenant (à jeton ou par slug : `shop`, `order`), portail emplois (`[companySlug]/careers`), pages légales. Y vivent aussi **toutes les consoles d'administration**, dont l'admin vendeur qui alimente la marketplace (donnée vendue, pas surface acheteuse).
2. **`front/marketplace` — surfaces d'agrégation cross-tenant.** Annuaires multi-vendeurs, catalogue cross-tenant, compte acheteur cross-tenant (register/login/favoris/avis), panier et checkout **multi-boutiques**, suivi acheteur agrégé. Critère discriminant : la surface assemble-t-elle l'offre de **plusieurs** tenants dans un même parcours ?
3. **Règle pour les futures verticales.** Une nouvelle verticale publique = **au plus** : (a) une surface mono-tenant dans `front/web` (route SSR indexable) ; (b) une intégration d'agrégation dans `front/marketplace`. **Aucune nouvelle app publique** sans ADR dédiée. Toute nouvelle surface publique s'appuie obligatoirement sur le plumbing mutualisé de BOS-050 (`PublicTenantResolver`, `IdempotentGuestWrite`, `TrackingSecretService`, throttle dédoublonné) — interdiction d'une énième réimplémentation du parcours panier → commande → suivi.
4. **Statu quo sur l'existant — aucun mouvement immédiat.** L'annuaire `restaurants/page.tsx` (surface d'agrégation vivant dans `front/web`) **reste où il est** : son déplacement vers la marketplace est une décision produit distincte (SEO, liens publics en circulation), à traiter par issue fille si le owner le décide — il n'est **pas** décidé ici. Travel conserve `front/travel-web` (app dédiée déjà tranchée, épic #7736) comme exception documentée à la règle 2. La convergence de l'existant est **opportuniste** : toute retouche substantielle d'une surface la fait basculer vers sa case canonique.
5. **Lien entre les apps.** Tout lien de `front/web` vers la marketplace (et réciproquement) est introduit explicitement, configuré par variable d'environnement, et documenté — l'absence actuelle de lien est un constat, pas un interdit.

### Issues filles

La présente décision n'ordonne **aucun mouvement de surface** : aucune issue fille n'est créée (critère 2 de #8226 — déclenché uniquement si l'ADR décidait un mouvement). Deux écarts documentaires constatés en revue sont signalés sur l'issue pour traitement au fil de l'eau : README marketplace (8 pages documentées vs 11 réelles) et absence de liens croisés entre les apps.

---

## Conséquences

**Positives**
- Règle écrite et opposable pour toute nouvelle verticale publique : fini l'atterrissage « au plus proche ».
- Le critère mono-tenant / cross-tenant aligne les apps sur la donnée qu'elles exposent (et sur les guards : jeton boutique vs compte acheteur cross-tenant).
- Compatibilité totale avec BOS-050 : la règle de surface + le plumbing mutualisé forment le contrat complet du commerce public.
- Aucun risque SEO/produit immédiat : l'existant ne bouge pas.

**Limites assumées**
- L'annuaire restaurants reste provisoirement « mal logé » (agrégation dans l'app mono-tenant) — assumé, avec une porte de sortie documentée.
- L'exception `front/travel-web` est actée : elle devient le précédent à citer pour toute demande d'app publique dédiée.
- Le doublon de parcours commande (`shop` à jeton vs `restaurants/[slug]`) n'est pas résolu ici : c'est un chantier d'implémentation ultérieur, cadré par la règle 3.

## Règles opérationnelles

1. Avant de créer une route publique, appliquer le critère discriminant (plusieurs tenants dans le parcours ? → marketplace ; un seul → front/web) et le citer dans la PR.
2. Toute nouvelle surface publique réutilise le plumbing BOS-050 ; une nouvelle copie du parcours panier → commande → suivi est un motif de refus en revue.
3. Pas de 3ᵉ app publique sans ADR (l'exception travel est documentée, pas généralisable).
4. ADR-0004 reste la borne supérieure : vitrine commerciale et consoles = enterprise-only ; toute exposition externe passe par les contrats publics.
5. Cette ADR est documentaire : elle n'autorise aucune implémentation avant validation owner.
