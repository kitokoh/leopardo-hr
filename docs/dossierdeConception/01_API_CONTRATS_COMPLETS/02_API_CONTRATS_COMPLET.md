# API CONTRATS — Leopardo RH
# Payloads JSON COMPLETS — Version 2.1 FINALE | Mars 2026
# Tous les endpoints couverts : 82+/82+
# Référence commune Backend (Claude Code) ↔ Mobile (Jules) ↔ Web (Cursor)

---

## CONVENTIONS GÉNÉRALES

```
Base URL      : https://api.leopardo-rh.com/v1
Auth header   : Authorization: Bearer {token}
Content-Type  : application/json
Accept        : application/json
Rate limit    : 60 req/min par token
Dates         : ISO 8601 (2026-04-15T08:30:00Z)
Heures        : HH:MM:SS (08:30:00)
Décimaux      : point (.) comme séparateur
Pagination    : ?page=1&per_page=15
```

---

## FORMAT DE RÉPONSE STANDARD

### Succès
```json
{ "data": { ... } }
{ "data": [ ... ], "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 74 } }
```

### Erreur
```json
{ "message": "CODE_ERREUR", "errors": { "field": ["message"] } }
```

---

## 1. AUTHENTIFICATION

### POST /public/register  ← Auto-onboarding public (Phase 1 MVP)
**Description :** Crée un compte Trial sans intervention du Super Admin. Accessible sans authentification.

**Request :**
```json
{
  "company_name": "Entreprise SARL",
  "manager_email": "contact@entreprise.com",
  "manager_password": "MotDePasse123!",
  "country": "DZ",
  "estimated_employees": 15,
  "plan": "starter"
}
```
**Validations :**
- `company_name` : requis, 2–100 caractères
- `manager_email` : requis, email valide, unique dans `user_lookups`
- `manager_password` : requis, min 8 caractères, 1 majuscule, 1 chiffre
- `country` : requis, code ISO 3166-1 alpha-2 valide (ex: DZ, MA, TN, TR, FR)
- `estimated_employees` : requis, entier > 0
- `plan` : optionnel, valeurs: `starter` | `business`, défaut: `starter`

**Rate limit :** 3 requêtes / heure / IP

**Response 201 :**
```json
{
  "data": {
    "company": {
      "id": "uuid",
      "name": "Entreprise SARL",
      "slug": "entreprise-sarl",
      "status": "trial",
      "trial_ends_at": "2026-04-13",
      "plan": "Starter"
    },
    "token": "1|abcdef...",
    "user": {
      "id": 1,
      "email": "contact@entreprise.com",
      "role": "manager",
      "manager_role": "principal"
    }
  }
}
```
**Response 422 — Email déjà utilisé :**
```json
{ "error": "EMAIL_ALREADY_EXISTS", "message": "Cet email est déjà enregistré." }
```
**Comportement backend :**
1. Créer `companies` (status=`trial`, subscription_end = NOW+14j)
2. Créer schéma `shared_tenants` entry + schéma si Enterprise
3. Créer `employees` (premier manager, role=`manager`, manager_role=`principal`)
4. Créer `user_lookups` (email → company)
5. Charger `hr_model_templates` selon `country` → pré-remplir settings
6. Envoyer email de bienvenue (queue)
7. Retourner token Sanctum

---


### POST /auth/login
**Request :**
```json
{
  "email": "ahmed.benali@entreprise.com",
  "password": "MonMotDePasse123!",
  "device_name": "iPhone 15 Pro d'Ahmed",
  "fcm_token": "fcm_abc123..."
}
```
**Response 200 :**
```json
{
  "data": {
    "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz",
    "token_type": "Bearer",
    "expires_at": "2026-07-15T10:00:00Z",
    "user": {
      "id": 42,
      "first_name": "Ahmed",
      "last_name": "Benali",
      "email": "ahmed.benali@entreprise.com",
      "role": "employee",
      "manager_role": null,
      "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg",
      "leave_balance": 12.5,
      "company": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "TechCorp SPA",
        "language": "fr",
        "timezone": "Africa/Algiers",
        "currency": "DZD",
        "logo_url": "https://api.leopardo-rh.com/storage/logos/techcorp.png"
      }
    }
  }
}
```
**Erreurs :**
- `401` : `INVALID_CREDENTIALS`
- `403` : `ACCOUNT_SUSPENDED` (entreprise suspendue)
- `403` : `SUBSCRIPTION_EXPIRED`

---

### POST /auth/logout
**Request :** aucun body
**Response 200 :**
```json
{ "message": "LOGGED_OUT" }
```

---

### Stratégie de renouvellement de token (Sanctum opaques)
- Token Flutter valide 90 jours (config `sanctum.expiration`)
- À l'expiration : Flutter reçoit `401 UNAUTHENTICATED`
- Flutter intercepte ce `401` et redirige vers l'écran de login
- L'utilisateur se reconnecte → nouveau token émis
- Pas d'endpoint refresh

---

### GET /auth/me
**Request :** aucun body
**Response 200 :**
```json
{
  "data": {
    "id": 42,
    "matricule": "EMP-0042",
    "first_name": "Ahmed",
    "last_name": "Benali",
    "email": "ahmed.benali@entreprise.com",
    "phone": "+213 555 123 456",
    "role": "employee",
    "manager_role": null,
    "department": { "id": 3, "name": "Développement" },
    "position": { "id": 7, "name": "Développeur Senior" },
    "hire_date": "2023-03-01",
    "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg",
    "leave_balance": 12.5,
    "status": "active",
    "company": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "TechCorp SPA",
      "language": "fr",
      "timezone": "Africa/Algiers",
      "currency": "DZD",
      "logo_url": "https://api.leopardo-rh.com/storage/logos/techcorp.png"
    }
  }
}
```

---

