# Feature Specification: ApprovalController::history — enveloppe data (Closes #4814)

**Branch**: `fix/4814-approval-history-envelope` | **Created**: 2026-08-17 | **Issue**: #4814 (P2, api, contract)

## Contexte
#4688 clôturée sans correctif. `history()` renvoie `response()->json($decisions)` (paginator brut). Forme canonique `{data: {...}}` (pattern #4500/#4698).

## Requirements
- **FR-001**: `['data' => $decisions]` ; **FR-002**: test forme `array_keys === ['data']` ; **FR-003**: clients mobiles compatibles.

## Success Criteria
- **SC-001**: `history` → `{data: {...}}` ; **SC-002**: suites Feature vertes.
