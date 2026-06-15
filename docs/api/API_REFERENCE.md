# API Reference — Leopardo RH

Leopardo RH is an **API-First Platform**. Every feature available in our mobile and web apps is powered by our RESTful API.

## 🚀 Getting Started

Our API follows REST principles, uses JSON for communication, and returns standard HTTP response codes.

### Base URLs
-   **Production:** `https://gestionemployerbackend.onrender.com/api/v1`
-   **Local:** `http://localhost:8000/api/v1`

---

## 📑 Interactive Documentation

-   **OpenAPI 3.0 Spec:** [Download api/openapi.yaml](../../api/openapi.yaml)
-   **Swagger UI:** Access `/docs` on any running instance to explore and test the API in real-time.
-   **Postman Collection:** A pre-configured collection is available in the [`/postman`](../../postman/) directory.

---

## 🔑 Authentication

Most endpoints require a **Bearer Token** obtained via the `/auth/login` endpoint.

```bash
curl -X POST https://api.leopardo-rh.com/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email": "admin@leopardo-rh.com", "password": "password123"}'
```

---

## 🏗 Core Resource Groups

| Resource | Description |
| :--- | :--- |
| **Authentication** | Login, Logout, Profile Management, and Password Reset. |
| **Employees** | CRUD for employee records, contracts, and department assignments. |
| **Attendance** | Check-in/out, GPS logs, and anomaly detection. |
| **Absences** | Leave requests, balances, and approval workflows. |
| **Payroll** | Salary calculations, payslips, and banking exports. |
| **Platform** | Super-admin controls, tenant creation, and health monitoring. |

---

## 🛠 SDKs & Examples

We provide official SDKs and integration examples to accelerate your development:
-   **JavaScript/TypeScript:** `sdk/javascript/`
-   **Python:** `sdk/python/`
-   **PHP:** `sdk/php/`
-   **Integration Samples:** See [`/examples`](../../examples/) for real-world integration code.

---

## 📡 Webhooks (Roadmap)

Integrate Leopardo RH into your existing workflows using webhooks.
-   `employee.created`
-   `attendance.anomaly_detected`
-   `payroll.finalized`

---

For security details, see [Auth System](../security/AUTH_SYSTEM.md).