### POST /auth/forgot-password
**Request :**
```json
{ "email": "ahmed.benali@entreprise.com" }
```
**Response 200 :**
```json
{ "message": "RESET_EMAIL_SENT" }
```

---

### POST /auth/reset-password
**Request :**
```json
{
  "token": "reset_token_from_email",
  "email": "ahmed.benali@entreprise.com",
  "password": "NouveauMotDePasse123!",
  "password_confirmation": "NouveauMotDePasse123!"
}
```
**Response 200 :**
```json
{ "message": "PASSWORD_RESET_SUCCESS" }
```

---

### POST /auth/device/fcm
**Request :**
```json
{
  "fcm_token": "fcm_xyz789...",
  "device_name": "iPhone 15 Pro d'Ahmed",
  "platform": "ios"
}
```
**Response 200 :**
```json
{ "message": "FCM_TOKEN_REGISTERED" }
```

---

### DELETE /auth/device/fcm
**Request :**
```json
{ "fcm_token": "fcm_xyz789..." }
```
**Response 200 :**
```json
{ "message": "FCM_TOKEN_REMOVED" }
```

---

## 2. EMPLOYÉS

### GET /employees
**Query params :** `?department_id=3&status=active&search=Ahmed&page=1&per_page=20`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 42,
      "matricule": "EMP-0042",
      "first_name": "Ahmed",
      "last_name": "Benali",
      "email": "ahmed.benali@entreprise.com",
      "role": "employee",
      "department": { "id": 3, "name": "Développement" },
      "position": { "id": 7, "name": "Développeur Senior" },
      "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg",
      "status": "active",
      "hire_date": "2023-03-01"
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 47 }
}
```

---

### POST /employees
**Request :**
```json
{
  "first_name": "Fatima",
  "last_name": "Zohra",
  "email": "f.zohra@entreprise.com",
  "phone": "+213 666 000 111",
  "role": "employee",
  "department_id": 3,
  "position_id": 7,
  "schedule_id": 1,
  "site_id": 1,
  "hire_date": "2026-04-01",
  "salary_base": 75000,
  "iban": "DZ123456789012345678901234",
  "zkteco_id": "00123"
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 101,
    "matricule": "EMP-0101",
    "first_name": "Fatima",
    "last_name": "Zohra",
    "email": "f.zohra@entreprise.com",
    "status": "active"
  }
}
```


**Response 403 — Limite de plan atteinte :**
```json
{
  "error": "PLAN_EMPLOYEE_LIMIT_REACHED",
  "message": "Votre plan Starter est limité à 20 employés. Passez au plan Business pour continuer.",
  "data": {
    "current_count": 20,
    "plan_limit": 20,
    "plan": "Starter",
    "upgrade_url": "https://app.leopardo-rh.com/settings/billing"
  }
}
```
**Note implémentation :** `PlanLimitMiddleware` vérifie `Employee::count() >= company.plan.max_employees` avant tout POST /employees. `max_employees = NULL` = illimité (Enterprise).

---

### GET /employees/{id}
**Response 200 :**
```json
{
  "data": {
    "id": 42,
    "matricule": "EMP-0042",
    "zkteco_id": "00042",
    "first_name": "Ahmed",
    "last_name": "Benali",
    "email": "ahmed.benali@entreprise.com",
    "phone": "+213 555 123 456",
    "role": "employee",
    "manager_role": null,
    "department": { "id": 3, "name": "Développement" },
    "position": { "id": 7, "name": "Développeur Senior" },
    "schedule": { "id": 1, "name": "Standard 8h-17h" },
    "site": { "id": 1, "name": "Siège social" },
    "hire_date": "2023-03-01",
    "salary_base": 75000,
    "iban": "DZ123456789012345678901234",
    "leave_balance": 12.5,
    "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg",
    "status": "active"
  }
}
```
**Erreurs :** `404` : `EMPLOYEE_NOT_FOUND`

---

### PUT /employees/{id}
**Request :** (tous les champs modifiables, partiels acceptés)
```json
{
  "phone": "+213 555 999 888",
  "department_id": 4,
  "salary_base": 80000
}
```
**Response 200 :** Retourne l'employé complet mis à jour (même format que GET /employees/{id})

---

### DELETE /employees/{id}
*Soft delete — archive l'employé, ne supprime pas les données*
**Response 200 :**
```json
{ "message": "EMPLOYEE_ARCHIVED" }
```

---

### POST /employees/import
**Request :** `multipart/form-data`
```
file: employees.csv (colonnes: first_name,last_name,email,department_id,position_id,hire_date,salary_base)
```
**Response 202 :**
```json
{
  "data": {
    "job_id": "import_abc123",
    "total_rows": 45,
    "message": "IMPORT_QUEUED"
  }
}
```

---

### GET /employees/{id}/payslips
**Query params :** `?year=2026`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 401,
      "period": "Avril 2026",
      "month": 4,
      "year": 2026,
      "salary_base": 75000,
      "net_salary": 61350,
      "status": "validated",
      "pdf_url": "https://api.leopardo-rh.com/storage/payslips/2026-04-42.pdf"
    }
  ]
}
```

---

## 3. CONFIGURATION ENTREPRISE

### GET /departments
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Direction", "employees_count": 3 },
    { "id": 2, "name": "Ressources Humaines", "employees_count": 5 },
    { "id": 3, "name": "Développement", "employees_count": 18 }
  ]
}
```

---

### POST /departments
**Request :**
```json
{ "name": "Marketing Digital" }
```
**Response 201 :**
```json
{ "data": { "id": 8, "name": "Marketing Digital", "employees_count": 0 } }
```

---

### PUT /departments/{id}
**Request :**
```json
{ "name": "Marketing & Communication" }
```
**Response 200 :**
```json
{ "data": { "id": 8, "name": "Marketing & Communication", "employees_count": 0 } }
```

---

### DELETE /departments/{id}
**Erreurs :** `409` : `DEPARTMENT_HAS_EMPLOYEES` (doit réaffecter avant de supprimer)
**Response 200 :**
```json
{ "message": "DEPARTMENT_DELETED" }
```

---

### GET /positions
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Directeur Général", "department_id": 1 },
    { "id": 7, "name": "Développeur Senior", "department_id": 3 }
  ]
}
```

