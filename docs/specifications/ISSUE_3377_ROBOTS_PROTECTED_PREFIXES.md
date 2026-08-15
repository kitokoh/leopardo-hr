# Mini-spécification — Issue #3377

## Objectif

Empêcher Googlebot/Bingbot de crawler les routes session-protégées servies à la racine du domaine (302 → login indexés), en garantissant une source de vérité unique entre `robots.ts` et le matcher du middleware.

## Constat

- `src/middleware.ts` protège 14 préfixes racine (`/absences`, `/attendance`, `/billing`, …).
- `src/app/robots.ts` (#3375) interdisait ces préfixes au groupe `user-agent: *` **mais** les groupes dédiés `Googlebot`/`Bingbot` (`allow: /` sans disallow) **écrasent** le groupe `*` selon la spec robots.txt → les deux principaux crawlers gardaient un accès total.
- Deux listes codées en dur (middleware + robots) sans garde anti-dérive.

## Décision

1. Nouvelle source unique `src/lib/protected-prefixes.ts` (`PROTECTED_PREFIXES`).
2. `robots.ts` consomme la source pour les groupes `*`, `Googlebot` et `Bingbot`.
3. Le `config.matcher` du middleware doit rester littéral (contrainte d'analyse statique Next.js) → le middleware n'importe pas la source ; un test Jest (`protected-prefixes.test.ts`) vérifie la parité dans les deux sens (chaque préfixe de la source est matché ; le matcher ne protège rien hors source) + la présence des disallow dans robots.txt pour les 3 groupes de bots.

## Critères d'acceptation

1. `GET /robots.txt` : les 14 préfixes + `/admin`, `/api`, `/auth` en disallow pour `*`, `Googlebot`, `Bingbot`.
2. Dérive middleware ↔ source = test rouge.
3. `eslint` + `tsc --noEmit` + `jest protected-prefixes` verts.

## Fichiers concernés

- `front/web/src/lib/protected-prefixes.ts` (nouveau)
- `front/web/src/app/robots.ts`
- `front/web/src/lib/__tests__/protected-prefixes.test.ts` (nouveau)
- `CHANGELOG.md`

## Plan de retour arrière

Réversion du commit — aucun impact runtime applicatif (robots.txt régénéré au build).
