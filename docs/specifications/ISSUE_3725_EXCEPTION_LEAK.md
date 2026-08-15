# ISSUE 3725 — Ne plus exposer les messages d'exception bruts dans les réponses API

> Spec Kit mini-spec — issue #3725 (audit 360° 2026-08-15, constat A-02).

## Contexte

Plusieurs contrôleurs renvoient `$e->getMessage()` tel quel dans les bodies
d'erreur HTTP : fuite de détails SQL/PDO/Redis/internes vers les tenants.

Sites concernés (constat A-02) :

| Fichier | Ligne | Type d'exception | Risque |
|---|---|---|---|
| `HR/.../EmployeeImportController.php` | 147 | `Throwable` | SQL/PDO brut (import CSV) |
| `Payroll/.../BulkPaymentController.php` | 122 | `Throwable` | Internes Redis |
| `Billing/.../WebhookController.php` | 226 | `Throwable` | Détail d'échec de livraison |
| `Core/Auth/.../AuthController.php` | 192 | `Exception` | Internes Socialite/OAuth |
| `Core/Auth/.../AuthController.php` | 246 | `Exception` | Internes Socialite/OAuth |

Sites **conservés tels quels** (messages métier localisés, pas une fuite) :

- `TaxSlabController::submit` / `SocialContributionController::submit` /
  `RateValidationAdminController::approve|reject` : `DomainException` portant
  un message métier `__('payroll.rate_*')` — comportement UX voulu (vérifié
  dans `TaxRateValidationService`).

## Décision

- Remplacer `$e->getMessage()` des 5 sites ci-dessus par un code d'erreur
  stable + message générique (FR), et logger les détails côté serveur via
  `Log::error('<domaine>.<action>.failed', ['context' => ..., 'error' => $e->getMessage()])`.
- Le pattern de référence : `PartnerDashboardController::requestPayout`
  (code `PAYOUT_REQUEST_FAILED` + message générique + log structuré).

## Contrat de réponse

| Site | Code erreur | HTTP |
|---|---|---|
| Import employés | `EMPLOYEE_IMPORT_FAILED` | 500 |
| Statut bulk payment | `BULK_PAYMENT_STATUS_UNAVAILABLE` | 503 |
| Test webhook | `WEBHOOK_DELIVERY_FAILED` | 422 |
| Callback Google (code) | `GOOGLE_AUTH_FAILED` | 422 |
| Login Google (token) | `GOOGLE_TOKEN_INVALID` | 422 |

## Validation

- `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` — 0 erreur.
- `vendor/bin/pint --test`.
- Recherche de régression : `rg 'getMessage\(\)' api/app/Modules api/app/Core` — plus aucune occurrence renvoyée dans un body 4xx/5xx hors DomainException métier.
