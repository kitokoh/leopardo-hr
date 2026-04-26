# Palette's Journal - Leopardo RH

This journal contains critical UX and accessibility learnings for the Leopardo RH project.

## 2025-05-14 - Initial Setup
**Learning:** Started exploring the mobile application.
**Action:** Investigating login and core features for micro-UX improvements.

## 2025-05-14 - Flutter Accessibility & Micro-UX
**Learning:** In Flutter, `tooltip` is the primary way to provide accessibility labels for `IconButton` widgets. Without it, screen readers only identify the element as a "Button".
**Action:** Always include a descriptive `tooltip` for icon-only buttons to ensure a11y compliance.

## 2025-05-15 - Empty State Consistency
**Learning:** The design system uses a specialized `EmptyState` widget to avoid "bare" empty screens. Using it inside a `ListView` ensures that pull-to-refresh functionality remains available even when the list is empty.
**Action:** Prefer `EmptyState` over simple `Text` widgets for all empty lists. Wrap it in a scrollable view to maintain interactive gestures like refresh.

## 2026-04-22 - Loading State Accessibility
**Learning:** `CircularProgressIndicator` doesn't provide feedback to screen readers by default.
**Action:** Wrap loading indicators in `Semantics` widgets with a descriptive `label` (e.g., 'Connexion en cours...') to inform users that an action is being processed. Avoid `const` on `Semantics` if the child or label might be dynamic, and watch for "const_with_non_const" analyzer errors.

## 2026-05-21 - Aesthetic vs Semantic Balance
**Learning:** Purely decorative elements (like name initials in a circle) can clutter screen reader navigation if they don't add unique information already present in text (like the full name).
**Action:** Use `ExcludeSemantics` to hide redundant decorative elements from screen readers while keeping them for sighted users.