---

### POST /positions
```json
{ "name": "Chef de Projet", "department_id": 3 }
```
**Response 201 :**
```json
{ "data": { "id": 12, "name": "Chef de Projet", "department_id": 3 } }
```

---

### PUT /positions/{id}
```json
{ "name": "Chef de Projet Senior" }
```
**Response 200 :** Retourne le poste mis à jour.

---

### DELETE /positions/{id}
**Response 200 :**
```json
{ "message": "POSITION_DELETED" }
```

---

### GET /schedules
**Response 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Standard 8h-17h",
      "start_time": "08:00:00",
      "end_time": "17:00:00",
      "break_minutes": 60,
      "days": ["monday","tuesday","wednesday","thursday","friday"],
      "late_tolerance_minutes": 10,
      "overtime_threshold_daily": 8.0
    }
  ]
}
```

---

### POST /schedules
```json
{
  "name": "Équipe Nuit",
  "start_time": "22:00:00",
  "end_time": "06:00:00",
  "break_minutes": 30,
  "days": ["monday","tuesday","wednesday","thursday","friday"],
  "late_tolerance_minutes": 5,
  "overtime_threshold_daily": 8.0
}
```
**Response 201 :** Retourne le planning créé.

---

### PUT /schedules/{id}
**Response 200 :** Retourne le planning mis à jour.

---

### DELETE /schedules/{id}
**Erreurs :** `409` : `SCHEDULE_IN_USE`
**Response 200 :**
```json
{ "message": "SCHEDULE_DELETED" }
```

---

### GET /sites
**Response 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Siège social Alger",
      "address": "12 Rue Didouche Mourad, Alger",
      "gps_lat": 36.7375,
      "gps_lng": 3.0868,
      "gps_radius_meters": 100,
      "timezone": "Africa/Algiers"
    }
  ]
}
```

---

### POST /sites
```json
{
  "name": "Antenne Oran",
  "address": "5 Boulevard de la Soummam, Oran",
  "gps_lat": 35.6969,
  "gps_lng": -0.6331,
  "gps_radius_meters": 150,
  "timezone": "Africa/Algiers"
}
```
**Response 201 :** Retourne le site créé.

---

### PUT /sites/{id}
**Response 200 :** Retourne le site mis à jour.

---

### DELETE /sites/{id}
**Response 200 :**
```json
{ "message": "SITE_DELETED" }
```

---

## 4. POINTAGE (ATTENDANCE)

### POST /attendance/check-in
**Request :**
```json
{
  "gps_lat": 36.7375,
  "gps_lng": 3.0868,
  "method": "mobile"
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 5432,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_out": null,
    "status": "ontime",
    "method": "mobile",
    "hours_worked": null
  }
}
```
**Erreurs :**
- `409` : `ALREADY_CHECKED_IN`
- `422` : `GPS_OUTSIDE_ZONE` (hors périmètre du site)
- `422` : `MISSING_SCHEDULE` (pas de planning assigné)

---

### POST /attendance/check-out
**Request :**
```json
{
  "gps_lat": 36.7375,
  "gps_lng": 3.0868
}
```
**Response 200 :**
```json
{
  "data": {
    "id": 5432,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_out": "2026-04-15T17:02:00Z",
    "status": "ontime",
    "method": "mobile",
    "hours_worked": 8.07,
    "overtime_hours": 0.0
  }
}
```
**Erreurs :**
- `422` : `MISSING_CHECK_IN`
- `422` : `CHECKOUT_BEFORE_CHECKIN`

---

### GET /attendance/today
**Response 200 (pas encore pointé) :**
```json
{
  "data": null,
  "context": {
    "is_holiday": false,
    "is_leave": false,
    "expected_start": "08:00:00"
  }
}
```
**Response 200 (arrivée pointée) :**
```json
{
  "data": {
    "id": 5432,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_out": null,
    "status": "ontime",
    "method": "mobile",
    "hours_worked": null
  }
}
```
**Response 200 (jour férié ou congé) :**
```json
{ "data": null, "context": { "is_holiday": true, "is_leave": false } }
```

---

### GET /attendance
**Query params :** `?employee_id=42&month=4&year=2026&status=late&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 5432,
      "employee": { "id": 42, "name": "Ahmed Benali", "photo_url": "..." },
      "date": "2026-04-15",
      "check_in": "2026-04-15T07:58:00Z",
      "check_out": "2026-04-15T17:02:00Z",
      "hours_worked": 8.07,
      "overtime_hours": 0.0,
      "status": "ontime",
      "method": "mobile",
      "is_manual_edit": false
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 15, "total": 22 }
}
```

---

### GET /attendance/{employee_id}
*Historique d'un employé spécifique*
**Query params :** `?month=4&year=2026`
**Response 200 :** Même format que GET /attendance mais filtré sur cet employé.

---

### PUT /attendance/{id}
*Correction manuelle gestionnaire*
**Request :**
```json
{
  "check_in": "2026-04-15T08:05:00Z",
  "check_out": "2026-04-15T17:30:00Z",
  "reason": "Erreur de pointage biométrique — confirmé par le superviseur"
}
```
**Response 200 :** Retourne le log mis à jour avec `is_manual_edit: true`.

---

### POST /attendance/qrcode
**Request :**
```json
{
  "qr_payload": "LEO-SITE-1-2026-04-15",
  "gps_lat": 36.7375,
  "gps_lng": 3.0868
}
```
**Response 201/200 :** Même format que check-in ou check-out selon l'état de l'employé.

---

