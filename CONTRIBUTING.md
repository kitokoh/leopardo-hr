# Contribution Guidelines

Thank you for your interest in contributing to Leopardo RH! We welcome contributions from the community to help us build the best open-source HR platform.

## 🤝 How to Contribute

### 1. Reporting Bugs
- Use the **Bug Report** template on GitHub.
- Provide a clear description, reproduction steps, and expected vs actual behavior.
- Attach logs or screenshots if relevant.

### 2. Suggesting Features
- Use the **Feature Request** template.
- Explain the "Why" behind the feature and how it benefits users.
- Outline the proposed implementation if possible.

### 3. Submitting Pull Requests
- **Fork the Repository:** Create your own fork and work on a branch.
- **Branch Naming:** Use `feat/`, `fix/`, `docs/`, or `refactor/` prefixes.
- **Atomic Commits:** Keep PRs focused on a single issue.
- **Testing:** Ensure all tests pass locally before submitting.
- **Documentation:** Update relevant `.md` files if you change logic or APIs.
- **Sync with Main:** Ensure your branch is up to date with `origin/main` before submitting.

## 🛠 Local Development Setup

To get started quickly, we recommend using Docker:

```bash
docker compose up -d
docker compose exec api php artisan migrate --seed
```

The API will be available at `http://localhost:8000`.

For detailed manual setup and per-platform instructions, see the [Development Guide](DEVELOPMENT.md).

## 📏 Coding Standards

- **Backend (Laravel):**
  - Follow PSR-12 strict.
  - Use `declare(strict_types=1)` in all new files.
  - Explicit return types on all public methods.
  - Use FormRequests for validation and API Resources for serialization.
  - Keep controllers thin by using Service classes.
- **Frontend (Vue/Next.js):**
  - Use TypeScript and functional components.
  - Use Tailwind CSS for styling.
  - Follow ESLint and Prettier configurations.
- **Mobile (Flutter):**
  - Follow official linting rules.
  - Use Bloc for state management.

## 🧪 Testing Requirements

We maintain high test coverage. Every PR should include:
- **Unit Tests** for new logic.
- **Feature/E2E Tests** for new endpoints or user flows.
- **Regression Checks** to ensure existing functionality remains intact.

### Running Tests

- **API:** `cd api && php artisan test`
- **Admin Dashboard:** `cd front/admin-dashboard && npm run test:e2e`
- **Web:** `cd front/web && npm run test`

## 🏷 Issue Labels

We use labels to organize our work. Look for the following when starting:
- `good first issue`: Ideal for new contributors.
- `help wanted`: Complex issues needing community expertise.
- `bug`: Something that needs fixing.
- `enhancement`: New features or improvements.

## 📜 Code of Conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

---

Need help? Join our [Community Discord](https://discord.gg/leopardo-rh) or check [SUPPORT.md](SUPPORT.md).
