# Feature Specification: Vitrine — metadata localisées via Accept-Language (Closes #4393)

**Feature Branch**: `fix/4393-vitrine-metadata-locale`
**Created**: 2026-08-16 | **Status**: Implemented
**Issue**: #4393 (P2, web, i18n, seo)

## Contexte

La vitrine sert le contenu dans la langue du visiteur (Accept-Language) mais
les metadata SEO (`<title>`, description) restent FR en dur pour ~15 pages
lorsque la locale vient du header (sans `?lang=`) : `<html lang="en">` + corps
EN, mais `<title>Documentation API | Guides techniques Leopardo RH</title>`.

Cause racine : `front/web/src/middleware.ts` (#4004) ne copiait **que** `?lang=`
vers l'en-tête `x-vitrine-lang` ; les ~20 layouts `(landing)/*/layout.tsx`
lisent `x-vitrine-lang ?? undefined` et retombent sur la metadata FR par
défaut. Le layout racine avait, lui, le fallback `resolveSsrLang` (#2657) —
d'où l'incohérence html lang vs metadata. `/pricing` était le seul layout avec
son propre fallback Accept-Language (#3487).

## User Stories & Testing

### User Story 1 — Un visiteur EN reçoit des metadata EN (P2)

En tant que visiteur anglophone arrivant sur `/docs` (navigateur en anglais,
sans `?lang=`), je veux que l'onglet et l'extrait Google soient en anglais.

**Acceptance Scenarios**:
1. Given `Accept-Language: en` (sans `?lang=`), When GET `/docs`, Then
   `<title>` est en anglais et `<html lang="en">`.
2. Given `Accept-Language: tr` ou `ar`, When GET `/faq`, Then `<title>` est en
   turc/arabe (mêmes locales que le contenu).
3. Given `?lang=en` + `Accept-Language: fr`, When GET une page, Then `?lang=`
   prime (comportement #4173 conservé).
4. Given aucun header ni `?lang=`, When GET une page, Then metadata FR
   (défaut historique inchangé).

### Edge Cases

- Header malformé (`de-DE,de;q=0.8`, vide, absent) → fr.
- `?lang=zz` (invalide) → normalisation Accept-Language, jamais de locale
  inconnue.
- Routes dashboard : l'en-tête est posé mais rien ne le lit (aucun effet).

## Requirements

### Functional Requirements

- **FR-001**: `middleware.ts` DOIT poser `x-vitrine-lang` sur toutes les
  requêtes matcher (défaut `fr`), avec `?lang=` prioritaire sur
  Accept-Language.
- **FR-002**: la normalisation Accept-Language DOIT être partagée
  (`resolveSsrVitrineLang` dans `src/lib/i18n.ts`) — même règle que le root
  layout (#2657), pas de copie locale.
- **FR-003**: aucune modification des ~20 layouts landing (le header suffit).

## Success Criteria

- **SC-001**: `curl -H "Accept-Language: en" /docs` → `<title>` EN (idem
  tr/ar, pages listées dans #4393).
- **SC-002**: tests unitaires `i18n-ssr-lang.test.ts` verts (priorité ?lang,
  normalisation, défauts).
- **SC-003**: tsc 0 erreur, eslint 0 warning, jest vert.
- **SC-004**: `?lang=` continue de primer (#4173).

## Assumptions

- La localisation du contenu (déjà OK) reste hors périmètre ; le ticket ne
  couvre que les metadata.
- Les metadata racine ×4 (#4368) et le contenu des pages (#4196/#4299/#4300)
  sont traités par d'autres PRs — ce fix est complémentaire et orthogonal.
