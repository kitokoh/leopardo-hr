# Spécification — Site vitrine entreprise en 1-clic (BC-27 SHOWCASE)

- **Statut :** validée par le fondateur le 2026-09-06 (commentaire EPIC #6862, exception FREEZE-SCOPE-60J accordée) — document canonique pour l'implémentation
- **BC :** BC-27 SHOWCASE (nouveau — inscription au registre MAT-001 via #6864)
- **Issues liées :** EPIC #6862 — programme #6863 → #6876
- **Références :** registre MAT-001, `AGENTS.md`, `.specify/constitution.md`, patterns publics existants (`throttle:shop-public`, shop Travel/Restaurant)

---

## 1. Vision

Le responsable d'un tenant (PME, usine, commerce…) décide, **en 1 clic**, de créer un **site vitrine public** pour son entreprise : un site propre (sections, thème, logo, contact) publié sur une URL stable, administré depuis la plateforme, **sans compétence technique** et **sans jamais exposer de donnée interne**.

## 2. Cas d'usage v1

| # | Rôle | Action | Résultat |
|---|---|---|---|
| US1 | Tenant admin / responsable | « Créer ma vitrine » (1 clic) | Site draft avec slug réservé + thème par défaut |
| US2 | Tenant admin / responsable | Éditer le contenu par sections | Aperçu temps réel |
| US3 | Tenant admin / responsable | Publier | URL publique stable `/vitrine/{slug}` |
| US4 | Visiteur (public) | Consulter le site | Rendue serveur, SEO, sans JS requis |
| US5 | Visiteur | Envoyer un message via le formulaire de contact | Notification tenant (BC-13) + accusé |
| US6 | Tenant admin | Dépublier / republier / changer de thème | Mise à jour + invalidation cache |

## 3. Placement & frontières

- **Nouveau BC-27** structuré DDD (Domain/Models…/Providers) sous `api/app/Modules/Showcase` (nom de module à confirmer à l'inscription MAT-001, cohérent avec les conventions de nommage).
- **Public vs privé strictement séparés :**
  - **Privé** (admin, auth + Policies) : gestion du site, sections, médias, publication.
  - **Public** (`GET /public/vitrine/{slug}`…) : **routes isolées** (pattern existant : groupe `throttle:shop-public`, sans auth), **DTO public dédié** — jamais de modèles Eloquent bruts, aucune donnée RH/tenant interne (employés, company_id, membres).
- Dépendances : BC-02 TENANT (contexte), BC-01 PLATFORM (feature flags), BC-13 COMMS (contact), BC-20 DOCUMENTS (médias), BC-12 GROWTH (relations marketing, optionnel), BC-28 CATALOG (composant « produits », optionnel).

## 4. Décisions d'architecture actées

1. **Pas de CMS open-source embarqué** dans le monolithe (surface de sécurité/maintenance disproportionnée). v1 = **moteur de sections + thèmes maison** (rendu serveur).
2. **Spike éditeur** (#6869) : évaluer GrapeJS (MIT, headless) ou équivalent pour l'UX d'édition ; selon la décision, l'éditeur retenu **exporte vers notre contrat de sections** — jamais l'inverse.
3. **URL v1 : `/vitrine/{slug}`** (chemin). Sous-domaine / domaine personnalisé = phase 2 (décision DNS/infra) — le modèle de données prévoit `custom_domain` nullable.
4. Le rendu public est **côté serveur (Laravel)** → indexable sans prerender JS.

## 5. Modèle de domaine (v1)

```
company_showcases
  id, company_id (FK tenant, unique), slug (unique), status: draft|published,
  theme (string), settings JSON (variables : couleurs, logo_id…),
  custom_domain nullable, published_at, created_at/updated_at

sections (JSON validé par type, versionné) : page = liste ordonnée de sections
  types v1 : hero | features | produits (BC-28, optionnel) | gallery |
             testimonials | contact | footer
  chaque type : JSON Schema (contenu, médias, styles limités)
```

- Migrations **tenant**, idempotentes (conventions §2.6 : résolution `current_schemas(false)`, préfixes séquentiels).
- Modèles Eloquent dans `Domain/Models`, exceptions/contrats métier, Actions (cas d'usage) — structure DDD complète exigée (validator CI).

## 6. API

**Privée (auth + Policies, verbes #4930)**
- `POST /showcase` (création 1-clic, slug auto), `GET/PATCH /showcase` (settings/thème), `POST /showcase/publish`, `POST /showcase/unpublish`, `GET /showcase/preview` (aperçu à token privé).
- Sections : `GET/POST /showcase/sections`, `PATCH/DELETE /showcase/sections/{id}`, réordonnancement (bulk PATCH).
- Médias : upload logo/images (validation type/taille, stockage existant).
- RBAC : tenant admin / responsable (Policy dédiée), isolation tenant systématique.

**Publique (isolée, throttle dédié, cache Redis TTL)**
- `GET /public/vitrine/{slug}` → DTO public complet (thème + sections rendues) ; `GET /public/vitrine/{slug}/sitemap.xml` (SEO) ; formulaire contact `POST /public/vitrine/{slug}/contact` (rate limit, honeypot, consentement RGPD, notification BC-13).
- 404 propre (inconnu/brouillon) ; `noindex` sur non publié ; **0 champ interne** dans les réponses (test de non-fuite).

## 7. Thèmes

- Moteur : thème = jeu de templates de sections + variables (couleurs, logo, typos) ; 3 thèmes v1 (« Industrie », « Service », « Commerce ») cohérents avec la charte produit.
- Contenu séparé de la présentation (même contenu, rendu différent par thème) ; échappement HTML systématique (anti-XSS).

## 8. RGPD & confiance (public)

- Aucun cookie tiers par défaut ; bannière cookies légère si nécessaire ; bloc « mentions légales / politique de confidentialité » éditable par le tenant ; lien de contact.
- Aucune donnée personnelle RH dans le rendu public (revue DTO + tests).
- Formulaire contact : minimisation + consentement ; entrée registre RGPD à la livraison.
- Cache public : jamais de contenu d'un tenant sous le slug d'un autre (clé = slug tenant).

## 9. SEO & performances

- Meta/OG par page (title, description, image), canonical, `sitemap.xml` (publiés uniquement), robots.txt ; rendu SSR.
- Lighthouse CI sur une page de référence ; budgets de poids ; images redimensionnées + cache headers.

## 10. i18n

- Contenu de sections **multilingue** (fr/en/ar/tr) ; éditeur avec sélecteur de langue ; rendu selon `Accept-Language` ou sélecteur exposé.
- UI d'administration dans les catalogues existants ; garde PA2-I18N-007 (aucune chaîne dure accentuée).

## 11. Hors périmètre v1 & phases suivantes

- Hors v1 : blog complet, paiement, multi-domaines, analytics avancés, marketplace de thèmes, édition libre HTML/CSS.
- Phase 2 : sous-domaine/domaine personnalisé ; composant « produits » BC-28 (issue #6891) ; analytics visiteur sans cookie (BC-22 plus tard).

## 12. Séquencement & issues

| Étape | Contenu | Issues |
|---|---|---|
| Spec | Ce document | #6863 |
| Registre | MAT-001 BC-27 + CODEOWNERS + docs | #6864 |
| Socle | Domaine + feature flag + tables + Policies | #6865 |
| Contenu | Contrat de sections JSON Schema + API CRUD | #6866 |
| Public | API publique isolée + cache (P0) | #6867 |
| Rendu | 3 thèmes + variables | #6868 |
| Éditeur | Spike GrapeJS/équivalent → décision | #6869 |
| Éditeur | Implémentation admin-dashboard (selon #6869) | #6870 |
| Publication | Workflow draft/published + invalidation cache | #6871 |
| Médias | Upload logo/images | #6872 |
| SEO | Meta/OG/sitemap/robots | #6873 |
| i18n | Multilingue + gardes | #6874 |
| RGPD | Mentions/cookies/registre | #6875 |
| E2E | Parcours complet Playwright | #6876 |

Ordre : #6863 → #6864 → #6865 → #6866 → #6867, puis #6868/#6871 (thèmes/publication), #6870 (éditeur, après spike #6869), le reste en parallèle.

## 13. Risques

| Risque | Mitigation |
|---|---|
| Fuite de données internes en public | DTO public dédié + tests de non-fuite + revue RGPD |
| XSS via contenu édité | Échappement systématique, JSON Schema stricts, sanitisation médias |
| Abus (spam formulaire, scraping) | Rate limits dédiés, honeypot, cache, quotas |
| Dérive « CMS complet » | Périmètre v1 verrouillé (sections+thèmes) ; spike avant toute librairie |
| SEO cassé | SSR + sitemap + tests Lighthouse |

## 14. Critères de qualité

- Tests : migrations idempotentes ; RBAC + isolation tenant ; CRUD sections (schéma valide/invalide) ; routes publiques sans auth ni fuite ; workflow publication ; e2e complet (#6876).
- PHPStan strict L8 0 sur delta ; Pint ; validator Module Structure (5 couches) ; CHANGELOG ; i18n.
