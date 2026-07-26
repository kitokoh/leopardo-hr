# Audit statut reel PA2-JOB-001 a 006 — 2026-07-25

Statut: complete
Auteur: audit interne KiloClaw (agent)
Perimetre: tickets `PA2-JOB-001` a `PA2-JOB-006` de `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` / GitHub Issues (#995-#1000), verifies contre le code reel (`api/app/Jobs`, `api/app/Modules/Notification`, `api/app/Modules/Platform`, `dev-hub/k6`, `dev-hub/load/k6`) et `CHANGELOG.md`.

Ce document suit la meme methode que `14_AUDIT_STATUT_PA2_MOB_006_A_009.md` : lecture directe du code source pour trancher chaque statut, plutot que de se fier uniquement au titre de l'issue GitHub. Declencheur : lors d'une revue de fusion de branches (2026-07-25), les 6 issues `PA2-JOB-*` (#995-#1000) sont restees ouvertes malgre l'absence totale de branche/PR portant leur nom, alors que du travail livre sous d'autres tickets (`PA2-PAY-012/013`, `PA2-QA-006`, `PA2-COMM-006/007/008/013`) couvre une partie ou la totalite de leurs criteres d'acceptation. Verification statique uniquement (pas de PHP/Composer/k6 execute dans l'environnement d'audit).

---

## PA2-JOB-001 — Redis/queues readiness (Issue #995)

**Statut : deja FAIT et deja clos.** Issue #995 est deja `CLOSED`. `CHANGELOG.md` documente explicitement la creation de la table `failed_jobs` (migration `2026_07_23_000001_create_failed_jobs_table.php`, schema `public`), la commande `queue:health-check`/`QueueHealthCheck::failedJobsCount()`, et le runbook `RUNBOOK_ALERTING.md` section 3.4. Aucune action necessaire — cite ici uniquement parce que les 5 tickets suivants le declarent en dependance et qu'il fallait confirmer qu'il n'etait pas le chainon manquant.

---

## PA2-JOB-002 — Notifications FCM production (Issue #996, ouverte, P0)

**Statut : FAIT, jamais trace ni clos.**

Criteres d'acceptation : *"device tokens preferences push history fallback polling"*. Verifie ligne par ligne dans le code :

- **Device tokens** : `api/app/Modules/Notification/Domain/Models/DeviceToken.php` + `DeviceTokenController` (register/list/revoke), migration `2026_05_18_000001_create_device_tokens_table.php`, couvert par `api/tests/Feature/DeviceTokenControllerTest.php`.
- **Preferences** : `NotificationPreference` (modele + migration), `NotificationPreferenceController`, `NotificationPreferenceProvisioner` (provisioning par defaut a la creation d'employe).
- **Push** : `PushNotificationService` + `SendPushNotificationJob` (job asynchrone, tenant-scoped comme documente dans `PA2-OPS-*`).
- **History** : `AppNotification`/`Notification` (modeles Domain), consultables via l'API notifications (inbox in-app).
- **Fallback polling** : `PA2-COMM-013` livre le fallback REST-polling explicitement pour le cas ou le canal push (Socket.IO) est indisponible — `front/admin-dashboard/e2e/notification-fallback-polling.spec.js` (test e2e Playwright) et `front/admin-dashboard/src/stores/realtime.js` (implementation store cote client). Le commentaire du fichier de test cite lui-meme `PA2-COMM-013`, pas `PA2-JOB-002`.

**Ecart residuel** : aucun cote code. Le seul manque est le lien CHANGELOG/issue — cette fonctionnalite a ete livree progressivement sous 4+ tickets `PA2-COMM-*`/`PA2-OPS-*` differents sans jamais mentionner `PA2-JOB-002` ni fermer l'issue #996.

**Recommandation** : fermer #996 en citant ce document + les preuves ci-dessus.

---

## PA2-JOB-003 — Communication multi-canal (Issue #997, ouverte, P1)

**Statut : FAIT, jamais trace ni clos.**

Criteres d'acceptation : *"Email SMS WhatsApp providers quotas quiet hours"*. Verifie :

- **Providers abstraits** : `api/app/Contracts/Communication/RetryableMessageProviderInterface.php`, implementations `MailMessageProvider` (PA2-COMM-007, email production-ready avec retry/audit/bounce) et `AuditMessageProvider` (fallback audit-only). WhatsApp couvert par `PA2-COMM-008` (opt-in consent + Cloud API provider, merge #1208 en attente — voir section conflits ci-dessous) et `NotificationPreference`/`CommunicationService` gerent deja le routage multi-canal.
- **Quotas** : `CommunicationService::quotaExceeded()` + `config('communication.monthly_channel_quotas.*')`.
- **Quiet hours** : `CommunicationService` lignes ~200-220, `config('communication.quiet_hours.*')`, avec `bypass_categories` pour les notifications critiques (ex. securite).
- **SMS** : aucun provider SMS dedie trouve (`AuditMessageProvider`/`MailMessageProvider` seulement) — c'est le seul canal du critere d'acceptation qui reste **audit-only par defaut**, ce qui correspond exactement a la formulation du ticket lui-meme ("providers audit-only ou actifs selon env").

**Ecart residuel** : SMS n'a pas de provider actif (seulement audit-only), ce qui est conforme au critere d'acceptation tel qu'ecrit, pas un manque. Note : **PA2-COMM-008 (WhatsApp) n'est pas encore mergee** au moment de cet audit — voir "Blocage transverse" ci-dessous.

**Recommandation** : fermer #997 apres le merge de PA2-COMM-008, en citant ce document.

---

## PA2-JOB-004 — Traitements paie asynchrones (Issue #998, ouverte, P1)

**Statut : FAIT, jamais trace ni clos.**

Criteres d'acceptation : *"Recalculs PDF notifications ne bloquent pas UI"*. Verifie — tous les jobs suivants existent et sont asynchrones (`ShouldQueue`) :

- `GeneratePaySlipPdfJob` / `GeneratePaymentDocumentJob` / `WarmPaySlipPdfPathsForPayrollRunJob` (PDF hors requete, PA2-PAY-014).
- `ProcessBulkPaymentJob` (paiement en masse, resilience partial-failure + audit trail, PA2-PAY-013, merge #1207 cette session).
- `ProcessPayrollBatchJob` (recalculs).
- `PrecalculatePayrollRuns` (commande, precalcul nocturne progressif, PA2-PAY-012, merge #1209 cette session).
- Notifications post-paiement : `PA2-COMM-010` (notify employee on payment document processing/ready/failed, merge #1205 en attente — voir section conflits) declenchees depuis ces memes jobs, pas de blocage UI (fire via queue, pas synchrone).

**Ecart residuel** : aucun cote code livre. **PA2-COMM-010 n'est pas encore mergee** — le volet notification de ce ticket est fonctionnellement complet en branche mais pas encore integre a `main`.

**Recommandation** : fermer #998 apres le merge de PA2-COMM-010, en citant ce document + PA2-PAY-012/013/014.

---

## PA2-JOB-005 — k6 stress tests gates (Issue #999, ouverte, P1)

**Statut : PARTIEL.**

Criteres d'acceptation : *"Scenarios 10/20/50/100 users, lancement manuel ou path-based"*. Verifie :

- `dev-hub/load/k6/attendance-punch-scale.js` (PA2-QA-004, merge #1213 cette session) implemente exactement les 4 paliers `10/20/50/100` VUs (`stageVus('STAGE_1_VUS', 10)` ... `100`), et `.github/workflows/k6-load-smoke.yml` expose `workflow_dispatch` (manuel) avec input `attendance_punch_mode: manual|path` — couvre le cote "pointage" du gate.
- **Le cote "paie" (PA2-QA-005, Issue #1070, toujours ouverte) n'a pas d'equivalent 10/20/50/100** : `dev-hub/load/k6/payroll-500-batch.js` existe mais teste un seul scenario fixe (500 employes, VUs/iterations configurables par env var, pas de paliers progressifs 10/20/50/100 comme le script attendance). Donc PA2-JOB-005 est couvert a moitie : le gate pointage existe, le gate paie progressif n'existe pas encore.

**Ecart residuel reel** : contrairement a PA2-JOB-002/003/004/006, celui-ci a un veritable manque de code — pas seulement un manque de tracabilite. `PA2-QA-005` (paie) reste a faire pour cloturer completement `PA2-JOB-005`.

**Recommandation** : ne **pas** fermer #999 tel quel. Ajouter un commentaire citant l'etat partiel (pointage fait via PA2-QA-004, paie manquant via PA2-QA-005 toujours ouvert) plutot que de le clore.

---

## PA2-JOB-006 — Observabilite go-live (Issue #1000, ouverte, P2)

**Statut : FAIT, jamais trace ni clos.**

Criteres d'acceptation : *"Uptime logs queue depth DB health alerting minimal"*. Verifie — `PA2-QA-006` (merge #1214 cette session) livre exactement ce perimetre : le docblock de `QueueObservabilityController` cite lui-meme explicitement *"Depends on PA2-JOB-001"* et expose `GET /api/v1/platform/observability/queues` avec queue depth, failed jobs (count + recents), last run de chaque commande Artisan planifiee, et un resume `alerts` derive. Complete par `PlatformCompanyHealthController`/`HealthController` deja existants (uptime/DB health) et `front/admin-dashboard/src/components/system/QueueObservabilityCard.vue` (affichage dashboard, cite dans le critere "dashboard").

**Ecart residuel** : aucun cote code. Meme situation que #996/#997/#998 : livre sous un autre ID de ticket (`PA2-QA-006`), jamais rattache a `PA2-JOB-006`.

**Recommandation** : fermer #1000 en citant ce document + PA2-QA-006 / commit du merge #1214.

---

## Blocage transverse identifie pendant cet audit

`PA2-JOB-003` et `PA2-JOB-004` dependent partiellement de deux PR qui, au moment de cet audit, **ne sont pas mergeables automatiquement** dans `main` : `PA2-COMM-008` (WhatsApp, PR #1208 / branche `feature/issue-1059`) et `PA2-COMM-010` (notifications paiement, PR #1205 / branche `feature/issue-1061`). Les deux modifient les memes fichiers `api/lang/{ar,en,fr,tr}/notifications.php` que d'autres branches deja fusionnees (nouvelles cles de traduction ajoutees en parallele par plusieurs tickets), produisant un conflit de contenu reel qui necessite une resolution manuelle (fusionner les deux jeux de cles plutot que d'en ecraser un). Tant que ces deux PR restent en conflit, `PA2-JOB-003` et `PA2-JOB-004` ne peuvent pas etre consideres comme 100% integres a `main`, meme si le code source des deux branches est fonctionnellement complet.

---

## Recommandations de cloture (resume)

| Ticket | Issue | Action recommandee |
|---|---|---|
| PA2-JOB-001 | #995 | Deja clos, rien a faire |
| PA2-JOB-002 | #996 | **Fermer**, citer ce document |
| PA2-JOB-003 | #997 | Fermer **apres** merge PA2-COMM-008 (conflit lang files a resoudre) |
| PA2-JOB-004 | #998 | Fermer **apres** merge PA2-COMM-010 (conflit lang files a resoudre) |
| PA2-JOB-005 | #999 | **Ne pas fermer** — commenter statut partiel (paie manquant, cf. PA2-QA-005 #1070 toujours ouvert) |
| PA2-JOB-006 | #1000 | **Fermer**, citer ce document + PA2-QA-006 |

Aucun changement de code applicatif dans cette revue (audit uniquement).
