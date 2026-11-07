# LEOPARDO TOOLBOX — Issues GitHub proposées

> **Statut** : ⚠️ **NON CRÉÉES** — le token GitHub fourni pour la session est invalide
> (API : `Bad credentials` ; `git push` : `Invalid username or token`). Vérifié le
> 2026-11-05. Dès qu'un token valide (scope `repo`) est fourni, ces issues peuvent
> être créées telles quelles via l'API.
>
> Commande de création (à exécuter avec un token valide) :
> ```bash
> curl -s -X POST -H "Authorization: Bearer $TOKEN" \
>   -H "Accept: application/vnd.github+json" \
>   https://api.github.com/repos/kitokoh/leopardo-hr/issues \
>   -d '{"title":"...","body":"...","labels":["..."]}'
> ```

---

## Issue 1 — `[Toolbox] Fondation du module autonome + catalogue déclaratif`

**Labels** : `enhancement`, `frontend`, `toolbox`

**Corps** :

### Contexte
Leopardo évolue vers un Business OS. La mission « Leopardo Toolbox » demande une
catégorie autonome d'outils numériques universels, accessible sans authentification
depuis la vitrine publique, extensible et légère.

### Objectif
Créer la fondation du module `front/web/src/modules/toolbox/` :
- types du catalogue (`ToolDefinition`, `ToolCategory`, `ToolWarningId`) ;
- catalogue déclaratif `data/catalog.ts` (métadonnées pures, **aucune logique**) ;
- registre `lib/registry.ts` (résolution slug, recherche, `generateStaticParams`) ;
- helpers `lib/validation.ts` (parseNumber, safeDivide, bornes, arrondi) et
  `lib/format.ts` (Intl, 4 locales) ;
- copie i18n `data/copy.ts` (fr/en/tr/ar) + libellés d'options.

### Contraintes
- Module **autonome** : aucune dépendance au Business OS, aucun appel API.
- Métadonnées **séparées** de la logique de calcul.
- Aucune modification de l'identité, des permissions, des paiements.

### Critères d'acceptation
- [x] `npx tsc --noEmit` passe.
- [x] `npx eslint src/modules/toolbox --max-warnings 0` passe.
- [x] Tests du registre et des helpers (slugs uniques, kebab-case, version sémantique).

---

## Issue 2 — `[Toolbox] Premier lot d'outils : IMC, TVA, salaire net, unités, pourcentages`

**Labels** : `enhancement`, `frontend`, `toolbox`

**Corps** :

### Objectif
Implémenter 5 outils, chacun avec logique pure documentée + tests :

| Outil | Formule | Référence |
|---|---|---|
| IMC | `poids(kg) ÷ taille(m)²` + classification OMS | OMS |
| TVA | `TTC = HT × (1 + taux)` ; `HT = TTC ÷ (1 + taux)` | impots.gouv.fr |
| Salaire brut → net | `net ≈ brut × (1 − taux cotisations)` — **estimation** | — |
| Convertisseur d'unités | facteurs exacts SI + formules affines (température) | BIPM |
| Pourcentages | `%` d'une valeur, évolution, remise | — |

### Contraintes de qualité (exigence mission)
- Formules vérifiées sur références fiables, unités et hypothèses documentées.
- Gestion des valeurs nulles / négatives / division par zéro / valeurs extrêmes.
- Avertissements pour les résultats indicatifs (salaire net) — jamais présentés
  comme une validation officielle.

### Critères d'acceptation
- [x] Chaque outil : `logic.ts` (fonction pure) + `logic.test.ts`.
- [x] Cas connus + cas limites testés (zéro, négatifs, NaN, Infinity, division par zéro).
- [x] 74 tests unitaires passent (`npx jest src/modules/toolbox`).

---

## Issue 3 — `[Toolbox] Routes publiques /toolbox et /toolbox/[slug] (sans authentification)`

**Labels** : `enhancement`, `frontend`, `toolbox`, `seo`

**Corps** :

### Objectif
Exposer la Toolbox comme catégorie publique :
- `/toolbox` : catalogue (recherche + filtre par catégorie, 100 % client) ;
- `/toolbox/[slug]` : page d'outil (SSG via `generateStaticParams`) ;
- 404 propre pour un slug inconnu.

### Contraintes
- **Aucune authentification** : `/toolbox` n'est PAS ajouté à `PROTECTED_PREFIXES`.
- Aucun compte artificiel pour les visiteurs.
- Métadonnées SEO localisées + alternates hreflang (fr/en/tr/ar).
- Si Toolbox est indisponible, la vitrine et les autres verticales continuent.

