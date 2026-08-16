# ISSUE_3958 — AttendanceModePickerScreen hors GoRouter

**Statut**: Fixed (PR `fix/3958-smart-attendance-gorouter`) · **Priorité**: P3 · **Module**: mobile

## Correctif

- `leopardo_employee/lib/app.dart` : sous-route `mode` de `/smart-attendance`
  (builder lit `state.extra` pour le mode courant, défaut 'gps').
- `smart_attendance_screen.dart` : `context.push<bool>('/smart-attendance/mode',
  extra: currentMode)` — résultat conservé (invalidate du provider si true).
- Plus aucune navigation `MaterialPageRoute` dans l'app employee (grep vérifié).
