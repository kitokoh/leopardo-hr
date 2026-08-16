# Tasks: format locale nombres/dates mobiles

- [x] [T1] [P] [US1] 3× `payroll_list_screen.dart` (employee/manager/hr) : `decimalPattern('fr')` → `decimalPattern(deviceIntlDateLocale)` (6 sites) + import `core/i18n/device_locale.dart`.
- [x] [T2] [P] [US2] `smart_attendance_screen.dart` (employee) : `DateFormat('dd/MM/yyyy')` → `DateFormat('dd/MM/yyyy', deviceIntlDateLocale)` + import.
- [ ] [T3] [P] [US1+US2] CI mobile : `flutter analyze` + tests widget verts (mobile-apps-ci.yml).