### POST /attendance/biometric
*Webhook ZKTeco*
**Headers :** `X-Device-Token: device_secret_token`
**Request :**
```json
{
  "zkteco_id": "00042",
  "event_type": "check_in",
  "device_id": "ZK-ALGER-01",
  "timestamp": "2026-04-15T07:58:00Z"
}
```
**Response 200 :**
```json
{ "message": "ATTENDANCE_RECORDED" }
```
**Erreurs :** `401` : `INVALID_DEVICE_TOKEN`

---

## 5. ABSENCES

### GET /absences
**Query params :** `?employee_id=42&status=pending&month=4&year=2026&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 801,
      "employee": { "id": 42, "name": "Ahmed Benali" },
      "type": { "id": 1, "label": "Congé payé", "color": "#4CAF50" },
      "start_date": "2026-06-01",
      "end_date": "2026-06-15",
      "days_count": 11,
      "status": "approved",
      "comment": "Vacances d'été",
      "approved_by": { "id": 5, "name": "Hamid Directeur" },
      "created_at": "2026-04-10T09:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 }
}
```

---

### POST /absences
**Request :**
```json
{
  "absence_type_id": 1,
  "start_date": "2026-08-01",
  "end_date": "2026-08-15",
  "comment": "Congé annuel"
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 805,
    "type": { "id": 1, "label": "Congé payé", "color": "#4CAF50" },
    "start_date": "2026-08-01",
    "end_date": "2026-08-15",
    "days_count": 11,
    "status": "pending",
    "comment": "Congé annuel"
  }
}
```
**Erreurs :**
- `422` : `INSUFFICIENT_LEAVE_BALANCE` (solde insuffisant)
- `422` : `OVERLAP_WITH_EXISTING` (conflit avec une absence existante)

---

### GET /absences/{id}
**Response 200 :** Retourne l'absence complète (même format que l'objet dans la liste).

---

### PUT /absences/{id}/approve
**Request :**
```json
{ "comment": "Approuvé — bon repos !" }
```
**Response 200 :**
```json
{ "data": { "id": 801, "status": "approved", "approved_by": { "id": 5, "name": "Hamid Directeur" } } }
```

---

### PUT /absences/{id}/reject
**Request :**
```json
{ "comment": "Période trop chargée — à reporter en septembre" }
```
**Response 200 :**
```json
{ "data": { "id": 801, "status": "rejected", "rejected_reason": "Période trop chargée..." } }
```

---

### PUT /absences/{id}/cancel
*Annulation par l'employé (uniquement si statut pending)*
**Response 200 :**
```json
{ "message": "ABSENCE_CANCELLED" }
```
**Erreurs :** `422` : `CANNOT_CANCEL_APPROVED`

---

### GET /absence-types
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "label": "Congé payé", "color": "#4CAF50", "requires_document": false, "is_paid": true },
    { "id": 2, "label": "Maladie", "color": "#F44336", "requires_document": true, "is_paid": true },
    { "id": 3, "label": "Sans solde", "color": "#9E9E9E", "requires_document": false, "is_paid": false }
  ]
}
```

---

### POST /absence-types
```json
{ "label": "Maternité", "color": "#E91E63", "requires_document": true, "is_paid": true }
```
**Response 201 :** Retourne le type créé.

---

### PUT /absence-types/{id}
**Response 200 :** Retourne le type mis à jour.

---

### DELETE /absence-types/{id}
**Erreurs :** `409` : `TYPE_HAS_ABSENCES`
**Response 200 :**
```json
{ "message": "ABSENCE_TYPE_DELETED" }
```

---

## 6. AVANCES SUR SALAIRE

### GET /advances
**Query params :** `?employee_id=42&status=pending&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 301,
      "employee": { "id": 42, "name": "Ahmed Benali" },
      "amount": 15000,
      "amount_remaining": 10000,
      "reason": "Frais médicaux urgents",
      "status": "approved",
      "repayment_months": 3,
      "monthly_deduction": 5000,
      "approved_at": "2026-03-15T10:00:00Z"
    }
  ]
}
```

---

### POST /advances
**Request :**
```json
{
  "amount": 15000,
  "reason": "Frais médicaux urgents",
  "repayment_months": 3
}
```
**Response 201 :**
```json
{ "data": { "id": 301, "amount": 15000, "status": "pending", "repayment_months": 3 } }
```

---

### GET /advances/{id}
**Response 200 :** Retourne l'avance complète.

---

### PUT /advances/{id}/approve
**Request :**
```json
{ "repayment_months": 3, "comment": "Approuvé — déduction dès mai 2026" }
```
**Response 200 :**
```json
{ "data": { "id": 301, "status": "approved", "monthly_deduction": 5000 } }
```

---

### PUT /advances/{id}/reject
**Request :**
```json
{ "comment": "Avance déjà en cours — attendre le remboursement complet" }
```
**Response 200 :**
```json
{ "data": { "id": 301, "status": "rejected" } }
```

---

### PUT /advances/{id}/repayment
*Modifier le plan de remboursement*
**Request :**
```json
{ "repayment_months": 6, "comment": "Accord pour étalement sur 6 mois" }
```
**Response 200 :**
```json
{ "data": { "id": 301, "repayment_months": 6, "monthly_deduction": 2500 } }
```

---

## 7. TÂCHES

### GET /tasks
**Query params :** `?status=inprogress&assignee_id=42&priority=high&project_id=1&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 901,
      "title": "Finaliser le module mobile",
      "description": "Connecter tous les écrans Flutter à l'API réelle",
      "status": "inprogress",
      "priority": "high",
      "assignee": { "id": 42, "name": "Ahmed Benali", "photo_url": "..." },
      "project": { "id": 1, "name": "Leopardo Mobile v1" },
      "due_date": "2026-05-15T18:00:00Z",
      "checklist": [
        { "id": 1, "label": "Maquettes Flutter", "done": true },
        { "id": 2, "label": "Connexion API", "done": false }
      ],
      "comments_count": 3
    }
  ]
}
```

---

### POST /tasks
**Request :**
```json
{
  "title": "Rédiger les tests unitaires PayrollService",
  "description": "Couvrir les cas : net = brut - cotisations - IR - retenues",
  "assignee_id": 42,
  "project_id": 1,
  "priority": "high",
  "due_date": "2026-05-20T18:00:00Z",
  "checklist": [
    { "label": "Test calcul de base" },
    { "label": "Test avec avance" },
    { "label": "Test IR tranches" }
  ]
}
```
**Response 201 :** Retourne la tâche créée.

---

### GET /tasks/{id}
**Response 200 :** Retourne la tâche complète avec checklist et commentaires.

---

### PUT /tasks/{id}
**Request :** (champs partiels acceptés)
```json
{
  "title": "Rédiger et valider les tests unitaires PayrollService",
  "priority": "urgent",
  "due_date": "2026-05-18T18:00:00Z"
}
```
**Response 200 :** Retourne la tâche mise à jour.

---

### PUT /tasks/{id}/status
*Mise à jour du statut par l'employé assigné*
**Request :**
```json
{ "status": "done", "checklist": [{ "id": 1, "done": true }, { "id": 2, "done": true }] }
```
**Response 200 :** Retourne la tâche mise à jour.

---

### DELETE /tasks/{id}
**Response 200 :**
```json
{ "message": "TASK_DELETED" }
```

---

### GET /tasks/{id}/comments
**Response 200 :**
```json
{
  "data": [
    {
      "id": 55,
      "author": { "id": 5, "name": "Hamid Directeur", "photo_url": "..." },
      "content": "Pensez à couvrir les tranches d'IR spécifiques à l'Algérie",
      "created_at": "2026-04-10T14:30:00Z"
    }
  ]
}
```

---

### POST /tasks/{id}/comments
**Request :**
```json
{ "content": "Fait — 3 scénarios de tranches IR couverts avec valeurs réelles." }
```
**Response 201 :** Retourne le commentaire créé.

---

### GET /projects
**Response 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Leopardo Mobile v1",
      "status": "active",
      "manager": { "id": 5, "name": "Hamid Directeur" },
      "tasks_count": 12,
      "tasks_done": 7
    }
  ]
}
```

