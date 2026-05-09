# System Diagrams — Leopardo RH

This document centralizes all architectural and workflow diagrams for the platform, providing a visual reference for developers and architects.

## 🏗 High-Level Architecture

The 3-tier Modular Monolith structure.

```mermaid
graph TD
    subgraph "Clients"
        Web[Next.js Dashboard]
        Mobile[Flutter App]
        Kiosk[ZKTeco Kiosk]
    end

    subgraph "Application Layer (Laravel)"
        API[Unified API Gateway]
        subgraph Modules
            HR[HR Domain]
            Finance[Finance Domain]
            Attendance[Attendance Domain]
        end
    end

    subgraph "Infrastructure"
        DB[(PostgreSQL)]
        Cache[(Redis)]
    end

    Web & Mobile & Kiosk --> API
    API --> HR & Finance & Attendance
    HR & Finance & Attendance --> DB
    API --> Cache
```

## 🌍 Multi-Tenant Isolation

How we handle Shared vs. Enterprise tenants.

```mermaid
graph LR
    Request[Incoming Request]
    Middleware[Tenant Middleware]
    Registry[Global User Lookup]

    subgraph "Database Routing"
        Shared[(Shared Schema)]
        SchemaA[(Enterprise A Schema)]
        SchemaB[(Enterprise B Schema)]
    end

    Request --> Middleware
    Middleware --> Registry
    Registry --> Middleware
    Middleware -- "Shared Mode" --> Shared
    Middleware -- "Schema Mode" --> SchemaA
    Middleware -- "Schema Mode" --> SchemaB
```

## 🕒 Attendance Synchronization

Workflow for hardware-to-cloud synchronization.

```mermaid
sequenceDiagram
    participant Device as ZKTeco Device
    participant API as Leopardo API
    participant DB as Tenant Database

    Device->>API: POST /api/v1/kiosks/punch
    Note right of API: Resolve tenant context
    API->>DB: Check Employee ID
    DB-->>API: Valid
    API->>DB: Insert Punch Record
    API->>API: Dispatch Event (AttendanceRegistered)
    API-->>Device: 201 Created
```

## 🤖 Leo AI Orchestration

Intent parsing and domain-specific data retrieval.

```mermaid
graph TD
    Query[Manager Query: 'Who is late?']
    NLP[Leo NLP Parser]
    Context[Tenant Context Binder]
    Agent[Attendance Agent]
    Result[Natural Language Answer]

    Query --> NLP
    NLP --> Context
    Context --> Agent
    Agent --> DB[(Tenant DB)]
    DB --> Agent
    Agent --> Result
```

## 💳 Subscription & Billing

Platform-level billing workflow.

```mermaid
graph LR
    Owner[Company Owner]
    Platform[Platform Service]
    Stripe[Payment Gateway]
    Limits[Quota Manager]

    Owner --> Platform
    Platform --> Stripe
    Stripe -- "Webhook: Success" --> Platform
    Platform --> Limits
    Limits -- "Enforce" --> TenantApp[Tenant Operations]
```

---

*Note: All diagrams are maintained using Mermaid.js syntax.*
