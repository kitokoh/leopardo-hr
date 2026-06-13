# Deployment Guide — Leopardo RH

Leopardo RH is designed for high availability and easy scalability. This guide covers local development, staging, and production deployment strategies.

## 🛠 Prerequisites

- **Docker & Docker Compose**
- **PHP 8.4+** (for local development without Docker)
- **Composer**
- **Node.js 18+ & npm/yarn**

## 🐳 Local Development (Quick Start)

The easiest way to run the full stack is via Docker:

```bash
docker-compose up -d
```

This will spin up:
- **API:** Laravel 11 on port 8000
- **Database:** PostgreSQL 16 on port 5432
- **Cache:** Redis on port 6379
- **Admin:** Next.js Dashboard on port 3000

## 🚀 Production Deployment

We recommend the following infrastructure for a production-ready environment:

### Backend (Laravel API)
- **Platform:** Render, AWS ECS, or DigitalOcean App Platform.
- **Environment Variables:** Ensure `APP_ENV=production` and `APP_DEBUG=false`.
- **Optimization:**
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```

### Database (PostgreSQL)
- **Platform:** Neon.tech or AWS RDS.
- **Note:** Ensure the database user has permissions to create new schemas (required for enterprise multi-tenancy).

### Frontend (Next.js)
- **Platform:** Vercel or Netlify.
- **Build Command:** `npm run build`

## 🔄 CI/CD Pipeline

We use **GitHub Actions** for automated testing and deployment.

- **On Push to `main`:**
    1. Runs Pest PHP tests.
    2. Runs Playwright E2E tests.
    3. Deploys to Staging environment.
- **On Release Tag:**
    1. Triggers production deployment.
    2. Generates updated Changelog.

## 📈 Monitoring & Observability

- **Logs:** Centralized logging via Logtail or Papertrail.
- **Performance:** Sentry for error tracking and performance monitoring.
- **Status:** Health check endpoint available at `/api/health`.

---

*For detailed infrastructure diagrams, see:*
- [Architecture Overview](../architecture/ARCHITECTURE.md)
- [Security Policy](../security/SECURITY.md)
