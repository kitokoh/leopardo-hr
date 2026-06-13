# API Reference — Leopardo RH

Leopardo RH provides a robust, RESTful API that powers all our official clients (Web, Mobile, Kiosk) and allows for third-party integrations.

## 🔑 Authentication

All API requests require a **Bearer Token** obtained via the login endpoint.

- **Base URL:** `https://gestionemployerbackend.onrender.com/api/v1`
- **Format:** `application/json`

```bash
curl -X GET "https://api.leopardo-rh.com/api/v1/employees" \
     -H "Authorization: Bearer {YOUR_TOKEN}" \
     -H "Accept: application/json"
```

## 🏗 API Specification

We use **OpenAPI 3.0** as our source of truth for API contracts.

- 📄 **[OpenAPI YAML](../../openapi/openapi.yaml)** — View the full technical specification.
- 🧪 **[Postman Collection](../../postman/leopardo_collection.json)** — *Coming Soon*

## 📦 Core Endpoints

### 🏢 Multi-Tenant Context
Every request is automatically scoped to the authenticated user's tenant (company). No manual `tenant_id` is required in the headers once authenticated.

### 👤 Employee Management
- `GET /employees` — List all employees.
- `POST /employees` — Create a new employee record.
- `GET /employees/{id}` — Retrieve detailed profile.

### 🕒 Attendance & Kiosk
- `POST /attendance/check-in` — Register start of work (GPS/Biometric).
- `POST /attendance/check-out` — Register end of work.
- `GET /kiosks/roster` — Fetch daily roster for ZKTeco devices.

### 💰 Payroll & Finance
- `GET /payroll/estimate` — Get AI-driven salary estimation.
- `POST /payroll/generate` — Trigger monthly payroll batch.

## 🚦 Rate Limiting & Versioning

- **Version:** Current stable is `v1`.
- **Limits:** 60 requests per minute per IP for public endpoints; higher limits for authenticated tenants.

---

*For detailed mobile-specific integration, see:*
- [Mobile Setup Guide](../mobile/README.md)
- [Web Implementation](../web/README.md)
