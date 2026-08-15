# ISSUE #3727 — BelongsToCompany fail-closed sur la surface API tenant

> Spec-kit — audit 360° 2026-08-15, constat A-04. Branche : `fix/3727-belongstocompany-fail-closed`.

## Problème

`BelongsToCompany::bootBelongsToCompany()` — sans compagnie courante liée au
conteneur, le scope global **sautait silencieusement** (`return;`) → requêtes
**toutes compagnies**. Cas d'exposition : `TenantMiddleware` laisse passer un
employé `ordinary` sans compagnie (`return $next($request)`) ; tout endpoint
tenant qu'il atteint ensuite et qui interroge un modèle scopé (74 modèles,
ex. `Notification::query()` dans `NotificationController::index`,
`WebhookController::index`) lisait/écrivait cross-tenant.

## Décision (blast radius maîtrisé)

Trois surfaces de requêtes existent :

| Surface | Binding `current_company` | Comportement cible |
|---------|---------------------------|--------------------|
| API tenant (`tenant` middleware) avec compagnie | lié | scope appliqué (inchangé) |
| API tenant, employé `ordinary` **sans compagnie** | absent | **fail-closed 403 `TENANT_CONTEXT_MISSING`** (nouveau) |
| Console / jobs / seeders / routes publiques / super-admin plateforme | absent | non scopé permis (inchangé), `withoutGlobalScopes()` explicite recommandé |

La discrimination se fait par un marqueur de conteneur `tenant_scope_required`
posé par `TenantMiddleware` **uniquement** sur le chemin ordinary-sans-compagnie
(et relâché en `finally`). Le super-admin plateforme (`auth:super_admin_api`,
cross-tenant par conception) et les routes publiques (trial signup, webhooks
Stripe/Chargily, careers) ne passent pas par ce middleware → aucun impact.

Un fail-closed global (HTTP entier) casserait la surface super-admin et les
webhooks publics : explicitement rejeté.

## Changements

1. `app/Core/Tenant/Domain/Exceptions/TenantContextMissingException.php`
   (nouveau) — `DomainException` 403 `TENANT_CONTEXT_MISSING`, rendu JSON
   automatique via le renderer `DomainException` existant.
2. `app/Shared/Traits/BelongsToCompany.php` — scope global + hook `creating` :
   marqueur présent sans compagnie → throw. (`App\Traits\BelongsToCompany`
   déprécié délègue au trait canonique → 74 modèles couverts.)
3. `app/Http/Middleware/TenantMiddleware.php` — marqueur posé/relâché sur le
   pass-through `ordinary` sans compagnie.
4. `tests/Feature/Security/TenantScopeFailClosedTest.php` (nouveau) :
   - requête scopée sans compagnie → 403 (régression) ;
   - `/auth/me` self-service sans modèle scopé → 200 (pas de sur-blocage) ;
   - requête scopée avec compagnie → 200 (pas de faux positif) ;
   - contexte console sans binding → création/lecture permises (inchangé).

## Critères d'acceptation

- [x] Fail-closed 403 JSON sur la surface tenant sans compagnie.
- [x] Zéro changement console/jobs/super-admin/publiques.
- [x] Tests de régression ajoutés ; PHPStan strict + Backend Coverage verts (CI).
- [ ] Suivi : passer `withoutGlobalScopes()` explicite sur les usages console
      légitimes au fil de l'eau (revue incrémentale, hors périmètre).
