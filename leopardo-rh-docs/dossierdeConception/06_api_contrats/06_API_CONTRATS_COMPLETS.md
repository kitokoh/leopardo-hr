# API CONTRATS COMPLETS — LEOPARDO RH
# 70/70 endpoints documentés
# Version 2.0 | Mars 2026
# Source de vérité partagée Backend ↔ Flutter ↔ Frontend

---

## CONVENTIONS
```
Base URL      : https://api.leopardo-rh.com/v1
Auth          : Authorization: Bearer {token}
Content-Type  : application/json
Dates         : ISO 8601 UTC (2026-04-15T08:30:00Z)
Pagination    : ?page=1&per_page=15
Rate limit    : 60 req/min par token
```

---

## 1. AUTHENTIFICATION (7 endpoints)

### POST /auth/login
```json
// Request
{ "email": "ahmed@corp.com", "password": "Pass123!", "device_name": "iPhone Ahmed", "fcm_token": "fcm_abc..." }
// 200
{ "data": { "token": "1|AbCd...", "token_type": "Bearer", "expires_at": "2026-07-15T10:00:00Z",
    "user": { "id": 42, "first_name": "Ahmed", "last_name": "Benali", "email": "ahmed@corp.com",
      "role": "employee", "manager_role": null, "photo_url": "https://api.../photos/42.jpg",
      "company": { "id": "uuid", "name": "TechCorp", "language": "fr", "timezone": "Africa/Algiers",
        "currency": "DZD", "logo_url": "https://api.../logos/tc.png" } } } }
// 401: { "error": "INVALID_CREDENTIALS", "message": "Email ou mot de passe incorrect" }
// 423: { "error": "ACCOUNT_LOCKED", "message": "Bloqué après 5 tentatives.", "retry_after": 900 }
```

### POST /auth/logout
```json
// Request: (aucun body) | 200: { "message": "Déconnexion réussie" }
```

### POST /auth/refresh
```json
// 200: { "data": { "token": "2|NewToken...", "expires_at": "2026-10-15T10:00:00Z" } }
```

### POST /auth/forgot-password
```json
// Request: { "email": "ahmed@corp.com" }
// 200 (toujours): { "message": "Si cet email existe, un lien de réinitialisation a été envoyé" }
```

### POST /auth/reset-password
```json
// Request: { "token": "abc123", "email": "ahmed@corp.com", "password": "New123!", "password_confirmation": "New123!" }
// 200: { "message": "Mot de passe réinitialisé" }
```

### GET /auth/me
```json
// 200: { "data": { "id": 42, "first_name": "Ahmed", "last_name": "Benali", "email": "...",
//   "role": "employee", "manager_role": null, "photo_url": "...",
//   "leave_balance": 12.5, "company": { "id": "uuid", "name": "TechCorp", ... } } }
```

### POST /auth/device/fcm | DELETE /auth/device/fcm
```json
// POST Request: { "fcm_token": "new_token", "platform": "android" }
// DELETE Request: { "fcm_token": "token_to_remove" }
// 200: { "message": "Token FCM mis à jour" }
```

---

## 2. PROFIL EMPLOYÉ (3 endpoints)

### GET /profile
```json
// 200: { "data": { "id": 42, "first_name": "Ahmed", "last_name": "Benali",
//   "email": "...", "phone": "+213555000", "photo_url": "...",
//   "matricule": "EMP-042", "hire_date": "2023-01-15",
//   "department": { "id": 3, "name": "Développement" },
//   "position": { "id": 7, "name": "Développeur Senior" },
//   "schedule": { "id": 1, "name": "Standard 8h-17h", "start_time": "08:00", "end_time": "17:00" },
//   "contract_type": "cdi", "leave_balance": 12.5 } }
```

### PUT /profile
```json
// Request: { "phone": "+213555001", "photo": "base64..." }  (champs modifiables par employé uniquement)
// 200: { "data": { ...profil mis à jour... }, "message": "Profil mis à jour" }
```

### PUT /profile/password
```json
// Request: { "current_password": "Old123!", "password": "New123!", "password_confirmation": "New123!" }
// 200: { "message": "Mot de passe modifié" }
// 422: { "error": "WRONG_CURRENT_PASSWORD" }
```

