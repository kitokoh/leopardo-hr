# ADR 0023 — admin-dashboard : typage TypeScript incrémental (JSDoc + checkJs, puis vue-tsc)

## Statut

Proposée.

**Date** : 2026-09-20
**Décideurs** : Équipe technique Leopardo HR (proposition agent, issue #7849)

> Note numérotation : au moment de la rédaction, le dernier ADR est 0021 ;
> le numéro 0022 a été volontairement sauté car un autre agent peut créer un
> ADR en parallèle (protocole anti-doublon #2400). Si 0022 reste libre au
> merge, ce fichier peut être renuméroté.

## Contexte

Audit externe 2026-09-20 (issue #7849) : `front/admin-dashboard` est la
**console super-admin plateforme** (RBAC plateforme, tenants,
billing/subscriptions) — la surface front la plus sensible — et c'est la
seule app web du repo en **JavaScript pur**, alors que `web`, `travel-web`
et `web-offline` sont en TypeScript `strict: true`. La stack n'était pas
documentée (l'auditeur croyait le projet en React/TS).

Constat mesuré (voir `front/admin-dashboard/STACK.md`) :

- Vue 3.5 + Vite 8 + Pinia 4, ESLint 10 flat config, Prettier.
- **96 `.vue` + 18 `.js`, ≈ 32 800 LOC** ; les `.js` (≈ 3 500 LOC)
  concentrent le code critique : `services/api.js` (470), `stores/auth.js`
  (314), `services/token-storage.js` (92), `router/index.js` (629, guards).
- **Aucun test unitaire** ; 33 specs Playwright e2e (smoke auth, navigation,
  flux métier) — filet de sécurité comportemental réel mais grossier.
- Aucun `tsconfig.json`/`jsconfig.json` avant #7849 ; zéro typage, zéro
  vérification statique au-delà d'ESLint (`no-undef` réactivé par #2481).

## Décision

**Migration TypeScript incrémentale, dirigée par le risque — pas de big-bang.**

Un rewrite `.vue` → `lang="ts"` massif (96 composants, sans tests unitaires)
serait déraisonnable : coût élevé, risque de régression sur une console de
production, valeur marginale sur des vues de présentation. À l'inverse, le
statu quo est indéfendable sur `services/` et `stores/` : la couche
API/auth/RBAC manipule des contrats backend sans aucune garantie.

Décision en trois volets :

1. **Typage graduel du JS critique d'abord** : JSDoc typé + `checkJs`
   progressif (opt-in par fichier via `// @ts-check`), vérifié par `vue-tsc
   --noEmit`. Aucun renommage `.js` → `.ts` requis pour obtenir la valeur.
2. **Gel du JavaScript non typé nouveau** : tout nouveau fichier `src/**/*.js`
   doit être soit `.ts`, soit `.js` avec `// @ts-check` (garde lint/CI,
   ébauche ci-dessous).
3. **Composants `.vue`** : `<script setup lang="ts">` **obligatoire sur les
   nouveaux composants** ; conversion opportuniste des existants (au moment
   où on les modifie), en commençant par `views/auth/`, `views/subscriptions/`,
   `views/users/`. Pas d'objectif de conversion exhaustive.

## Plan par phases

| Phase | Contenu | Effort estimé | Risque | Critères de sortie |
|---|---|---|---|---|
| **0 — Socle outillage** (fait en partie ici) | `jsconfig.json` non-bloquant (`checkJs: false`, alias `@/`) ; STACK.md ; cette ADR | 0,5 j | Nul (aucun fichier converti, build inchangé) | `npm ci && npm run build` vert sans modification de src ; stack documentée |
| **1 — Couche API & auth (JSDoc + @ts-check)** | `src/services/token-storage.js`, `src/services/api.js`, `src/stores/auth.js` : JSDoc complet (typedefs des payloads API : `PlatformUser`, `Permission`, réponses paginées…), en-tête `// @ts-check` ; dev-dep `typescript` + `vue-tsc` ; script `npm run typecheck` (`vue-tsc --noEmit`) | 2–3 j | Faible (commentaires seulement ; e2e login-smoke/platform-auth couvrent la zone) | `vue-tsc --noEmit` vert ; 0 `any` implicite dans les 3 fichiers ; typedefs réutilisables dans `src/types/` (fichiers `.d.ts` ou JSDoc) |
| **2 — Stores & router restants** | `stores/{dashboard,realtime,travel,edgeNodes,locale,theme}.js`, `router/index.js` (typage `meta` : `requiresAuth`, permissions), `navigation/navigation.js`, `services/{travel,showcase}.js`, composables | 3–4 j | Faible/moyen (realtime.js 427 LOC, couplage socket.io) | Tous les `.js` de `src/` sous `@ts-check` ; `checkJs: true` activable globalement dans jsconfig/tsconfig sans erreur |
| **3 — Gel du JS nouveau + garde CI** | Basculer `jsconfig.json` → `tsconfig.json` (`allowJs: true`, `checkJs: true`, `strict: false`) ; garde CI `typecheck` bloquante ; règle « no-new-js » (ébauche ci-dessous) | 1 j | Faible | CI échoue si nouveau `.js` sans `@ts-check` ou si `vue-tsc` casse ; documenté dans STACK.md |
| **4 — Composants sensibles en `lang="ts"`** (opportuniste, continu) | `views/auth/`, `views/subscriptions/`, `views/users/`, `views/system/` puis au fil des retouches ; ratio `lang="ts"`/total suivi trimestriellement | ~0,5 j / vue, étalé | Moyen (templates non vérifiés avant conversion) | Ratio publié ; 100 % des **nouveaux** composants en TS ; zones auth/billing converties |
| **5 (optionnel) — strict progressif** | `strict: true` par dossier (`services/`, `stores/` d'abord) via overrides | 2–3 j | Moyen | `strict` sur services+stores ; décision explicite pour le reste |

**Total cœur (phases 0–3) : ≈ 7–9 jours-homme**, sans conversion de masse,
sans rupture de build, réversible à chaque phase.

Ordre des dossiers (risque décroissant) :
`services/token-storage` → `services/api` → `stores/auth` → `router/` →
`stores/*` → `navigation/` → `composables/` → `views/auth|subscriptions|users` → reste.

## Garde CI proposée (DOC uniquement — ne modifie pas `.github/` ici)

Ébauche de step à ajouter dans `web-ci.yml` (ou `tests.yml`) **à partir de la
phase 3** :

```yaml
# .github/workflows/web-ci.yml (extrait — à intégrer par un mainteneur)
- name: Typecheck admin-dashboard (vue-tsc, checkJs progressif)
  working-directory: front/admin-dashboard
  run: npm run typecheck   # vue-tsc --noEmit

- name: Gel du JS nouveau (no-new-js)
  working-directory: front/admin-dashboard
  run: |
    # Tout .js de src/ doit être opt-in @ts-check (liste d'exemptions gelée
    # dans scripts/legacy-js-allowlist.txt, jamais étendue).
    fail=0
    while IFS= read -r f; do
      grep -q '@ts-check' "$f" || grep -qxF "$f" scripts/legacy-js-allowlist.txt || { echo "JS non typé interdit: $f"; fail=1; }
    done < <(git ls-files 'src/**/*.js')
    exit $fail
```

Variante ESLint (équivalente, phase 3) — overrides flat config :

```js
// eslint.config.js (extrait) — interdit tout nouveau .js hors allowlist
{
  files: ['src/**/*.js'],
  ignores: [/* allowlist gelée des .js legacy pas encore sous @ts-check */],
  rules: { 'no-restricted-syntax': ['error', { selector: 'Program:not(:has(> :first-child))', message: 'Nouveau code en .ts ou .js + // @ts-check (ADR 0023).' }] },
}
```

(La forme exacte — script shell vs plugin ESLint — sera tranchée à la
phase 3 ; l'intention est : **allowlist gelée, tout nouveau JS non typé
fait échouer la CI**.)

## Conséquences

- La divergence de stack est désormais **documentée et assumée** : Vue 3/JS
  aujourd'hui, typage incrémental dirigé par le risque (pas de parité
  `strict: true` promise à court terme avec web/travel-web/web-offline).
- Le coût est concentré là où le typage paie : contrats API, auth, RBAC,
  billing. Les 96 vues ne bloquent rien.
- `jsconfig.json` (phase 0, livré avec #7849) est purement additif :
  `checkJs: false`, aucun impact sur `vite build` ni sur le lint.
- Dépendances à ajouter en phase 1 (nécessite la décision-dépendance du
  repo) : `typescript`, `vue-tsc` (dev only).
- Risque résiduel : sans tests unitaires, les conversions `.vue` (phase 4)
  s'appuient sur les e2e Playwright — d'où le choix « opportuniste » plutôt
  que systématique.

## Règles opérationnelles

- Nouveau composant : `<script setup lang="ts">` (dès acceptation de l'ADR).
- Nouveau module JS : `.ts`, ou `.js` + `// @ts-check` + JSDoc.
- Ne jamais étendre l'allowlist legacy ; on ne peut qu'en retirer.
- `STACK.md` est le point d'entrée de la stack réelle ; le tenir à jour à
  chaque phase (ratio de conversion inclus à partir de la phase 4).