---

### POST /projects
```json
{ "name": "Refonte Site Web", "manager_id": 5 }
```
**Response 201 :** Retourne le projet créé.

---

### PUT /projects/{id}
**Response 200 :** Retourne le projet mis à jour.

---

## 8. PAIE

### POST /payroll/calculate
*Simulation — ne valide pas, ne génère pas de PDF*
**Request :**
```json
{ "employee_ids": [42, 43, 44], "month": 4, "year": 2026 }
```
**Response 200 :**
```json
{
  "data": [
    {
      "employee_id": 42,
      "gross_salary": 75000,
      "overtime_pay": 2500,
      "deductions": {
        "social_security": 7500,
        "income_tax": 4250,
        "penalty_deduction": 0,
        "advance_deduction": 5000,
        "absence_deduction": 0
      },
      "net_salary": 60750,
      "advance_remaining_after": 5000
    }
  ]
}
```

---

### POST /payroll/validate
*Valide et génère les bulletins PDF en arrière-plan*
**Request :**
```json
{ "employee_ids": [42, 43, 44], "month": 4, "year": 2026 }
```
**Response 202 :**
```json
{
  "data": {
    "job_id": "payroll_job_abc123",
    "message": "PAYROLL_GENERATION_QUEUED",
    "employees_count": 3
  }
}
```

---

### GET /payroll
**Query params :** `?month=4&year=2026&status=validated&employee_id=42&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 401,
      "employee": { "id": 42, "name": "Ahmed Benali", "matricule": "EMP-0042" },
      "period": "Avril 2026",
      "month": 4,
      "year": 2026,
      "gross_salary": 75000,
      "net_salary": 60750,
      "status": "validated",
      "pdf_url": "https://api.leopardo-rh.com/storage/payslips/2026-04-42.pdf"
    }
  ]
}
```

---

### GET /payroll/{id}
**Response 200 :**
```json
{
  "data": {
    "id": 401,
    "employee": { "id": 42, "name": "Ahmed Benali", "matricule": "EMP-0042", "iban": "DZ..." },
    "period": "Avril 2026",
    "month": 4,
    "year": 2026,
    "gross_salary": 75000,
    "overtime_pay": 2500,
    "deductions": {
      "social_security": 7500,
      "income_tax": 4250,
      "penalty_deduction": 0,
      "advance_deduction": 5000,
      "absence_deduction": 0
    },
    "net_salary": 60750,
    "status": "validated",
    "pdf_url": "https://api.leopardo-rh.com/storage/payslips/2026-04-42.pdf",
    "validated_at": "2026-04-28T10:00:00Z"
  }
}
```

---

### GET /payroll/{id}/pdf
*Télécharger le PDF du bulletin*
**Response 200 :** Fichier PDF (Content-Type: application/pdf)

---

### GET /payroll/status/{job_id}
**Response 200 :**
```json
{
  "data": {
    "job_id": "payroll_job_abc123",
    "status": "processing",
    "progress": 67,
    "completed": 2,
    "total": 3,
    "errors": []
  }
}
```
**Statuts possibles :** `queued`, `processing`, `completed`, `failed`

---

### GET /payroll/export-bank
**Query params :** `?month=4&year=2026`
**Response 200 :** Fichier CSV/Excel (virement bancaire format CITI ou standard)

---

## 9. ÉVALUATIONS

### GET /evaluations
**Query params :** `?employee_id=42&period=2026-Q1&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 201,
      "employee": { "id": 42, "name": "Ahmed Benali" },
      "period": "2026-Q1",
      "overall_score": 4.2,
      "status": "completed",
      "self_eval_done": true,
      "evaluated_by": { "id": 5, "name": "Hamid Directeur" },
      "created_at": "2026-04-01T09:00:00Z"
    }
  ]
}
```

