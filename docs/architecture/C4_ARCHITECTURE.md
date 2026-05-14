# Leopardo RH - C4 Architecture

## Niveau 1 - Contexte

```mermaid
C4Context
    title Leopardo RH - Contexte systeme
    Person(employee, "Employe", "Pointe, consulte ses bulletins et demande des conges")
    Person(manager, "Manager/RH", "Valide, pilote l'equipe et prepare la paie")
    Person(superAdmin, "Super-admin plateforme", "Suit les clients, plans, health et support")
    Person(integrator, "Integrateur partenaire", "Consomme API, webhooks et exports")
    System(leopardo, "Leopardo RH", "SaaS RH multi-tenant pour PME")
    System_Ext(openai, "LLM providers", "OpenAI / Claude via LLMClient")
    System_Ext(storage, "Object Storage", "Backups, exports, documents")
    System_Ext(sentry, "Sentry", "Observabilite optionnelle")
    Rel(employee, leopardo, "Mobile / Web manager")
    Rel(manager, leopardo, "Web manager / Admin tenant")
    Rel(superAdmin, leopardo, "Admin-dashboard")
    Rel(integrator, leopardo, "OpenAPI / Webhooks")
    Rel(leopardo, openai, "Orchestration IA")
    Rel(leopardo, storage, "Backups et fichiers")
    Rel(leopardo, sentry, "Erreurs et traces")
```

## Niveau 2 - Containers

```mermaid
C4Container
    title Leopardo RH - Containers
    Person(user, "Utilisateurs")
    Container(admin, "Admin Dashboard", "Vue 3 / Vite", "Pilotage plateforme et modules admin")
    Container(web, "Web manager", "Laravel Blade / Next selon surface", "Portail manager et vitrine")
    Container(mobile, "Mobile", "Flutter Riverpod", "Pointage, conges, paie, notifications")
    Container(api, "Backend API", "Laravel 11 / PHP 8.4", "API REST, RBAC, tenant, workflows RH")
    ContainerDb(pg, "PostgreSQL 16", "Schemas public/tenant", "Donnees transactionnelles")
    Container(redis, "Redis 7", "Cache / queue / rate limit", "Performance et traitements async")
    Container(ci, "GitHub Actions", "CI/CD", "Tests, quality, deploy gates")
    Rel(user, admin, "HTTPS")
    Rel(user, web, "HTTPS")
    Rel(user, mobile, "HTTPS")
    Rel(admin, api, "Bearer API")
    Rel(web, api, "Session/API")
    Rel(mobile, api, "Bearer API")
    Rel(api, pg, "SQL + search_path tenant")
    Rel(api, redis, "Cache, queue, throttle")
    Rel(ci, api, "Tests, migrations, quality")
```

## Niveau 3 - Backend composants critiques

```mermaid
C4Component
    title Leopardo RH - Backend Laravel
    Container_Boundary(api, "Backend API") {
        Component(routes, "Routes API/Web", "Laravel routes", "Surface v1, modules, docs")
        Component(auth, "Auth + RBAC", "Sanctum / guards / middleware", "Auth employee, super-admin, manager roles")
        Component(tenant, "TenantMiddleware + TenantManager", "Middleware/service", "Resolution company et search_path")
        Component(hr, "Services RH", "PayrollService, AttendanceService, AbsenceService", "Logique metier coeur")
        Component(ai, "AI Orchestrator", "App\\AI\\Orchestrator", "Boucle agentique plafonnee")
        Component(audit, "Audit/Webhooks", "Listeners/services", "Traçabilite et integrations")
        Component(openapi, "OpenAPI docs", "api/openapi.yaml + Swagger UI", "Contrat integrateurs")
    }
    ContainerDb(pg, "PostgreSQL")
    Container(redis, "Redis")
    Rel(routes, auth, "Controle acces")
    Rel(auth, tenant, "Contexte tenant")
    Rel(routes, hr, "Commandes metier")
    Rel(hr, audit, "Events")
    Rel(routes, ai, "Routes IA gardees")
    Rel(openapi, routes, "Documente")
    Rel(tenant, pg, "SET search_path")
    Rel(hr, pg, "Eloquent/SQL")
    Rel(hr, redis, "Cache/queue")
```

## Regles de maintien

- Mettre a jour ce diagramme quand une surface deployable, un store ou un provider externe devient critique.
- Garder `api/openapi.yaml` comme source contractuelle, pas ce document.
- Les decisions structurantes doivent etre ajoutees dans `docs/architecture/adr/`.
