# Plan 74 — Module Marketing, Phase 4 : UI web dashboard

Date : 2026-07-19
Statut : livre (lots 74.1 a 74.3 termines, branche
`codex/marketing-phase4-web-ui`)

> Ecart vs plan initial : la route dashboard est `/social-marketing`
> et non `/marketing`. La page vitrine publique existante
> `src/app/(landing)/marketing/page.tsx` occupe deja `/marketing` (Next.js
> App Router refuse deux pages paralleles resolvant le meme chemin,
> erreur de build Turbopack a la construction). `client-features.ts`,
> la page dashboard et les tests e2e utilisent donc `/social-marketing`.
Reference : `MARKETING_MODULE_PLAN.md` (racine du repo), Phase 3 livree
(branche `codex/marketing-phase3-api-cron`, non mergee).

## Contexte / audit

Phase 3 (cron + API REST `api/v1/marketing/*`) est prete cote backend :
`GET/POST /marketing/social-account`, `POST .../connect`,
`POST .../disconnect`, `GET/POST /marketing/social-posts`,
`GET/PATCH/DELETE /marketing/social-posts/{id}`,
`POST /marketing/social-posts/{id}/publish`. Middleware
`api.manager:marketing,principal` (seuls managers `marketing` ou
`principal` peuvent y acceder).

Cote frontend (`front/web`, Next.js App Router, dashboard client
manager) :
- Aucune route `/marketing` n'existe sous `src/app/(dashboard)/`.
- `src/lib/client-features.ts` (`CLIENT_MODULES` + `ROUTE_TO_MODULE`) ne
  connait pas de module `marketing` — le lien sidebar et le feature-gate
  (`FeatureLockedPanel`) reposent entierement sur ce fichier.
- Les pages existantes suivent deux styles : pages "riches" avec stats +
  cards (`training/page.tsx`, `contracts/page.tsx`, `reports/page.tsx`)
  utilisant `framer-motion` + `lucide-react`, et pages "connectees API"
  plus sobres avec `ModulePageShell` (`absences/page.tsx`,
  `employees/page.tsx`). On retient `ModulePageShell` pour Marketing car
  la page combine plusieurs sous-sections (compte + liste posts +
  formulaire) et le shell donne deja le header + placeholder standard.
- Aucun composant de formulaire de creation de post generique existant a
  reutiliser ; on ecrit un formulaire local a la page (pas de nouveau
  design system, on reste sur Tailwind inline comme le reste du
  dashboard).
- `apiFetch` (`src/lib/api-client.ts`) gere deja l'auth Bearer, les
  erreurs 401 (redirect login) et les erreurs API (`ApiError`) — reutilise
  tel quel, pas de nouveau client HTTP.
