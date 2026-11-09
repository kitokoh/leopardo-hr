# Leopardo — Guide de marque officiel

> Issue #9194 · Source de vérité des tokens : `front/web/src/modules/vitrine/lib/brand-tokens.ts`
> Assets : `front/web/public/brand/`

Ce document est destiné au département marketing **et** aux développeurs. Il
décrit l'identité visuelle officielle de Leopardo et les règles d'usage des
assets. En cas de divergence entre ce guide et le code, **le code fait foi** —
et ce guide doit être corrigé.

---

## 1. Assets officiels

| Fichier | Usage | Fond |
|---|---|---|
| `brand/leopardo-logo.svg` | Logo principal (emblème + logotype) | Clair |
| `brand/leopardo-logo-dark.svg` | Logo principal | Sombre |
| `brand/leopardo-symbol.svg` | Monogramme seul, transparent | Indifférent |
| `brand/leopardo-icon.svg` | Icône carrée à fond plein (favicon, app icon, avatar) | — |

**Formats.** Les SVG sont la source. Pour un usage qui exige du raster (réseaux
sociaux, présentations), exporter en PNG à 2× la taille d'affichage, jamais
depuis une capture d'écran.

**Ne jamais** : recolorer le monogramme, déformer les proportions, ajouter une
ombre portée au logotype, ou recomposer le logo à partir de ses deux branches.

---

## 2. Couleurs officielles

### Couleur primaire — Émeraude (action)

| Token | Hex | Usage |
|---|---|---|
| `emerald.500` | `#10b981` | CTA principal, succès |
| `emerald.600` | `#059669` | CTA survolé, texte sur fond clair |
| `emerald.400` | `#34d399` | Accent sur fond sombre |

### Couleur de marque — Teal (identité)

| Token | Hex | Usage |
|---|---|---|
| `brand.500` | `#14b8a6` | Identité, éléments de marque |
| `brand.600` | `#0d9488` | Texte de marque sur fond clair |
| `brand.950` | `#042f2e` | Fond profond |

### Couleur secondaire — Cyan (accent)

| Token | Hex | Usage |
|---|---|---|
| `cyan.500` | `#06b6d4` | Liens, accents, dégradés |
| `cyan.400` | `#22d3ee` | Accent sur fond sombre |

### Accent chaud — Ambre (emblème)

| Token | Hex | Usage |
|---|---|---|
| `amber.400` | `#fbbf24` | Branche « T » de l'emblème, slogan |
| `amber.600` | `#d97706` | Slogan sur fond clair |

### Surfaces et texte (thème sombre, défaut)

| Token | Hex | Usage |
|---|---|---|
| `surface.base` | `#0b1326` | Fond principal |
| `surface.bright` | `#31394d` | Cartes, panneaux élevés |
| `surface.variant` | `#2d3449` | Fond alternatif |
| `on-surface.base` | `#dae2fd` | Texte principal |
| `on-surface.variant` | `#bbcabf` | Texte secondaire |

### Dégradé officiel

```
linear-gradient(135deg, #fbbf24 0%, #34d399 55%, #059669 100%)
```

Réservé à l'emblème et aux bordures luminescentes au survol. **Ne pas** en faire
un fond de section pleine largeur : il perdrait sa valeur d'accent.

### Contrastes vérifiés (WCAG 2.1 AA)

Valeurs **mesurées** (formule WCAG 2.1, verrouillées par
`brand-tokens.test.ts` — un changement de couleur fait échouer la CI) :

| Combinaison | Ratio | Verdict |
|---|---|---|
| `on-surface.base` sur `surface.base` | 14.34:1 | AAA |
| `on-surface.variant` sur `surface.base` | 10.85:1 | AAA |
| `amber.400` sur `surface.base` | 11.08:1 | AAA |
| `emerald.800` sur blanc | 7.68:1 | AAA |
| `emerald.700` sur blanc | 5.48:1 | AA (texte normal) |
| `brand.700` sur blanc | 5.47:1 | AA (texte normal) |
| `amber.700` sur blanc | 5.02:1 | AA (texte normal) |
| `emerald.600` sur blanc | 3.77:1 | ⚠️ grands textes seulement |
| `brand.600` sur blanc | 3.74:1 | ⚠️ grands textes seulement |
| `amber.600` sur blanc | 3.19:1 | ⚠️ grands textes seulement |
| `emerald.500` sur blanc | 2.54:1 | ❌ non textuel uniquement |

