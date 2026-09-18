# Contributing to Leopardo

First off, thank you for considering contributing to Leopardo! It's people like you who make Leopardo a great business suite for its community of users and contributors.

## 🏛 Technical Standards

We maintain high standards for code quality, security, and documentation.

-   **Backend:** PHP 8.4, Laravel 12 (^12.60), PSR-12, Pest PHP for testing.
-   **Frontend:** Next.js 16, TypeScript, Tailwind CSS.
-   **Mobile:** Flutter 3.x, Riverpod for state management.
-   **Architecture:** Modular Monolith & Domain-Driven Design (DDD).
-   **Claim protocol:** a claim is a **local lock** — never commit a `.claim-marker` file (`docs/plan/PLAN_100PCT.md` §6.4, issue #5447). Use an untracked local file or an empty `claim marker #N` commit (protocol #2400). CI fails on any PR adding a `.claim-marker` (`check-no-claim-marker.sh`).

---

## 🆘 Need help?

Check [SUPPORT.md](SUPPORT.md) for the right channel (bugs, ideas, security, docs).

---

## 🚀 How to Contribute

1.  **Explore:** Read our [System Architecture](docs/architecture/ARCHITECTURE.md) to understand the project structure.
2.  **Environment Setup:** Follow the [Development Guide](DEVELOPMENT.md) for local setup (Docker Compose, `.env`, migrations). For production/worker deployment topics, see the separate [Deployment Guide](docs/deployment/DEPLOYMENT_GUIDE.md).
3.  **Find an Issue:** Look for issues labeled `good first issue` or `Agent-Ready` (the `enterprise-ready` label does not exist in this repo — PM review 2026-08-17).
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

## 🔄 Cross review (alternate maintainer)

The repository is currently maintained by a single human maintainer (@kitokoh).
To guarantee quality review on sensitive paths (CI/CD workflows, middleware,
policies, configs, OpenAPI spec), the following procedure applies (issue #1730):

1. **Self-review**: follow the checklist in the [PR template](.github/PULL_REQUEST_TEMPLATE.md).
2. **Sensitive paths**: an approval is required; if you are an external
   contributor, a qualified maintainer (or a community reviewer who has already
   worked on that path) must approve. PRs touching `.github/workflows/`,
   `/api/app/Http/Middleware/`, `/api/app/Policies/`, `/api/config/` and
   `/api/openapi.yaml` trigger the CODEOWNERS review.
3. **Traced cross audit**: high-impact changes (security, data, deployment)
   must mention in the PR the audit that was performed (linked issues, test
   scenarios covered) to enable an informed review.

> When a second maintainer joins, they will be added to [CODEOWNERS](CODEOWNERS)
> to share the mandatory reviews on sensitive paths.

## 📜 Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## 📑 Detailed Guidelines

For a deep dive into our coding conventions, testing strategies, and CI/CD workflows, please refer to the full **[Contribution Guidelines](docs/contributing/GUIDELINES.md)**.