- Role d'acces : seuls `role=manager` + `manager_role in (principal,
  marketing)` doivent voir le module actif (aligne avec le backend
  `api.manager:marketing,principal`). Pattern existant dans
  `hasRoleAccess()` : cas special deja code pour `billing`/`integrations`
  (restriction a `principal` uniquement) — Marketing ajoute un cas
  equivalent mais autorisant aussi `marketing`.
- Pas de composant d'upload media existant dans `front/web` (les
  `media_paths` de l'API attendent des URLs de chaines deja hebergees,
  pas d'upload direct) : Phase 4 limite la creation de post a
  texte + plateformes + date de planification, sans upload de fichier
  (poste texte seul, ce qui couvre le cas d'usage principal Ayrshare).
  L'upload media pourra etre ajoute dans un lot ulterieur si demande.
- Aucun test Jest/RTL existant pour les pages `(dashboard)/*` (seuls
  `src/modules/vitrine/**` ont des tests unitaires) ; la couverture de
  ces pages passe par les specs Playwright `e2e/client-feature-gates.spec.ts`
  (feature gating) et `e2e/manager-workday-smoke.spec.ts` (smoke complet).
  Ce plan suit le meme pattern : pas de nouveau test Jest, ajout cible
  dans `client-feature-gates.spec.ts` pour verifier le nouveau module.

## Lot 74.1 — Entree module + acces + squelette de page

- `src/lib/client-features.ts` :
  - Ajout de `'marketing'` a `ClientModuleKey`.
  - Nouvelle entree dans `CLIENT_MODULES` (`href: '/marketing'`, groupe
    `general`, `capabilityKeys: ['marketing', 'can_view_marketing']`,
    `featureKeys: ['marketing', 'social_marketing']`, `allowedRoles:
    ['manager']` — filtre precis fait dans `hasRoleAccess`).
  - `ROUTE_TO_MODULE['/marketing'] = 'marketing'`.
  - `hasRoleAccess()` : cas special Marketing — seuls `manager_role in
    (principal, marketing)` autorises (meme logique que le cas
    billing/integrations, inverse : liste blanche au lieu de
    liste unique).
- `src/app/(dashboard)/marketing/page.tsx` (nouveau) : squelette avec
  `ModulePageShell`, chargement de l'etat du compte
  (`GET /marketing/social-account`, gere le 404
  `SOCIAL_ACCOUNT_NOT_FOUND` comme etat "non connecte" plutot qu'une
  erreur), panneau "Connecter mon compte" (formulaire `display_name` +
  bouton, `POST /marketing/social-account/connect`) et bouton
  "Deconnecter" quand un compte actif existe
  (`POST /marketing/social-account/disconnect`).
- Mise a jour `e2e/client-feature-gates.spec.ts` : nouveau test
  "marketing manager can access the marketing module" (role_locked pour
  un manager `rh`, accessible pour `marketing`/`principal`).

## Lot 74.2 — Liste des posts + creation + planification/publication

- Meme fichier `marketing/page.tsx`, section additionnelle :
  - Liste des posts (`GET /marketing/social-posts`), affichage
    contenu/plateformes/statut/date planifiee, pagination simple
    (bouton "Charger plus" si `meta.current_page < meta.last_page`).
  - Formulaire de creation (`content`, checkboxes plateformes limitees a
    la liste `StoreSocialPostRequest::supportedPlatforms()` cote
    frontend en dur — meme liste que le backend, a garder synchronisee
    manuellement faute d'endpoint de decouverte des plateformes cote
    API), champ date/heure optionnel (`scheduled_at`).
  - `POST /marketing/social-posts` a la soumission ; si succes, refresh
    de la liste.
  - Actions rapides par ligne : "Publier maintenant"
    (`POST .../publish` sans date) et "Supprimer" (`DELETE ...`) —
    uniquement visibles/actives sur les posts `draft`/`scheduled` (pas
    `published`), coherent avec les policies backend.
- Pas de nouveau test Playwright dedie pour ce lot (couvert par le smoke
  existant + le test de gating du lot 74.1) ; verification manuelle via
  `npm run build` + `npm run lint` avant push.

## Lot 74.3 — Qualite, verification, documentation

- `npm run lint` (ESLint, `--max-warnings 0`) sur `front/web`.
- `npm run build` (Next.js) pour valider le typage TypeScript strict et
  l'absence d'erreur de build.
- Mise a jour `MARKETING_MODULE_PLAN.md` : Phase 4 passee a "Prete" avec
  detail livre ; note explicite sur l'absence d'upload media (hors
  scope, cf. audit ci-dessus).

## Notes de sequencement

- Chaque lot correspond a un commit/push separe sur la branche de
  travail `codex/marketing-phase4-web-ui`, dans l'ordre
  74.1 -> 74.2 -> 74.3, verifie (`lint`/`build`) avant chaque push.
- Pas de creation de PR/merge automatique (meme contrainte que Phase 3) :
  commits pousses directement sur la branche dediee, merge sur `main`
  laisse a une decision humaine separee.
- Phase 5 (onglet mobile `leopardo_manager`) reste hors scope de ce plan.
