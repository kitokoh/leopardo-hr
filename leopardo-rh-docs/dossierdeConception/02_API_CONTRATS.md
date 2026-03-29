# API CONTRATS — Leopardo RH
# Payloads JSON exacts par endpoint
# Version 1.0 | Mars 2026
# Référence commune Backend (Claude Code) ↔ Mobile (Jules)

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

## 1. AUTHENTIFICATION

### POST /auth/login
**Note sur l'implémentation :** Le serveur utilise la table `user_lookups` pour identifier instantanément l'entreprise et le schéma de l'utilisateur sans avoir à parcourir tous les schémas de la base de données.

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
**Response 401 (mauvais identifiants) :**
```json
{
  "error": "INVALID_CREDENTIALS",
  "message": "Email ou mot de passe incorrect"
}
```
**Response 423 (compte bloqué) :**
```json
{
  "error": "ACCOUNT_LOCKED",
  "message": "Compte bloqué après 5 tentatives. Réessayez dans 15 minutes.",
  "retry_after": 900
}
```

---

### POST /auth/logout
**Request :** *(aucun body — token révoqué depuis le header)*
**Response 200 :**
```json
{ "message": "Déconnexion réussie" }
```

---

### POST /auth/refresh
**Request :** *(aucun body — utilise le token actuel)*
**Response 200 :**
```json
{
  "data": {
    "token": "2|NouveauTokenAbCdEf",
    "expires_at": "2026-10-15T10:00:00Z"
  }
}
```

---

### POST /auth/forgot-password
**Request :**
```json
{ "email": "ahmed.benali@entreprise.com" }
```
**Response 200 (toujours — même si email inconnu pour sécurité) :**
```json
{ "message": "Si cet email existe, un lien de réinitialisation a été envoyé" }
```

---

### POST /auth/reset-password
**Request :**
```json
{
  "token": "abc123def456",
  "email": "ahmed.benali@entreprise.com",
  "password": "NouveauMotDePasse123!",
  "password_confirmation": "NouveauMotDePasse123!"
}
```
**Response 200 :**
```json
{ "message": "Mot de passe réinitialisé avec succès" }
```

---

### POST /auth/device/fcm
**Request :** `{ "fcm_token": "new_token_abc123", "platform": "android" }`
**Response 200 :** `{ "message": "Token FCM enregistré" }`

---

### DELETE /auth/device/fcm
**Request :** `{ "fcm_token": "token_abc123" }`
**Response 200 :** `{ "message": "Token FCM supprimé" }`

---

### GET /auth/me
**Auth :** Bearer token
**Response 200 :**
```json
{
  "data": {
    "id": 42,
    "first_name": "Ahmed",
    "last_name": "Benali",
    "email": "ahmed.benali@entreprise.com",
    "role": "employee",
    "manager_role": null,
    "photo_url": "https://api.leopardo-rh.com/storage/photos/42.jpg",
    "company": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "TechCorp SPA",
      "language": "fr",
      "timezone": "Africa/Algiers",
      "currency": "DZD"
    }
  }
}
```

---

### GET /attendance/today
**Auth :** Bearer token
**Response 200 (si pointé) :**
```json
{
  "data": {
    "id": 1547,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_out": null,
    "status": "incomplete",
    "method": "mobile"
  }
}
```
**Response 200 (si non pointé) :**
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

---

## 2. SUPER ADMIN — ENTREPRISES

### GET /admin/companies
**Params :** `?page=1&per_page=15&status=active&search=techcorp`
**Response 200 :**
```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "TechCorp SPA",
      "sector": "Technologie",
      "country": "DZ",
      "city": "Alger",
      "language": "fr",
      "currency": "DZD",
      "timezone": "Africa/Algiers",
      "plan": { "id": 2, "name": "Business" },
      "status": "active",
      "employees_count": 47,
      "subscription_start": "2026-01-01",
      "subscription_end": "2026-12-31",
      "logo_url": null,
      "created_at": "2026-01-01T09:00:00Z"
    }
  ],
  "meta": {
    "total": 23,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2
  }
}
```

---

