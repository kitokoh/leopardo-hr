## Triage JUnit — 105 échec(s) / 10382 test(s)

Source : 1543 suite(s) JUnit · 105 signature(s) distincte(s). Une signature = une cause probable : c'est l'unité de travail, pas le nombre de tests.

### Échecs par module

| Module | Échecs | Signatures |
|---|---:|---:|
| `Travel` | 33 | 33 |
| `Restaurant` | 25 | 25 |
| `Racine` | 20 | 20 |
| `Solutions` | 6 | 6 |
| `Security` | 5 | 5 |
| `Billing` | 4 | 4 |
| `Communication` | 2 | 2 |
| `EdgeSync` | 2 | 2 |
| `Auth` | 1 | 1 |
| `Demo` | 1 | 1 |
| `Edge` | 1 | 1 |
| `Leave` | 1 | 1 |
| `Marketing` | 1 | 1 |
| `Recruitment` | 1 | 1 |
| `TravelAgency` | 1 | 1 |
| `User` | 1 | 1 |

### Signatures classées (top 25 sur 105)

| # | Module | Signature | Localisation | Échecs |
|---:|---|---|---|---:|
| 1 | `Auth` | `ExpectationFailedException` — Un appareil enrôlé doit obtenir une durée nettement plus longue qu'une surface interactive. | `tests/Feature/Auth/SessionDurationTest.php:109` | 1 |
| 2 | `Billing` | `QueryException` — Illuminate\Database\QueryException: SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on table "partner_audit_logs" violates foreign key constraint "partner_audit_logs_admin_id_foreign" | `app/Modules/Billing/Infrastructure/Services/PartnerService.php:240` | 1 |
| 3 | `Billing` | `ErrorException` — ErrorException: Undefined array key 1 | `tests/Feature/Billing/GenerateMonthlyInvoicesTest.php:112` | 1 |
| 4 | `Billing` | `ExpectationFailedException` — Failed asserting that two strings are identical. | `tests/Feature/Billing/GenerateMonthlyInvoicesTest.php:158` | 1 |
| 5 | `Billing` | `ExpectationFailedException` — Failed asserting that null is not null. | `tests/Feature/Billing/GenerateMonthlyInvoicesTest.php:249` | 1 |
| 6 | `Communication` | `ExpectationFailedException` — Failed asserting that a row in the table [crm_contacts] matches the attributes { | `tests/Feature/Communication/CommunicationApplicationActionsTest.php:336` | 1 |
| 7 | `Communication` | `ExpectationFailedException` — Failed asserting that null is identical to 2. | `tests/Feature/Communication/CommunicationClassificationTest.php:262` | 1 |
| 8 | `Demo` | `ExpectationFailedException` — The expected [App\Mail\CommunicationMail] mailable was not sent. | `tests/Feature/Demo/ProvisionDemoTenantJobTest.php:52` | 1 |
| 9 | `Edge` | `ExpectationFailedException` — Failed asserting that 'The time clock terminal Nouveau Kiosque at Startup XYZ has not reported for —. Clock-ins recorded during this period will be synced automatically as soon as the connection is restored. If the issue persists, check the terminal network connection or write to us: support@leopard | `tests/Feature/Edge/EdgeSilentNodeDetectionTest.php:273` | 1 |
| 10 | `EdgeSync` | `ExpectationFailedException` — Failed asserting that two strings are equal. | `tests/Feature/EdgeSync/EdgeOfflineScenarioTest.php:405` | 1 |
| 11 | `EdgeSync` | `ExpectationFailedException` — Unable to find JSON: | `tests/Feature/EdgeSync/EdgeSyncTest.php:144` | 1 |
| 12 | `Leave` | `ExpectationFailedException` — Expected response status code [201] but received 422. | `tests/Feature/Leave/LeavePendingReservationTest.php:160` | 1 |
| 13 | `Marketing` | `ExpectationFailedException` — Expected response status code [201] but received 422. | `tests/Feature/Marketing/MarketingOsPlatformTest.php:663` | 1 |
| 14 | `Racine` | `QueryException` — Illuminate\Database\QueryException: SQLSTATE[21000]: Cardinality violation: 7 ERROR: more than one row returned by a subquery used as an expression (Connection: pgsql, Host: 127.0.0.1, Port: 5432, Database: leopardo_test_test_2, SQL: SELECT 1 AS present | `database/migrations/tenant/2026_09_14_000005_5821_align_edu_classes_fk_name.php:85` | 1 |
| 15 | `Racine` | `ExpectationFailedException` — Expected response status code [200] but received 503. | `tests/Feature/AIGatewayAndAnalyticsTest.php:204` | 1 |
| 16 | `Racine` | `ExpectationFailedException` — Failed asserting that two strings are identical. | `tests/Feature/ApiFrLiteralsCatalogedTest.php:67` | 1 |
| 17 | `Racine` | `AssertionFailedError` — Invalid JSON was returned from the route. | `tests/Feature/AuthGoogleSignInTest.php:161` | 1 |
| 18 | `Racine` | `ExpectationFailedException` — Expected response status code [201] but received 422. | `tests/Feature/AuthSelfRegistrationTest.php:109` | 1 |
| 19 | `Racine` | `ExpectationFailedException` — Failed asserting that two strings are identical. | `tests/Feature/AuthSelfRegistrationTest.php:91` | 1 |
| 20 | `Racine` | `ExpectationFailedException` — Failed asserting that a row in the table [audit_logs] matches the attributes { | `tests/Feature/CrmFeatureGateTest.php:192` | 1 |
| 21 | `Racine` | `ExpectationFailedException` — crm_accounts doit exister après migrate | `tests/Feature/CrmMigrationsTest.php:240` | 1 |
| 22 | `Racine` | `ExpectationFailedException` — Expected response status code [200] but received 500. | `tests/Feature/HealthEndpointTest.php:96` | 1 |
| 23 | `Racine` | `ExpectationFailedException` — Failed asserting that null is identical to false. | `tests/Feature/HealthReadyCriticalDepsTest.php:102` | 1 |
| 24 | `Racine` | `ExpectationFailedException` — Failed asserting that null is identical to true. | `tests/Feature/PaymentWebhookControllerTest.php:349` | 1 |
| 25 | `Racine` | `ExpectationFailedException` — The expected [App\Mail\InvoicePaymentReceiptMail] mailable was queued 2 times instead of 1 time. | `tests/Feature/PaymentWebhookControllerTest.php:379` | 1 |

