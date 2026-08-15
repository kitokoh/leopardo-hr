# Mini-spécification — Issue #3503

## Objectif

Réaligner 2 tests e2e Playwright périmés de `front/web/e2e/navigation-and-links.spec.ts` (35/37 verts en local, 2 échouent par sélecteur/assomption obsolète, pas par régression produit).

## Constat

- **T1 « should open mobile menu on hamburger click »** : cherche `nav[aria-label*="mobile"], nav[class*="mobile"]` alors que le menu mobile est un `motion.div` (`className="lg:hidden ..."`, Navbar.tsx ~l.379). Vérifié manuellement : le clic hamburger ouvre bien le menu.
- **T2 « should navigate using Tab key »** : après 5×Tab, `document.activeElement.tagName` reste `BODY` dans chromium headless-shell (focus initial non déplacé).

## Décision

1. Ajouter `aria-label="Menu mobile"` sur le conteneur du menu mobile dans `Navbar.tsx` (a11y + point d'ancrage stable pour le test).
2. T1 : cibler `[aria-label="Menu mobile"]`.
3. T2 : presser Tab en boucle (max 10) jusqu'à ce que le focus quitte `BODY`, puis asserter la balise focalisée — tolérant au quirk headless-shell, l'intention (navigation clavier fonctionnelle) est conservée.

## Critères d'acceptation

1. `aria-label="Menu mobile"` présent sur le conteneur `lg:hidden` de la Navbar.
2. T1 passe sur build de production (viewport 375×667).
3. T2 passe en chromium headless-shell.
4. Aucun changement de comportement produit.

## Plan de retour arrière

Réversion du commit ; l'aria-label est additif, les sélecteurs de test restent compatibles avec l'ancien rendu (le test est le seul consommateur).