### POST /admin/companies
**Request :**
```json
{
  "name": "BTP Maghreb EURL",
  "sector": "Construction",
  "country": "DZ",
  "city": "Oran",
  "address": "12 Rue des Artisans, Oran",
  "email": "admin@btpmaghreb.dz",
  "phone": "+213 41 234567",
  "language": "ar",
  "currency": "DZD",
  "timezone": "Africa/Algiers",
  "plan_id": 1,
  "trial_days": 14,
  "hr_model_country": "DZ",
  "logo": "(base64 ou null)",
  "notes": "Client référé par TechCorp",
  "first_manager": {
    "first_name": "Karim",
    "last_name": "Mansouri",
    "email": "k.mansouri@btpmaghreb.dz",
    "phone": "+213 661 234567"
  }
}
```
**Response 201 :**
```json
{
  "data": {
    "id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
    "name": "BTP Maghreb EURL",
    "schema_name": "company_7c9e66797425",
    "status": "trial",
    "subscription_end": "2026-04-11",
    "first_manager_email": "k.mansouri@btpmaghreb.dz"
  },
  "message": "Entreprise créée. Email de bienvenue envoyé à k.mansouri@btpmaghreb.dz"
}
```

---

### PUT /admin/companies/{id}/suspend
**Request :**
```json
{ "reason": "Abonnement impayé depuis 30 jours" }
```
**Response 200 :**
```json
{ "message": "Entreprise suspendue. Accès bloqué." }
```

---

## 3. EMPLOYÉS

