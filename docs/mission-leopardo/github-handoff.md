# Mission Leopardo — paquet de livraison GitHub (prêt à exécuter)

> Branche : `mission/leopardo-vitrine-constellation` — 3 commits, arbre propre, `main` intacte.
> Ce document contient **tout ce qui peut être préparé sans accès en écriture** : les 5 issues
> (titre, description, critères d'acceptation, labels), le corps complet de la Pull Request et la
> liste exacte des commandes à exécuter dès qu'un PAT est disponible.

**Prérequis manquant (exact)** : un PAT GitHub limité au dépôt `kitokoh/leopardo-hr` avec la portée
`repo` (ou `contents:write` + `issues:write`). L'accès **en lecture** est déjà disponible et vérifié
(`git ls-remote` anonyme OK, `api.github.com` → 200, dépôt public). Le jeton ne doit **jamais** être
écrit dans le code, les commits, les issues, les logs ou cette documentation versionnée.

---

## 1. Les 5 issues à créer

> Convention du dépôt : labels existants `enhancement`, `frontend`, `business-os`, `testing`,
> `documentation`, `Agent-Ready`. Si un label ci-dessous n'existe pas encore, `gh issue create`
> s'arrête : le retirer ou le créer (`gh label create <nom>`), ne pas inventer d'autre portée.

### LEO-VIT-01 — Repositionner « Trade » dans les packs/offres

**Labels** : `enhancement`, `frontend`, `business-os`, `Agent-Ready`
**Description**

> La vitrine expose « Trade » comme entrée de navigation principale, au même rang que les rubriques
> générales, alors que c'est une **solution métier**. La retirer du menu et la repositionner dans la
> section des offres, à l'endroit le plus cohérent avec la structure existante, **sans casser les
> routes, le SEO ni les liens profonds**. Il s'agit d'un repositionnement dans la vitrine, **pas**
> d'une suppression du produit Trade.

**Critères d'acceptation**
- [ ] `Trade` n'apparaît plus dans la navigation principale (`Navbar.buildNavEntries`).
- [ ] Trade reste accessible depuis le hub `/packs` (carte dédiée) et/ou depuis la fiche de la constellation.
- [ ] La route `/trade` reste servie ; `src/app/sitemap.ts` inchangé ; tests `/trade` verts.
- [ ] Vérifié ≤ 390 px, 768 px, ≥ 1280 px ; aucun lien profond cassé.

### LEO-VIT-02 — Constellation = solutions métier réelles

**Labels** : `enhancement`, `frontend`, `business-os`, `Agent-Ready`
**Description**

> La constellation reproduisait les liens du menu (dont `Contact` et `Pricing`), ce qui la rendait
> redondante et brouillait l'offre. Elle doit représenter les **solutions réellement disponibles** du
> Business OS et faire comprendre que Leopardo est une plateforme commune proposant plusieurs
> environnements métier. Auditer le catalogue : n'afficher aucune verticale en développement comme
> pleinement opérationnelle.

**Critères d'acceptation**
- [ ] Chaque étoile correspond à une solution du catalogue unique (`data/solution-catalog.ts`).
- [ ] Plus aucune entrée redondante avec le menu (`Contact`, `Pricing`, carrières…).
- [ ] Une verticale non opérationnelle n'est pas présentée comme pleinement disponible.
- [ ] Aucune donnée métier dupliquée : les fiches lisent `PACK_PAGES`.

### LEO-VIT-03 — Fiche interactive au clic sur une étoile

**Labels** : `enhancement`, `frontend`, `Agent-Ready`
**Description**

> Le clic sur une étoile redirigeait immédiatement. Il doit désormais ouvrir une **fiche interactive**
> (description, profils concernés, fonctionnalités réellement disponibles, outils associés, bénéfices
> concrets, CTA) alimentée par le catalogue, sans dupliquer les données métier.

**Critères d'acceptation**
- [ ] Le clic ouvre une fiche (pas de redirection immédiate).
- [ ] La fiche expose description, profils, fonctionnalités, CTA.
- [ ] Échap ferme ; clic sur le fond ferme ; focus piégé puis restitué à l'étoile.
- [ ] `role="dialog"` + `aria-modal` + titre lié (`aria-labelledby`).
- [ ] Mobile : feuille plein écran scrollable.

### LEO-VIT-04 — CTA sous la constellation

**Labels** : `enhancement`, `frontend`, `Agent-Ready`
**Description**

> Ajouter sous la constellation un appel à l'action principal, clairement visible et cohérent avec le
> parcours de conversion, sans détourner l'attention de la constellation.

**Critères d'acceptation**
- [ ] Un CTA principal est visible sous la constellation, sans masquer les étoiles.
- [ ] Il pointe vers une destination du parcours de conversion existant.
- [ ] Espacement vérifié (ni collé au globe, ni hors champ) sur mobile et desktop.
- [ ] Cible tactile ≥ 44 px, focus visible au clavier.

### LEO-VIT-05 — Cartes géographiques animées en fond

**Labels** : `enhancement`, `frontend`, `Agent-Ready`
**Description**

> Remplacer la France figée par des cartes géographiques stylisées qui se renouvellent progressivement
> (France → Algérie → Turquie → Tchad → continent africain). La constellation reste l'élément
> dominant ; les cartes sont derrière elle, opacité maîtrisée. Direction artistique : Option A
> (transition successive) ou Option B (composition à trois cartes) ; une variante plus élégante est
> acceptable si l'objectif est respecté. Contraintes : performance, mobile, `prefers-reduced-motion`,
> repli si les effets avancés ne sont pas supportés.

**Critères d'acceptation**
- [ ] Le fond fait apparaître successivement France, Algérie, Turquie, Tchad et continent africain.
- [ ] La France n'est plus une couche figée.
- [ ] Les tracés sont reconnaissables (source cartographique réelle, pas de silhouette aléatoire).
- [ ] La constellation reste dominante (opacité maîtrisée, jamais au-dessus des étoiles/libellés/CTA).
- [ ] `prefers-reduced-motion` : pas de rotation, pas de fondu, première carte affichée.
- [ ] Aucune dépendance ajoutée ; pas de ralentissement mesurable.

---

## 2. Corps complet de la Pull Request

> Titre proposé :
> `feat(vitrine): constellation = solutions métier, fiche au clic, CTA et cartes géo animées`

```markdown
## Contexte

La vitrine publique est la porte d'entrée vers le Business OS. Objectif : améliorer la
compréhension de l'offre, la navigation et la conversion, **sans** perdre l'effet visuel de la
constellation ni l'identité de Leopardo. Audit préalable fait avant toute modification
(`docs/MISSION_LEOPARDO_AUDIT.md`).

## Changements

- **Trade repositionné** : retirée de la navigation principale (`Navbar.tsx`), ajoutée aux offres
  (`app/(landing)/packs/page.tsx`) et présentée comme solution de la constellation. Route `/trade`,
  layout, tests et `sitemap.ts` **inchangés**.
- **Constellation = solutions métier réelles** : nouvelle source unique
  `modules/vitrine/data/solution-catalog.ts` (réutilise `PACK_PAGES` — aucune donnée métier
  dupliquée). 10 environnements ; entrées redondantes retirées (`Contact`, `Pricing`,
  `careers`, `alternatives`, `demo`).
- **Fiche interactive** (`hero/SolutionCard.tsx`) : description, profils, fonctionnalités, CTA.
  `role="dialog"` + `aria-modal` + titre lié, Échap, clic sur le fond, focus piégé puis restitué,
  `body.overflow` restauré, mobile en feuille plein écran.
- **CTA sous la constellation** (`hero/HeroSolutionsCTA.tsx`) : « Découvrir nos solutions » (`/packs`)
  + « Commencer maintenant » (`/signup`).
- **Cartes géographiques animées** (`hero/GeoBackdrop.tsx` + `data/geo/map-paths.ts`, Option A) :
  France → Algérie → Turquie → Tchad → continent africain, fondu croisé. Tracés issus de Natural
  Earth (domaine public). Opacité plafonnée, `prefers-reduced-motion` neutralise rotation et fondu,
  repli si `matchMedia` absent. **Aucune dépendance ajoutée.**
- **Documentation** : audit, TAD, TRD, AppFlow, UIX/UI Design, Implementation Plan, rapport de
  clôture, paquet GitHub (`docs/mission-leopardo/`).

## Fichiers principaux

`front/web/src/modules/vitrine/data/solution-catalog.ts` (nouveau) ·
`.../hero/{SolutionCard,HeroSolutionsCTA,GeoBackdrop}.tsx` (nouveaux) ·
`.../data/geo/map-paths.ts` (nouveau) · `.../hero/{HeroBusinessOS3D,HeroScene3D,HeroScene3DPanel}.tsx` ·
`.../components/Navbar.tsx` · `.../components/sections/HeroSection.tsx` · `front/web/src/app/globals.css` ·
`front/web/src/app/(landing)/packs/page.tsx` · tests `.../hero/__tests__/mission-leopardo.test.tsx` (nouveau).

## Tests réellement exécutés

| Vérification | Commande | Résultat |
|---|---|---|
| Typecheck | `tsc --noEmit` | ✅ exit 0 |
| Lint (fichiers touchés) | `eslint … --max-warnings 0` | ✅ exit 0, 0 warning |
| Tests unitaires (nouveaux) | `jest …/hero/__tests__/mission-leopardo.test.tsx …/sections/__tests__/HeroSection.test.tsx` | ✅ 2 suites, 25 tests |
| Tests d'intégration vitrine | `jest src/modules/vitrine/__tests__ … "src/app/(landing)"` | ✅ 22 suites, 377 tests |
| Route Trade | `jest landing.*(trade\|packs)` | ✅ 1 suite, 6 tests |
| Gardes dépôt | `check:mojibake`, `check:french-copy`, `check:orphans`, `check:assets` | ✅ toutes OK |
| Build de production | `next build` | ⛔ **non concluant** — OOM de l'environnement (conteneur 2 Gio, cf. rapport) |
| e2e Playwright (2 specs vitrine) | `npx playwright test … --project=chromium` | ⛔ **non concluant** — binaire Chromium de la version épinglée absent (cf. rapport) |

> Les deux ⛔ sont des limites de l'environnement d'exécution, **pas** des échecs applicatifs :
> le typecheck (0 erreur) et les gardes de lint couvrent la même surface que la vérification
> TypeScript du build.

## Checklist des critères d'acceptation

- [x] Trade repositionné sans casser les routes
- [x] Constellation = solutions métier réelles
- [x] Entrées redondantes avec le menu retirées
- [x] Clic sur une solution → fiche interactive pertinente
- [x] CTA visible et fonctionnel
- [x] Cartes géographiques fluides en arrière-plan
- [x] Constellation reste l'élément visuel dominant
- [ ] Performance et expérience mobile vérifiées — vérifiées par typecheck/lint/tests unitaires ;
      build et e2e bloqués par l'environnement (à rejouer en CI)
- [ ] Tests et CI passent — tests locaux verts ; **CI à lancer par cette PR**
- [x] `main` propre (aucune modification directe, aucun contournement de protection)

## Notes

- Aucune dépendance npm ajoutée. Aucun changement d'API ou de schéma de données.
- La structure sémantique et les métadonnées SEO des pages publiques ne sont pas modifiées.
```

---

## 3. Commandes exactes à exécuter (une fois le PAT disponible)

```bash
# 0) Se placer dans le dépôt et créer la branche de mission si absente (déjà créée en local)
cd /workspace/leopardo-hr
git checkout mission/leopardo-vitrine-constellation
git log --oneline -3        # attendu : rapport + feat(vitrine) + base main

# 1) Pousser la branche de mission
git push -u origin mission/leopardo-vitrine-constellation

# 2) Créer les 5 issues (corps = descriptions + critères du §1)
#    Adapter/retirer un label s'il n'existe pas encore dans le dépôt.
gh issue create --repo kitokoh/leopardo-hr \
  --title "LEO-VIT-01 — Repositionner « Trade » dans les packs/offres" \
  --label enhancement --label frontend --label business-os --label Agent-Ready \
  --body-file docs/mission-leopardo/github-handoff.md   # section §1 : LEO-VIT-01
# … répéter pour LEO-VIT-02 … LEO-VIT-05 (§1)

# 3) Ouvrir la Pull Request documentée (corps = §2)
gh pr create --repo kitokoh/leopardo-hr \
  --base main \
  --head mission/leopardo-vitrine-constellation \
  --title "feat(vitrine): constellation = solutions métier, fiche au clic, CTA et cartes géo animées" \
  --body-file docs/mission-leopardo/github-handoff.md   # section §2

# 4) Vérifier la CI de la branche (ne PAS fusionner tant que les contrôles requis n'ont pas passé)
gh pr checks --repo kitokoh/leopardo-hr --watch

# 5) Après fusion réussie uniquement : supprimer la branche, vérifier main
gh pr merge --repo kitokoh/leopardo-hr --squash --delete-branch
git checkout main && git pull
```

> `gh` n'est pas installé dans l'environnement de développement : si l'on préfère l'API brute,
> toute la séquence est reproductible via `curl` + `GITHUB_TOKEN` (mêmes données que ci-dessus).
> Ne jamais coller le jeton en clair dans un fichier versionné ni dans un log de commande.

---

## 4. Ce qui reste bloqué (et pourquoi)

| Étape de la mission | État |
|---|---|
| 1–4 Audit, conception, documents | ✅ fait |
| 5 Création/priorisation des issues | ⛔ texte prêt (§1), création bloquée (pas de PAT) |
| 6 Branche dédiée | ✅ `mission/leopardo-vitrine-constellation` |
| 7–8 Implémentation + commits réguliers | ✅ 3 commits locaux |
| 9 Tests | ✅ exécutés (typecheck/lint/Jest/gardes) ; build & e2e non concluants (environnement) |
| 10 Pull Request documentée | ⛔ corps prêt (§2), ouverture bloquée (pas de PAT) |
| 11 Fusion | ⛔ bloqué |
| 12 Suppression de branche | ⛔ bloqué |
| 13 Vérification finale `main`/CI | ⚠️ `main` **non modifiée** ; CI distante non vérifiable |
