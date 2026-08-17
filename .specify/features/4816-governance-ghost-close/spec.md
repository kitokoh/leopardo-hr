# Feature Specification: Gouvernance — anti clôture sans merge (Closes #4816)

**Branch**: `fix/4816-governance-ghost-close` | **Created**: 2026-08-17 | **Issue**: #4816 (P1, process)

## Contexte
~84 issues clôturées le 2026-08-17 ; plusieurs sans correctif mergé (#4690/#4687/#4688/#4305/#4410 vérifiées non résolues, #4723/#4714/#4716 clôturées avant le merge de leurs PRs).

## Requirements
- **FR-001**: documenter la règle « clôture uniquement après merge (ou commentaire motivé) » dans AGENTS.md/constitution.
- **FR-002**: garde inverse de `check-issues-left-open-by-merged-prs.sh` : issues clôturées sans PR mergé référencé → alert.
- **FR-003**: ré-ouverture via tickets dédiés (fait : #4812-#4815).

## Success Criteria
- **SC-001**: règle documentée ; **SC-002**: garde opérationnelle.
