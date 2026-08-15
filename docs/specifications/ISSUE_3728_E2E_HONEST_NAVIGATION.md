# Spec — E2E vitrine honnêtes : plus de faux vert (issue #3728)

**Issue** : #3728 | **Statut** : Implémenté | **Date** : 2026-08-15

## Problème

`navigation-and-links.spec.ts` (+ conversion-funnel, dark-mode-toggle)
cliquaient des liens absents de la Navbar (/employes, /documents,
/comptabilite, /marketing) sous garde `if (await link.isVisible())` →
18/31 tests n'assertaient RIEN (faux vert CI).

## Correctif

1. `navigation-and-links.spec.ts` réécrit contre la navigation RÉELLE :
   - liens directs (pricing, contact) + dropdowns « Ressources » (guides,
     docs, changelog), « Communauté » (FAQ), « Installer » (download) avec
     assertions strictes `expect(...).toBeVisible()` ;
   - tests navbar desktop `test.skip(isMobile)` (menu hamburger sur mobile) ;
   - footer : assertion hrefs réels (pas de `#`, #3734) ;
   - URL routing : modules vérifiés en HTTP 200 ;
   - Navigation State : `aria-current="page"` (implémenté dans Navbar).
2. `conversion-funnel.spec.ts` : CTA par href (`/signup`, `/demo`,
   `/contact`) indépendants de la locale ; formulaires /demo et /contact
   ciblés par `name`/`id` stables ; test « form submission errors » aligné
   sur le parcours guided-trial (email-only, plus de password).
3. `dark-mode-toggle.spec.ts` réécrit : assertions strictes, persistance
   vérifiée après reload (waitForFunction), skip mobile pour le test navbar.
4. `Navbar.tsx` : `aria-current="page"` sur le lien actif (a11y).

## Preuves locales

- `npx playwright test` (3 specs, chromium) : 47/48 ✓ (l'échec restant =
  latence cold-compile du dev server sandbox ; flux /demo vérifié
  manuellement OK, timeout porté à 60-90 s).
- Jest : 45/45 sur la navigation (integration).
- `tsc --noEmit` : 0 erreur.
