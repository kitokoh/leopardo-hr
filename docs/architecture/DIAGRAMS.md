# Architecture Visuals

This directory contains diagrams and visual assets explaining the Leopardo RH internal mechanics.

## 🔑 Multi-Tenant Authentication
The following diagram explains how we route a single login request to the correct tenant schema in our multi-tenant PostgreSQL database.

```mermaid
sequenceDiagram
    autonumber
    participant App as Mobile App (Flutter)
    participant AC as AuthController
    participant UL as user_lookups (public)
    participant EM as employees (tenant schema)
    participant CSM as CheckSubscription Middleware

    App->>AC: POST /auth/login
    AC->>UL: SELECT * FROM user_lookups WHERE email = :email
    UL-->>AC: {company_id, schema_name, employee_id}
    AC->>AC: SET search_path TO schema_name
    AC->>EM: Validate Password
    EM-->>AC: OK
    AC->>CSM: Verify Subscription
    CSM-->>AC: Active
    AC-->>App: 200 OK + Token
```

## 📊 Database Topology
Our database is organized to scale while maintaining strict isolation.

```mermaid
graph TD
    DB[(PostgreSQL 16 Instance)]

    Public[Public Schema]
    Shared[Shared Tenants Schema]
    Enterprise1[Enterprise Tenant A Schema]
    Enterprise2[Enterprise Tenant B Schema]

    DB --> Public
    DB --> Shared
    DB --> Enterprise1
    DB --> Enterprise2

    Public --> Plans
    Public --> Companies
    Public --> UserLookups

    Shared --> SME1[Company SME-1 Data]
    Shared --> SME2[Company SME-2 Data]

    Enterprise1 --> EntA[Company Ent-A Data]
    Enterprise2 --> EntB[Company Ent-B Data]
```
