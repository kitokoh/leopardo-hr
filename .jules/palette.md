## 2026-05-09 - Password Toggle Accessibility
**Learning:** When adding a password visibility toggle with an `aria-label` that contains the same text as the input's label (e.g., "Mot de passe"), Playwright's `getByLabel` with a regex can become ambiguous if strict mode is on.
**Action:** Use `{ exact: true }` with `getByLabel` or use `getByRole('textbox', { name: '...' })` to disambiguate interactive elements from their associated form fields in tests.