---

## 3. POINTAGE (8 endpoints)

### GET /attendance/today
```json
// 200 (pointé): { "data": { "id": 1547, "date": "2026-04-15", "check_in": "2026-04-15T07:58:00Z",
//   "check_out": null, "status": "incomplete", "method": "mobile", "hours_worked": null } }
// 200 (non pointé): { "data": null, "context": { "is_holiday": false, "is_leave": false,
//   "expected_start": "08:00:00", "late_tolerance_minutes": 15 } }
```

### POST /attendance/check-in
```json
// Request: { "gps_lat": 36.7538, "gps_lng": 3.0588, "photo": "base64..." }  (optionnels si désactivés)
// 201: { "data": { "id": 1548, "check_in": "2026-04-15T07:58:00Z", "status": "ontime" },
//   "message": "Arrivée enregistrée à 07:58" }
// 409: { "error": "ALREADY_CHECKED_IN", "message": "Déjà pointé à 07:58" }
// 422: { "error": "GPS_OUT_OF_RANGE", "message": "Vous n'êtes pas dans la zone autorisée" }
```

### POST /attendance/check-out
```json
// Request: { "gps_lat": 36.7538, "gps_lng": 3.0588 }
// 200: { "data": { "id": 1547, "check_out": "2026-04-15T17:02:00Z", "hours_worked": 8.07,
//   "overtime_hours": 0.07, "status": "ontime" } }
// 422: { "error": "MISSING_CHECK_IN", "message": "Aucun pointage d'arrivée trouvé ce jour" }
```

### GET /attendance
```json
// Query: ?employee_id=42&from=2026-04-01&to=2026-04-30&status=late&page=1
// 200: { "data": [ { "id": 1547, "date": "2026-04-15", "check_in": "...", "check_out": "...",
//   "status": "late", "hours_worked": 7.83, "late_minutes": 12, "method": "mobile" } ],
//   "meta": { "total": 23, "per_page": 15, "current_page": 1, "last_page": 2 } }
```

### GET /attendance/{id}
```json
// 200: { "data": { ...log complet avec employee, corrections éventuelles... } }
```

### PUT /attendance/{id}  [Manager uniquement]
```json
// Request: { "check_in": "2026-04-15T08:10:00Z", "check_out": "2026-04-15T17:00:00Z",
//   "correction_note": "Employé a oublié de pointer" }
// 200: { "data": { ...log corrigé... }, "message": "Pointage corrigé" }
// 422: { "error": "CHECKOUT_BEFORE_CHECKIN" }
```

### POST /attendance/manual  [Manager uniquement]
```json
// Request: { "employee_id": 42, "date": "2026-04-15", "check_in": "08:00", "check_out": "17:00",
//   "method": "manual", "note": "Pointage biométrique en panne" }
// 201: { "data": { ...log créé... } }
```

### GET /attendance/realtime  [Manager uniquement]
```json
// 200: { "data": { "present": 42, "absent": 8, "late": 3, "on_leave": 5, "total": 58,
//   "employees": [ { "id": 1, "name": "Ahmed B.", "status": "ontime", "check_in": "07:58" }, ... ] } }
```

---

## 4. ABSENCES ET CONGÉS (10 endpoints)

### GET /absences
```json
// Query: ?status=pending&employee_id=42&from=2026-01-01&to=2026-12-31
// 200: { "data": [ { "id": 89, "employee": { "id": 42, "name": "Ahmed Benali" },
//   "type": { "id": 2, "name": "Congé annuel", "code": "CA" },
//   "start_date": "2026-05-01", "end_date": "2026-05-07", "days_count": 5,
//   "status": "pending", "created_at": "..." } ], "meta": {...} }
```

### POST /absences
```json
// Request: { "absence_type_id": 2, "start_date": "2026-05-01", "end_date": "2026-05-07",
//   "reason": "Vacances familiales", "proof": "base64..." }
// 201: { "data": { "id": 90, "days_count": 5, "status": "pending", ... } }
// 422: { "error": "INSUFFICIENT_LEAVE_BALANCE", "message": "Solde insuffisant", "available": 3.0 }
```

