# Feature Specification: SignupForm.test.tsx dé-flaké — fake timers + AnimatePresence + userEvent (issue #4035)

**Feature Branch**: `fix/4035-signupform-flaky-test`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Audit QA qa-expert14 2026-08-15 — `npm test` (vitrine) échoue de façon non déterministe sur main : `SignupForm.test.tsx › Guided trial tracking (#2469) › polls pending → ready and shows the access link` (échec ~1 run sur 8 en suite complète, passe en isolation).

## Problème

Le test « polls pending → ready » active `jest.useFakeTimers()` **en milieu de suite** pour piloter le polling 5 s du guided trial. Deux interactions rendent le test non déterministe :

1. **`AnimatePresence mode="wait"`** (SignupForm.tsx:301) : l'étape tracking n'est montée qu'après la sortie animée de l'étape OTP (0,3 s). framer-motion capture la **vraie** `requestAnimationFrame` au chargement du module (avant `jest.useFakeTimers()`) — `advanceTimersByTime`/waitFor ne pilotent donc pas l'animation. Selon le temps réel écoulé pendant l'exécution, l'animation se termine ou non → le DOM reste sur l'OTP et `findByText(/préparation de votre espace/i)` timeout (vérifié par instrumentation : `startTracking` s'exécute, l'étape ne monte pas).
2. **`userEvent.click` sous fake timers** : les phases pointerup/click sont désynchronisées par l'avancement des timers → clicks intermittemment avalés (vérifié : `submitSignupForm` parfois non appelé, `startTracking` parfois non déclenché).

## Décision

Neutraliser framer-motion **dans le test uniquement** (zéro changement de code de production — l'animation n'est pas l'objet du test) :

- `AnimatePresence` → `Fragment` : les étapes montent de façon synchrone.
- `motion.<tag>` → élément DOM réel du tag via `forwardRef` (props motion strippées : initial/animate/exit/transition/whileFocus/whileHover/whileTap/whileInView/variants/layout). Critique : le design system utilise `motion.input` (Input) et `motion.select` (Select) — le mock doit produire de **vrais** contrôles pour les requêtes `getByRole`.
- `fillValidForm` (describes Cold-start + Guided trial) : sélectionner **employees avant role** — `watch('role')` re-rend le composant et, avec le mock aux commits synchrones, orphelinait la référence du 2ᵉ select (valeur « employees » perdue → validation 422 fantôme).
- Test « polls pending » : `fireEvent.click` (synchrone) pour checkbox/submit/tracking au lieu de `userEvent.click` — élimine l'avalement intermittent sous fake timers.

## User Scenarios & Testing

### User Story 1 — La suite SignupForm est déterministe (Priority: P1)

**Independent Test**: `npx jest src/modules/vitrine/components/forms/__tests__/SignupForm.test.tsx` vert **10/10 runs consécutifs** (avant : ~1/8 en échec).

**Acceptance Scenarios**:

1. **Given** la suite complète, **When** on l'exécute 10×, **Then** 19/19 tests verts à chaque run.
2. **Given** `npm test` vitrine, **When** exécuté, **Then** 351 tests verts (21 suites).
3. **Given** le lint, **When** `npm run lint`, **Then** 0 erreur (react/display-name inclus).
4. **Given** TypeScript, **When** `npx tsc --noEmit`, **Then** 0 erreur.
5. **Given** aucun changement de production, **When** on inspecte le diff, **Then** seuls `__tests__/SignupForm.test.tsx` et CHANGELOG sont modifiés.

## Edge Cases

- `motion.input`/`motion.select`/`motion.div` rendus avec le bon tag DOM — les requêtes `getByRole('textbox'|'combobox'|'checkbox'|'button')` continuent de matcher.
- `forwardRef` conservé : le composant passe des refs (otpRefs sur les inputs OTP).
- Le polling 5 s reste piloté par les fake timers (`advanceTimersByTime(5000)`) — seule l'animation de transition est neutralisée.
- `react/display-name` ESLint : le mock expose `displayName` par tag.
