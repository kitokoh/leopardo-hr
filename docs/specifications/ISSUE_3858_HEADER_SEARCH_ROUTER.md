# ISSUE_3858 — Header search : useRouter() dans event handler → TypeError

**Statut**: Fixed (PR `fix/3858-header-search-router`) · **Priorité**: P1 · **Module**: admin

## Constat

`Header.vue:303-308` appelait `useRouter()` dans `handleSearch()` (hors setup).
`useRouter()` = `inject(routerKey)` (vue-router 5) ; hors `setup()`, `inject()`
renvoie `undefined` → `router.getRoutes()` lève une TypeError non catchée.
La recherche header était morte depuis la refonte premium.

## Correctif

Instance `const router = useRouter()` remontée au niveau setup (ligne 266) ;
`handleSearch()` utilise l'instance. Vérifié : `eslint .` et `vite build` OK.
Grep : plus aucun `useRouter()` dans un corps de fonction dans admin-dashboard.