### GET /absences/{id}
```json
// 200: { "data": { ...détail complet avec historique d'approbation... } }
```

### PUT /absences/{id}/approve  [Manager]
```json
// Request: { "comment": "Approuvé, bon repos !" }
// 200: { "data": { ...absence avec status=approved... } }
```

### PUT /absences/{id}/reject  [Manager]
```json
// Request: { "reason": "Période trop chargée, reporter en juillet" }
// 200: { "data": { ...absence avec status=rejected, rejected_reason... } }
```

### PUT /absences/{id}/cancel  [Employé — si status=pending]
```json
// 200: { "data": { ...absence avec status=cancelled... } }
```

### GET /absence-types
```json
// 200: { "data": [ { "id": 1, "name": "Maladie", "code": "ML", "is_paid": true,
//   "deducts_leave": false, "requires_proof": true, "max_days_once": 3 } ] }
```

### POST /absence-types  [Manager]
```json
// Request: { "name": "Maternité", "code": "MAT", "is_paid": true, "deducts_leave": false,
//   "requires_proof": true, "max_days_once": 90 }
// 201: { "data": { ...type créé... } }
```

### PUT /absence-types/{id}  [Manager]
```json
// Request: { "max_days_once": 120 }
// 200: { "data": { ...type mis à jour... } }
```

### DELETE /absence-types/{id}  [Manager]
```json
// 200: { "message": "Type supprimé" }
// 422: { "error": "TYPE_IN_USE", "message": "Ce type a des absences associées" }
```

---

## 5. EMPLOYÉS (9 endpoints)

### GET /employees
```json
// Query: ?department_id=3&is_active=true&search=ahmed
// 200: { "data": [ { "id": 42, "matricule": "EMP-042", "first_name": "Ahmed",
//   "last_name": "Benali", "email": "...", "role": "employee",
//   "department": { "id": 3, "name": "Dev" }, "is_active": true } ], "meta": {...} }
```

### POST /employees  [Manager Principal / RH]
```json
// Request: { "first_name": "Sara", "last_name": "Martin", "email": "sara@corp.com",
//   "phone": "+33600000", "department_id": 3, "position_id": 7, "schedule_id": 1,
//   "contract_type": "cdi", "hire_date": "2026-04-01",
//   "salary_base": 45000, "iban": "FR76..." }
// 201: { "data": { ...employé créé, mot de passe temporaire envoyé par email... } }
```

### GET /employees/{id}
```json
// 200: { "data": { ...fiche complète (selon RBAC, certains champs masqués)... } }
```

### PUT /employees/{id}
```json
// Request: { "department_id": 4, "salary_base": 48000 }
// 200: { "data": { ...employé mis à jour... } }
```

### DELETE /employees/{id}  [Manager Principal uniquement — soft delete]
```json
// 200: { "message": "Employé archivé" }
```

### POST /employees/import  [Manager Principal / RH]
```json
// Request: multipart/form-data { "file": CSV_FILE }
// 202: { "message": "Import en cours", "job_id": "uuid", "total_rows": 45 }
```

### GET /employees/import/{job_id}
```json
// 200: { "status": "processing|done|failed", "imported": 40, "errors": 5,
//   "error_details": [ { "row": 12, "field": "email", "message": "Email déjà existant" } ] }
```

### GET /employees/{id}/payslips
```json
// 200: { "data": [ { "id": 101, "period": "2026-03", "net_payable": 38420.50,
//   "status": "paid", "pdf_url": "https://api.../payslips/101" } ] }
```

### GET /employees/{id}/leave-balance
```json
// 200: { "data": { "current_balance": 12.5, "accrued_ytd": 5.0, "used_ytd": 3.0,
//   "history": [ { "date": "2026-03-01", "type": "accrual", "days": 1.5, "balance_after": 12.5 } ] } }
```

---

## 6. CONFIGURATION ENTREPRISE (16 endpoints)

