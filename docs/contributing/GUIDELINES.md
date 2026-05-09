# Professional Contribution Guidelines

## 🌟 Introduction
Leopardo RH is built on the principle of open-source excellence. We expect high-quality code, thorough testing, and clear communication.

## 🏗 Workflow
1. **Explore:** Read the [ARCHITECTURE.md](../../ARCHITECTURE.md) and [Dossier de Conception](../../docs/dossierdeConception/README.md).
2. **Issue:** Find an open issue or create a new one to discuss your proposal.
3. **Branch:** `git checkout -b type/topic` (e.g., `feat/biometric-auth`).
4. **Code:** Implement your changes following our [Coding Standards](#coding-standards).
5. **Test:** Write and run tests. **Zero regression policy.**
6. **Document:** Update docs if you change any public-facing behavior.
7. **PR:** Open a Pull Request using our [Template](../../.github/PULL_REQUEST_TEMPLATE.md).

## 💻 Coding Standards

### PHP (Laravel)
- Use **Strict Types** where possible.
- Favor **Dependency Injection**.
- All database changes must include a **Migration** and **Seeder** (if applicable).
- Adhere to **PSR-12**.

### Typescript (Next.js)
- No `any` type. Define interfaces for all data structures.
- Use **Server Components** by default.
- Tailwind class ordering (use the official prettier plugin).

### Dart (Flutter)
- Follow **Effective Dart** guidelines.
- Maintain a clean separation between UI and Logic (Bloc/Provider).

## 🧪 Definition of Done
- [ ] Code follows project standards.
- [ ] All tests pass (Unit + Integration).
- [ ] Documentation updated.
- [ ] PR template filled correctly.
- [ ] Branch is rebased on `main`.

---

Join the movement and help us redefine HR for SMEs!
