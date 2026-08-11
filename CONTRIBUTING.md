# Contributing to Leopardo RH

First off, thank you for considering contributing to Leopardo RH! It's people like you who make Leopardo RH such a great tool for the HR community.

## 🏛 Technical Standards

Leopardo RH is an **Enterprise-Grade** platform. We maintain high standards for code quality, security, and documentation.

-   **Backend:** PHP 8.4, Laravel 12 (^12.60), PSR-12, Pest PHP for testing.
-   **Frontend:** Next.js 16, TypeScript, Tailwind CSS.
-   **Mobile:** Flutter 3.x, Riverpod for state management.
-   **Architecture:** Modular Monolith & Domain-Driven Design (DDD).

---

## 🆘 Need help?

Check [SUPPORT.md](SUPPORT.md) for the right channel (bugs, ideas, security, docs).

---

## 🚀 How to Contribute

1.  **Explore:** Read our [System Architecture](docs/architecture/ARCHITECTURE.md) to understand the project structure.
2.  **Environment Setup:** Follow the [Development Guide](DEVELOPMENT.md) for local setup (Docker Compose, `.env`, migrations). For production/worker deployment topics, see the separate [Deployment Guide](docs/deployment/DEPLOYMENT_GUIDE.md).
3.  **Find an Issue:** Look for issues labeled `good first issue` or `enterprise-ready`.
4.  **Branching:** Use descriptive branch names: `feat/xxx`, `fix/xxx`, `docs/xxx`.
5.  **Quality Gate:** Ensure all tests pass (`php artisan test`) and linting is clean.
6.  **Submit PR:** Use our [Pull Request Template](.github/PULL_REQUEST_TEMPLATE.md).

---

## 🔒 Security: never quote a real secret

This repository is **public**. Never copy a real secret (password, token, API key,
connection string — even partially truncated) into an audit report, issue, PR,
commit message, log, or doc. Use `<REDACTED>` placeholders and link the tracking
issue instead. The CI guard `secret-scan.yml` scans both HEAD and history; a real
secret in a report is a security incident (see [SECURITY.md](SECURITY.md), issue #1614).

---

## 🔄 Revue croisée (mainteneur alternatif)

Le dépôt est actuellement maintenu par un seul mainteneur humain (@kitokoh).
Pour garantir une revue de qualité sur les chemins sensibles (workflows CI/CD,
middleware, policies, configs, spec OpenAPI), la procédure suivante s'applique
(issue #1730) :

1. **Auto-revue** : suivez la checklist du [template de PR](.github/PULL_REQUEST_TEMPLATE.md).
2. **Chemins sensibles** : une approbation est requise ; si vous êtes un
   contributeur externe, un mainteneur qualifié (ou un reviewer communautaire
   ayant déjà touché ce chemin) doit approuver. Les PR touchant `.github/workflows/`,
   `/api/app/Http/Middleware/`, `/api/app/Policies/`, `/api/config/` et
   `/api/openapi.yaml` déclenchent la revue CODEOWNERS.
3. **Audit croisé tracé** : les modifications à fort impact (sécurité, données,
   déploiement) doivent mentionner dans la PR l'audit effectué (issues liées,
   scénarios de test couverts) pour permettre une revue éclairée.

> Quand un second mainteneur rejoint, il sera ajouté dans [CODEOWNERS](CODEOWNERS)
> pour répartir les révisions obligatoires sur les chemins sensibles.

## 📜 Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## 📑 Detailed Guidelines

For a deep dive into our coding conventions, testing strategies, and CI/CD workflows, please refer to the full **[Contribution Guidelines](docs/contributing/GUIDELINES.md)**.

---

Made with Precision by the **Leopardo RH Engineering Team**.