### Départements
```
GET    /departments           → liste
POST   /departments           → créer { "name": "Marketing" }
PUT    /departments/{id}      → modifier
DELETE /departments/{id}      → supprimer (422 si employés liés)
```

### Postes
```
GET    /positions             → ?department_id=3
POST   /positions             → { "name": "Chef de projet", "department_id": 3 }
PUT    /positions/{id}
DELETE /positions/{id}
```

### Plannings
```
GET    /schedules
POST   /schedules             → { "name": "Décalé 9h-18h", "start_time": "09:00", "end_time": "18:00",
                                  "break_minutes": 60, "work_days": [1,2,3,4,5],
                                  "late_tolerance_minutes": 10 }
PUT    /schedules/{id}
DELETE /schedules/{id}
```

### Sites
```
GET    /sites
POST   /sites                 → { "name": "Siège Alger", "address": "...", "gps_lat": 36.75,
                                  "gps_lng": 3.05, "gps_radius_m": 200 }
PUT    /sites/{id}
DELETE /sites/{id}
```

---

## 7. APPAREILS BIOMÉTRIQUES (5 endpoints)

### GET /devices
```json
// 200: { "data": [ { "id": 1, "name": "ZKTeco K40 Entrée", "ip": "192.168.1.100",
//   "port": 4370, "site_id": 1, "last_sync": "2026-04-15T08:00:00Z",
//   "status": "online" } ] }
```

### POST /devices  [Manager Principal]
```json
// Request: { "name": "ZKTeco K40 Entrée", "ip": "192.168.1.100", "port": 4370,
//   "serial": "BEP123", "site_id": 1, "sync_mode": "push" }
// 201: { "data": { ...device créé... } }
```

### PUT /devices/{id}
### DELETE /devices/{id}

### POST /devices/{id}/test-connection
```json
// 200: { "success": true, "latency_ms": 12 }
// 200: { "success": false, "error": "Connection refused" }
```

---

## 8. TÂCHES ET PROJETS (10 endpoints)

### GET /tasks
```json
// Query: ?status=todo&assigned_to=42&priority=high&project_id=5
// 200: { "data": [ { "id": 77, "title": "Corriger bug paie", "priority": "high",
//   "status": "todo", "due_date": "2026-04-20",
//   "assigned_to": { "id": 42, "name": "Ahmed Benali" },
//   "project": { "id": 5, "name": "Refonte Paie" } } ], "meta": {...} }
```

### POST /tasks  [Manager]
```json
// Request: { "title": "Préparer rapport Q1", "description": "...", "assigned_to": 42,
//   "priority": "medium", "due_date": "2026-04-30", "project_id": 5,
//   "checklist": [{"text": "Collecter données", "done": false}] }
// 201: { "data": { ...tâche créée, notification envoyée à assigned_to... } }
```

### GET /tasks/{id}
### PUT /tasks/{id}
### DELETE /tasks/{id}

### PUT /tasks/{id}/status
```json
// Request: { "status": "in_progress" }
// 200: { "data": { ...tâche mise à jour... } }
```

### GET /tasks/{id}/comments
```json
// 200: { "data": [ { "id": 5, "author": { "id": 42, "name": "Ahmed" },
//   "content": "Je commence ce soir", "created_at": "..." } ] }
```

### POST /tasks/{id}/comments
```json
// Request: { "content": "Terminé, en attente de validation" }
// 201: { "data": { ...commentaire créé... } }
```

### GET /projects
### POST /projects
```json
// Request: { "name": "Refonte Paie", "description": "...", "start_date": "2026-04-01",
//   "end_date": "2026-06-30" }
```

---

## 9. AVANCES SUR SALAIRE (6 endpoints)

### GET /advances
```json
// Query: ?status=pending&employee_id=42
// 200: { "data": [ { "id": 12, "employee": { ... }, "amount": 5000,
//   "status": "pending", "repayment_months": 3,
//   "monthly_deduction": 1666.67, "created_at": "..." } ] }
```

