# Feature Specification: Locale AR — correction du mojibake UTF-8 double-encodé

**Feature Branch**: `fix/2275-web-arabic-mojibake`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2275

## Contexte
11 fichiers de `front/web/src` contiennent des chaînes AR double-encodées (`Ø§Ù„Ø£Ø³Ø¹Ø§Ø±` au lieu de `الأسعار`) : Navbar, PricingSection, TrustedBrands, LaunchOperatingSystemSection, pages pricing/download/demo/branding/mobile/login, dashboard layout, login page. Les catalogues JSON + locale-catalog.ts sont propres.

## User Stories & Testing

### User Story 1 — L'arabe s'affiche correctement (P1)
**Acceptance Scenarios**:
1. Given la locale AR, When on visite pricing/download/demo/branding/mobile/login + navbar, Then texte arabe lisible (pas de `Ø§Ù„`/`Ø£`).
2. Given `dir=rtl`, When affichage, Then mise en page RTL intacte.

### User Story 2 — Garde anti-régression (P1)
**Acceptance Scenarios**:
1. Given un scan des patterns mojibake (`Ø§Ù„`, `Ø£Ø³Ø¹Ø§Ø±`, `Ã‰`, `Ã `, `Ù…`), When exécution sur src/, Then 0 résultat.
2. Given le scan intégré (script npm ou test), When CI, Then vert.