> **Règle de texte sur fond clair.** Le vert de marque **ne passe pas AA en
> texte normal** avant `emerald.700` (5.48:1). Pour du texte courant sur fond
> clair, utiliser `emerald.700` ou `emerald.800` — **jamais** `emerald.500` ni
> `emerald.600`. Ces deux derniers restent valides pour les **grands textes**
> (≥ 24 px, ou ≥ 19 px gras), les **bordures**, les **icônes** et les **fonds de
> bouton** (où le texte est blanc sur le vert, pas l'inverse).
>
> Même contrainte pour l'ambre : `amber.600` (3.19:1) est réservé au slogan en
> grand corps ; pour du texte courant, `amber.700` (5.02:1).

---

## 3. Typographie

| Rôle | Police | Graisse | Interlettrage |
|---|---|---|---|
| Display / logotype | Inter | 900 | `-0.02em` |
| Corps | Inter | 400 | `0` |
| Sur-titre / slogan | Inter | 800 | `0.22em`, majuscules |
| Code / données | JetBrains Mono | 400 | `0` |

Le slogan officiel est **`BUSINESS OS`**, toujours en majuscules, toujours
au-dessus de 8 px, toujours avec l'interlettrage large. Ne pas le traduire.

---

## 4. Règles d'usage

### Espace de sécurité

Autour de l'emblème, laisser un vide d'au moins **25 % de sa hauteur** sur les
quatre côtés (`BRAND_CLEAR_SPACE_RATIO`). Aucun texte, bord de cadre ou autre
logo ne doit entrer dans cette zone.

### Tailles minimales

| Support | Emblème | Logotype complet |
|---|---|---|
| Écran | 24 px | 96 px de large |
| Impression | 8 mm | 30 mm de large |

En dessous, utiliser `leopardo-icon.svg` (emblème seul) plutôt que de réduire le
logo complet.

### Fonds autorisés

- ✅ Fond clair (`#ffffff`, `#f8fafc`) → `leopardo-logo.svg`
- ✅ Fond sombre (`#0b1326`, `#0f172a`) → `leopardo-logo-dark.svg`
- ✅ Photo ou image → uniquement si le contraste local est ≥ 4.5:1, sinon ajouter
  un voile sombre à 60 % d'opacité
- ❌ Fond saturé (émeraude, cyan, ambre plein) → utiliser la version monochrome
  blanche, à demander au design

### Usages interdits

- Recolorer, dégrader ou appliquer un filtre au logo
- Étirer, incliner ou faire pivoter le logo
- Détourer l'emblème de son carré arrondi
- Placer le logo dans une forme qui n'est pas le carré arrondi officiel
- Écrire « Leopardo » dans une autre police que Inter 900

---

## 5. Implémentation en code

```ts
import {
  BRAND_ASSETS,
  BRAND_GRADIENT,
  BRAND_TYPOGRAPHY,
  BRAND_TAGLINE,
} from '@/modules/vitrine/lib/brand-tokens';
```

- **Composant React** : `LeopardoBrandMark` (`modules/vitrine/components/`) —
  emblème + logotype, tailles `sm|md|lg|xl`, variantes `full|icon`, tons
  `auto|dark|light`.
- **Fichiers statiques** : `BRAND_ASSETS` donne les chemins publics.
- **Ne jamais** réécrire un hexadécimal de marque dans un composant : importer le
  token. Un changement de marque doit se propager depuis un seul endroit.

---

## 6. Ce qui reste à fournir (hors périmètre code)

Ces éléments ne peuvent pas être produits en code et restent à la charge du
design / marketing :

1. **Version monochrome blanche** du logo complet (fonds saturés).
2. **Version monochrome noire** (impression 1 couleur, fax, tampon).
3. **Fichiers raster haute résolution** (PNG 2×, 4×) pour les réseaux sociaux.
4. **Kit réseaux sociaux** : bannières LinkedIn / X / Instagram aux formats
   officiels.
5. **Modèles de présentation** (Keynote / PowerPoint) aux couleurs de la marque.
6. **Papeterie** : carte de visite, en-tête, signature e-mail.
7. **Guide de ton de voix** : la personnalité éditoriale n'est pas couverte ici.

Tant que ces éléments ne sont pas livrés, les usages correspondants doivent
utiliser les assets existants avec un voile de contraste, jamais une
recomposition approximative.
