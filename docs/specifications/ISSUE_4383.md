# ISSUE_4383 — PartnerApply : la validation contrôleur écrase les coordonnées de candidature

**Statut**: Fix proposé (PR `fix/4383-partner-apply-validation`) · **Priorité**: P1 · **Module**: Growth (API)

## Constat (audit 360° 2026-08-16)

`PartnerApplyTest::test_apply_persists_contact_fields` est **rouge sur main**
(HEAD 3903f209). Le fix #4186 (PR #4211, mergé) a ajouté les colonnes
`name/email/phone/website/commission_rate` sur `partners` + le fillable + le
passage des coordonnées dans `PartnerService::apply`, mais a oublié la
validation du contrôleur :

```php
// app/Modules/Growth/Interfaces/Api/V1/Controllers/PartnerDashboardController.php
$validated = $request->validate([
    'type'            => 'required|in:individual,agency,accountant',
    'payment_details' => 'nullable|string',
]);
```

`validate()` ne retourne que les clés déclarées → les coordonnées du payload
sont **silencieusement écrasées** avant `PartnerService::apply`
(`$details['name'] ?? null`). Résultat : ligne `partners` créée avec coordonnées
NULL. La CI backend sur main échoue (1 test rouge).

## Cause racine

Fix #4186 incomplet : le contrat HTTP (contrôleur) n'a pas été aligné sur le
nouveau contrat service. Les champs sont acceptés dans le payload mais jamais
propagés — le test d'acceptation du fix (#4186) ne passait pas en CI (famine
#3545) et le merge est passé malgré tout.

## Fix attendu

Étendre la validation de `apply()` pour accepter et propager :
- `name` : `nullable|string|max:150`
- `email` : `nullable|email|max:150`
- `phone` : `nullable|string|max:40`
- `website` : `nullable|url|max:255`
- `commission_rate` : `nullable|numeric|min:0|max:1`
- `type` et `payment_details` inchangés

## Critères d'acceptation

1. `php artisan test --env=testing --filter=PartnerApplyTest` → 2 passed.
2. `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` → 0 erreur.
3. POST `/api/v1/growth/partner/apply` avec coordonnées → 201 + coordonnées
   persistées (assertSame exact).
4. POST sans coordonnées → 201, `name`/`email` NULL (comportement conservé).
5. CI backend verte sur la PR (workflow `tests.yml`).

## Tests

`api/tests/Feature/Growth/PartnerApplyTest.php` (existant, re-devient vert).

## Artifacts spec-kit

Issue GitHub #4383 (ce document) · branche `fix/4383-partner-apply-validation`
