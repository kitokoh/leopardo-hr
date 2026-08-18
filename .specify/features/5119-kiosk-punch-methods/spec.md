# Feature Specification: Kiosque — méthodes de pointage configurables par tenant (Epic #5119)

**Feature Branch**: `fix/5119-kiosk-punch-methods`
**Issues**: #5119 (epic) · #5120 (API data-model) · #5121 (enforcement) · #5122 (employé badge/enrollment) · #5123 (kiosk web UI) · #5124 (spec-kit/docs)
**Presets**: `leopardo-multitenancy` (table + endpoint API)

## Contexte

Le kiosque (ZKTeco + `front/zkteco-kiosk` + `POST /zkteco/sync-attendance/{serialNumber}`) accepte
aujourd'hui tous les types de pointage sans distinction. `ZktecoDevice` expose déjà
`fingerprint_capacity`/`face_capacity`, et l'employé a `biometric_fingerprint_enabled` /
`biometric_face_enabled` (garde `BIOMETRIC_NOT_APPROVED` dans `KioskAttendanceService`).
Le `punch_type` (0-5) décrit l'événement (entrée/sortie/pause…), **pas** la méthode biométrique.

Le tenant doit pouvoir **paramétrer, depuis l'API de configuration des kiosques
(`/api/v1/zkteco/devices`), les méthodes de pointage autorisées** : `fingerprint` (doigt),
`face` (visage), `card` (carte/badge) — par device, avec un défaut entreprise optionnel.

## User Stories

### US1 — Configurer les méthodes par kiosque (P1)
En tant que manager RH, je paramètre chaque kiosque avec les méthodes autorisées
(empreinte, visage, carte) depuis l'API devices, et je vois la config en lecture.

**Acceptance Scenarios**:
1. Given un device existant, When `PUT /zkteco/devices/{id}` avec `punch_methods: ["face","card"]`, Then la config est persistée et visible en `GET`.
2. Given `punch_methods` absent, When store/update, Then `null` (toutes méthodes, rétro-compat).
3. Given une valeur invalide (`"retina"`), When update, Then 422 (validation `in:fingerprint,face,card`).
4. Given un autre tenant, When GET/PUT le device, Then 404 (isolation inchangée).

### US2 — Enforcement à la sync (P1)
En tant que kiosque, je n'envoie que des pointages dont la méthode est autorisée ; sinon le backend refuse proprement, avec un message localisé.

**Acceptance Scenarios**:
1. Given un device autorisant `["face","card"]`, When sync d'un record `method: "fingerprint"`, Then refus du record (`PUNCH_METHOD_NOT_ALLOWED`).
2. Given méthode autorisée mais employé non enrôlé (ex. `card` sans `badge_number`), When sync, Then refus (`EMPLOYEE_METHOD_NOT_ENROLLED`).
3. Given payload sans `method`, When sync, Then comportement actuel (empreinte) — rétro-compat.
4. Given refus, Then entrée sur le canal log `audit` (device, employé, méthode, IP).

### US3 — Employé : badge + statut d'enrôlement (P2)
En tant que manager RH, je renseigne le badge/carte d'un employé et je vois pour chacun quelles méthodes sont enrôlées.

**Acceptance Scenarios**:
1. Given `PATCH /employees/{id}` avec `badge_number: "A-1042"`, Then champ persisté, unique dans le tenant.
2. Given badge vide, Then exposé absent ; Given renseigné, Then `enrollment.card: true` dans `EmployeeResource`.
3. Given un badge existant, When sync `method: "card"` + `badge_number`, Then l'employé est résolu (en plus de `zkteco_id`/`matricule`).

### US4 — Kiosk web piloté par la config (P2)
En tant qu'employé devant le kiosque, je ne vois que les méthodes autorisées, et je peux pointer par carte (saisie du badge).

**Acceptance Scenarios**:
1. Given config `["card"]`, When ouverture du kiosk, Then seul le flux carte est proposé.
2. Given flux carte, When saisie du badge, Then pointage envoyé avec `method: "card"` + `badge_number`.
3. Given aucune méthode autorisée, Then état « méthode non disponible » (pas de crash).
4. Given libellés, Then affichés en fr/en/tr/ar (`i18n.js`).

## Requirements

- **FR-001** (`#5120` API) : `ZktecoDevice.punch_methods` jsonb nullable (`fingerprint|face|card`), fillable + cast array, validation `array|in|distinct`, exposition index/show, défaut entreprise via `company_settings` (`kiosk.punch_methods.default`).
- **FR-002** (`#5121` enforcement) : helper `methodAllowed(device, method)` ; mapping enrollment employé (`fingerprint`→`biometric_fingerprint_enabled`, `face`→`biometric_face_enabled`, `card`→`badge_number` non vide) ; codes `PUNCH_METHOD_NOT_ALLOWED` / `EMPLOYEE_METHOD_NOT_ENROLLED` ×4 locales ; log audit ; rétro-compat sans `method`.
- **FR-003** (`#5122` employé) : `employees.badge_number` nullable unique scoped company (migration additive + index partiel) ; PATCH validé ; bloc `enrollment: {fingerprint, face, card}` dans `EmployeeResource` ; lookup kiosk incluant `badge_number`.
- **FR-004** (`#5123` kiosk web) : chargement de `punch_methods` au boot (config/bridge) ; sélecteur filtré + état vide ; flux carte (saisie badge) ; payload `method` ajouté au POST punch local et à la sync ; i18n ×4.
- **FR-005** (`#5124` docs) : `.specify/features/5119-kiosk-punch-methods/{spec.md,tasks.md}` ; MAJ `docs/dossierdeConception/` (multitenancy/kiosque) + guide device ; CHANGELOG à la livraison.

## Decisions

- **Rétro-compatibilité** : `punch_methods` absent/null ⇒ toutes méthodes ; payload `method` absent ⇒ `fingerprint` (comportement actuel). Aucun retrait de champ.
- **Portée** : config par **device** (chaque kiosque peut différer) + défaut entreprise optionnel. Pas de scope mobile (smart-attendance) dans cette épic.
- **Carte** : le badge est un champ employé formel (`badge_number`) ; le fallback `matricule` actuel reste en secours.

## Success Criteria

- CRUD `punch_methods` testé (API) ; enforcement testé (records refusés + codes ×4) ; badge + `enrollment` exposés et testés ; kiosk web filtré + flux carte ; `flutter analyze`/CI kiosk verts ; docs + spec mergées.
