# Deployment Guide

Leopardo RH is designed for modern cloud environments. Our primary stack is optimized for **Render** (API) and **Vercel** (Web), with **Neon** as the PostgreSQL provider.

## 🚀 One-Click Deployment (Recommended)

### 1. Backend API (Render + Neon)
1. Fork the repository.
2. Create a **Web Service** on Render.
3. Set **Docker Context** to `api/` and **Dockerfile Path** to `Dockerfile.prod`.
4. Configure environment variables (refer to `api/.env.example`):
   - `APP_KEY`: Your Laravel application key.
   - `DB_URL`: Your Neon.tech connection string.
   - `DB_SEARCH_PATH`: `shared_tenants,public`.
   - `RUN_MIGRATIONS`: `true`.

Detailed Render setup: [RENDER_GUIDE.md](docs/deployment/RENDER_GUIDE.md).

### 2. Frontend Dashboard (Vercel)
1. Connect your fork to Vercel.
2. Set the root directory to `front/web/`.
3. Configure environment variables:
   - `NEXT_PUBLIC_API_URL`: URL of your Render API.

## 🏗 Infrastructure Overview

| Component | Target Platform | Runtime |
|-----------|-----------------|---------|
| **API Gateway** | Render / AWS App Runner | Docker (PHP 8.4) |
| **Database** | Neon / AWS RDS | PostgreSQL 16 |
| **Dashboard** | Vercel / Netlify | Node.js (Next.js) |
| **Mobile App** | Play Store / App Store | Flutter |

## 🔒 Security Recommendations

- **SSL/TLS:** Always enforce HTTPS. Render and Vercel provide this automatically.
- **Database Access:** Use IP whitelisting or VPC peering if available to restrict database access.
- **Backups:** Enable automated snapshots in Neon.tech.

## 🛠 Manual Deployment (Self-Hosted)

For self-hosting using Docker Compose:

```bash
cd api
docker compose -f docker-compose.prod.yml up -d
```

Ensure your reverse proxy (Nginx/Traefik) is correctly configured with security headers as specified in [SECURITY.md](SECURITY.md).

---

For monitoring and incident response, see [OBSERVABILITY.md](docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md).
