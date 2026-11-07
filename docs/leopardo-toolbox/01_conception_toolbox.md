# LEOPARDO TOOLBOX — Document de conception

> **Mission** : créer une catégorie autonome « Leopardo Toolbox » — un écosystème d'outils numériques universels (calculateurs, convertisseurs) accessible sans authentification depuis la vitrine publique, extensible, léger et fiable.
> **Contexte projet** : Leopardo HR évolue vers Leopardo Business OS (documenté dans `docs/mission-business-os/`).
> **Statut** : Phase 1 — conception (2026-11-05). L'implémentation suit dans les phases autorisées.

---

## 0. Résumé exécutif

Leopardo Toolbox est livré comme **module frontend autonome** dans la vitrine Next.js (`front/web`), à calcul **100 % côté client**, sans backend dédié dans le premier périmètre. Trois principes :

1. **Autonome** : pas un module RH ni une sous-catégorie métier — catégorie à part entière de la vitrine publique.
2. **Extensible** : catalogue d'outils déclaratif (métadonnées JSON/TS) découplé de la logique de calcul pure (fonctions pures testées).
3. **Sans risque pour le Business OS** : aucune migration tenant, aucun endpoint REST, aucun changement d'identité/rôles/paiements. Si Toolbox est indisponible, la vitrine et les verticales continuent de fonctionner.

## 1. Audit de l'existant (Phase 0) — constats factuels

### 1.1 Monorepo multi-stack

| Composant | Stack | Rôle |
|---|---|---|
| `api/` | Laravel PHP 8.4, PostgreSQL multi-tenant | Backend métier (39 modules DDD sous `app/Modules/`, socle `Core/Auth`, `Core/Tenant`, `Core/Feature`) |
| `front/web/` | Next.js 16 (App Router), React 19, TypeScript 5.9, Tailwind 4, Framer Motion | **Vitrine publique** + dashboard SaaS (déployé Vercel) |
| `front/mobile_apps/` | Flutter (9 apps) | Apps mobiles |
| `front/admin-dashboard/` | Vue 3 | Console super-admin plateforme |
| `shared/i18n/` | JSON 4 langues (fr, en, ar, tr) | Source unique des traductions, propagée vers web/backend/mobile |

### 1.2 Vitrine publique (`front/web`)

