# Tasks: RBAC webhooks

- [x] [T1] [P] [US1] `WebhookEndpointPolicy` (manage/view/create/update/delete/test) + enregistrement `Gate::policy`.
- [x] [T2] [P] [US1] `WebhookController` : `$this->authorize()` sur les 6 méthodes, suppression des gardes inline.
- [x] [T3] [P] [US1] `WebhookRbacTest` : rh → 403 store/update/destroy/test ; principal → 201 store.
- [x] [T4] [P] [US1] Régression : `WebhookSsrfGuardTest` 8/8 vert ; échecs EmailBounce/PaymentWebhook prouvés pré-existants sur main (env secrets).
