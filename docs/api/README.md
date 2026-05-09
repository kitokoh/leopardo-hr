# API Documentation Hub

Leopardo RH provides a robust, multi-tenant REST API designed for performance and security.

## 🔑 Authentication
All API requests must be authenticated using **Laravel Sanctum** tokens.
- **Header:** `Authorization: Bearer <your_token>`
- **Tenant Context:** The tenant is automatically identified via your authentication token.

## 📡 Core Endpoints

### Authentication
- `POST /api/v1/auth/login`: Authenticate and receive a token.
- `POST /api/v1/auth/logout`: Revoke the current token.
- `GET /api/v1/auth/me`: Retrieve current user profile.

### Employee Management
- `GET /api/v1/employees`: List employees in your tenant.
- `POST /api/v1/employees`: Create a new employee record.
- `GET /api/v1/employees/{id}`: View detailed employee data.

### Attendance & Pointage
- `POST /api/v1/attendance/check-in`: Register a new arrival.
- `POST /api/v1/attendance/check-out`: Register a departure.
- `GET /api/v1/attendance/today`: Real-time status for the current day.

## 🛠 Integration Tools
- **OpenAPI Spec:** Find the raw specification in `/openapi/v1.yaml`.
- **Postman Collection:** Import the latest collection from `/postman/leopardo-v1.json`.
- **SDK Examples:** See code snippets in the `/sdk/` directory.

## 📖 Related Resources
- [Architecture Overview](../../ARCHITECTURE.md)
- [Security & RBAC](../../SECURITY.md)
- [Multi-Tenancy Guide](../../docs/architecture/MULTITENANCY.md)

---

For technical support or API key inquiries, contact `api-support@leopardo-rh.com`.
