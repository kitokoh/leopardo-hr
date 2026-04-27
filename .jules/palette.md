# Palette's Journal - Leopardo RH

This journal contains critical UX and accessibility learnings for the Leopardo RH project.

## 2025-05-14 - Initial Setup
**Learning:** Started exploring the mobile application.
**Action:** Investigating login and core features for micro-UX improvements.

## 2025-05-14 - Flutter Accessibility & Micro-UX
**Learning:** In Flutter, `tooltip` is the primary way to provide accessibility labels for `IconButton` widgets. Without it, screen readers only identify the element as a "Button".
**Action:** Always include a descriptive `tooltip` for icon-only buttons to ensure a11y compliance.

## 2026-04-22 - Loading State Accessibility
**Learning:** `CircularProgressIndicator` doesn't provide feedback to screen readers by default.
**Action:** Wrap loading indicators in `Semantics` widgets with a descriptive `label` (e.g., 'Connexion en cours...') to inform users that an action is being processed. Avoid `const` on `Semantics` if the child or label might be dynamic, and watch for "const_with_non_const" analyzer errors.

## 2026-04-22 - Flutter Pull-to-Refresh with Riverpod
**Learning:** When using `RefreshIndicator` with Riverpod, returning `ref.refresh(provider)` might not wait for the actual async operation to complete, causing the spinner to disappear too early.
**Action:** Always use `await ref.refresh(provider.future)` to ensure the `RefreshIndicator` remains active until the data is fully reloaded.

## 2026-04-22 - Scrollable Empty States
**Learning:** A common UX issue in Flutter is the "dead" empty state that prevents pull-to-refresh because the view is not scrollable.
**Action:** Wrap empty states in a `ListView` or `SingleChildScrollView` with `physics: AlwaysScrollableScrollPhysics()` to maintain gesture consistency. Use a `SizedBox(height: 80)` at the top of empty states within lists to maintain a balanced visual breathing room.
