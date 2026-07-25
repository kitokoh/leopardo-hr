# Audit de statut — PA2-MOB-010 (issue #980, "Design system mobile 2026")

**Method:** direct source inspection of the 4 Flutter apps (`leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`) and the shared `leopardo_core` package, cross-referenced against the concrete gaps identified in `12_AUDIT_MOBILE_DESIGN_UX.md` (sections 2.1-2.3) and their corresponding follow-up tickets `PA2-MOB-011`/`012`/`013`. No Flutter/Dart toolchain is available in this sandbox (same limitation noted in `12_AUDIT_MOBILE_DESIGN_UX.md`), so this is a static code review, not a `flutter analyze`/build run.

**Acceptance criteria (issue #980):** *"Composants unifies contrastes lisibles dark mode coherent"* — unified components, readable contrast, coherent dark mode.

---

## Finding: all three concrete gaps raised against this ticket are already closed

`12_AUDIT_MOBILE_DESIGN_UX.md` section 3 explicitly stated PA2-MOB-010 had "aucune preuve" (no evidence) of delivery and listed the specific gaps in sections 2.1-2.3. Re-checking each gap today:

### 1. Hardcoded hex color literals in attendance/pointage screens (section 2.1) — **fixed**

The audit measured 106 literal `Color(0x...)` occurrences in `leopardo_employee`, 36 in `leopardo_manager`, 37 in `leopardo_hr`, 3 in `leopardo_platform_admin`. Re-measured today across all 4 apps' `lib/` trees, excluding the theme token files themselves (`leopardo_core/lib/core/theme/*`, where these constants are legitimately declared once):

```
grep -rln "Color(0x" front/mobile_apps/{leopardo_employee,leopardo_manager,leopardo_hr,leopardo_platform_admin}/lib | grep -v "core/theme"
=> 0 files
```

Zero hardcoded hex literals remain outside the token source of truth. `AppColors` now also declares the previously-undeclared Material accents the audit flagged (`mobileAccentBlue`, `mobileAccentGreen`, `mobileAccentPurple`, `mobileAccentGrey`, `mobileAccentRedLight`, `mobileAccentRedSoft`, `mobileAccentOrange`, etc. in `leopardo_core/lib/core/theme/app_colors.dart`), so the "ungoverned secondary palette" problem described in the audit no longer exists either. This matches `PA2-MOB-011`'s Definition of Done.

### 2. Dark mode forced without a documented product decision (section 2.2) — **fixed**

All 4 root `MaterialApp`/`MaterialApp.router` widgets still declare `themeMode: ThemeMode.dark` (`leopardo_employee/lib/app.dart:267`, `leopardo_manager/lib/app.dart:321`, `leopardo_hr/lib/app.dart:319`, `leopardo_platform_admin/lib/src/platform_admin_app.dart:87`), but this is no longer an undocumented contradiction: `docs/PLAN_ACTION2/15_DECISION_THEME_MOBILE.md` records the explicit product decision (dark confirmed as the real primary experience, no `ThemeMode.system` toggle needed absent a documented user request) and `AppTheme`'s class comment in `leopardo_core/lib/core/theme/app_theme.dart` was corrected to match reality instead of asserting the opposite. This is `PA2-MOB-012`, already marked "FAIT (2026-07-22)" in `12_AUDIT_MOBILE_DESIGN_UX.md` section 4.

### 3. `leopardo_platform_admin` not sharing the common widget vocabulary (section 2.3) — **fixed**

The audit measured **0** usages of `PulseButton`/`LeopardoBadge`/`ShimmerLoading`/`LeopardoQrCard` anywhere in `leopardo_platform_admin`. Re-measured today:

```
grep -rn "LeopardoBadge|MobileSurface|ShimmerLoading|LeopardoQrCard" front/mobile_apps/leopardo_platform_admin/lib | wc -l
=> 58
```

Specifically, the two screens the audit called out by name as natural candidates now use the shared vocabulary:
- `company_create_screen.dart`: `LeopardoBadge` for country/status chips, `MobileSurface` decoration/colors throughout.
- `company_detail_screen.dart`: `ShimmerLoading` for the loading skeleton, `MobileSurface` for status/disabled colors.

This matches `PA2-MOB-013`'s Definition of Done (`LeopardoBadge` for trial/active status, `MobileSurface`/`ShimmerLoading` parity with the other 3 apps).

### Contrast (WCAG AA) — already verified in the prior audit, re-confirmed unchanged

`12_AUDIT_MOBILE_DESIGN_UX.md` section 1 already computed real WCAG contrast ratios on the dark theme's key text/background pairs (14.2:1, 6.6:1, 6.96:1/5.71:1, 4.76:1 — all above the 4.5:1 AA threshold for normal text). No token values referenced there have changed since (`leopardo_core/lib/core/theme/app_colors.dart` still declares the identical hex values), so this remains valid.

## Decision

**PA2-MOB-010 / issue #980 is functionally complete.** The three concrete, measured gaps that were blocking it (hardcoded color literals, undocumented forced dark mode, `leopardo_platform_admin` widget-vocabulary gap) were each closed by their dedicated follow-up tickets (`PA2-MOB-011`, `PA2-MOB-012`, `PA2-MOB-013`), and this audit re-verifies all three with fresh measurements rather than assuming the sub-tickets' completion. No code changes were needed for this ticket itself — it is a closing/bookkeeping audit, consistent with the pattern already used for `PA2-JOB-004` (`17_AUDIT_STATUT_PA2_JOB_001_A_006.md`) and `PA2-KIO-001` (`22_AUDIT_STATUT_PA2_KIO_001.md`).

**Recommendation:** close issue #980 referencing this document.
