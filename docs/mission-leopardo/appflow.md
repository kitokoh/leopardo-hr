# AppFlow — interactions et parcours du visiteur

## 1. Parcours principal (premier écran)

```
Arrivée sur /
   │
   ├─ Lecture de la promesse (héros)
   │
   ├─ Regard sur la constellation — 10 solutions métier
   │      │
   │      └─ survol : l'étoile s'illumine, son nom s'affiche
   │      └─ clic    ▶ fiche interactive (SolutionCard)
   │
   └─ CTA sous la constellation
          ├─ « Découvrir nos solutions » ▶ /packs
          └─ « Commencer maintenant »    ▶ /signup
```

## 2. Parcours « fiche d'une solution »

```
Clic sur une étoile (index)
   │
   ▼
SolutionCard s'ouvre
   │
   ├─ Titre + accroche          (ex. « Restaurant OS », « Travel OS »…)
   ├─ Description courte
   ├─ Profils concernés
   ├─ Fonctionnalités réellement disponibles
   │
   ├─ CTA principal ──▶ page complète du pack / de la solution
   ├─ CTA secondaire ─▶ /signup (onboarding)
   │
   └─ Fermeture : bouton ✕ · touche Échap · clic sur le fond
```

### Règles d'interaction

| Action | Résultat |
|---|---|
| Survol d'une étoile | L'étoile s'illumine ; curseur `pointer` |
| Clic sur une étoile | La fiche s'ouvre — **aucune redirection** |
| `Tab` / `Shift+Tab` | Le focus cycle **dans** la fiche |
| `Échap` | La fiche se ferme |
| Clic sur le fond assombri | La fiche se ferme |
| Fermeture | Le focus revient à l'étoile ; le défilement du corps est restauré |

## 3. Parcours « Trade » (après repositionnement)

```
AVANT :  Navbar ▶ Trade ▶ /trade
APRÈS :  Navbar ▶ Packs ▶ /packs ▶ carte « International Trade OS » ▶ /trade
         ou
         Constellation ▶ étoile « International Trade OS » ▶ fiche ▶ /trade
```

- `/trade` reste accessible en direct ; les résultats de recherche et les liens existants continuent de fonctionner.
- Depuis le hub `/packs`, la carte Trade occupe la largeur complète en fin de grille (elle répond au **modèle d'activité**, les autres cartes au **métier**).

## 4. Parcours de repli (animations indisponibles)

Si WebGL est absent ou refusé (`hasWebGL`, `saveData`, `prefers-reduced-motion`) :

```
Héros
   ├─ Texte de promesse (toujours affiché)
   ├─ Repli 2D décoratif (ParticleField)
   └─ CTA sous la constellation (toujours affiché, toujours cliquable)

L'offre reste entièrement compréhensible via :
  navbar · cartes de verticales · hub /packs · CTA · footer
```

Aucune information n'est portée par la seule constellation : le repli ne supprime pas d'accès.

## 5. États de chargement

- La 3D est chargée en `requestIdleCallback` : le héros ne l'attend pas.
- La fiche utilise le contenu déjà chargé (données synchrones) : pas d'état de chargement à gérer.
- En cas d'absence de solution pour un index, `SolutionCard` retourne `null` (fail-closed) — aucun écran cassé.
