# Mobile notifications production proof - 2026-06-01

## Scope

Lot 67.5 validates the notification chain that matters for launch:

- employee app: FCM token registration after auth, token removal before logout, notification list, mark-as-read, delete and refresh.
- manager app: same lifecycle, plus manager test push endpoint `POST /api/v1/push-notifications/send`.
- backend: device token register/list/delete, app notification creation through `CommunicationService`, FCM HTTP v1 send through `PushNotificationService`, failed token invalidation.
- platform admin: Firebase initializes as optional runtime infrastructure, but platform push delivery remains audit-only until a dedicated public `super_admin_device_tokens` contract exists.

## Contracts checked

- `POST /api/v1/device-tokens`
- `GET /api/v1/device-tokens`
- `DELETE /api/v1/device-tokens`
- `GET /api/v1/notifications`
- `PUT /api/v1/notifications/read-all`
- `PUT /api/v1/notifications/{notification}/read`
- `DELETE /api/v1/notifications/{notification}`
- `POST /api/v1/push-notifications/send`
- `GET/PATCH /api/v1/notification-preferences`

## Automated guards

- `dev-hub/tools/validate-mobile-notification-production-proof.ps1`
- `api/tests/Feature/DeviceTokenControllerTest.php`
- `api/tests/Feature/NotificationControllerTest.php`
- `api/tests/Feature/CommunicationServiceTest.php`
- `api/tests/Feature/FrontendApiContractTest.php`
- `api/tests/Feature/OpenApiDocsTest.php`

## Operational requirements

GitHub/Render secrets:

- `FIREBASE_SERVICE_ACCOUNT_JSON` or a readable Firebase service account path in `FIREBASE_CREDENTIALS`.
- Firebase project id exposed through `FIREBASE_PROJECT_ID`.
- Queue worker must listen to `documents,pdf,payroll,notifications,webhooks,default`.
- Redis must stay configured for queues and health checks.

## Manual proof scenario

1. Login employee app with a demo employee.
2. Confirm the app calls `POST /device-tokens` after auth.
3. Login manager app with a principal/RH manager.
4. Trigger `POST /push-notifications/send` for the employee.
5. Confirm employee sees the app notification in `/notifications`.
6. Mark it as read, delete it, refresh the list.
7. Logout employee and confirm `DELETE /device-tokens` is called.

## Known boundary

The platform admin app is intentionally not wired to tenant `/device-tokens`, because that endpoint is scoped to `Employee` and tenant context. Super-admin push requires a separate public table and routes, for example `platform_device_tokens`, before true FCM delivery can be claimed. Until then, platform admin notification proof is limited to authenticated platform flows and audit/reporting.