### POST /advances  [Employé — si feature activée]
```json
// Request: { "amount": 5000, "reason": "Frais médicaux urgents", "repayment_months": 3 }
// 201: { "data": { ...avance créée avec status=pending... } }
// 422: { "error": "ADVANCE_MODULE_DISABLED" }
// 422: { "error": "ACTIVE_ADVANCE_EXISTS", "message": "Vous avez déjà une avance en cours" }
```

### GET /advances/{id}
### PUT /advances/{id}/approve  [Manager]
```json
// Request: { "repayment_months": 3 }
// 200: { "data": { ...avance avec status=approved, plan de remboursement calculé... } }
```

### PUT /advances/{id}/reject  [Manager]
```json
// Request: { "reason": "Seuil mensuel d'avances atteint" }
// 200: { "data": { ...avance avec status=rejected... } }
```

### PUT /advances/{id}/repayment  [Manager]
```json
// Request: { "repayment_months": 5 }  (modifier le plan)
// 200: { "data": { ...plan mis à jour... } }
```

---

## 10. PAIE (8 endpoints)

### GET /payroll
```json
// Query: ?year=2026&month=4&status=draft&employee_id=42
// 200: { "data": [ { "id": 101, "employee": { "id": 42, "name": "Ahmed Benali" },
//   "period": "2026-04", "gross_total": 52000, "net_payable": 38420.50,
//   "status": "draft" } ], "meta": {...} }
```

### POST /payroll/calculate  [Manager]
```json
// Request: { "year": 2026, "month": 4 }  (calcule pour TOUS les employés)
// 202: { "message": "Calcul en cours", "job_id": "uuid" }
```

### GET /payroll/status/{job_id}
```json
// 200: { "status": "processing|done|failed", "progress": 45,
//   "total_employees": 85, "processed": 38 }
```

### GET /payroll/{id}
```json
// 200: { "data": { "id": 101, "employee": {...}, "period": "2026-04",
//   "salary_base": 45000, "overtime_hours": 4, "overtime_amount": 3750,
//   "bonuses": [{"label": "Prime transport", "amount": 2500}],
//   "gross_total": 51250, "cotisations_salariales": [...],
//   "cotisations_total": 4612, "ir_amount": 8218,
//   "net_before_deductions": 38420, "advance_deduction": 1666, "absence_deduction": 0,
//   "net_payable": 36754.50, "status": "draft" } }
```

### PUT /payroll/{id}/validate  [Manager]
```json
// 200: { "data": { ...bulletin avec status=validated... } }
```

### GET /payroll/{id}/pdf
```json
// 200: application/pdf (téléchargement direct)
// 202: { "message": "PDF en génération", "job_id": "uuid" } (si non encore généré)
```

### POST /payroll/export-bank  [Manager]
```json
// Request: { "year": 2026, "month": 4, "format": "sepa|dz_cnep|ma_cih|tn_bna" }
// 200: application/octet-stream (fichier virement)
```

### GET /payroll/summary  [Manager]
```json
// Query: ?year=2026&month=4
// 200: { "data": { "total_employees": 85, "total_gross": 4250000,
//   "total_net": 3100000, "total_cotisations": 720000,
//   "total_ir": 430000, "total_advances": 45000 } }
```

---

## 11. ÉVALUATIONS (5 endpoints)

### GET /evaluations
```json
// Query: ?employee_id=42&period=2026-Q1
// 200: { "data": [ { "id": 5, "employee": {...}, "period": "2026-Q1",
//   "global_score": 4.2, "status": "completed" } ] }
```

### POST /evaluations  [Manager]
```json
// Request: { "employee_id": 42, "period": "2026-Q1",
//   "scores": { "performance": 4, "attitude": 5, "punctuality": 4, "teamwork": 4 } }
// 201: { "data": { ...évaluation créée, notification auto-éval envoyée à l'employé... } }
```

### GET /evaluations/{id}
### PUT /evaluations/{id}  [Manager]
### PUT /evaluations/{id}/self  [Employé — auto-évaluation]
```json
// Request: { "self_evaluation": { "performance": 4, "comment": "J'ai géré X projets..." } }
// 200: { "data": { ...évaluation avec self_evaluation renseignée... } }
```

---

## 12. NOTIFICATIONS (5 endpoints)

