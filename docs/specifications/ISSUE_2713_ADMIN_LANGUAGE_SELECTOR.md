# Mini-spécification — Issue #2713

## Objectif

Rendre le sélecteur de langue de l’admin dashboard réellement accessible aux utilisateurs afin d’activer les catalogues FR, AR, TR et EN déjà présents dans le projet.

## Contexte

Le store `locale` expose déjà `supported`, `current` et `setLocale()`, persiste le choix dans `localStorage` et applique la direction RTL pour l’arabe. Cependant, aucune commande UI ne permettait d’appeler `setLocale`; les dictionnaires restaient donc inatteignables depuis l’interface.

## Périmètre

Le changement ajoute un sélecteur global dans `Header.vue`, relié au store existant. Il ne modifie ni les catalogues, ni les routes, ni les contrats API, ni la logique d’authentification.

## Règles fonctionnelles

1. Le contrôle expose les quatre locales supportées par `localeStore.supported`.
2. La valeur courante est sélectionnée au chargement.
3. Un changement appelle `localeStore.setLocale()`, ce qui conserve la persistance et la mise à jour de `document.lang`/`document.dir` existantes.
4. Les noms affichés restent compréhensibles dans leur forme native : Français, العربية, Türkçe, English.
5. Le contrôle est clavier-accessible, possède une étiquette visible pour les lecteurs d’écran et conserve un style compatible avec les thèmes clair/sombre.

## Critères d’acceptation

- Un utilisateur connecté voit un sélecteur de langue dans le header global.
- Le sélecteur propose FR, AR, TR et EN et reflète la locale active.
- Le changement vers AR applique la direction RTL via le store existant; les autres locales restent en LTR.
- Le choix est persistant après rechargement via `admin_locale`.
- `npm run lint` et `npm run build` passent dans `front/admin-dashboard`.

## Fichiers concernés

- `front/admin-dashboard/src/components/layout/Header.vue`
- `docs/specifications/ISSUE_2713_ADMIN_LANGUAGE_SELECTOR.md`
- `CHANGELOG.md`

## Plan de retour arrière

Réversion du commit de l’issue #2713; aucun changement de schéma ou de donnée persistante côté serveur n’est requis.

## Trace Spec Kit

Issue : #2713  
Branche : `fix/2713-admin-language-selector`  
Date : 2026-08-15

## Statut

Implémentation prête pour revue.