---

### POST /evaluations
**Request :**
```json
{
  "employee_id": 42,
  "period": "2026-Q2",
  "criteria": [
    { "name": "Qualité du travail", "score": 4, "comment": "Livraisons propres et documentées" },
    { "name": "Ponctualité", "score": 5, "comment": "Jamais en retard" },
    { "name": "Travail en équipe", "score": 4, "comment": "Bon esprit d'équipe" }
  ],
  "global_comment": "Excellent employé — candidat pour promotion"
}
```
**Response 201 :** Retourne l'évaluation créée avec `overall_score` calculé.

---

### GET /evaluations/{employee_id}
*Toutes les évaluations d'un employé*
**Response 200 :** Liste des évaluations de cet employé.

---

### PUT /evaluations/{id}/self
*Auto-évaluation par l'employé*
**Request :**
```json
{
  "criteria": [
    { "name": "Qualité du travail", "score": 4, "comment": "Je pense avoir bien livré ce trimestre" }
  ],
  "global_comment": "Je souhaite évoluer vers une responsabilité de lead technique"
}
```
**Response 200 :** Retourne l'évaluation mise à jour avec `self_eval_done: true`.

---

## 10. RAPPORTS

### GET /reports/attendance
**Query params :** `?month=4&year=2026&department_id=3`
**Response 200 :**
```json
{
  "data": {
    "period": "Avril 2026",
    "department": "Développement",
    "summary": {
      "total_employees": 18,
      "total_working_days": 22,
      "average_punctuality_rate": 94.5,
      "total_late_occurrences": 23,
      "total_absences_days": 18
    },
    "by_employee": [
      {
        "employee": { "id": 42, "name": "Ahmed Benali" },
        "days_present": 21,
        "days_absent": 1,
        "late_occurrences": 0,
        "total_hours": 168.5,
        "overtime_hours": 8.5
      }
    ]
  }
}
```

---

### GET /reports/absences
**Query params :** `?year=2026&department_id=3`
**Response 200 :**
```json
{
  "data": {
    "period": "2026",
    "summary": {
      "total_absence_days": 145,
      "by_type": [
        { "type": "Congé payé", "days": 98, "percentage": 67.6 },
        { "type": "Maladie", "days": 32, "percentage": 22.1 }
      ]
    }
  }
}
```

---

### GET /reports/payroll
**Query params :** `?month=4&year=2026`
**Response 200 :**
```json
{
  "data": {
    "period": "Avril 2026",
    "summary": {
      "total_gross": 3250000,
      "total_net": 2687000,
      "total_social_security": 325000,
      "total_income_tax": 184000,
      "total_advance_deductions": 54000,
      "employees_count": 47
    }
  }
}
```

---

### GET /reports/performance
**Query params :** `?period=2026-Q1&department_id=3`
**Response 200 :**
```json
{
  "data": {
    "period": "2026-Q1",
    "summary": {
      "tasks_total": 124,
      "tasks_completed": 98,
      "tasks_overdue": 8,
      "completion_rate": 79.0,
      "average_evaluation_score": 4.1
    }
  }
}
```

---

## 11. NOTIFICATIONS

### GET /notifications
**Query params :** `?is_read=false&type=absence_approved&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": 551,
      "type": "absence_approved",
      "title": "Absence approuvée",
      "body": "Votre demande de congé du 01/06 au 15/06 a été approuvée.",
      "data": { "absence_id": 801 },
      "is_read": false,
      "created_at": "2026-04-10T11:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 4 }
}
```
**Types possibles :** `absence_approved`, `absence_rejected`, `advance_approved`, `advance_rejected`, `task_assigned`, `task_commented`, `payslip_available`, `evaluation_received`

---

### PUT /notifications/{id}/read
**Response 200 :**
```json
{ "message": "NOTIFICATION_MARKED_READ" }
```

---

### PUT /notifications/read-all
**Response 200 :**
```json
{ "message": "ALL_NOTIFICATIONS_MARKED_READ" }
```

---

### GET /notifications/count
*Pour le badge cloche — appelé toutes les 5 minutes*
**Response 200 :**
```json
{ "data": { "unread_count": 3 } }
```

---

## 12. PARAMÈTRES ENTREPRISE

### GET /settings
**Response 200 :**
```json
{
  "data": {
    "company_name": "TechCorp SPA",
    "language": "fr",
    "timezone": "Africa/Algiers",
    "currency": "DZD",
    "hr_model": "algeria",
    "payroll_day": 28,
    "leave_accrual_monthly": 2.5,
    "max_carry_over_days": 15,
    "overtime_rate_multiplier": 1.5,
    "late_tolerance_minutes": 10,
    "logo_url": "https://api.leopardo-rh.com/storage/logos/techcorp.png"
  }
}
```

---

### PUT /settings
**Request :** (champs partiels acceptés)
```json
{
  "payroll_day": 30,
  "leave_accrual_monthly": 2.5,
  "late_tolerance_minutes": 15
}
```
**Response 200 :** Retourne tous les paramètres mis à jour.

---

### GET /settings/hr-models
**Response 200 :**
```json
{
  "data": [
    { "code": "algeria", "name": "Algérie (DAS, IRG)", "currency": "DZD" },
    { "code": "morocco", "name": "Maroc (CNSS, IR)", "currency": "MAD" },
    { "code": "tunisia", "name": "Tunisie (CNSS, IRPP)", "currency": "TND" },
    { "code": "turkey", "name": "Türkiye (SGK, Gelir Vergisi)", "currency": "TRY" },
    { "code": "france", "name": "France (URSSAF, IR)", "currency": "EUR" }
  ]
}
```

---

### PUT /settings/apply-hr-model
**Request :**
```json
{ "hr_model": "morocco" }
```
**Response 200 :**
```json
{ "message": "HR_MODEL_APPLIED", "data": { "hr_model": "morocco", "currency": "MAD" } }
```