### Critères d'acceptation
- [x] `/toolbox` → 200 sans cookie de session.
- [x] Les 5 pages d'outils → 200 ; slug inconnu → 404.
- [x] Rendu vérifié par capture navigateur (aucune casse visuelle).

---

## Issue 4 — `[Toolbox] Intégration vitrine : lien footer + sitemap`

**Labels** : `enhancement`, `frontend`, `toolbox`, `vitrine-authorized`

**Corps** :

### Objectif
Rendre la Toolbox découvrable depuis la vitrine :
- entrée « Outils » dans le footer (4 locales) → `/toolbox` ;
- `/toolbox` + les 5 pages d'outils dans `sitemap.ts`.

### Contrainte de gouvernance
Le verrou vitrine CI (`vitrine-lock-guard.yml`) exige une branche préfixée
`(feat|fix|chore|mkt|agent)/vitrine-*` pour toucher `modules/vitrine/**` ou
`(landing)/**`. La branche `feat/vitrine-toolbox` est conforme.

### Critères d'acceptation
- [x] Lien footer présent et fonctionnel (vérifié par capture).
- [x] Sitemap contient `/toolbox` + 5 slugs.
- [x] Gardes `check:mojibake`, `check:french-copy`, `check:orphans` passent.

---

## Issue 5 — `[Toolbox] DÉCISION : persistance « Mes outils » (favoris, historique, synchronisation)`

**Labels** : `decision`, `toolbox`, `architecture`

**Corps** :

### Question à trancher
Faut-il une persistance **serveur** des favoris/historique Toolbox, ou
`localStorage` suffit-il ?

| Option | Avantages | Inconvénients |
|---|---|---|
| **A. localStorage seul** (défaut actuel) | Aucun backend, aucun risque, RGPD simple | Pas de synchronisation multi-appareils |
| **B. API + table tenant `toolbox_user_state`** | Synchronisation, espace personnel | Nouveau module DDD, migrations tenant, tests cross-tenant, touche les données utilisateur |

### Impact
L'option B touche les données utilisateur et l'isolation des tenants →
**décision explicite requise** avant toute implémentation (exigence mission :
« Si des décisions structurantes touchent l'identité, les permissions, le Core ou
les paiements, les présenter pour validation avant toute modification »).

### Recommandation
Commencer par **A** (localStorage), mesurer l'usage, puis décider B sur données.

---

## Issue 6 — `[Toolbox] DÉCISION : administration des métadonnées d'outils`

**Labels** : `decision`, `toolbox`, `architecture`

**Corps** :

### Question à trancher
Les métadonnées d'outils doivent-elles rester un **catalogue statique versionné**
(dans le code) ou devenir une **table administrable** (`toolbox_tools`) pilotée par
un super-admin ?

| Option | Avantages | Inconvénients |
|---|---|---|
| **A. Catalogue statique** (défaut actuel) | Versionné avec le code, revu en PR, aucun risque | Ajout d'outil = déploiement |
| **B. Table administrable** | Activation/désactivation à chaud, sans déploiement | Nouveau module DDD + permissions + isolation tenant |

### Impact
L'option B touche l'administration plateforme et les permissions globales →
**décision explicite requise**.

### Recommandation
Rester en **A** tant que le rythme d'ajout d'outils ne justifie pas B.

---

## Issue 7 — `[Toolbox] Lot 2 d'outils (devises, dates, règle de trois, âge, taux horaire)`

**Labels** : `enhancement`, `frontend`, `toolbox`

**Corps** :

### Objectif
Étendre le catalogue avec 5 outils supplémentaires, même structure
(`tools/<slug>/logic.ts` + tests + formulaire) :

| Outil | Approche | Avertissement |
|---|---|---|
| Convertisseur devises | Taux de référence **statiques documentés** (hors ligne, pas d'API) | Taux datés → « estimation » |
| Durée entre deux dates | Calcul calendaire (années/mois/jours) | — |
| Règle de trois | `x = b × c ÷ a` + gestion des zéros | — |
| Âge (années-mois-jours) | Différence calendaire | — |
| Taux horaire / mensuel | `brut mensuel ÷ (heures/semaine × 52 ÷ 12)` | « approximatif » |

### Critères d'acceptation
- [ ] Chaque outil testé (cas connus + limites).
- [ ] Aucune dépendance externe payante ; devises hors ligne avec date de référence.
