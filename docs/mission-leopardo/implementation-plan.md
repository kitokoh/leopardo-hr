# Implementation Plan — Mission Leopardo (vitrine / constellation)

## 1. Ordre de travail suivi

Conforme à l'ordre imposé (§11.B de la mission) :

| # | Étape | État |
|---|---|---|
| 1 | Audit de l'existant | ✅ fait (voir `../MISSION_LEOPARDO_AUDIT.md`) |
| 2 | Identification des besoins et lacunes | ✅ fait (A1–A6 de l'audit) |
| 3 | Conception technique et fonctionnelle | ✅ fait (`tad.md`, `trd.md`) |
| 4 | Rédaction des documents | ✅ fait (ce dossier) |
| 5 | Création et priorisation des issues GitHub | ⛔ **bloqué** — aucun accès GitHub en écriture |
| 6 | Branche dédiée | ✅ `mission/leopardo-vitrine-constellation` |
| 7 | Implémentation progressive | ✅ fait |
| 8 | Commits réguliers | ✅ commits locaux (poussée impossible) |
| 9 | Tests | ✅ exécutés localement (typecheck + Jest ciblé + gardes) |
| 10 | Pull Request documentée | ⛔ bloqué (pas de push) |
| 11 | Fusion | ⛔ bloqué |
| 12 | Suppression de branche | ⛔ bloqué |
| 13 | Vérification finale de `main` | ⚠️ `main` **non modifiée** (rien n'a été poussé) — donc intacte par construction |

## 2. Tâches

### T1 — Repositionner Trade (LEO-VIT-01)
- **Fichiers** : `Navbar.tsx`, `app/(landing)/packs/page.tsx`
- **Dépendances** : aucune
- **Risque** : casser un lien profond ou le SEO de `/trade`
- **Mitigation** : la route, le layout, les tests et `sitemap.ts` de `/trade` ne sont pas touchés ; l'entrée est retirée de la navbar mais la destination est reprise dans les offres **et** dans la fiche de la constellation
- **Test** : test d'intégration de navigation (présence/absence des entrées) + `src/app/(landing)/trade/__tests__`

### T2 — Constellation = solutions métier (LEO-VIT-02)
- **Fichiers** : `data/solution-catalog.ts` (nouveau), `HeroBusinessOS3D.tsx`, `HeroScene3D.tsx`, `HeroScene3DPanel.tsx`, `HeroSection.tsx`
- **Dépendances** : T1 (le catalogue alimente aussi l'entrée Trade)
- **Risque** : afficher une verticale comme opérationnelle alors qu'elle ne l'est pas
- **Mitigation** : le catalogue ne contient que des destinations existantes et testées (`/restaurateur`, `/packs/agence-de-voyage`, `/packs/station-service`, `/packs/ecole`, `/packs/vtc`, `/trade`, `/packs/cagnottes`, `/employes`, `/comptabilite`, `/documents`) ; aucun « bientôt disponible » présenté comme actif
- **Test** : `HeroBusinessOS3D` (libellés, clic → index), tests de la page d'accueil

### T3 — Fiche interactive au clic (LEO-VIT-03)
- **Fichiers** : `SolutionCard.tsx` (nouveau), `HeroScene3DPanel.tsx`
- **Dépendances** : T2 (catalogue)
- **Risque** : régression d'accessibilité (piège de focus, Échap) ; fuite d'écouteurs
- **Mitigation** : un seul écouteur `keydown` par surface, nettoyé au démontage ; focus rendu à l'étoile d'origine ; `body.overflow` restauré
- **Test** : ouverture au clic, fermeture (bouton/fond/Échap), `role="dialog"` + `aria-modal`, focus restitué

### T4 — CTA sous la constellation (LEO-VIT-04)
- **Fichiers** : `HeroSolutionsCTA.tsx` (nouveau), `HeroSection.tsx`
- **Dépendances** : T2 (libellés partagés)
- **Risque** : CTA qui masque la constellation ou sort du champ
- **Mitigation** : le bloc vit dans le flux du héros (`z-20`), sous le canvas, avec le `pb-24` du conteneur ; taille de cible ≥ 48 px
- **Test** : présence du CTA dans la page d'accueil + destination `/packs`

### T5 — Cartes géographiques animées (LEO-VIT-05)
- **Fichiers** : `data/geo/map-paths.ts` (généré), `GeoBackdrop.tsx` (nouveau), `app/globals.css`, `HeroSection.tsx`
- **Dépendances** : aucune
- **Risque** : animation lourde (CPU), ou cartes qui concurrencent les étoiles
- **Mitigation** : SVG statique + fondu d'opacité en CSS (aucune frame three.js, aucune dépendance ajoutée) ; opacité plafonnée à 0.22 ; `prefers-reduced-motion` neutralise rotation **et** fondu ; repli si `matchMedia` est absent
- **Test** : `GeoBackdrop` (rotation sous mouvement autorisé, statique sous `reduced-motion`, aucune erreur si `matchMedia` absent)

## 3. Matrice des risques

| Risque | Probabilité | Impact | Traitement |
|---|---|---|---|
| Accès GitHub absent | **avéré** | élevé | documenté (§7 de l'audit) ; livraison locale + issues proposées |
| Lien profond `/trade` cassé | faible | élevé | route/layout/sitemap/tests non modifiés |
| Régression d'accessibilité de la fiche | moyenne | élevé | Échap + piège de focus + restitution, testés |
| Animation coûteuse | faible | moyen | SVG + opacité seule, plafonnée, `will-change` limité |
| Divergence des données métier | moyenne | moyen | source unique (`solution-catalog` → `PACK_PAGES`) |
| Tests existants en échec sur les libellés | moyenne | faible | libellés recalculés depuis le catalogue partagé |

## 4. Tests prévus et réellement exécutés

| Test | Cible | Résultat |
|---|---|---|
| Typecheck | `tsc --noEmit` (front/web) | **voir rapport de clôture** |
| Jest ciblé | `app/(landing)/packs`, `components/hero`, `components/sections` | **voir rapport de clôture** |
| Jest Intégration | `navigation-routing`, `landing-page`, `conversion-flows` | **voir rapport de clôture** |
| Gardes du dépôt | i18n, copie FR, tokens de design, mojibake | **voir rapport de clôture** |

> Les résultats exacts (et les échecs éventuels) figurent dans le rapport de clôture ; aucun résultat n'est inventé.

## 5. Hors périmètre (explicitement écarté)

- Modification du modèle de données ou de l'API → **back-end non concerné**, aucun schéma produit.
- Remplacement du moteur WebGL existant → conservé (il fonctionne).
- Suppression de `FranceConstellation` → conservée (encore utilisée comme fond par d'autres pages).
- Optimisation cartographique interactive (zoom, sélection de pays) → non demandée.