---

## 13. APPAREILS BIOMÉTRIQUES (ZKTeco / QR)

### GET /devices
**Response 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "name": "ZKTeco Entrée principale",
      "type": "zkteco",
      "model": "ZK-X800",
      "ip_address": "192.168.1.50",
      "site": { "id": 1, "name": "Siège social" },
      "status": "online",
      "last_sync_at": "2026-04-15T08:00:00Z"
    }
  ]
}
```

---

### POST /devices
```json
{
  "name": "QR Code Salle B",
  "type": "qr",
  "site_id": 1
}
```
**Response 201 :** Retourne l'appareil créé avec son `device_token`.

---

### PUT /devices/{id}
```json
{ "name": "ZKTeco Entrée Nord", "ip_address": "192.168.1.55" }
```
**Response 200 :** Retourne l'appareil mis à jour.

---

### DELETE /devices/{id}
**Response 200 :**
```json
{ "message": "DEVICE_DELETED" }
```

### POST /devices/{id}/rotate-token
**Description :** Renouvelle le token d'authentification d'un appareil ZKTeco (en cas de compromission). Réservé aux rôles `principal` et `rh`.

**Response 200 :**
```json
{
  "data": {
    "device_id": 3,
    "new_token": "lrh_tkn_9f3a...",
    "rotated_at": "2026-04-01T10:00:00Z",
    "message": "Token renouvelé. Reconfigurer l'appareil avec le nouveau token."
  }
}
```
**Response 404 :** Device introuvable.
**Response 403 :** Rôle insuffisant.

---

### POST /devices/{id}/test-connection
**Response 200 :**
```json
{ "data": { "status": "reachable", "latency_ms": 23, "firmware": "ZEM800-2.4.13" } }
```
**Response 200 (hors ligne) :**
```json
{ "data": { "status": "unreachable", "error": "CONNECTION_TIMEOUT" } }
```

---

## 14. SUPER ADMIN

### GET /admin/companies
**Query params :** `?status=active&plan=business&search=TechCorp&page=1`
**Response 200 :**
```json
{
  "data": [
    {
      "id": "550e8400-...",
      "name": "TechCorp SPA",
      "plan": { "name": "Business" },
      "employees_count": 47,
      "status": "active",
      "subscription_ends_at": "2026-12-31",
      "tenancy_type": "schema",
      "created_at": "2025-01-15T00:00:00Z"
    }
  ]
}
```

---

### POST /admin/companies
**Request :**
```json
{
  "name": "NouvelleEntreprise SARL",
  "email": "admin@nouvelle-entreprise.com",
  "plan_id": 2,
  "language": "fr",
  "timezone": "Africa/Algiers",
  "currency": "DZD",
  "hr_model": "algeria",
  "tenancy_type": "schema",
  "admin_first_name": "Karim",
  "admin_last_name": "Boumediene",
  "admin_password": "MotDePasse123!"
}
```
**Response 201 :** Retourne la company créée avec le schéma provisioned.

---

### GET /admin/companies/{id}
**Response 200 :** Retourne la company complète avec stats.

---

### PUT /admin/companies/{id}
**Response 200 :** Retourne la company mise à jour.

---

### PUT /admin/companies/{id}/suspend
**Request :**
```json
{ "reason": "Impayé depuis 60 jours" }
```
**Response 200 :**
```json
{ "message": "COMPANY_SUSPENDED" }
```

---

### PUT /admin/companies/{id}/reactivate
**Response 200 :**
```json
{ "message": "COMPANY_REACTIVATED" }
```

---

### PUT /admin/companies/{id}/extend
**Request :**
```json
{ "days": 30, "reason": "Geste commercial — retard livraison feature" }
```
**Response 200 :**
```json
{ "data": { "subscription_ends_at": "2027-01-30" } }
```

---

### GET /admin/plans
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Starter", "price_monthly": 29, "max_employees": 20, "is_active": true },
    { "id": 2, "name": "Business", "price_monthly": 79, "max_employees": 200, "is_active": true },
    { "id": 3, "name": "Enterprise", "price_monthly": 199, "max_employees": null, "is_active": true }
  ]
}
```

---

### POST /admin/plans
```json
{ "name": "Pro", "price_monthly": 49, "price_yearly": 490, "max_employees": 50, "features": { "biometric": true, "tasks": false } }
```
**Response 201 :** Retourne le plan créé.

---

### PUT /admin/plans/{id}
**Response 200 :** Retourne le plan mis à jour.

---

### GET /admin/billing
**Query params :** `?company_id=xxx&month=4&year=2026`
**Response 200 :** Liste des transactions de facturation.

---

### POST /admin/billing/invoice
**Request :**
```json
{ "company_id": "550e8400-...", "amount": 79, "description": "Abonnement Business Avril 2026" }
```
**Response 201 :** Retourne la facture créée.

---

### GET /admin/stats
**Response 200 :**
```json
{
  "data": {
    "total_companies": 42,
    "active_companies": 38,
    "total_employees": 1847,
    "mrr": 3241,
    "new_signups_this_month": 5,
    "churn_this_month": 1,
    "by_plan": [
      { "plan": "Starter", "count": 22 },
      { "plan": "Business", "count": 14 },
      { "plan": "Enterprise", "count": 6 }
    ]
  }
}
```

---

## 9. PROFIL EMPLOYÉ (3 endpoints)

### GET /profile
**Response 200 :**
```json
{
  "data": {
    "id": 42,
    "first_name": "Ahmed", "last_name": "Benali",
    "email": "ahmed.benali@corp.com",
    "phone": "+213 555 123 456",
    "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg",
    "matricule": "EMP-0042",
    "hire_date": "2023-01-15",
    "contract_type": "CDI",
    "department": { "id": 3, "name": "Développement" },
    "position": { "id": 7, "name": "Développeur Senior" },
    "leave_balance": 12.5
  }
}
```

