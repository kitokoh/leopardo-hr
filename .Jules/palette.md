# Palette's Journal - Leopardo RH

This journal contains critical UX and accessibility learnings for the Leopardo RH project.

## 2025-05-14 - Initial Setup
**Learning:** Started exploring the mobile application.
**Action:** Investigating login and core features for micro-UX improvements.

## 2025-05-14 - Flutter Accessibility & Micro-UX
**Learning:** In Flutter, `tooltip` is the primary way to provide accessibility labels for `IconButton` widgets. Without it, screen readers only identify the element as a "Button".
**Action:** Always include a descriptive `tooltip` for icon-only buttons to ensure a11y compliance.
