# UIX + UI Design — direction artistique et composants

## 1. Positionnement visuel

Qualité comparable à un produit technologique haut de gamme : profond, contrasté, lisible, international. La constellation est le **sujet principal** de la page — elle sert à comprendre l'offre, pas à décorer.

## 2. Palette en vigueur (réutilisée, non redéfinie)

| Rôle | Valeur |
|---|---|
| Fond profond | `slate-950` / `#020617` |
| Signal de marque | `emerald-500/600` (`#10b981` / `#059669`) |
| Accent technique | `cyan-500/600` (`#06b6d4` / `#0891b2`) |
| Texte principal | `white` / `slate-200` |
| Texte secondaire | `slate-300` / `slate-400` |

Le fond géographique emploie **exactement** les tokens de marque (remplissage `emerald` translucide, trait `cyan`) : il prolonge l'identité au lieu d'en introduire une autre.

## 3. Hierarchie des plans (héros `stage`)

```
z-20  CTA sous la constellation   (texte + boutons)
z-10  Constellation (canvas)      + libellés projetés
z-0   Cartes géographiques (GeoBackdrop)
      Texte de promesse
```

- Les cartes ne sont **jamais** au-dessus des étoiles, des libellés ou du CTA.
- Opacité maximale du fond : `0.22` — présence visible, concurrence nulle.
- La constellation reste le point le plus lumineux de l'écran.

## 4. Composants

### 4.1 `SolutionCard` (fiche interactive)

- **Conteneur** : `rounded-3xl`, bordure `emerald-500/20`, ombre `emerald-500/10`, verre (`backdrop-blur-xl`).
- **En-tête** : badge « Solution Leopardo », titre, accroche, bouton de fermeture. Un filet dégradé (`emerald` → transparent) souligne le haut.
- **Corps** : description, puis deux blocs — « Pour qui » (puces) et « Ce qui est inclus » (liste cochée).
- **Actions** : CTA principal en dégradé `emerald → cyan` (même famille que le reste de la vitrine), CTA secondaire en verre bordé.
- **Mobile** : `rounded-t-3xl`, ancrée en bas, plein écran, corps scrollable (`max-h-[92dvh]`).
- **Desktop** : centrée, largeur maximale `2xl`.
- **Mouvement** : apparition 0,28 s (`opacity` + léger `y` + `scale`) — rapide, jamais spectaculaire.

### 4.2 `HeroSolutionsCTA` (CTA sous la constellation)

- Titre court, sous-titre d'une ligne, deux boutons, ligne de réassurance.
- Cible tactile ≥ 48 px ; `focus-visible` explicite (anneau `emerald-400`).
- Bouton principal : dégradé `emerald-700 → emerald-800`, survol vers `emerald-600 → cyan-600`, léger grossissement `1.03`.

### 4.3 `GeoBackdrop` (cartes animées)

- 5 couches SVG empilées, centrées, dimensionnées en `vmin` (le cadrage suit la fenêtre).
- La couche active est à `opacity 0.22`, les autres à `0`.
- Transition : `opacity 1400ms ease-in-out` — fondu croisé lent.
- Cadence : une nouvelle carte toutes les 9 s.
- **Option A retenue** (transition successive) : les cartes ne coexistent jamais.

## 5. Mouvement

| Élément | Durée | Déclencheur |
|---|---|---|
| Apparition de la fiche | 0,28 s | Ouverture |
| Survol des boutons | 0,3 s | Curseur |
| Flèche des boutons | 0,3 s | Curseur |
| Fondu des cartes | 1,4 s | Intervalle |

Rien ne clignote. Rien ne dépasse 1,4 s. Aucun élément textuel n'est animé.

## 6. Accessibilité visuelle

- Contraste sur fond profond : texte principal `white`, secondaire `slate-300` (≥ AA).
- Éléments interactifs : anneau de focus explicite, jamais supprimé.
- Information jamais portée par la couleur seule (les fonctionnalités ont une icône de validation + texte).

## 7. Responsive

| Palier | Comportement |
|---|---|
| ≤ 639 px | Fiche plein écran ancrée en bas ; CTA empilés verticalement ; la carte active reste lisible |
| 640–1023 px | Fiche centrée ; CTA côte à côte |
| ≥ 1024 px | Même disposition, plus d'espace autour de la constellation |

## 8. Ce qui a été évité

- ❌ Animation rapide, clignotement, texte illisible.
- ❌ Transformer toute la page en animation.
- ❌ Cartes concurrentes des étoiles (Option B écartée après examen).
- ❌ Toute nouvelle dépendance visuelle (librairie d'animation, moteur de cartes).