### PUT /profile
**Request :**
```json
{ "phone": "+213 555 999 888", "preferred_language": "fr" }
```
**Response 200 :** Profil mis à jour (même format que GET /profile)
**Note :** L'employé ne peut modifier que : `phone`, `preferred_language`. Pas l'email ni le salaire.

### POST /profile/photo
**Request :** `multipart/form-data` — champ `photo` (JPEG/PNG, max 2MB)
**Response 200 :**
```json
{ "data": { "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg" } }
```

### PUT /profile/password
**Request :**
```json
{ "current_password": "OldPass123!", "password": "NewPass456!", "password_confirmation": "NewPass456!" }
```
**Response 200 :** `{ "message": "Mot de passe mis à jour" }`
**Erreurs :** `422 CURRENT_PASSWORD_WRONG`

---

## 10. NOTIFICATIONS SSE — WEB (1 endpoint)

### GET /notifications/stream
**Headers :** `Accept: text/event-stream`
**Auth :** Bearer token (Sanctum)
**Response :** Stream SSE continu — `Content-Type: text/event-stream`
```
event: notification
data: {"id":99,"type":"absence.approved","title":"Congé approuvé","body":"Votre demande...","data":{"absence_id":12}}

event: heartbeat
data: {"ts":"2026-04-15T10:00:30Z"}
```
**Note Nginx :** `proxy_buffering off` obligatoire sur `/api/v1/notifications/stream`
**Voir spec complète :** `12_notifications/14_NOTIFICATION_TEMPLATES.md`

---

## 11. ONBOARDING (2 endpoints)

### GET /onboarding/status
**Response 200 :**
```json
{
  "data": {
    "completed": false,
    "current_step": 1,
    "steps": [
      { "id": 1, "title": "Ajoutez vos employés", "completed": false, "required": true },
      { "id": 2, "title": "Configurez votre planning", "completed": false, "required": true },
      { "id": 3, "title": "Téléchargez l'app mobile", "completed": false, "required": false },
      { "id": 4, "title": "Premier pointage de test", "completed": false, "required": false }
    ]
  }
}
```

### POST /onboarding/complete
**Request :** `{}` (aucun body)
**Response 200 :** `{ "message": "Onboarding complété" }`
**Effet :** Positionne `company_settings.onboarding_completed = true`

---

## 12. ADMIN — LANGUES & MODÈLES RH (4 endpoints)

### GET /admin/languages
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "code": "fr", "name_fr": "Français", "name_native": "Français", "is_rtl": false, "is_active": true },
    { "id": 2, "code": "ar", "name_fr": "Arabe", "name_native": "العربية", "is_rtl": true, "is_active": true },
    { "id": 3, "code": "en", "name_fr": "Anglais", "name_native": "English", "is_rtl": false, "is_active": true },
    { "id": 4, "code": "tr", "name_fr": "Turc", "name_native": "Türkçe", "is_rtl": false, "is_active": true }
  ]
}
```

### PUT /admin/languages/{id}
**Request :** `{ "is_active": false }`
**Response 200 :** Langue mise à jour

### GET /admin/hr-models
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "country_code": "DZ", "name": "Droit du travail algérien" },
    { "id": 2, "country_code": "MA", "name": "Droit du travail marocain" },
    { "id": 3, "country_code": "TN", "name": "Droit du travail tunisien" },
    { "id": 4, "country_code": "FR", "name": "Droit du travail français" },
    { "id": 5, "country_code": "TR", "name": "Droit du travail turc" },
    { "id": 6, "country_code": "SN", "name": "Droit du travail sénégalais" },
    { "id": 7, "country_code": "CI", "name": "Droit du travail ivoirien" }
  ]
}
```

### GET /admin/hr-models/{country_code}
**Response 200 :** Modèle RH complet avec `cotisations`, `ir_brackets`, `leave_rules`, `holiday_calendar`

---

## 13. EMPLOYES - ESTIMATIONS RAPIDES (2 endpoints)

### GET /employees/{id}/daily-summary?date=2026-04-04
**Description :** Resume journalier d'un employe avec estimation du montant du jour.

**Response 200 :**
```json
{
  "data": {
    "employee_id": 12,
    "name": "Ahmed Yilmaz",
    "date": "2026-04-04",
    "check_in": "08:30",
    "check_out": "18:15",
    "hours_worked": 9.75,
    "overtime_hours": 1.75,
    "daily_wage": 487.50,
    "wage_breakdown": {
      "base": 400.00,
      "overtime": 87.50
    },
    "status": "complete"
  }
}
```

**Regles :**
- Roles autorises : `manager` (`principal`, `rh`, `dept`, `superviseur`) + employe sur lui-meme.
- Le montant retourne est une estimation operationnelle, pas un bulletin officiel.
- Les dedications fiscales et sociales definitives restent calculees en fin de mois.

### GET /employees/{id}/quick-estimate?from=2026-04-01&to=2026-04-17
**Description :** Simulation rapide sur periode libre (sortie employee, litige, avance de calcul).

**Response 200 :**
```json
{
  "data": {
    "employee_id": 12,
    "name": "Ahmed Yilmaz",
    "period": "2026-04-01 -> 2026-04-17",
    "working_days_in_period": 12,
    "days_present": 11,
    "days_absent": 1,
    "total_hours_worked": 89.5,
    "overtime_hours": 5.5,
    "estimated_gross": 5230.00,
    "estimated_deductions": 523.00,
    "estimated_net": 4707.00,
    "currency": "TRY",
    "disclaimer": "Estimation - le net exact inclura les cotisations et IR calcules en fin de mois"
  }
}
```

**Regles :**
- `from` et `to` requis, `from <= to`, plage max recommandee 62 jours.
- Role autorise : manager uniquement.
- Endpoint en lecture seule, sans generation de paie ni ecriture DB.
