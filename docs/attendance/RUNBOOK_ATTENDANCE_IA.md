# Runbook — Exploitation Attendance IA : appareils kiosque, OCR FuelStation, fournisseurs IA (issue #6777, QLT-003)

- **Statut :** actif — procédures opératoires du module Attendance IA
- **Date :** 2026-09-02
- **Référentiel :** `docs/attendance/CARTOGRAPHIE_POINTAGE.md` (ATT-001 #6760, invariants R-1→R-5)
- **Issues couvertes :** BIO-005 #6766 (cycle de vie kiosque), BIO-004 #6765 (verify-face), BIO-007 #6772 (offline borné), AI-002 #6771 (OCR compteurs FuelStation), QLT-003 #6777 (ce lot)
- **Composants :** `api/app/Core/AI` (contrats + adaptateurs), `api/app/Modules/Attendance` (kiosques, enrôlement, vérification faciale), `api/app/Modules/FuelStation` (OCR compteurs), tables tenant `attendance_kiosks` / `biometric_enrollments` / `biometric_enrollment_requests` / `biometric_audit_logs` / `attendance_logs` / `fuel_meter_ocr_requests`, file `database` (`failed_jobs` = dead-letter), routes `/api/v1/kiosks*`, `/api/v1/fuel-station/*`
- **Runbooks liés :** `docs/ops/RUNBOOK_AI.md` (BC-23 assistant IA), `docs/GESTION_PROJET/RUNBOOK_ZKTECO_CLIENT.md` (bornes ZKTeco terrain), `docs/ops/RENDER_QUEUE_WORKERS.md` (workers queue)

---

## 1. Architecture en une minute — ports/adaptateurs

Les moteurs biométriques/OCR ne sont **jamais** importés par les modules
(règle R-5, garde CI `check-ai-vendor-boundary.sh`, QLT-003 #6777) : ils sont
branchés derrière des contrats `Core\AI` et résolus **par configuration**.

| Contrat (`Core/AI`) | Adaptateurs (`Core/AI/Infrastructure/Adapters/`) | Binding | Config (`config/ai.php`) | Consommateur |
|---|---|---|---|---|
| `FaceVerificationPort` (BIO-001 #6762) | `UnavailableFaceVerificationAdapter` (défaut fail-closed), `FakeFaceVerificationAdapter` (tests), `<fournisseur réel à brancher>` | `AttendanceServiceProvider` (singleton, résolution lazy) | `models.face_verification.adapter` → `FACE_VERIFICATION_ADAPTER` | `KioskFaceVerificationService` (BIO-004 #6765) → `POST /api/v1/kiosks/{deviceCode}/verify-face` |
| `ModelInferencePort` (AI-001 #6770) | `UnavailableModelInferenceAdapter` (défaut fail-closed), `FakeModelInferenceAdapter` (tests), `<fournisseur réel à brancher>` | `FuelStationServiceProvider` (singleton, résolution lazy) | `models.inference.adapter` → `MODEL_INFERENCE_ADAPTER` | `MeterOcrService` (AI-002 #6771) → `ProcessMeterOcrJob` → `fuel_meter_ocr_requests` |

Binding type : `config('ai.models.*.adapter') ?: Unavailable*Adapter::class` —
une variable **vide** équivaut à **absente** (fail-closed garanti). Aucun SDK
fournisseur ne doit apparaître hors du dossier `Adapters/`.

## 2. Variables de configuration

| Config | Clé `.env` | Défaut | Effet |
|---|---|---|---|
| `ai.enabled` | `AI_ENABLED` | `false` | Kill switch de l'assistant IA générique BC-23 (`/api/v1/ai/*`, `feature_disabled`). **Ne coupe pas** verify-face ni OCR (pilotés par les adaptateurs ci-dessous). |
| `ai.provider` | `AI_PROVIDER` | `openai` | Provider LLM générique BC-23 (openai/claude) — voir `RUNBOOK_AI.md`. |
| `ai.models.face_verification.adapter` | `FACE_VERIFICATION_ADAPTER` | vide → `UnavailableFaceVerificationAdapter` | Classe de l'adaptateur `FaceVerificationPort`. Vide = fail-closed : verify-face répond 503 `provider_unavailable` (+ `fallback_methods`). |
| `ai.models.inference.adapter` | `MODEL_INFERENCE_ADAPTER` | vide → `UnavailableModelInferenceAdapter` | Classe de l'adaptateur `ModelInferencePort`. Vide = fail-closed : OCR échoue en `PROVIDER_UNAVAILABLE` (ligne `failed`, rejouable). |
| `ai.meter_ocr.confidence_threshold` *(AI-002 #6771)* | `METER_OCR_CONFIDENCE_THRESHOLD` | `0.92` | Seuil de confiance OCR : sous le seuil (ou anomalie) → `needs_review` (revue humaine manager), jamais d'auto-enregistrement. |
| `attendance.kiosk.offline.max_age_days` | `KIOSK_OFFLINE_MAX_AGE_DAYS` | `14` | Fenêtre d'ancienneté max d'un événement offline synchronisé (BIO-007 #6772, au-delà → `EVENT_EXPIRED`). |
| `attendance.kiosk.offline.max_events_per_batch` | `KIOSK_OFFLINE_MAX_EVENTS_PER_BATCH` | `500` | Taille max d'un batch de synchro offline signé (BIO-007 #6772). |

Toute nouvelle variable doit être ajoutée à `api/.env.example` (garde CI
`check-env-example-parity.sh`) ; les valeurs vivent en env/Pulumi, jamais dans
le code ni les logs.

## 3. Incident A — Appareil révoqué (403 DEVICE_REVOKED)

### Symptômes
- Le kiosque reçoit `403 DEVICE_REVOKED` sur **toutes** ses routes
  (`punch`, `sync`, `roster`, `config`, `verify-face`) — réponse explicite,
  pas un 404 ambigu (BIO-005 #6766).
- Log canal `audit` : `kiosk_auth.revoked_device` (device_code hashé,
  company_id, IP).
- En base : `attendance_kiosks.status = 'revoked'`, `revoked_at` renseigné.
- Cause habituelle : vol/perte/compromission → révocation manuelle manager
  (`POST /api/v1/kiosks/{kiosk}/revoke`). Un appareil révoqué le reste
  (pas d'endpoint « un-revoke ») : l'audit et l'historique sont conservés.

### Actions pas-à-pas
1. **Identifier** l'appareil : `GET /api/v1/kiosks` (manager) → repérer le
   kiosque `status: revoked` ; confirmer `revoked_at` / `revoked_by_employee_id`
   dans l'audit (`biometric_audit_logs`, event `device.revoked`).
2. **Décider** entre rotation et re-provisioning :
   - **Appareil physique intact, secret compromis** → rotation du `sync_token`
     (garde le même `device_code` et le statut actif) : voir §6.
   - **Appareil remplacé ou identité douteuse** → re-provisioning d'un
     **nouveau** kiosque : `POST /api/v1/kiosks` (manager) → `201` avec
     `data.device_code` + `data.sync_token` (retournés en clair **une seule
     fois**, à la création). **Ne jamais réutiliser** un `device_code`
     révoqué (identité d'appareil unique, hachée au repos #5588).
3. **Reporter** les nouveaux secrets dans l'appareil physique / le pont local
   (`config.json` du bridge, cf. `RUNBOOK_ZKTECO_CLIENT.md`) et re-pointer
   l'appareil sur le bon `device_code`.
4. **Valider** : `GET /api/v1/kiosks/{deviceCode}/config` → `200` ; punch de test →
   `200`/`201` (plus de 403).

## 4. Incident B — Queue OCR bloquée (AI-002 #6771)

### Symptômes
- Lignes `fuel_meter_ocr_requests` **coincées en `queued` / `processing`**
  au-delà de quelques minutes (les soumissions répondent 202 mais ne sont
  jamais soldées).
- Échecs définitifs : `failed_jobs` contient `ProcessMeterOcrJob` (après
  3 essais, backoff `[10, 60]` s, timeout 120 s) et la ligne reste `failed`
  avec `error_code` (`PROVIDER_UNAVAILABLE`, `PROVIDER_TIMEOUT`, …).
- Causes fréquentes : worker queue arrêté, fournisseur IA en panne (§5),
  adaptateur non configuré (fail-closed), job orphelin (retry_after 90 s :
  `DB_QUEUE_RETRY_AFTER`).

### Actions pas-à-pas
1. **Vérifier le worker** : `leopardo-queue-worker` consomme la file par
   défaut (`php artisan queue:work --queue=…,default`, connexion `database`
   — `docs/ops/RENDER_QUEUE_WORKERS.md`). Un `processing` orphelin est
   relâché automatiquement après `retry_after`.
2. **État de la file** (sur le schéma du tenant concerné) :
   ```sql
   SELECT id, company_id, status, attempts, error_code, model_version, updated_at
     FROM fuel_meter_ocr_requests
    WHERE status IN ('queued','processing','failed')
    ORDER BY updated_at;
   ```
3. **Inspecter la dead-letter** (Laravel `failed_jobs`) :
   ```bash
   php artisan queue:failed            # lister
   php artisan queue:retry <job_id>    # rejouer UN job après correction
   php artisan queue:retry all         # rejouer tout (prudence : d'abord la cause)
   php artisan queue:flush             # purger la DLQ (JAMAIS sans revue)
   ```
4. **Corriger la cause racine** avant tout replay : fournisseur (§5),
   worker, quota stockage photo, etc. Rejouer en boucle sans cause corrigée
   recrée des échecs (3 retries → DLQ).
5. **Re-dispatch d'une demande précise** : le service n'accepte que les
   lignes `queued|failed` (les statuts `succeeded|needs_review|rejected` ne
   sont **jamais** re-traités — garde de rejeu) :
   ```sql
   -- schéma du tenant ; ligne ciblée, jamais de purge/UPDATE en masse sans revue
   UPDATE fuel_meter_ocr_requests
      SET status='queued', error_code=NULL, attempts=0
    WHERE id=<id> AND company_id='<tenant>';
   ```
   puis re-dispatcher le job (il est tenant-scoped, middleware
   `EnsureTenantContext`) :
   ```bash
   php artisan tinker --execute="App\Modules\FuelStation\Infrastructure\Jobs\ProcessMeterOcrJob::dispatch(<id>,'<tenant>');"
   ```
   Un crash entre persistance et dispatch ne perd jamais la demande (la
   ligne est persistée AVANT le dispatch) ; une même `idempotency_key` ne
   crée jamais de doublon.
6. **Valider** : la ligne passe `processing` → `succeeded` (relevé
   auto-enregistré si confiance ≥ seuil et sans anomalie) ou `needs_review`
   (revue manager via `POST /api/v1/fuel-station/meter-ocr-requests/{ocr}/review`) ;
   `failed_jobs` vide ou résolu.

## 5. Incident C — Fournisseur IA indisponible

### Symptômes
- **verify-face** (`POST /api/v1/kiosks/{deviceCode}/verify-face`) : `503` avec
  `reason_code` (`provider_unavailable`, `FACE_PROVIDER_NOT_CONFIGURED`, …)
  et `data.fallback_methods` (méthodes de repli du kiosque). Aucun pointage
  créé, aucun employé bloqué : le kiosque propose les fallbacks (badge, PIN,
  manager…).
- **OCR** : `ProcessMeterOcrJob` échoue (3 retries avec backoff) →
  `failed_jobs` ; ligne `fuel_meter_ocr_requests` en `failed` avec
  `error_code = PROVIDER_UNAVAILABLE` / `PROVIDER_TIMEOUT` ; log structuré
  `ocr.provider_unavailable`.
- Audit : `verification.provider_unavailable` (jamais de capture/gabarit dans
  les logs — BIO-008 #6773).

### Comportement attendu (fail-closed)
- **Défaut de configuration** (adaptateur vide) = service **indisponible
  assumé** : 503 `provider_unavailable` (face) / `failed PROVIDER_UNAVAILABLE`
  (OCR). Jamais de faux succès, jamais d'appel fournisseur non configuré.
- Un pointage facial n'est accepté que si le fournisseur a **réellement**
  vérifié l'employé (aucun contournement par l'échec).

### Actions pas-à-pas
1. **Diagnostiquer** : quel adaptateur est actif ? (`FACE_VERIFICATION_ADAPTER`
   / `MODEL_INFERENCE_ADAPTER` sur l'env) ; statut du fournisseur (status
   page) ; erreurs dans Sentry / logs structurés (`correlation_id`).
2. **Basculer d'adaptateur par configuration** — aucun changement de code :
   ```bash
   # ex. bascule du moteur d'inférence (OCR) vers un fournisseur secondaire
   MODEL_INFERENCE_ADAPTER='App\Core\AI\Infrastructure\Adapters\<NouvelAdaptateur>'
   # ou retour au fail-closed explicite :
   MODEL_INFERENCE_ADAPTER=
   FACE_VERIFICATION_ADAPTER=
   ```
   Puis **redéployer** (ou au minimum redémarrer les workers PHP + service
   web) : le binding singleton est résolu au boot — un simple changement
   d'env ne prend effet qu'au prochain démarrage.
3. **Kill switch étroit** : adaptateur vide = coupure propre du moteur
   (503/`unavailable`), sans effet de bord, sans code.
4. **Kill switch global BC-23** : `AI_ENABLED=false` (assistant IA générique
   uniquement — `RUNBOOK_AI.md`). Ne pas confondre avec les adaptateurs.
5. **Après rétablissement du fournisseur** : rejouer l'OCR (§4.5) ; vérifier
   le retour du taux de succès.
6. **Valider** : verify-face → `200`/`201` pour un employé enrôlé ;
   soumission OCR → `succeeded` ; plus de 503/`PROVIDER_UNAVAILABLE` récents.

## 6. Rotation du `sync_token` d'un kiosque

Rotation périodique ou post-compromission — l'identité de l'appareil
(`device_code`) est conservée, le secret change immédiatement.

1. `POST /api/v1/kiosks/{kiosk}/rotate-token` (manager, `auth:sanctum` +
   tenant) → `200` : `data.sync_token` = **nouveau token en clair** (unique
   retour — l'ancien hash est remplacé sur-le-champ).
2. Audit : event `device.token_rotated` (`biometric_audit_logs`).
3. Reporter le nouveau token dans l'appareil / le pont local.
4. Valider : punch/sync avec le nouveau token → `200`/`201` ; l'ancien token
   répond désormais `401 INVALID_KIOSK_TOKEN` (log `kiosk_auth.failed`).

## 7. Rollback d'un lot Attendance IA (décrire — ne pas exécuter sans validation)

Chaque lot est livré par une PR dédiée (`bc/attendance-ia-lotN-*`, fusionnée
en chaîne puis vers `main`) ; les migrations tenant sont **additives et
réentrantes** (`schemaHasColumn` / `schemaTableExists`, `down()` propre).

1. Identifier la PR du lot à annuler et son SHA de merge.
2. **Revert du code** : `git revert -m 1 <sha_du_merge>` (PR de hotfix
   dédiée, revue + CI) — procédure plateforme : `RUNBOOK_ROLLBACK.md`.
3. **Migrations** : si un `down()` est requis (ex. table
   `biometric_enrollments` supprimée par la migration #6764, colonnes
   retirées par #6766/#6772), exécuter le rollback **tenant par tenant**
   avec la procédure de migration standard — **jamais** en parallèle du
   trafic sans backup. Revert du code ≠ suppression des données : la
   décision de rétention (`biometric_enrollments`, gabarits chiffrés,
   `biometric_audit_logs`) est prise explicitement (RGPD, audit).
4. **Après rollback** : re-déployer, vérifier les gardes CI
   (`check-attendance-boundary.sh`, `check-ai-vendor-boundary.sh`),
   smoke : `/api/v1/health`, check-in kiosque, enrôlement, OCR.
5. Si l'incident est un provider (pas un défaut de code), préférer la
   **bascule par configuration** (§5.2) au revert.

## 8. Supervision

| Métrique / alerte | Source | Seuil conseillé | Action |
|---|---|---|---|
| Kiosque révoqué inattendu | `biometric_audit_logs` event `device.revoked` | > 0 hors changement planifié | Vérifier vol/compromission (§3) |
| `sync_token` roté | event `device.token_rotated` | anormalement fréquent | Vérifier boucle de rotation / fuite |
| Taux d'échec verify-face | audit `verification.*` (reason_code) | 503 `provider_unavailable` en hausse | Voir §5 |
| `fuel_meter_ocr_requests` en retard | requête §4.2 | `queued`/`processing` > 15 min | Vérifier worker (§4.1) puis fournisseur (§5) |
| `failed_jobs` non vide | `php artisan queue:failed` | > 0 nouveau / heure | Revue DLQ (§4.3), jamais de purge auto |
| OCR `needs_review` en attente | `fuel_meter_ocr_requests` | revue manager > 24 h | Alerter le manager (revue humaine) |

## 9. Contacts & escalade

- **Propriétaires BC (registre `dev-hub/governance/bounded-context-registry.json`) :**
  - **BC-05 Attendance, Planning & Workforce** — Agent 05 (BC-WORKFORCE) :
    kiosques, enrôlement biométrique, vérification faciale ;
  - **BC-15 FuelStation** — Agent 15 (BC-FUEL) : OCR compteurs
    (`fuel_meter_ocr_requests`, `ProcessMeterOcrJob`) ;
  - **BC-23 AI Assistive Services** — Agent 23 (BC-AI) : assistant IA
    générique (`docs/ops/RUNBOOK_AI.md`), contrats `Core\AI` partagés.
- **Incidents P1 :** `RUNBOOK_INCIDENT_P1.md` ; Sentry (tags `attendance`,
  `kiosk`, `fuel`, `ocr`) ; logs structurés (`request_id`/`correlation_id`).