### GET /employees
**Params :** `?page=1&per_page=15&department_id=3&status=active&search=ahmed&sort=last_name`
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
      "phone": "+213 661 234567",
      "role": "employee",
      "department": { "id": 3, "name": "Commercial" },
      "position": { "id": 7, "name": "Commercial Senior" },
      "schedule": { "id": 1, "name": "Standard 8h-17h" },
      "manager": { "id": 5, "first_name": "Sara", "last_name": "Meziani" },
      "contract_type": "CDI",
      "contract_start": "2024-03-01",
      "contract_end": null,
      "leave_balance": 12.5,
      "status": "active",
      "photo_url": null
    }
  ],
  "meta": { "total": 47, "per_page": 15, "current_page": 1, "last_page": 4 }
}
```

---

### GET /employees/{id}
**Response 200 :** *(Format complet identique à GET /employees mais pour un seul objet)*

### PUT /employees/{id}
**Request :** *(Mêmes champs que POST /employees, tous optionnels)*
**Response 200 :** `{ "data": { "id": 42, ... }, "message": "Employé mis à jour" }`

### DELETE /employees/{id}
**Response 200 :** `{ "message": "Employé archivé avec succès" }`

### POST /employees/import
**Request :** `multipart/form-data` (file: employees.csv)
**Response 200 :** `{ "imported": 45, "errors": [] }`

### GET /employees/{id}/payslips
**Response 200 :** `{ "data": [ { "id": 101, "period": "2026-03", "net_salary": 75000, "status": "validated" } ] }`

---

## 4. POINTAGE

### POST /attendance/check-in
**Request :**
```json
{
  "gps_lat": 36.7372,
  "gps_lng": 3.0869,
  "photo": "(base64 jpg — null si non activé)"
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 1547,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_in_display": "07:58",
    "status": "ontime",
    "method": "mobile",
    "gps_valid": true,
    "message": "Pointage d'arrivée enregistré"
  }
}
```
**Response 422 (hors zone GPS) :**
```json
{
  "error": "GPS_OUT_OF_ZONE",
  "message": "Vous n'êtes pas dans la zone de travail autorisée (distance : 450m, maximum : 100m)"
}
```
**Response 409 (déjà pointé arrivée) :**
```json
{
  "error": "ALREADY_CHECKED_IN",
  "message": "Vous avez déjà pointé votre arrivée aujourd'hui à 07:58. Voulez-vous pointer votre départ ?",
  "check_in": "2026-04-15T07:58:00Z"
}
```

---

### POST /attendance/check-out
**Request :**
```json
{
  "gps_lat": 36.7372,
  "gps_lng": 3.0869,
  "photo": null
}
```
**Response 200 :**
```json
{
  "data": {
    "id": 1547,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_out": "2026-04-15T17:02:00Z",
    "hours_worked": 8.07,
    "overtime_hours": 0.07,
    "status": "ontime"
  }
}
```

---

### POST /attendance/qrcode
**Request :**
```json
{ "qr_token": "LEOPARDO-QR-550e8400-e29b-41d4-a716" }
```
**Response :** *(même format que check-in / check-out selon contexte)*

---

### POST /attendance/biometric *(webhook ZKTeco)*
**Auth :** `X-Device-Token: {token_haché}` *(pas de Bearer)*
**Request :**
```json
{
  "device_serial": "ZK-ABC123",
  "employee_zkteco_id": "0042",
  "timestamp": "2026-04-15T07:57:45Z",
  "direction": "in"
}
```
**Response 200 :**
```json
{ "status": "recorded", "attendance_log_id": 1547 }
```

---

### POST /devices/{id}/test-connection
**Response 200 :** `{ "status": "online", "latency_ms": 120 }`

---

### GET /attendance
**Params :** `?employee_id=42&from=2026-04-01&to=2026-04-30`

> ⚠️ **Convention dates — valable sur TOUS les endpoints :**
> Toutes les valeurs timestamp sont en **ISO 8601 UTC complet** (`2026-04-15T07:58:00Z`).
> Le formatage pour l'affichage (HH:mm, dd/MM/yyyy, etc.) se fait **uniquement côté Flutter**
> avec `DateFormat('HH:mm').format(dateTime)` — jamais côté API.

**Response 200 :**
```json
{
  "data": [
    {
      "id": 1547,
      "date": "2026-04-15",
      "check_in": "2026-04-15T07:58:00Z",
      "check_out": "2026-04-15T17:02:00Z",
      "hours_worked": 8.07,
      "overtime_hours": 0.07,
      "status": "ontime",
      "method": "mobile",
      "is_manual_edit": false
    },
    {
      "id": 1546,
      "date": "2026-04-14",
      "check_in": "2026-04-14T08:22:00Z",
      "check_out": "2026-04-14T17:00:00Z",
      "hours_worked": 7.63,
      "overtime_hours": 0,
      "status": "late",
      "method": "qrcode",
      "is_manual_edit": false
    }
  ]
}
```

---

### PUT /attendance/{id} *(correction manuelle gestionnaire)*
**Request :**
```json
{
  "check_in": "08:00",
  "check_out": "17:00",
  "edit_reason": "L'employé a oublié de pointer — confirmé par téléphone"
}
```
**Response 200 :**
```json
{
  "data": { "id": 1547, "check_in": "08:00", "check_out": "17:00", "is_manual_edit": true },
  "message": "Pointage corrigé et tracé dans le journal d'audit"
}
```

---

## 5. ABSENCES ET CONGÉS

### GET /absences
**Params :** `?status=pending&employee_id=42&from=2026-01-01&to=2026-12-31`
**Response 200 :** `{ "data": [ ... ] }`

### GET /absences/{id}
**Response 200 :** `{ "data": { "id": 89, ... } }`

### PUT /absences/{id}/cancel
**Response 200 :** `{ "message": "Demande annulée par l'employé" }`

### GET /absence-types
**Response 200 :** `{ "data": [ { "id": 1, "label": "Congé payé", "deducts_leave": true } ] }`

### POST /absences
**Request :**
```json
{
  "type_id": 1,
  "start_date": "2026-05-10",
  "end_date": "2026-05-17",
  "comment": "Congé annuel planifié",
  "attachment": null
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 89,
    "type": { "id": 1, "label": "Congé payé", "color": "#4CAF50" },
    "start_date": "2026-05-10",
    "end_date": "2026-05-17",
    "days_count": 6,
    "status": "pending",
    "leave_balance_before": 12.5,
    "leave_balance_after_if_approved": 6.5
  },
  "message": "Demande soumise. Votre gestionnaire a été notifié."
}
```
**Response 422 (solde insuffisant) :**
```json
{
  "error": "INSUFFICIENT_LEAVE_BALANCE",
  "message": "Solde insuffisant. Disponible : 4 jours, demandés : 6 jours."
}
```
**Response 422 (préavis insuffisant) :**
```json
{
  "error": "INSUFFICIENT_NOTICE",
  "message": "Ce type de congé nécessite un préavis de 3 jours minimum. Date de début trop proche."
}
```

---

### PUT /absences/{id}/approve *(gestionnaire)*
**Request :**
```json
{ "comment": "Approuvé — bon travail ce mois-ci." }
```
**Response 200 :**
```json
{
  "data": { "id": 89, "status": "approved" },
  "message": "Congé approuvé. L'employé a été notifié."
}
```

---

### PUT /absences/{id}/reject *(gestionnaire)*
**Request :**
```json
{ "comment": "Effectif insuffisant sur cette période. Merci de proposer d'autres dates." }
```
**Response 200 :**
```json
{
  "data": { "id": 89, "status": "rejected" },
  "message": "Congé refusé. L'employé a été notifié avec le motif."
}
```

---

## 6. AVANCES SUR SALAIRE

### GET /advances
**Params :** `?status=pending&employee_id=42`

### GET /advances/{id}
**Response 200 :** `{ "data": { "id": 23, ... } }`

### PUT /advances/{id}/reject
**Request :** `{ "comment": "Raison du refus" }`
**Response 200 :** `{ "message": "Demande d'avance refusée" }`

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
{
  "data": {
    "id": 23,
    "amount": 15000,
    "status": "pending",
    "repayment_plan": [
      { "month": "2026-05", "amount": 5000 },
      { "month": "2026-06", "amount": 5000 },
      { "month": "2026-07", "amount": 5000 }
    ],
    "salary_base": 75000,
    "max_allowed": 37500
  },
  "message": "Demande d'avance soumise. Votre gestionnaire a été notifié."
}
```
**Response 422 :**
```json
{
  "error": "ADVANCE_LIMIT_EXCEEDED",
  "message": "Montant demandé (15 000 DA) supérieur au maximum autorisé (50% du salaire = 37 500 DA)"
}
```

