# AGENT-START-HERE — Épopée « Attendance IA » (bounded context Attendance)

> Guide d'ordre de lecture et de travail pour un agent (ou un humain) qui
> arrive sur l'épopée Attendance IA SANS contexte préalable. QLT-003 #6777.
> Référentiel métier : `docs/attendance/CARTOGRAPHIE_POINTAGE.md` (ATT-001 #6760).

Ce fichier répond à deux questions : **dans quel ordre les issues ont-elles
été conçues** (pour comprendre l'architecture), et **quels fichiers portent
quoi** (pour savoir où toucher). L'épopée est livrée par **lots** de branches
emboîtées (`bc/attendance-ia-lotN-*`) ; chaque lot fusionne le précédent — au
moment de lire ce guide, les lots 1→5b sont **mergeables / branchés** (PRs
ouvertes vers `main`, `#6778`→`#6785`) et le lot 6 est **en PR** (branches
sœurs listées en §4).

## 1. Lire d'abord (10 minutes)

1. `docs/attendance/CARTOGRAPHIE_POINTAGE.md` — la carte du pointage : tables,
   routes, services, événements, invariants R-1→R-5. C'est la racine de tout.
2. `api/app/Core/AI/` — les contrats IA (ports) + adaptateurs fail-closed.
3. `api/config/ai.php` (section `models.*`) et `api/config/attendance.php`
   (section `kiosk.offline`) — les seules variables de configuration du module.
4. `dev-hub/tools/check-attendance-boundary.sh` puis
   `dev-hub/tools/check-ai-vendor-boundary.sh` (QLT-003 #6777) — les frontières
   que la CI verrouille : Payroll ne lit que la projection `AttendanceLog`,
   aucun module n'importe de SDK biométrique/OCR en direct.

Règle d'or de l'épopée (R-5 de la cartographie) : **les moteurs biométriques
et d'OCR se branchent derrière les contrats `Core\AI`, jamais en import direct
dans un module.** Le seul endroit légal pour un SDK fournisseur est
`api/app/Core/AI/Infrastructure/Adapters/`.

## 2. Ordre des issues (conception → implémentation)

| # | Issue | Lot | Statut | Contenu en une phrase |
|---|---|---|---|---|
| ATT-001 | #6760 | lot 1 | ✅ mergé/branché (PR #6778) | Cartographie du pointage + Attendance propriétaire des événements de présence |
| ATT-002 | #6761 | lot 1 | ✅ mergé/branché (PR #6778) | Modèle de pointage multi-méthodes (énumérations + politique) |
| AI-001 | #6770 | lot 2 | ✅ mergé/branché (PR #6779) | Contrat commun des modèles IA (visage/liveness/OCR) : `ModelInferencePort` |
| BIO-001 | #6762 | lot 2 | ✅ mergé/branché (PR #6779) | Contrat `FaceVerificationPort` + adaptateurs testables/fail-closed |
| BIO-002 | #6763 | lot 3 | ✅ mergé/branché (PR #6780) | Cycle de vie d'enrôlement facial versionné (machine à états) |
| BIO-003 | #6764 | lot 3 | ✅ mergé/branché (PR #6780) | Stockage tenant-scoped des gabarits biométriques (chiffré) |
| BIO-005 | #6766 | lot 4 | ✅ mergé/branché (PR #6783) | Enregistrement + authentification des kiosques (`device_code`, `sync_token`) |
| BIO-006 | #6767 | lot 4 | ✅ mergé/branché (PR #6783) | Matrice de méthodes de pointage + fallback (badge, PIN, manager) |
| BIO-008 | #6773 | lot 4 | ✅ mergé/branché (PR #6783) | Audit/observabilité biométrique sans fuite de gabarits ni captures |
| ATT-003 | #6768 | lot 5 | ✅ mergé/branché (PR #6784) | Événement versionné `AttendanceRecorded.v1` (découplage Payroll) |
| BIO-004 | #6765 | lot 5b | ✅ mergé/branché (PR #6785) | Vérification faciale 1:1 au pointage kiosque (`verify-face`) |
| ATT-004 | #6769 | lot 6 | 🔄 en PR (branche `lot6-routes-offline-ocr-ui-qa`) | Routes d'enrôlement kiosque + pointage unifiées (provisioning versionné) |
| BIO-007 | #6772 | lot 6 | 🔄 en PR (idem) | Offline borné signé + synchronisation kiosque idempotente |
| BIO-009 | #6774 | lot 6 | 🔄 en PR (branche `lot6-bio009-ui`) | Interface kiosque multi-méthodes |
| AI-002 | #6771 | lot 6 | 🔄 en PR (branche `lot6-ai002-ocr`) | OCR des compteurs FuelStation via queue durable |
| QLT-001 | #6775 | lot 6 | 🔄 en PR (branche `lot6-qlt-tests`) | Tests de domaine + isolation cross-tenant du pointage biométrique |
| QLT-002 | #6776 | lot 6 | 🔄 en PR (idem) | Tests d'intégration kiosque + non-régression empreinte |
| QLT-003 | #6777 | lot 6 | 🔄 en PR (branche `lot6-qlt003-guards`) | **Ce lot** : gardes CI + documentation opératoire (AGENT-START-HERE + runbook) |

Légende : ✅ = implémentation mergée dans la chaîne de lots / PR ouverte vers
`main` (#6778→#6785) ; 🔄 = lot 6 en cours (PR unique à ouvrir depuis les
branches sœurs, empilées sur `lot6-routes-offline-ocr-ui-qa`).

## 3. Fichiers clés par issue (où est quoi)

### ATT-001/002 — fondation DDD + pointage multi-méthodes
- `docs/attendance/CARTOGRAPHIE_POINTAGE.md` (la carte, §1-§7)
- `api/app/Modules/Attendance/Domain/Enums/VerificationMethod.php` —
  méthodes de vérification (face, empreinte, badge, PIN, …)
- `api/app/Modules/Attendance/Domain/Enums/VerificationResult.php` — résultats
- `api/app/Modules/Attendance/Domain/Support/PunchRecordingPolicy.php` —
  quelles méthodes peuvent pointer, selon le kiosque/le mode
- `api/app/Modules/Attendance/Domain/Models/AttendanceLog.php` —
  l'événement de présence (source de vérité des heures)

### AI-001 + BIO-001 — contrats IA (le cœur de l'épopée)
- `api/app/Core/AI/Domain/Contracts/ModelInferencePort.php` (AI-001 #6770) —
  inférence générique : OCR compteurs, liveness, modèles
- `api/app/Core/AI/Domain/Contracts/FaceVerificationPort.php` (BIO-001 #6762) —
  vérification faciale 1:1
- `api/app/Core/AI/Domain/Enums/{ModelType,ModelExecutionStatus,FaceVerificationStatus}.php`
- `api/app/Core/AI/Domain/ValueObjects/{ModelRequest,ModelResult,ModelVersion,FaceVerificationRequest,FaceVerificationResult}.php`
- `api/app/Core/AI/Domain/Support/ModelOutputValidator.php` + `Domain/Exceptions/ModelOutputValidationException.php`
- `api/app/Core/AI/Infrastructure/Adapters/` — **seul endroit légal pour un
  SDK** : `Unavailable{FaceVerification,ModelInference}Adapter` (fail-closed),
  `Fake{FaceVerification,ModelInference}Adapter` (tests)
- Résolution par configuration : `api/config/ai.php` → `models.face_verification.adapter`
  (bind `FaceVerificationPort`, `api/app/Modules/Attendance/Providers/AttendanceServiceProvider.php`)
  et `models.inference.adapter` (bind `ModelInferencePort`, provider FuelStation — AI-002 #6771)

### BIO-002/003 — enrôlement versionné + stockage des gabarits
- `api/app/Modules/Attendance/Domain/Enums/BiometricEnrollmentStatus.php`
- `api/app/Modules/Attendance/Domain/Support/BiometricEnrollmentStateMachine.php`
- `api/app/Modules/Attendance/Domain/Models/BiometricEnrollment.php` (gabarits
  chiffrés, versionnés, tenant-scoped) + `BiometricEnrollmentRequest.php` (consentement)
- `api/app/Modules/Attendance/Infrastructure/Services/KioskEnrollmentService.php`

### BIO-005/006/008 — kiosques : cycle de vie, méthodes, audit
- `api/app/Modules/Attendance/Domain/Models/AttendanceKiosk.php` (`device_code`
  haché #5588, `sync_token_hash`, `biometric_mode`, `punch_methods`, `revoked_at`)
- `api/app/Modules/Attendance/Interfaces/Api/V1/KioskController.php` — register /
  revoke / rotate-token / config / punch / sync / verify-face (routes :
  `api/routes/modules/rh.php`)
- `api/app/Modules/Attendance/Infrastructure/Services/KioskAttendanceService.php`
- `api/app/Modules/Attendance/Infrastructure/Services/BiometricAuditLogger.php`
  (audit BIO-008 : événements `verification.*`, `device.*`, jamais de gabarit/capture)

### ATT-003 — événement versionné
- `api/app/Events/AttendanceRecorded.php` — `AttendanceRecorded.v1` (contrat de
  payload, versionné) ; publié depuis le moteur d'Attendance pour découpler Payroll

### BIO-004 — vérification faciale kiosque
- `api/app/Modules/Attendance/Infrastructure/Services/KioskFaceVerificationService.php`
  (identifie → contrôle → compare au gabarit ACTIF → pointe ; échec facial =
  jamais de présence créée)
- `POST /kiosks/{deviceCode}/verify-face` → 200/201 si vérifié, sinon 422
  (rejet/qualité/liveness/non-enrôlé) ou 503 `provider_unavailable` +
  `fallback_methods` (adaptateur non configuré)

### ATT-004 + BIO-007 — provisioning versionné + offline borné signé (lot 6)
- `api/app/Modules/Attendance/Interfaces/Api/V1/KioskController.php::doRegister`
  (provisioning : `device_code` + `sync_token` en clair UNE seule fois) ;
  `KioskEnrollmentController` (enrôlement kiosque versionné)
- `api/app/Modules/Attendance/Infrastructure/Services/KioskOfflineSyncGuard.php`
  (politique offline bornée : fenêtre d'ancienneté + taille de batch)
- `api/config/attendance.php` → `kiosk.offline.*`
  (`KIOSK_OFFLINE_MAX_AGE_DAYS`, `KIOSK_OFFLINE_MAX_EVENTS_PER_BATCH`)

### AI-002 — OCR compteurs FuelStation (lot 6, branche `lot6-ai002-ocr`)
- `api/app/Modules/FuelStation/Domain/Models/FuelMeterOcrRequest.php` (statuts
  `queued|processing|succeeded|needs_review|rejected|failed`)
- `api/app/Modules/FuelStation/Infrastructure/Jobs/ProcessMeterOcrJob.php`
  (queue durable, 3 retries, backoff `[10,60]`, timeout 120 s, tenant-scoped)
- `api/app/Modules/FuelStation/Infrastructure/Services/MeterOcrService.php`
  (`submit()` persiste AVANT dispatch ; `process()` ; revue humaine)
- Routes : `POST .../meters/{meter}/readings/ocr` (202 asynchrone),
  `GET /fuel-station/meter-ocr-requests/{ocr}`,
  `POST .../meter-ocr-requests/{ocr}/review` (manager) — `api/routes/modules/fuel_station.php`

### QLT-003 — gardes + docs (ce lot, branche `lot6-qlt003-guards`)
- `dev-hub/tools/check-ai-vendor-boundary.sh` + étape associée dans
  `.github/workflows/architecture-check.yml` (job Hygiene Guards)
- `docs/attendance/AGENT-START-HERE.md` (ce fichier)
- `docs/attendance/RUNBOOK_ATTENDANCE_IA.md` (exploitation : appareil révoqué,
  queue OCR bloquée, fournisseur IA indisponible, rollback)

## 4. État des branches (lot 6 — à ouvrir en PR)

```
main
└─ (lots 1→5b : PRs #6778→#6785, chaîne emboîtée)
   └─ bc/attendance-ia-lot6-routes-offline-ocr-ui-qa   ← base du lot 6
      ├─ bc/attendance-ia-lot6-ai002-ocr     (AI-002 #6771)
      ├─ bc/attendance-ia-lot6-bio009-ui     (BIO-009 #6774)
      ├─ bc/attendance-ia-lot6-qlt-tests     (QLT-001/002 #6775 #6776)
      └─ bc/attendance-ia-lot6-qlt003-guards (QLT-003 #6777 — gardes + docs)
```

Travailler sur le lot 6 = se brancher sur `bc/attendance-ia-lot6-routes-offline-ocr-ui-qa`
(ou sur la branche sœur concernée) et respecter les gardes CI :
`check-attendance-boundary.sh` (R-2/R-3) et `check-ai-vendor-boundary.sh` (R-5)
ne se contournent pas — un nouveau besoin passe par un contrat `Core/AI`
versionné, jamais par un import direct.

## 5. Pièges connus

- Ne PAS écrire dans `attendance_logs` hors des services d'Attendance (R-1).
- Payroll ne consomme QUE la projection `AttendanceLog` et les événements
  versionnés (R-2) — pas les services/adaptateurs/modèles d'appareils.
- Les gabarits biométriques et captures ne doivent jamais apparaître dans les
  logs d'audit (BIO-008) ni dans les réponses API.
- `device_code` et `sync_token` en clair ne sont retournés qu'au provisioning
  (`POST /kiosks`, 201) et à la rotation (`POST /kiosks/{kiosk}/rotate-token`).
- Toute route kiosque publique passe par `X-Kiosk-Token` (hash vérifié) et les
  middlewares `kiosk.search_path` / `kiosk.device` — ne pas court-circuiter.
- Les variables d'environnement du module vivent dans `.env.example` :
  `FACE_VERIFICATION_ADAPTER`, `MODEL_INFERENCE_ADAPTER`, `KIOSK_OFFLINE_*` —
  toute nouvelle variable doit y être ajoutée (garde `check-env-example-parity.sh`).