### GET /notifications
```json
// Query: ?is_read=false&page=1
// 200: { "data": [ { "id": 55, "type": "absence.approved", "title": "Congé approuvé",
//   "body": "Votre demande du 1 au 7 mai a été approuvée",
//   "data": {"absence_id": 89, "route": "/absences/89"},
//   "is_read": false, "created_at": "..." } ], "meta": {...} }
```

### GET /notifications/count
```json
// 200: { "data": { "unread": 3 } }
```

### PUT /notifications/{id}/read
```json
// 200: { "data": { "id": 55, "is_read": true, "read_at": "..." } }
```

### PUT /notifications/read-all
```json
// 200: { "message": "Toutes les notifications marquées comme lues", "updated": 3 }
```

### DELETE /notifications/{id}
```json
// 200: { "message": "Notification supprimée" }
```

---

## 13. PARAMÈTRES ENTREPRISE (3 endpoints)

### GET /settings
```json
// 200: { "data": { "payroll.penalty_mode": "proportional",
//   "payroll.overtime_rate_1": "1.25", "payroll.overtime_rate_2": "1.50",
//   "attendance.photo_required": "false", "attendance.gps_required": "false",
//   "advances.enabled": "true", "advances.max_per_month": "1",
//   "leave.carry_over": "true", "leave.carry_over_max": "15" } }
```

### PUT /settings  [Manager Principal]
```json
// Request: { "attendance.gps_required": "true", "payroll.penalty_mode": "bracket" }
// 200: { "data": { ...settings mis à jour... } }
```

### PUT /settings/apply-hr-model  [Manager Principal]
```json
// Request: { "country_code": "MA" }
// 200: { "message": "Modèle Maroc appliqué", "data": { ...settings reconfigurés... } }
```

---

## 14. RAPPORTS (4 endpoints)

### GET /reports/attendance
```json
// Query: ?from=2026-04-01&to=2026-04-30&department_id=3
// 200: { "data": { "summary": {"present_rate": 94.2, "late_rate": 8.1, "absent_rate": 5.8},
//   "by_employee": [ { "employee_id": 42, "name": "Ahmed", "days_present": 22,
//   "days_late": 2, "total_late_minutes": 35, "hours_worked": 176.5 } ] } }
```

### GET /reports/absences
```json
// Query: ?year=2026&department_id=3
// 200: { "data": { "total_days_absent": 145, "by_type": [...], "by_month": [...],
//   "top_absent": [ { "employee": {...}, "days": 12 } ] } }
```

### GET /reports/payroll
```json
// Query: ?year=2026
// 200: { "data": { "by_month": [ { "month": 1, "total_gross": 4200000,
//   "total_net": 3080000 } ], "ytd_total_gross": 16800000 } }
```

### GET /reports/performance
```json
// Query: ?period=2026-Q1
// 200: { "data": { "avg_score": 3.8, "by_department": [...],
//   "top_performers": [...], "below_average": [...] } }
```

---

## 15. SUPER ADMIN (12 endpoints)

### GET    /admin/companies
### POST   /admin/companies
```json
// Request: { "name": "TechCorp", "sector": "IT", "country": "DZ", "city": "Alger",
//   "email": "admin@techcorp.com", "plan_id": 2, "language": "fr",
//   "timezone": "Africa/Algiers", "currency": "DZD",
//   "manager_first_name": "Karim", "manager_last_name": "Belabed" }
// 201: { "data": { "company": {...}, "manager": {...},
//   "message": "Entreprise créée, email envoyé à admin@techcorp.com" } }
```

### GET    /admin/companies/{id}
### PUT    /admin/companies/{id}
### PUT    /admin/companies/{id}/suspend
### PUT    /admin/companies/{id}/activate
### GET    /admin/plans
### POST   /admin/plans
### PUT    /admin/plans/{id}
### GET    /admin/invoices
### POST   /admin/invoices
### GET    /admin/stats
```json
// 200: { "data": { "total_companies": 125, "active": 98, "trial": 20, "suspended": 7,
//   "mrr_eur": 7450, "arr_eur": 89400, "total_employees": 3420 } }
```