---

### PUT /advances/{id}/approve *(gestionnaire)*
**Request :**
```json
{
  "amount": 15000,
  "repayment_plan": [
    { "month": "2026-05", "amount": 5000 },
    { "month": "2026-06", "amount": 5000 },
    { "month": "2026-07", "amount": 5000 }
  ],
  "comment": "Approuvé — remboursement sur 3 mois."
}
```
**Response 200 :**
```json
{
  "data": { "id": 23, "status": "approved", "amount_remaining": 15000 },
  "message": "Avance approuvée et plan de remboursement activé."
}
```

> ⚠️ **Workflow de clôture automatique des avances — règle pour PayrollService :**
> À chaque calcul de paie mensuel, pour chaque avance `status=approved` de l'employé :
> 1. Vérifier si le mois courant figure dans `repayment_plan` et `paid = false`
> 2. Déduire la mensualité du `net_salary` → renseigner `advance_deduction` dans `payrolls`
> 3. Marquer la mensualité `paid = true` dans le JSON `repayment_plan`
> 4. Mettre à jour `amount_remaining -= mensualité`
> 5. Si `amount_remaining <= 0` → passer `status = 'repaid'` dans la même transaction
> Tout se passe dans `DB::transaction()` pour garantir la cohérence.

---

## 7. TÂCHES

### POST /tasks *(gestionnaire)*
**Request :**
```json
{
  "title": "Préparer le rapport commercial Q1 2026",
  "description": "Consolider les chiffres de ventes Q1 et préparer le deck PowerPoint pour la réunion du 20 avril.",
  "assigned_to": [42, 45],
  "due_date": "2026-04-18T17:00:00",
  "priority": "high",
  "category": "Commercial",
  "project_id": null,
  "checklist": [
    { "label": "Collecter les données CRM", "done": false },
    { "label": "Calculer les KPIs", "done": false },
    { "label": "Préparer le PowerPoint", "done": false },
    { "label": "Envoyer pour révision", "done": false }
  ],
  "visibility": "visible"
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 156,
    "title": "Préparer le rapport commercial Q1 2026",
    "status": "todo",
    "priority": "high",
    "due_date": "2026-04-18T17:00:00Z",
    "assigned_to": [
      { "id": 42, "name": "Ahmed Benali" },
      { "id": 45, "name": "Lina Hamidi" }
    ]
  },
  "message": "Tâche créée. Les employés assignés ont été notifiés."
}
```

---

### PUT /tasks/{id}/status *(employé)*
**Request :**
```json
{
  "status": "review",
  "comment": "Rapport finalisé et prêt pour validation. Lien vers le fichier partagé dans les commentaires."
}
```
**Response 200 :**
```json
{
  "data": { "id": 156, "status": "review" },
  "message": "Tâche soumise pour révision. Le gestionnaire a été notifié."
}
```

---

### POST /tasks/{id}/comments
**Request :**
```json
{
  "content": "Voici le lien vers le fichier : drive.google.com/file/...",
  "attachment": null
}
```
**Response 201 :**
```json
{
  "data": {
    "id": 89,
    "task_id": 156,
    "author": { "id": 42, "name": "Ahmed Benali" },
    "content": "Voici le lien vers le fichier : drive.google.com/file/...",
    "created_at": "2026-04-17T14:30:00Z"
  }
}
```

