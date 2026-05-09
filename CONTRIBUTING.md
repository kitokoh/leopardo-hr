# Contributing to Leopardo RH

First off, thank you for considering contributing to Leopardo RH! It's people like you that make Leopardo RH such a powerful tool for SMEs worldwide.

---

## 🗺 Your Journey Starts Here

1.  **[Code of Conduct](CODE_OF_CONDUCT.md):** Please review our standards for community behavior.
2.  **[Architecture Overview](ARCHITECTURE.md):** Understand the "Modular Monolith" and our multi-tenancy strategy.
3.  **[Quick Start Guide](QUICKSTART.md):** Get your local environment running in minutes.

---

## 🛠 How Can I Contribute?

### 🪲 Reporting Bugs
Found a bug? Help us squash it!
*   **Check existing issues:** Your bug might already be reported.
*   **Use the template:** Fill out the [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md) with as much detail as possible.
*   **Provide a reproduction:** A clear set of steps (or a failing test case) is the fastest way to a fix.

### ✨ Suggesting Enhancements
We love new ideas!
*   **Explain the use case:** Who does this help? Why is it important?
*   **Draft the implementation:** You don't need code, but a rough plan helps us discuss the technical impact.

### ⌨️ Pull Requests (PRs)
Ready to write some code?
1.  **Fork and Branch:** Create a branch from `main` using `feat/`, `fix/`, or `refactor/`.
2.  **Keep it Focused:** One PR should solve one problem.
3.  **Test Your Changes:** We won't merge code that breaks the build. Run `cd api && ./vendor/bin/pest`.
4.  **Update Documentation:** If you change an API or a setting, update the relevant `.md` file.

---

## 📐 Coding Standards & Style

### Backend (Laravel/PHP)
*   **PHP 8.4+:** Leverage latest features (readonly, type hinting).
*   **Pest PHP:** All new features must have feature tests.
*   **Form Requests:** Keep controllers clean by moving validation to requests.
*   **Services:** Logic belongs in `app/Services/` or `app/Modules/`.

### Frontend (Next.js/React)
*   **TypeScript:** Strictly typed components and props.
*   **Tailwind CSS:** Use our design system tokens (see `docs/REFERENTIEL_PRODUIT/COULEURS.md`).
*   **Atomic Design:** Keep components small and reusable.

### Mobile (Flutter)
*   **BLoC Pattern:** For state management.
*   **Clean Architecture:** Clear separation between Data, Domain, and Presentation layers.

---

## 🚀 The PR Lifecycle

1.  **Submission:** Open your PR against `main`.
2.  **Automated Checks:** Our CI will run tests, linting, and security scans.
3.  **Peer Review:** A maintainer will review your code. We aim for a 48-hour turnaround.
4.  **Merge:** Once approved and green, your code joins the leopard pride! 🐆

---

### Questions?
Join the conversation on [Discord](https://discord.gg/leopardo-rh) or email `dev@leopardo-rh.com`.
