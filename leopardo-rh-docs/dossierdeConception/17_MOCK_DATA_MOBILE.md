# MOCK DATA POUR DÉVELOPPEMENT MOBILE — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. EMPLOYÉ CONNECTÉ (`/auth/me`)

```json
{
  "data": {
    "id": 101,
    "first_name": "Hamid",
    "last_name": "Djebari",
    "email": "h.djebari@techcorp.dz",
    "role": "manager",
    "manager_role": "dept",
    "photo_url": "https://i.pravatar.cc/150?u=101",
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

## 2. POINTAGE DU JOUR (`/attendance/today`)

### Cas : Pas encore pointé
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

### Cas : Déjà pointé (Arrivée)
```json
{
  "data": {
    "id": 5432,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:55:12Z",
    "check_out": null,
    "status": "ontime",
    "method": "mobile"
  }
}
```

---

## 3. HISTORIQUE DE POINTAGE (`/attendance`)

```json
{
  "data": [
    {
      "id": 5432,
      "date": "2026-04-15",
      "check_in": "2026-04-15T07:55:12Z",
      "check_out": "2026-04-15T17:05:00Z",
      "hours_worked": 8.16,
      "status": "ontime"
    },
    {
      "id": 5431,
      "date": "2026-04-14",
      "check_in": "2026-04-14T08:12:00Z",
      "check_out": "2026-04-14T17:00:00Z",
      "hours_worked": 7.8,
      "status": "late"
    }
  ]
}
```

---

## 4. LISTE DES DEMANDES D'ABSENCE (`/absences`)

```json
{
  "data": [
    {
      "id": 801,
      "type": { "id": 1, "label": "Congé payé", "color": "#4CAF50" },
      "start_date": "2026-06-01",
      "end_date": "2026-06-15",
      "days_count": 11,
      "status": "approved",
      "comment": "Vacances d'été"
    },
    {
      "id": 802,
      "type": { "id": 2, "label": "Maladie", "color": "#F44336" },
      "start_date": "2026-04-10",
      "end_date": "2026-04-11",
      "days_count": 2,
      "status": "pending",
      "comment": "Grippe saisonnière"
    }
  ]
}
```

---

## 5. LISTE DES TÂCHES (`/tasks`)

```json
{
  "data": [
    {
      "id": 901,
      "title": "Finaliser le module mobile",
      "status": "inprogress",
      "priority": "high",
      "due_date": "2026-05-15T18:00:00Z",
      "checklist": [
        { "label": "Maquettes Flutter", "done": true },
        { "label": "Connexion API", "done": false }
      ]
    }
  ]
}
```

---

## 6. BULLETINS DE PAIE (`/payroll`)

```json
{
  "data": [
    {
      "id": 401,
      "period": "Avril 2026",
      "net_salary": 65400,
      "status": "validated",
      "pdf_url": "https://api.leopardo-rh.com/storage/payslips/2026-04-101.pdf"
    }
  ]
}
```
