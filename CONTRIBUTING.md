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
- **Branch Naming:** Use `feat/`, `fix/`, `docs/`, or `refactor/` prefixes.
- **Atomic Commits:** Keep PRs focused on a single issue.
- **Testing:** Ensure all tests pass locally before submitting.
- **Documentation:** Update relevant `.md` files if you change logic or APIs.

## 🛠 Local Development Setup

To get started quickly, run our bootstrap script:

```bash
./scripts/bootstrap.sh
```

For manual setup instructions, see the [Quick Start Guide](QUICKSTART.md).

## 📏 Coding Standards

- **Backend (Laravel):** Follow PSR-12, use FormRequests for validation, and keep controllers thin.
- **Frontend (Next.js):** Use TypeScript, functional components, and Tailwind CSS for styling.
- **Mobile (Flutter):** Follow official linting rules and use Bloc/Provider for state management.

## 🧪 Testing Requirements

We maintain high test coverage. Every PR should include:
- **Unit Tests** for new logic.
- **Feature Tests** for new endpoints or user flows.
- **Regression Checks** to ensure existing functionality remains intact.

For more details on running tests, see [TESTING.md](TESTING.md).

## 📜 Code of Conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

---

Need help? Contact the maintainers at `dev@leopardo-rh.com` or join our [Community Discord](https://discord.gg/leopardo-rh).
