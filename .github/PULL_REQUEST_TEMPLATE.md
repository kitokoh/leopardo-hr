## 📝 Pull Request Overview

**Issue Reference(s):** Fixes # <!-- une ligne "Fixes #N" / "Closes #N" par issue si cette PR livre un lot BC (docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md) -->
> ⚠️ **Obligatoire (PA2-OPS-008) :** cette PR doit obligatoirement inclure `Closes #XXX` (ou `Fixes #XXX` / `Resolves #XXX`) dans son titre ou sa description, sauf si elle est explicitement typee `docs:`/`chore:`. Un garde CI bloquant (`.github/workflows/pr-issue-guard.yml`, `dev-hub/tools/check-pr-closes-issue.sh`) refuse toute PR qui ne le fait pas. Pour une PR de lot BC (branche `bc/<code>-*`), répéter le mot-clé pour CHAQUE issue fermée.
**Category:** [Feature / Bug Fix / Documentation / Refactor]

---

## 🚀 Changes Description

Please provide a clear and concise description of the changes introduced by this PR.

-   Item 1
-   Item 2

---

## 🗺️ Surfaces touchees

Cocher toutes les surfaces reellement modifiees par cette PR (aide au triage) :

-   [ ] API (`api/app`)
-   [ ] Web (`front/web`)
-   [ ] Admin dashboard (`front/admin-dashboard`)
-   [ ] Mobile (`front/mobile_apps/*`)
-   [ ] Kiosk (`front/zkteco-kiosk`)
-   [ ] CI / GitHub Actions (`.github/workflows`)
-   [ ] Docs uniquement (`docs/`, `*.md`)
-   [ ] Infra / config (`render.yaml`, `docker*`, etc.)

---

## 🔌 Contrat API

-   [ ] Cette PR ajoute/modifie une route, un payload de requete, ou une reponse API existante.
    -   Si coche : `openapi.yaml` mis a jour ? [ ] oui / [ ] non applicable
    -   Si coche : compatibilite retro-active verifiee pour web/mobile/kiosk existants ? [ ] oui / [ ] non applicable
-   [ ] Aucun changement de contrat API dans cette PR.

---

## ⚠️ Risques residuels

Listez les risques connus, limitations, ou dette technique deliberement laissee de cote (vide si aucun) :

-   Aucun / voir description ci-dessus.

---

## 🛡 Quality Checklist (Enterprise Standards)

-   [ ] **Code Quality:** My code follows the project's coding conventions (PSR-12, ESLint).
-   [ ] **Testing:** I have added or updated tests for my changes.
-   [ ] **Verification:** I have verified the changes locally (API, Web, or Mobile).
-   [ ] **Documentation:** I have updated the relevant documentation hub files.
-   [ ] **Security:** I have checked for potential security implications (RBAC, SQLi, XSS).
-   [ ] **Breaking Changes:** This PR does not break existing functionality (or provides a migration path).

---

## 📸 Screenshots / Demos

**Obligatoire si une case UI est cochee ci-dessus** (Web, Admin dashboard, Mobile, ou Kiosk). Ajouter des captures/GIFs avant/apres.

Non applicable si aucune surface UI n'est touchee.

---

## 🤝 Contributor Agreement

By submitting this PR, I agree that my contributions are licensed under the **MIT License**.
