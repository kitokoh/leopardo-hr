# ⚡ Quick Start Guide

Welcome to the Leopardo RH developer community! Follow this guide to get your local environment running in under 5 minutes.

## 📋 Prerequisites
- **Docker Desktop** (Recommended)
- **Node.js 18+** & **NPM**
- **PHP 8.4** & **Composer** (Optional if using Docker)

## 🚀 1. The One-Command Setup

Run the bootstrap script from the repository root to initialize environment files and install dependencies:

```bash
chmod +x scripts/bootstrap.sh
./scripts/bootstrap.sh
```

## 🐘 2. Start the Backend (API)

We recommend using **Laravel Sail** for a consistent Docker-based experience:

```bash
cd api
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

Your API is now live at `http://localhost:8000`.

## ⚛️ 3. Start the Frontend (Web)

Launch the Next.js development server:

```bash
cd web
npm run dev
```

The dashboard is now live at `http://localhost:3000`.

## 🧪 4. Verify the Installation

Run the backend test suite to ensure everything is configured correctly:

```bash
cd api
./vendor/bin/sail artisan test
```

## 🛠 Next Steps
- Read the [Architecture Overview](ARCHITECTURE.md).
- Learn about [Multi-Tenancy Implementation](docs/architecture/MULTITENANCY.md).
- Explore the [API Reference](docs/api/README.md).

---

Need help? Join our [Discord Community](https://discord.gg/leopardo-rh) or check the [Support Guide](SUPPORT.md).
