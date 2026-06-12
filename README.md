# <p align="center"> 🐆 Leopardo RH</p>
<p align="center"><b>Modern, AI-Powered, Multi-Tenant HR Management Platform for SMEs</b></p>

<p align="center">
  <a href="https://github.com/kitokoh/leopardo-hr/actions"><img src="https://img.shields.io/github/actions/workflow/status/kitokoh/leopardo-hr/tests.yml?branch=main&style=for-the-badge&logo=github" alt="CI Status"></a>
  <a href="https://codecov.io/gh/kitokoh/leopardo-hr"><img src="https://img.shields.io/codecov/c/github/kitokoh/leopardo-hr?style=for-the-badge&logo=codecov" alt="Code Coverage"></a>
  <a href="SECURITY.md"><img src="https://img.shields.io/badge/Security-Hardened-brightgreen?style=for-the-badge&logo=anchor" alt="Security Hardened"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/kitokoh/leopardo-hr?style=for-the-badge" alt="License"></a>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Flutter-3.x-02569B?style=for-the-badge&logo=flutter" alt="Flutter 3.x">
</p>

---

Leopardo RH is an enterprise-grade HR SaaS platform specifically designed to empower SMEs with tools typically reserved for large corporations. From **Automated Payroll** and **Biometric Attendance** to **AI-driven Workforce Insights**, Leopardo RH is the all-in-one cockpit for modern human resources.

## ✨ Key Features

- 🏢 **Multi-Tenant Architecture:** Secure isolation with dedicated schemas for Enterprise clients.
- 🕒 **Smart Attendance:** Real-time check-in/out via Mobile (GPS) and ZKTeco biometric devices.
- 💰 **Automated Payroll:** One-click salary calculations compliant with multi-country regulations (DZ, MA, FR, etc.).
- 🤖 **AI Assistant:** Intelligent workforce summaries and automated anomaly detection.
- 📱 **Unified Experience:** Native Flutter mobile app for employees and a powerful Next.js dashboard for managers.
- 🌍 **International Ready:** Full RTL (Arabic) support and multilingual capabilities (FR, EN, TR).
- 🔒 **Enterprise Security:** AES-256 data encryption at rest and strict RBAC governance.

## 🏗 Architecture & Infrastructure

Leopardo RH is built on a modular monolith foundation, ensuring high performance and ease of deployment.

### Déploiement de production

| Composant | Plateforme | URL |
|-----------|-----------|-----|
| 🔧 API Laravel | Render | https://gestionemployerbackend.onrender.com |
| 🌐 Vitrine Web | Vercel | https://gestionemployer-backend.vercel.app |
| 📊 Admin Dashboard | Cloudflare Pages | https://leo-admin.pages.dev |
| ⚡ Redis Cache/Queue | Upstash | https://REDACTED.upstash.io |
| 🔥 Firebase | Google Firebase | Projet : leopardo-rh |
| 📱 App Employee (Android) | Firebase App Distribution | https://appdistribution.firebase.dev/i/e2bde6595da9d96e |
| 📱 App Manager (Android) | Firebase App Distribution | https://appdistribution.firebase.dev/i/e51102534a5dff22 |
| 📱 App Platform Admin (Android) | Firebase App Distribution | https://appdistribution.firebase.dev/i/f37b128b1c89a006 |

### Diagramme d'architecture

```mermaid
graph TB
    subgraph "Clients"
        VA[Vitrine Web\nVercel]
        AD[Admin Dashboard\nCloudflare Pages]
        ME[App Employee\nFlutter Android/iOS]
        MM[App Manager\nFlutter Android/iOS]
        MA[App Platform Admin\nFlutter Android/iOS]
        KI[Kiosk ZKTeco\nLinux Desktop]
    end

    subgraph "Backend — Render"
        API[Laravel 11 API\ngestionemployerbackend.onrender.com]
        QW[Queue Workers\nHorizon]
    end

    subgraph "Data & Cache — Upstash"
        RD[(Redis\nUpstash TLS)]
    end

    subgraph "Database — Render"
        PG[(PostgreSQL 16)]
    end

    subgraph "Firebase — Google"
        FCM[FCM Push Notifications]
        FAD[Firebase App Distribution\nAPK de test]
        FS[Firebase Storage]
    end

    VA & AD & ME & MM & MA & KI --> API
    API --> PG
    API --> RD
    QW --> RD
    QW --> FCM
    QW --> FS
    API --> FCM
    FAD --> ME & MM & MA
```

### Queues Redis (Upstash)

| Queue | Usage | Priorité |
|-------|-------|----------|
| `default` | Tâches générales | Normale |
| `pdf` | Génération bulletins de paie PDF | Haute |
| `notifications` | Push FCM employés | Haute |
| `payroll` | Calculs de paie batch | Normale |
| `webhooks` | Dispatch intégrations externes | Basse |

For a deep dive into our design, see [ARCHITECTURE.md](docs/ARCHITECTURE.md).

## 🚀 Quick Start

Get your development environment up and running in minutes:

```bash
# 1. Clone the repository
git clone https://github.com/kitokoh/leopardo-hr.git && cd leopardo-hr

# 2. Run the bootstrap

# 3. Start the API
cd api && .sail up -d
```

Detailed onboarding instructions: [DEVELOPMENT.md](dev-hub/DEVELOPMENT.md).

## 📚 Documentation Hub

| Section | Description |
|---------|-------------|
| 🛠 **[Architecture](docs/architecture/README.md)** | System design, multi-tenancy, and ERD. |
| 🔑 **[Security](SECURITY.md)** | Data protection, encryption, and RBAC matrix. |
| 🌐 **[API Reference](docs/api/README.md)** | OpenAPI specs and Postman collections. |
| 🚀 **[Deployment](docs/DEPLOYMENT_GUIDE.md)** | Render, Vercel, and Docker production guides. |
| 🤝 **[Contributing](dev-hub/CONTRIBUTING.md)** | Developer guidelines and coding standards. |

## 🛠 Tech Stack

- **Backend:** Laravel 11, PHP 8.4, PostgreSQL 16
- **Frontend:** Next.js 16.2, Tailwind CSS, Shadcn/UI
- **Mobile:** Flutter 3.x, flutter_riverpod 3.3
- **Infra:** Render, Vercel, Neon.tech
- **Testing:** Pest PHP, Playwright, Flutter Test

## 🗺 Roadmap

- [x] MVP: Core HR & Attendance
- [x] Multi-tenant Schema Isolation
- [x] AI Salary Estimation Layer
- [ ] Phase 2: Full Leave Management & Approvals
- [ ] Phase 3: ZKTeco Cloud Integration
- [ ] Phase 4: Automated Banking Exports (SEPA, etc.)

Check out our [Full Roadmap](docs/ROADMAP.md).

## 🛡 Security & Compliance

We take data privacy seriously. Leopardo RH is designed with **RGPD** and local African regulations (DZ Loi 18-07, MA Loi 09-08) in mind. Sensitive data is always encrypted.

See our [Security Policy](SECURITY.md) for more info.

## 🤝 Community & Support

- **Found a bug?** [Open an issue](https://github.com/kitokoh/leopardo-hr/issues/new?template=bug_report.md)
- **Need help?** Join our [Discord](https://discord.gg/leopardo-rh) or check [SUPPORT.md](SUPPORT.md).
- **Want to contribute?** See [CONTRIBUTING.md](dev-hub/CONTRIBUTING.md).

---

<p align="center">
  Built with ❤️ by the Leopardo RH Team. Ready for the next generation of workforce management.
</p>