---

## 8. PAIE

### POST /payroll/calculate *(simulation)*
**Request :**
```json
{
  "period_month": 4,
  "period_year": 2026,
  "employee_ids": null
}
```
**Response 200 :**
```json
{
  "data": {
    "period": "Avril 2026",
    "validation_token": "pay-sim-7c9e6679-7425-40de-944b-e07fc1f90ae7",
    "token_expires_at": "2026-04-30T23:59:59Z",
    "employees_count": 47,
    "total_gross": 3245000,
    "total_net": 2687500,
    "total_cotisations": 412000,
    "total_ir": 145500,
    "anomalies": [
      {
        "employee_id": 38,
        "employee_name": "Yacine Kaci",
        "type": "zero_attendance",
        "message": "Aucune journée de présence enregistrée ce mois"
      }
    ],
    "preview": [
      {
        "employee_id": 42,
        "employee_name": "Ahmed Benali",
        "gross": 75000,
        "overtime": 3500,
        "cotisations": 12300,
        "ir": 4500,
        "advance_deduction": 5000,
        "absence_deduction": 0,
        "net": 56700,
        "vs_last_month": 3500
      }
    ]
  },
  "status": "draft"
}
```

> ⚠️ **Note sur `validation_token` :**
> Le token est un UUID généré côté serveur, stocké en cache Redis avec TTL = fin de journée.
> Il est **obligatoire** pour appeler `/payroll/validate` — remplace la string fragile
> "VALIDER_PAIE_AVRIL_2026" qui posait des problèmes de localisation (langue/encodage).
> Si le gestionnaire recalcule, un nouveau token est généré et l'ancien est invalidé.

---

### POST /payroll/validate
**Request :**
```json
{
  "validation_token": "pay-sim-7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "send_email_to_employees": true
}
```
**Response 202 (async) :**
```json
{
  "message": "Validation lancée. Les 47 bulletins PDF sont en cours de génération.",
  "job_id": "pay-2026-04-abc123",
  "status_url": "/payroll/status/pay-2026-04-abc123"
}
```

---

### GET /payroll/export-bank
**Params :** `?period_month=4&period_year=2026&format=DZ_GENERIC`
**Response 200 :**
```json
{
  "data": {
    "file_url": "https://api.leopardo-rh.com/storage/exports/virement-avril-2026.csv",
    "file_name": "virement-masse-salariale-avril-2026.csv",
    "format": "DZ_GENERIC",
    "total_amount": 2687500,
    "employees_count": 46,
    "generated_at": "2026-04-30T10:15:00Z",
    "expires_at": "2026-05-01T10:15:00Z"
  }
}
```

---

## 9. RAPPORTS

### GET /reports/attendance
**Params :** `?from=2026-04-01&to=2026-04-30&department_id=3&format=json`
**Response 200 (format json) :**
```json
{
  "data": {
    "period": { "from": "2026-04-01", "to": "2026-04-30" },
    "summary": {
      "total_employees": 12,
      "average_attendance_rate": 94.2,
      "total_hours_worked": 1876.5,
      "total_overtime_hours": 45.5,
      "total_late_arrivals": 8,
      "total_absences": 3
    },
    "by_employee": [
      {
        "employee_id": 42,
        "employee_name": "Ahmed Benali",
        "days_present": 21,
        "days_absent": 1,
        "hours_worked": 168.5,
        "overtime_hours": 8.5,
        "late_count": 1,
        "attendance_rate": 95.5
      }
    ]
  }
}
```
**Pour PDF/Excel :** Ajouter `&format=pdf` ou `&format=excel` → Response 200 avec `file_url`

---

## 10. CONFIGURATION (DÉPARTEMENTS, POSTES, SITES, PLANNINGS)

### GET /departments
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Direction Générale", "manager": { "id": 1, "name": "Boss" } },
    { "id": 2, "name": "RH", "manager": null }
  ]
}
```

### POST /departments
**Request :** `{ "name": "Informatique", "manager_id": 42 }`

---

### GET /positions
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Développeur Senior", "department_id": 3 },
    { "id": 2, "name": "Chef de projet", "department_id": 3 }
  ]
}
```

---