- **Routes** : groupe `(landing)` pour les pages marketing (pricing, employes, comptabilite, marketing, decouvrir, blog, guides, …) ; groupe `(dashboard)` pour les zones connectées ; routes publiques racine (`/vitrine/[slug]`, `/restaurants`, `/decouvrir`, `/packs`, `/trade`, …).
- **Navigation** : `Navbar.tsx` (≤ 7 entrées, libellés i18n `vitrine.nav.*`) et `Footer.tsx` (route map `getFooterHref()`).
- **Garde pages orphelines (#8075)** : toute page top-level de `(landing)` doit être liée par un lien interne (navbar ou footer) — vérifié par `scripts/check-orphan-pages.mjs` en CI.
- **i18n** : catalogues générés depuis `shared/i18n/locales/{fr,en,ar,tr}.json` via `shared/i18n/sync/sync-web.js` → `src/lib/i18n/locales/*.json`, chargés par `locale-catalog.ts` (`t(locale, key)`). La vitrine a une couche de copie typée `VitrineCopy` dans `modules/vitrine/lib/vitrine-locale.ts` (FR/EN/TR/AR en dur dans ce fichier, pas encore dans le catalogue partagé).
- **Design system** : Tailwind 4, palettes emerald/or, dark mode, composants communs `modules/vitrine/components/common/*` (Button, Card, Input, Badge, Container, Section).

### 1.3 Authentification & permissions

- **Session** : cookie `leopardo_token` (JWT). Middleware `src/proxy.ts` redirige les préfixes protégés (`PROTECTED_PREFIXES` dans `src/lib/protected-prefixes.ts`) vers `/auth/login` — **gate cosmétique**, la vraie auth est côté API.
- **Vitrine publique = sans session** : `/decouvrir`, `/restaurants`, `/packs`, `/trade` sont des précédents de surfaces publiques sans auth.
- **RBAC backend** : rôles `super_admin`, `company_admin`, `hr_manager`, `manager`, `employee` ; isolation tenant stricte (`company_id`, `search_path`, `BelongsToCompany`).

### 1.4 Contraintes de gouvernance (critiques pour cette mission)

- **Verrou vitrine (2026-10-28)** : `.github/workflows/vitrine-lock-guard.yml` + `dev-hub/tools/check-vitrine-agent-lock.sh` bloquent toute PR touchant `front/web/src/modules/vitrine/**` ou `front/web/src/app/(landing)/**` **sauf si** : branche préfixée `feat|fix|chore|mkt|agent)/vitrine-`, auteur `kitokoh`, ou label `vitrine-authorized`.
  → **Notre branche devra donc être préfixée `vitrine-`** (ex. `feat/vitrine-toolbox`) pour intégrer la Toolbox dans la navbar/footer de la vitrine.
- **Freeze Scope 60j (#5147)** : expiré (gate J60 `held` le 2026-10-26) — plus de contrainte de revue, mais prudence de non-régression.
- **Registre BC (#5859)** : nouveau bounded context = enregistrement dans `dev-hub/governance/bounded-context-registry.json` + label. Notre position : **pas de BC backend** (aucune logique côté API au premier périmètre), la Toolbox vit dans le frontend vitrine ; un registre « BC-Toolbox » n'est requis que si on ajoute un module backend plus tard — et il faudra alors une décision explicite (exigence mission : « pas de Bounded Context supplémentaire sans justification architecturale »).
- **Constitution Spec-First** : toute feature significative commence par une spec → ce document + issues GitHub avant code.

### 1.5 Précédent utile

- `RoiCalculator.tsx` : calculateur ROI 100 % client-side intégré à la landing — confirme le pattern « formulaires + formules côté client » déjà accepté dans la vitrine.
- `sitemap.ts` : liste les routes statiques ; à étendre pour indexer les pages outils.
- Tests : Jest (jsdom) + Testing Library, Playwright e2e, lint ESLint `--max-warnings 0`, typecheck `tsc --noEmit`, gardes mojibake/accents FR (`check:french-copy`) et `check:orphans`.

## 2. Décisions structurantes (posées dans le périmètre autorisé)

| # | Décision | Justification | Statut |
|---|---|---|---|
| D1 | Toolbox = **module frontend autonome** `front/web/src/modules/toolbox/**` (pas de BC backend) | Calculs purs côté client (exigence mission), zéro risque pour le Business OS, coût minimal | ✅ posée |
| D2 | **Calcul 100 % client** au premier périmètre ; **aucun endpoint API dédié** | « Calculs côté client quand c'est approprié ; pas d'API backend par calculatrice » | ✅ posée |
| D3 | Catalogue **déclaratif** : `data/catalog.ts` (métadonnées) + un module par outil (`tools/<slug>/logic.ts` + `tool-page.tsx` + `form.tsx`) | « métadonnées séparées de la logique » ; ajout d'un outil = 1 dossier + 1 entrée catalogue | ✅ posée |
| D4 | Accès **sans authentification** sur `/outils` + `/outils/[slug]` (hors `PROTECTED_PREFIXES`) | Exigence mission | ✅ posée |
| D5 | **Pas de persistance serveur** au premier périmètre ; espace « Mes outils » en **localStorage** par défaut | Pas de compte artificiel pour visiteurs ; persistance backend = décision séparée (D9) | ✅ posée (défaut) |
| D6 | Reprendre les **composants UI communs** de la vitrine et le **design system** (emerald/or, dark) — pas de nouvelle bibliothèque | « éviter dépendances lourdes » | ✅ posée |
| D7 | **i18n 4 langues** via le catalogue partagé (clés `toolbox.*`) + sync | Convention projet | ✅ posée |
| D8 | Branche dédiée préfixée `vitrine-` pour l'intégration navbar/footer | Verrou vitrine CI | ✅ posée |
| D9 | **Décision à valider** : persistance des favoris/historique (localStorage seul vs API + table tenant `toolbox_user_state`) | Touche l'identité/les données utilisateur → validation explicite requise (mission : « présentées pour validation avant toute modification ») | ⏳ **à valider** |
| D10 | **Décision à valider** : visibilité/métadonnées d'administration backend (table `toolbox_tools` pilotée par super-admin vs catalogue statique versionné) | Touche l'administration plateforme → validation requise | ⏳ **à valider** |

## 3. Architecture cible

### 3.1 Placement dans le monorepo

```
front/web/src/modules/toolbox/
├── AGENTS.md                  # Règles du module (autonomie, interdictions)
├── README.md                  # Mode d'emploi de contribution
├── index.ts                   # Exports publics du module
├── types/
│   └── index.ts               # ToolDefinition, ToolCategory, ToolInputSpec, ToolResult…
├── data/
│   ├── catalog.ts             # 🗂 Catalogue déclaratif (métadonnées de tous les outils)
│   └── categories.ts          # Catégories (calcul, conversion, finances, temps, santé…)
├── lib/
│   ├── registry.ts            # Résolution slug → définition (méta-lookup pur, testable)
│   ├── format.ts              # Formatage nombres, devises, unités (décimales, fr/en…)
│   └── validation.ts          # Helpers de validation d'entrées (bornes, zéro, NaN…)
├── components/
│   ├── ToolCard.tsx           # Carte d'outil (catalogue)
│   ├── ToolShell.tsx          # Gabarit de page outil (titre, description, avertisseurs)
│   ├── ToolForm.tsx           # Rendu générique d'un formulaire depuis la ToolDefinition
│   ├── NumberField.tsx        # Champ numérique avec unité, bornes, pas
│   ├── ResultPanel.tsx        # Affichage structuré des résultats
│   └── DisclaimerBox.tsx      # Avertissements (résultats théoriques, pas un conseil)
├── tools/
│   ├── imc/                   # Exemple : IMC (indice de masse corporelle)
│   │   ├── logic.ts           # calculateImc() — fonction pure + formules documentées
│   │   ├── logic.test.ts      # Tests (cas connus, limites)
│   │   └── metadata.ts        # Métadonnées spécifiques (traductions, unités, bornes)
│   └── …/                     # Un dossier par outil du premier lot
└── app/                       # (dans src/app, pas ici — voir 3.2)
```

### 3.2 Routes (App Router Next.js)

| Route | Type | Accès |
|---|---|---|
| `/outils` | `src/app/outils/page.tsx` | Public (catalogue, listes par catégorie, recherche client) |
| `/outils/[slug]` | `src/app/outils/[slug]/page.tsx` | Public (page outil statique, `generateStaticParams`, métadonnées SEO) |
| `/outils/[slug]/not-found` | 404 si slug inconnu | — |

**Non inclus** : `PROTECTED_PREFIXES` (aucune entrée ajoutée) → pas de gate de session → visiteur anonyme servi directement. Pattern exact des surfaces publiques existantes (`/decouvrir`, `/packs`).

### 3.3 Modèle de données (catalogue statique, pas de BDD)

```ts
type ToolCategoryId = 'calcul' | 'conversion' | 'finance' | 'temps' | 'sante' | 'travail';

interface ToolDefinition {
  slug: string;                       // 'imc', 'tva', 'salaire-net'…
  category: ToolCategoryId;
  nameKey: string;                    // clé i18n `toolbox.tools.<slug>.name`
  descriptionKey: string;
  keywords: string[];                 // SEO + recherche catalogue
  version: string;                   // sémantique du calcul (v1.0.0)
  inputs: ToolInputSpec[];            // schéma des champs
  inputDefaults: Record<string, number | string>;
  warnings?: ToolWarning[];          // avertissements à afficher (ex. « résultat théorique »)
  references?: { labelKey: string; url: string }[]; // références/formules
  component: 'imc' | 'tva' | …;      // clé du formulaire dédié (fallback générique)
}
```

`ToolInputSpec` : `{ id, labelKey, unit?, min?, max?, step?, placeholder?, required?, type: 'number'|'select'|'date' }`.

Les **formules vivent dans le code** (`tools/<slug>/logic.ts`), documentées par commentaires : formule, unités, hypothèses, références (ex. OMS pour l'IMC). Le catalogue ne contient **aucune logique** (métadonnées pures) → garde testable.

### 3.4 Stratégie d'accès anonyme / authentifié

| Situation | Comportement |
|---|---|
| Visiteur anonyme (`/outils/*`) | ✅ Accès complet aux calculs. Aucun compte requis. Pas de copie locale persistée — on peut proposer soft « enregistrer dans l'onglet » (localStorage avec badge « stocké sur cet appareil »). |
| Utilisateur authentifié (cookie `leopardo_token` présent) | ✅ Mêmes outils publics + possibilité de **« Mes outils »** : favoris, historique, dernières valeurs — **localStorage d'abord** (D5), synchronisation serveur = décision D9. Le module ne lit pas l'identité pour les calculs. |
| Rôles RH / manager / employé | Aucun impact : la Toolbox est une catégorie publique, hors RBAC métier. |
| super_admin | Aucun nouveau privilège au premier périmètre (D10). |

### 3.5 Sécurité & non-régression

- **Aucun endpoint API ajouté** → aucune surface d'attaque backend, aucun changement RBAC/tenant/paiement.
- **Calculs côté client uniquement** → pas d'exfiltration de données, pas de donnée personnelle collectée (aucun analytics au premier périmètre ; si analytics, uniquement événement « outil utilisé » agrégé et conforme RGPD, sans valeur saisie).
- **Sanitization des saisies** : bornes min/max sur chaque champ, gestion `NaN`/`Infinity`/négatifs/division par zéro dans `lib/validation.ts`.
- **CSP** : pas de nouveau script externe ; aucune directive à modifier (vérifier à la relecture `src/lib/csp.ts`).
- **isolation** : dossier `src/modules/toolbox/` 100 % autonome ; la seule dépendance vers l'existant est `@/lib/i18n` (catalogue) et les composants `common` de la vitrine (composants UI, pas de logique métier). Les pages `(landing)` ne changent que pour le lien navbar/footer.

## 4. Parcours utilisateur

1. **Découverte** : visiteur ouvre la vitrine → navbar (liens) ou footer → catégorie « Outils ».
2. **Catalogue** (`/outils`) : cartes par catégorie, recherche par nom/mot-clé, tri (populaire/alphabétique).
3. **Utilisation** (`/outils/[slug]`) : formulaire (avec unités claires, bornes) → résultat instantané (mise à jour en direct à la saisie) → avertissements éventuels → bouton « copier le résultat ».
4. **Retour/partage** : lien permalink de l'outil (pas d'état dans l'URL par défaut), bouton partage.
5. **(Connecté, optionnel)** : « ☆ Favori » + historique local → « Mes outils ».

Chaque page outil embarquera ses **métadonnées SEO** (title, description, keywords) et sera **indexable** (sitemap, robots autorisé).

## 5. Arborescence des outils (premier lot proposé)

Critères de sélection (mission) : utilité, demande, simplicité, fiabilité des formules, risque d'erreur faible, testabilité, indépendance technique, valeur grand public.

| # | Outil (slug) | Catégorie | Formule/approche | Fiabilité | Justification (critères) |
|---|---|---|---|---|---|
| 1 | **IMC** (`imc`) | Santé | `poids(kg) / (taille(m))²` + catégories OMS | Élevée (standard OMS) | Simplicité maximale, formule universelle et vérifiable, cas tests clairs |
| 2 | **TVA** (`tva`) | Finance | `HT × taux`, reverse `TTC → HT`, taux FR 2.1/5.5/10/20 % | Élevée (taux officiels FR, documentés) | Demande grand public forte, calcul simple, risque faible |
| 3 | **Salaire brut → net** (`salaire-net`) | Travail | Estimations cotisations (message : **estimation indicative**, ne remplace pas un bulletin) | Moyenne (dépend pays/profil) → **avertissement explicite** | Demande très forte, valeur grand public ; restrictions : estimation, pas de légal |
| 4 | **Convertisseur devises** (`convertisseur-devises`) | Conversion | Taux fixes de référence documentés, **hors ligne** (pas d'appel API) + base saisie utilisateur | Moyenne (taux datés, à confirmer) → **avertissement** | Utilité universelle ; risque : taux obsolètes → on documente la date de référence |
| 5 | **Convertisseur unités** (`convertisseur-unites`) | Conversion | Facteurs exacts (métrique↔impérial, volume, masse, longueur, température) | Élevée (facteurs exacts normalisés) | Indépendant, très testable, aucun risque |
| 6 | **Durée entre deux dates** (`duree-dates`) | Temps | Calcul calendaire (années/mois/jours) | Élevée (logique pure) | Simple, demandé, testable |
| 7 | **Pourcentage / évolution** (`pourcentage`) | Calcul | `%` d'une valeur, évolution `(nouveau−ancien)/ancien × 100` | Élevée | Universel, base des autres outils |
| 8 | **Règle de trois / proportionnalité** (`regle-de-trois`) | Calcul | `a/b = c/x → x = b×c/a` + gestion des zéros | Élevée | Pédagogique, testable, anti-erreur (vérif. zéro) |
| 9 | **Age / âge en années-mois-jours** (`age`) | Temps | Différence calendaire depuis date de naissance | Élevée | Simple, intemporel |
| 10 | **Taux horaire / mensuel** (`taux-horaire`) | Travail | `brut mensuel / (heures/semaine × 52 / 12)` | Élevée (formule standard) | Utile pour freelances/employés, avertissement « approximatif » |

**Premier lot retenu pour l'implémentation (sous-ensemble) :** IMC, TVA, salaire brut→net (estimation), convertisseur d'unités, pourcentage/évolution — les 5 plus testables et à plus forte valeur. Les autres (devises avec taux fixes, dates, règle de trois, âge, taux horaire) sont prévus au lot suivant (même structure, ajout incrémental).

## 6. Plan d'implémentation

### Étape 1 — Fondation (branche `feat/vitrine-toolbox`)
1. `src/modules/toolbox/{types,data,lib,components}/` squelettes.
2. Catalogue déclaratif (2-3 outils d'abord) + `registry.ts` + tests du registry.
3. Shell UI : `ToolShell`, `ToolCard`, `ToolForm` (générique), `NumberField`, `ResultPanel`, `DisclaimerBox`.
4. Pages `/outils` + `/outils/[slug]` (SSG, generateStaticParams, metadata SEO).
5. i18n : clés `toolbox.*` dans les 4 catalogues (`shared/i18n/locales/{fr,en,ar,tr}.json`) + resync web + copies VitrineCopy si besoin.

### Étape 2 — Premier lot d'outils
- `imc`, `tva`, `salaire-net`, `convertisseur-unites`, `pourcentage`.
- Chaque outil : `logic.ts` (fonctions pures documentées), `logic.test.ts` (cas connus + limites), `metadata.ts`, formulaire (générique ou dédié).

### Étape 3 — Intégration vitrine (zone verrouillée, branche `vitrine-`)
- Navbar : entrée « Outils » (≤ 7 entrées — retirer/ajuster un lien si nécessaire, à valider avec le proprio vitrine).
- Footer : colonne Ressources + `getFooterHref` (satisfait garde #8075).
- `sitemap.ts` : `/outils` + 5 slugs (priorité 0.6-0.7).
- robots : déjà autorisé (hors PROTECTED_PREFIXES).

### Étape 4 — « Mes outils » (dépend de la décision D9)
- localStorage : favoris + historique (10 dernières valeurs par outil) + « reprises ».
- Synchronisation serveur (multi-appareils) : **uniquement si D9 validé** → nouveau module API + table tenant → sort du périmètre actuel sans décision.

### Étape 5 — Qualité & validation
- `npm run lint`, `npm run typecheck`, `npm test` (Jest), `npm run build`.
- Gardes projet : `check:mojibake`, `check:french-copy` (aucun accent littéral FR hors i18n), `check:orphans` (page liée), `check:assets`.
- Tests ciblés : formules (cas connus, zéro, négatifs, extrêmes, NaN), composants (rendu, validation, disclaimers), intégration catalogue (slug unique, entrées complètes).

## 7. Matrice d'accès (visiteur → administrateur)

| Fonctionnalité | Visiteur anonyme | Utilisateur public | Professionnel (client) | Collaborateur Leopardo (rôles RH…) | Administrateur (super_admin) |
|---|---|---|---|---|---|
| Parcourir le catalogue `/outils` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utiliser un outil (calcul) | ✅ **sans compte** | ✅ (+ « Mes outils » local) | ✅ | ✅ | ✅ |
| Résultats + copie/partage | ✅ | ✅ | ✅ | ✅ | ✅ |
| Favoris (localStorage) | ⚠️ local éphémère | ✅ local | ✅ local | ✅ local | ✅ local |
| Historique (localStorage) | ⚠️ local éphémère | ✅ local | ✅ local | ✅ local | ✅ local |
| Synchronisation multi-appareils | — | ⏳ D9 | ⏳ D9 | ⏳ D9 | ⏳ D9 |
| Administration des outils (métadonnées) | — | — | — | — | ⏳ D10 |
| Accès aux données des autres | — | — | — | — | — (aucune donnée serveur au périmètre 1) |

## 8. Non-périmètre & garde-fous

- ❌ Aucune modification de l'identité, des paiements, des rôles/superadmin, des permissions globales.
- ❌ Aucune nouvelle table DB au périmètre 1 (D9/D10 à trancher avant toute migration).
- ❌ Aucun endpoint API REST dédié par calculatrice.
- ❌ Aucune dépendance lourde (pas de lib de calcul externe : les formules sont écrites et testées à la main).
- ❌ Aucun service externe payant (devises = taux de référence statiques documentés, hors ligne).
- ⚠️ Estimations (salaire net, devises) **toujours** accompagnées d'un avertissement « estimation indicative / théorique, ne constitue ni un bulletin ni une validation de sécurité ».
- 🛡️ Si Toolbox plante : page 404/erreur gérée locale, la vitrine et le dashboard continuent (module isolé, aucune route protégée touchée).

## 9. Prochaines actions

1. Créer les issues GitHub (see issues list).
2. Créer la branche `feat/vitrine-toolbox` + commit de claim.
3. Implémenter la fondation (étape 1) et le premier lot (étape 2).
4. Intégration vitrine (étape 3) — branche conforme au verrou.
5. Valider D9 (persistance) et D10 (admin) avec le propriétaire produit avant toute implémentation serveur.
