# API Documentation Hub

Leopardo RH provides a multi-tenant REST API for the mobile apps, admin surfaces, kiosk and future partners.

## Authentication

All private API requests must be authenticated using Laravel Sanctum tokens.

- Header: `Authorization: Bearer <your_token>`
- Tenant context: resolved from the authenticated token and tenant lookup.

## Core Endpoints

### Authentication

- `POST /api/v1/auth/login`: authenticate and receive a token.
- `POST /api/v1/auth/logout`: revoke the current token.
- `GET /api/v1/auth/me`: retrieve current user profile.

### Employee Management

- `GET /api/v1/employees`: list employees in your tenant.
- `POST /api/v1/employees`: create a new employee record.
- `GET /api/v1/employees/{id}`: view detailed employee data.

### Attendance

- `POST /api/v1/attendance/check-in`: register a new arrival.
- `POST /api/v1/attendance/check-out`: register a departure.
- `GET /api/v1/attendance/today`: real-time status for the current day.

## Integration Tools

- Public documentation: `/docs`
- OpenAPI spec: `/docs/openapi.yaml`
- API Explorer: `/api-explorer`
- SDK examples: `dev-hub/sdk/`

## Related Resources

- [Architecture Overview](../architecture/C4_ARCHITECTURE.md)
- [Security RBAC Matrix](../security/RBAC_ROUTE_MATRIX.md)
- [Multi-Tenancy Guide](../architecture/MULTITENANCY.md)

For partner integration guidance, use `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md`.