### GET /sites
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Siège Alger", "gps_lat": 36.7372, "gps_lng": 3.0869, "gps_radius_m": 100 }
  ]
}
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
      "work_days": [1,2,3,4,5],
      "is_default": true
    }
  ]
}
```

---

## 11. NOTIFICATIONS

### GET /notifications
**Params :** `?read=false&page=1`
**Response 200 :**
```json
{
  "data": [
    { "id": 1, "type": "absence.approved", "title": "Congé approuvé", "body": "Votre demande pour le 15 mai...", "read_at": null, "created_at": "..." }
  ],
  "meta": { "unread_count": 3 }
}
```

### PUT /notifications/{id}/read
**Response 200 :** `{ "message": "Marquée comme lue" }`

### PUT /notifications/read-all
**Response 200 :** `{ "message": "Toutes les notifications marquées comme lues" }`

---

## 12. PARAMÈTRES ENTREPRISE

### GET /settings
**Response 200 :**
```json
{
  "data": {
    "general": {
      "company_name": "TechCorp SPA",
      "language": "fr",
      "timezone": "Africa/Algiers",
      "currency": "DZD",
      "logo_url": null
    },
    "attendance": {
      "gps_enabled": false,
      "gps_radius_m": 100,
      "photo_enabled": false,
      "qr_code_token": "LEOPARDO-QR-abc123",
      "biometric_enabled": false
    },
    "leave": {
      "accrual_rate_monthly": 2.5,
      "max_balance": 60,
      "carry_over": true,
      "carry_over_max_days": 15,
      "validation_levels": 1,
      "min_notice_days": 3
    },
    "advance": {
      "enabled": false,
      "max_percentage": 50,
      "max_simultaneous": 1,
      "max_repayment_months": 3,
      "min_delay_days": 30
    },
    "payroll": {
      "cotisations": [
        { "name": "CNAS Employé", "rate": 9.0, "base": "gross", "ceiling": null },
        { "name": "Retraite", "rate": 1.5, "base": "gross", "ceiling": null }
      ],
      "ir_brackets": [
        { "min": 0, "max": 120000, "rate": 0 },
        { "min": 120001, "max": 360000, "rate": 20 },
        { "min": 360001, "max": 1440000, "rate": 30 },
        { "min": 1440001, "max": null, "rate": 35 }
      ],
      "overtime_rate_1": 1.25,
      "overtime_rate_2": 1.50,
      "bank_export_format": "DZ_GENERIC"
    }
  }
}
```

---

## 13. SUPER ADMIN — FACTURATION ET PLANS

### GET /admin/plans
**Response 200 :** `{ "data": [ { "id": 1, "name": "Starter", "price_monthly": 0 } ] }`

### POST /admin/billing/invoice
**Request :** `{ "company_id": "uuid", "amount": 500, "period": "2026-04" }`
**Response 201 :** `{ "data": { "id": 501, "pdf_url": "..." } }`

---

## CODES D'ERREUR STANDARD

| Code | HTTP | Description |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Champs invalides ou manquants |
| `UNAUTHORIZED` | 401 | Token manquant ou expiré |
| `FORBIDDEN` | 403 | Permission insuffisante pour cette action |
| `NOT_FOUND` | 404 | Ressource introuvable |
| `ACCOUNT_LOCKED` | 423 | Trop de tentatives de connexion |
| `INVALID_CREDENTIALS` | 401 | Email ou mot de passe incorrect |
| `GPS_OUT_OF_ZONE` | 422 | Pointage hors zone géographique autorisée |
| `ALREADY_CHECKED_IN` | 409 | Pointage arrivée déjà enregistré aujourd'hui |
| `ALREADY_CHECKED_OUT` | 409 | Pointage départ déjà enregistré aujourd'hui |
| `INSUFFICIENT_LEAVE_BALANCE` | 422 | Solde de congés insuffisant |
| `INSUFFICIENT_NOTICE` | 422 | Délai de prévenance insuffisant |
| `ADVANCE_LIMIT_EXCEEDED` | 422 | Montant avance dépasse le maximum autorisé |
| `ADVANCE_PENDING_EXISTS` | 409 | Une demande d'avance est déjà en attente |
| `PAYROLL_ALREADY_VALIDATED` | 409 | La paie de cette période est déjà validée |
| `FEATURE_DISABLED` | 403 | Fonctionnalité désactivée pour cette entreprise |
| `PLAN_LIMIT_REACHED` | 402 | Limite du plan atteinte (ex: nb employés max) |
| `SUBSCRIPTION_EXPIRED` | 402 | Abonnement expiré — contacter le support |
| `SERVER_ERROR` | 500 | Erreur serveur interne — loguée automatiquement |
