# ⚡ Quick Start for Developers

Leopardo RH is designed to be developer-friendly. This guide will help you set up a full multi-tenant environment on your local machine.

## 📋 Prerequisites

-   **Docker** & **Docker Compose**
-   **Node.js 18+**
-   **Git**

---

## 🚀 1. Automated Setup

The fastest way to get started is using our bootstrap script, which handles environment files, dependencies, and key generation.

```bash
chmod +x scripts/bootstrap.sh
./scripts/bootstrap.sh
```

## 🐘 2. Backend & Database (Laravel Sail)

We use Laravel Sail (Docker) to ensure everyone has the same environment.

```bash
cd api

# Start the environment
./vendor/bin/sail up -d

# Run migrations and seed the 'shared' and 'tenant' data
./vendor/bin/sail artisan migrate --seed
```

The API is now available at [http://localhost:8000](http://localhost:8000).

## ⚛️ 3. Web Dashboard (Next.js)

```bash
cd web
npm install
npm run dev
```

The admin dashboard is now available at [http://localhost:3000](http://localhost:3000).

## 🧪 4. Verify Your Setup

Run the test suite to confirm isolation and core logic are working as expected:

```bash
cd api
./vendor/bin/sail artisan test --group isolation
```

---

## 🏗 Key Entry Points

*   **API Routes:** `api/routes/api.php` (Core) & `api/routes/modules/` (Domains).
*   **Tenant Logic:** `api/app/Http/Middleware/TenantMiddleware.php`.
*   **Frontend Components:** `web/components/ui/`.
*   **Seeders:** Use `api/database/seeders/DatabaseSeeder.php` to create demo tenants.

## 🛠 Pro Tips

*   **Mailhog:** Visit [http://localhost:8025](http://localhost:8025) to catch all outgoing emails.
*   **Redis:** Sail includes a Redis instance for caching and queues.
*   **Logs:** Use `tail -f api/storage/logs/laravel.log` to watch the backend in real-time.

---

### Need Help?
Check the [Contributing Guide](CONTRIBUTING.md) or join our [Discord](https://discord.gg/leopardo-rh).
