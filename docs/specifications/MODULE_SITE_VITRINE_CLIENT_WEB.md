# Spécification — Module horizontal « Site vitrine » dans l'espace client web (BC-27 SHOWCASE)

- **Statut :** implémenté (branche `feat/6862-showcase-client-web`), à valider par le propriétaire.
- **BC :** BC-27 SHOWCASE (déjà inscrit au registre).
- **Issues liées :** EPIC #6862 (socle BC-27 : #6864 → #6876, mergés). Ce lot **étend** le module livré, il ne le remplace pas.
- **Références :** `docs/specifications/SOLUTION_SITE_VITRINE.md` (spec canonique BC-27), `AGENTS.md` (règle « nouveau module »), `front/web/src/lib/client-features.ts`.

---

## 1. Constat (audit 2026-09-13)

Le module vitrine BC-27 est **livré** côté API :

| Brique | État |
| --- | --- |
| Domaine + tables tenant (`company_showcases`, `company_showcase_sections`, `showcase_media`) | Livré (#6865/#6866/#6872) |
| API privée `/api/v1/showcase/*` (création 1-clic, sections, médias, publication, aperçu) | Livré (#6866/#6871) |
| API publique isolée `/api/v1/public/vitrine/{slug}` (DTO public, sitemap, robots, médias) | Livré (#6867/#6873) |
| Thèmes v1 (`industrie`/`service`/`commerce`), i18n, RGPD | Livré (#6868/#6874/#6875) |
| Éditeur visuel | Livré dans **`front/admin-dashboard`** (Vue, app admin plateforme) |
| **Surface client web (`front/web`)** | **Absente** |

Deux trous empêchaient un client (manager principal/rh d'un tenant) d'utiliser le module :

1. **Aucune entrée d'espace client** : `front/web` n'avait ni route `/showcase`, ni entrée de navigation, ni service API — la vitrine n'était atteignable que par l'admin plateforme.
2. **Flag non enregistré** : `Company::hasFeature('company_showcase')` fonctionnait si la clé était posée à la main, mais `company_showcase` était **absent** de `config/feature-flags.php` **et** de `Company::KNOWN_MODULES`. Conséquence : l'admin plateforme ne pouvait pas l'activer (la reconstruction des features depuis `KNOWN_MODULES` l'ignorait) et `/auth/me` ne remontait jamais la clé — exactement la classe de défaut corrigée par #7235 pour `accounting`/`crm`/`travelagency`.

## 2. Objectif

Faire du site vitrine un **module horizontal** de l'espace client : tout responsable de tenant peut **créer, éditer et publier le site public de son entreprise en 1 clic**, sans compétence technique, et le consulter sur une URL stable — sans qu'aucune donnée interne ne fuite.

## 3. Périmètre

### 3.1 Enregistrement du module (backend)

- `api/config/feature-flags.php` : déclaration `company_showcase` (scope `module`, défaut `false`, `killable`, depuis `4.32.0`).
- `api/app/Core/Tenant/Domain/Models/Company.php` :
  - `KNOWN_MODULES` reçoit `company_showcase` (activation par l'admin plateforme + exposition `/auth/me`) ;
  - `HORIZONTAL_TOOLS` reçoit `showcase` (outil transverse sélectionnable à l'inscription).
- `api/app/Modules/Billing/Application/Actions/ProvisionGuidedTrial.php` : `mirroredFeatures()` mappe la clé de sélection `showcase` vers le flag `company_showcase` (table de correspondance littérale — **pas d'import cross-BC** Billing → Showcase).

### 3.2 Espace client (front/web)

- `src/lib/client-features.ts` : clé `showcase`, entrée `CLIENT_MODULES` (`href: /showcase`, `featureKeys: ['showcase','company_showcase']`), mapping `ROUTE_TO_MODULE`, et règle RBAC manager `principal|rh` (miroir du middleware API `api.manager:principal,rh`).
- `src/lib/protected-prefixes.ts` + `src/middleware.ts` : `/showcase` rejoint la zone session-protégée (gate middleware + `robots.txt`).
- `src/lib/showcase.ts` : service typé des endpoints privés + amorçage `defaultShowcaseSections()` (hero/contact/footer conformes aux JSON Schemas v1).
- `src/app/(dashboard)/showcase/page.tsx` : création 1-clic, thème, édition des sections, publication, aperçu brouillon, URL publique.

### 3.3 Rendu public (front/web)

- `src/lib/showcase-public-api.ts` : accès SSR au DTO public (`GET /public/vitrine/{slug}`, `?lang=`, `?token=`).
- `src/app/vitrine/[slug]/page.tsx` : page SSR indexable, thème appliqué en variables CSS, sections v1 rendues, noindex si aperçu.

### 3.4 i18n

- 50 clés `showcase.*` ×4 langues dans `shared/i18n/locales/*.json` (source de vérité), régénérées par les 3 syncs.

## 4. Sécurité & isolation tenant

- **Gestion** : routes `/showcase/*` derrière `auth:sanctum` + `tenant` + gate `module.showcase` (fail-closed) + RBAC `principal,rh`. La page `/showcase` est dans `PROTECTED_PREFIXES` (gate cosmétique middleware + disallow robots).
- **Public** : la page `/vitrine/{slug}` ne consomme que le DTO public (jamais `id`, `company_id`, `showcase_id`, médias internes) ; un brouillon répond 404 sans jeton d'aperçu. Le jeton d'aperçu n'est jamais exposé publiquement.
- **Isolation** : la vitrine est tenant-scoped (`company_id` unique) ; le slug public est le slug du tenant (unique global déjà garanti par `companies.slug`).

## 5. Hors périmètre

- Édition avancée (galerie, produits BC-28) : reste dans l'éditeur admin-dashboard ; la page client renvoie une note explicite.
- Domaine/sous-domaine personnalisé (phase 2, champ `custom_domain` déjà réservé).
- Activation self-service d'un module verrouillé : le panneau « Modules & plan » continue de demander l'activation à l'admin plateforme (comportement existant).

## 6. Validation

- `front/web` : `tsc --noEmit` 0 erreur, `eslint --max-warnings 0` 0, tests Jest `client-features-showcase` + `showcase-defaults` verts.
- PHP : `php -l` OK sur les 3 fichiers modifiés (pas de runtime PHP complet localement — les tests API de BC-27 restent la référence CI).
- i18n : `node shared/i18n/validators/validate.js` → `I18N_VALIDATION_OK (4 locales)` ; parité des clés garantie par les 3 syncs.
