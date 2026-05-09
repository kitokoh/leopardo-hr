# Render Deployment Guide

Render is our recommended platform for hosting the Leopardo RH API due to its excellent Docker support and seamless scaling.

## 📋 Prerequisites
- A GitHub account.
- A Neon.tech PostgreSQL database.
- The Leopardo RH repository forked to your account.

## 🚀 Setup Steps

### 1. Database Initialization
Create a project on Neon and retrieve your connection string. It should look like:
`postgresql://user:pass@host/dbname?sslmode=require`

### 2. Create Web Service on Render
1. Go to [Render Dashboard](https://dashboard.render.com).
2. **New > Web Service**.
3. Select your repository.
4. **Environment:** Docker.
5. **Docker Context:** `api/`.
6. **Dockerfile Path:** `Dockerfile.prod`.

### 3. Environment Variables
Add the following keys in the Render "Environment" tab:

| Variable | Source / Value |
|----------|----------------|
| `APP_KEY` | Run `php artisan key:generate --show` locally. |
| `DB_URL` | Your Neon connection string. |
| `DB_SEARCH_PATH` | `shared_tenants,public` |
| `APP_ENV` | `production` |
| `RUN_MIGRATIONS` | `true` |

## 🔄 Post-Deployment
Once Render completes the build, your API will be live. You can verify it by visiting:
`https://your-app.onrender.com/api/v1/health`

## 🛠 Troubleshooting
- **Build Fails:** Check if you set the Docker Context correctly to `api/`.
- **Database Error:** Ensure the `DB_URL` is correct and Neon has allowed the connection.
- **Migrations Fail:** Check Render logs to see if the database user has permission to create schemas.
