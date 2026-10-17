# TRD — Exigences fonctionnelles et techniques

## A. Exigences fonctionnelles

### F1 — Repositionnement de Trade
- F1.1 `Trade` ne figure plus dans la navigation principale.
- F1.2 Trade reste atteignable depuis les offres : carte dédiée sur `/packs` et fiche de la constellation.
- F1.3 La route `/trade` continue de répondre ; `sitemap.ts` et les redirections sont inchangés.
- F1.4 Aucun lien profond n'est cassé ; le paramètre `?lang=` continue d'être propagé.

### F2 — Constellation
- F2.1 Chaque étoile représente une solution réellement enregistrée (catalogue).
- F2.2 Aucune étoile ne duplique une entrée du menu (`Contact`, `Pricing`, carrières…).
- F2.3 Une verticale non opérationnelle n'est pas présentée comme pleinement disponible.
- F2.4 La composition fait comprendre : une plateforme commune + des environnements métier, chacun avec ses outils.

### F3 — Fiche interactive
- F3.1 Le clic sur une étoile ouvre une fiche ; il ne redirige pas immédiatement.
- F3.2 La fiche présente : description courte, profils concernés, fonctionnalités incluses, bénéfices/apps, bouton CTA.
- F3.3 Fermeture : Échap, bouton de fermeture, clic sur le fond.
- F3.4 Navigation clavier complète, focus piégé dans la fiche, focus restitué à l'étoile à la fermeture.
- F3.5 Expérience mobile adaptée (feuille plein écran scrollable).
- F3.6 Contenu alimenté par la source unique ; pas de duplication métier.

### F4 — CTA sous la constellation
- F4.1 Un CTA principal est visible sous la constellation.
- F4.2 Il mène à une destination pertinente du parcours de conversion.
- F4.3 Il ne masque ni les étoiles ni les libellés ; hiérarchie visuelle et espacement maîtrisés.

### F5 — Fond géographique animé
- F5.1 Cartes successives : France puis Algérie, Turquie, Tchad, continent africain.
- F5.2 La France n'est plus une couche figée.
- F5.3 Tracés géographiquement reconnaissables (source cartographique réelle).
- F5.4 Transition fluide et élégante ; la constellation reste dominante.
- F5.5 Pas d'animation lourde ; version mobile adaptée ; comportement de secours si les effets avancés ne sont pas supportés.

## B. Exigences techniques

### T1 — Performance
- T1.1 Aucune dépendance npm nouvelle.
- T1.2 Le fond géographique ne doit pas retarder l'affichage du contenu principal (SVG déclaratif, aucune requête, aucun calcul au runtime).
- T1.3 Le budget d'images three.js reste inchangé (le fond n'ajoute aucune frame GPU).
- T1.4 Le rendu WebGL reste en pause hors écran et onglet caché.

### T2 — Accessibilité
- T2.1 Le fond géographique et le canvas restent `aria-hidden` (décor).
- T2.2 La fiche est un `role="dialog"` `aria-modal`, avec titre lié `aria-labelledby`.
- T2.3 Échap ferme ; focus piégé (Tab/Shift+Tab cycliques) ; focus restitué.
- T2.4 `prefers-reduced-motion: reduce` : pas de rotation de cartes, pas de fondu, première carte affichée.
- T2.5 Focus visible sur tous les éléments interactifs ; contrastes conformes (WCAG AA) sur les textes des fiches et du CTA.
- T2.6 Toute destination portée par la constellation est également atteignable par un autre chemin (navbar, cartes de verticales, hub `/packs`, footer).

### T3 — SEO / routage
- T3.1 Aucune URL ajoutée, retirée ou renommée.
- T3.2 `sitemap.ts` inchangé.
- T3.3 Métadonnées de `/trade` inchangées.

### T4 — Qualité
- T4.1 `tsc --noEmit` sans erreur.
- T4.2 Suite Jest ciblée verte.
- T4.3 Gardes de conformité du dépôt respectées (i18n, copie FR, tokens de design, mojibake).
- T4.4 Le code suit les décisions du TAD ; pas de donnée métier dupliquée.

### T5 — Sécurité
- T5.1 Aucun secret dans le code, les commits, les issues, les logs ou la documentation.
- T5.2 Les accès GitHub ne doivent être utilisés que s'ils sont disponibles dans l'environnement sécurisé ; sinon, indiquer le prérequis manquant sans inventer de résultat.

## C. Traçabilité

| Exigence | Composant |
|---|---|
| F1 | `Navbar.tsx`, `app/(landing)/packs/page.tsx` |
| F2 | `data/solution-catalog.ts`, `HeroScene3DPanel.tsx` |
| F3 | `SolutionCard.tsx`, `HeroScene3DPanel.tsx` |
| F4 | `HeroSolutionsCTA.tsx`, `HeroSection.tsx` |
| F5 | `GeoBackdrop.tsx`, `data/geo/map-paths.ts`, `app/globals.css` |
| T1–T5 | l'ensemble |
