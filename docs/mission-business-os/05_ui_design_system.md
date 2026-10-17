# 05 — UI Design System

**Source de vérité amont :** `docs/REFERENTIEL_PRODUIT/COULEURS.md` (tokens partagés
Flutter ↔ Tailwind, règle L.07) et `front/web/tailwind.config.ts`.
Ce document **ne redéfinit aucun token** : il en fixe l'usage et l'articulation pour les
surfaces concernées par la mission. Toute modification de couleur passe par COULEURS.md +
`AppColors.dart` + `tailwind.config.ts` dans le **même commit** (garde `web-design-tokens.yml`).

---

## 1. Fondations

### 1.1 Couleurs (telles que définies, non modifiées)

| Rôle | Hex | Tailwind | Usage mission |
|---|---|---|---|
| Marque / RH | `#10B981` | `emerald-500` | action principale, état validé, focus |
| Marque profonde | `#059669` / `#047857` | `emerald-600` / `emerald-700` | survol, bouton primaire sur fond clair |
| Accent technique | `#06B6D4` | `cyan-500` | signal secondaire, dégradé de CTA |
| Finance | `#F59E0B` | `amber-500` | avertissement, en attente |
| Sécurité | `#3B82F6` | `blue-500` | information, lien |
| IA / Leo | `#7C3AED` | `violet-600` | assistance, suggestion |
| Danger | `#EF4444` | `red-500` | erreur bloquante, action destructive |
| Fond profond | `#020617` | `slate-950` | vitrine, héros, surfaces sombres |
| Fond clair | `#FFFFFF` / `#F8FAFC` | `white` / `slate-50` | dashboard, formulaires |
| Texte principal | `#0F172A` | `slate-900` | corps de texte sur clair |
| Texte secondaire | `#475569` | `slate-600` | aides, métadonnées |
| Bordure | `#E2E8F0` | `slate-200` | séparateurs, cartes |

**Règle d'usage :** une couleur de domaine porte **un seul sens** dans un écran donné.
On n'utilise pas `amber` pour un succès, ni `emerald` pour une alerte.

### 1.2 Typographie

| Niveau | Token | Usage |
|---|---|---|
| Display | `4xl`/`5xl` (2.25–3rem) | titre de héros uniquement |
| Titre d'écran | `2xl`/`3xl` (1.5–1.875rem) | titre d'étape d'onboarding, de page |
| Titre de section | `xl` (1.25rem) | titres de blocs |
| Corps | `base` (1rem / 1.5) | texte courant |
| Secondaire | `sm` (0.875rem / 1.25) | aides, métadonnées, libellés de champ |
| Micro | `xs` (0.75rem / 1rem) | badges, horodatages — **jamais** du contenu porteur de sens seul |

Police : `Inter` (`var(--font-inter)`), monospace `JetBrains Mono` pour les références et
identifiants techniques.

### 1.3 Espacement et rayons

Échelle d'espacement : `xs .25 · sm .5 · md 1 · lg 1.5 · xl 2 · 2xl 3 · 3xl 4 · 4xl 6`
(en rem). Rythme vertical d'un écran d'étape : `3xl` au-dessus du titre, `xl` entre blocs,
`lg` entre champs.

Rayons : `sm .375 · md .5 · lg .75 · xl 1 · 2xl 1.5 · 3xl 2` (rem).
Cartes de contenu `2xl` ; panneaux et feuilles `3xl` ; boutons `lg` (jamais `full` sauf
pastilles de statut).

### 1.4 Élévation

Trois niveaux seulement : `sm` (carte posée), `lg` (panneau flottant), `2xl` (dialogue).
Aucune ombre colorée hors `shadow-emerald` / `shadow-cyan` déjà définies pour les CTA.

---

## 2. Grille et responsive

| Palier | Largeur | Colonnes | Comportement |
|---|---|---|---|
| `xs` | ≤ 639 px | 1 | assistant en pleine hauteur, une question visible |
| `sm` | 640–767 px | 1 + marges | idem, boutons côte à côte si ≥ 2 actions |
| `md` | 768–1023 px | 8 | formulaire centré `max-w-2xl` |
| `lg` | 1024–1279 px | 12 | deux colonnes : progression (4) + contenu (8) |
| `xl` | ≥ 1280 px | 12 | progression persistante à gauche, contenu `max-w-3xl` |

Règle terrain (POS/KDS/kiosque) : la grille est **pilotée par la cible tactile**, pas par la
largeur — 4 colonnes minimum en paysage tablette, 2 en portrait.

---

## 3. Composants

### 3.1 Boutons

| Variante | Fond | Texte | Bordure | Usage |
|---|---|---|---|---|
| Primaire | dégradé `emerald-600 → emerald-700` | blanc | aucune | action de progression |
| Secondaire | transparent | `slate-900` | `slate-200` | action alternative |
| Fantôme | transparent | `emerald-700` | aucune | action tertiaire, « Passer » |
| Danger | `red-600` | blanc | aucune | suppression, annulation |
| Désactivé | `slate-100` | `slate-400` | `slate-200` | action indisponible — **toujours** avec un motif accessible |

Hauteurs : `sm 36 px` · `md 44 px` · `lg 48 px` · `terrain 56 px`.
État : `hover` (+1 ombre), `active` (échelle 0.98), `focus-visible` (anneau `emerald-400`,
`2 px`, offset `2 px`), `loading` (spinner + libellé conservé, jamais remplacé).

### 3.2 Champs

- Label au-dessus, **toujours** visible (pas de placeholder comme label).
- Aide sous le champ (`text-sm text-slate-600`), erreur en `red-600` avec icône.
- `aria-describedby` pointe l'aide **et** l'erreur.
- Hauteur `44 px` ; `48 px` sur les surfaces terrain.
- Sélecteurs de pays/devise/fuseau : liste fermée issue de `/supported-countries`,
  recherche incrémentale, jamais de champ libre.

### 3.3 Fil d'étapes

- Horizontal sur `md+`, vertical sur `lg+` en colonne latérale, condensé en barre de
  progression + libellé d'étape courante sur mobile.
- Chaque étape : numéro ou coche, libellé, état accessible (`aria-current="step"`).
- Les étapes non encore disponibles ne sont **pas** cliquables (pas de faux lien).

### 3.4 Carte d'application (Mon Logiciel)

```
┌────────────────────────────────────┐
│ [icône]  Nom de l'application      │
│          Rôle en une ligne         │
│  ● Disponible   Android · Web      │
│  Accordéon « Détails »             │
└────────────────────────────────────┘
```

- Dégradé d'icône issu des accents existants (`software-catalog.ts`).
- Badge : pastille + **texte**. Trois valeurs seulement (published / pilot /
  not_distributed).
- L'accordéon est un bouton avec `aria-expanded`, son contenu est dans un `region` lié.

### 3.5 Bandeau de connexion (terrain)

- Hauteur fixe, ne pousse pas le contenu (position `sticky`).
- Trois états : `En ligne` (discret), `Hors ligne — N en attente` (amber), `Erreur de
  synchronisation` (red + action).
- Aucune animation de clignotement.

### 3.6 Tableau (console superadmin)

- En-tête collant, lignes `48 px`, densité configurable (compact/confortable).
- Colonne de statut en première position après l'identité.
- Actions de ligne derrière un menu, jamais une rangée de boutons.

### 3.7 Dialogue et feuille

- Dialogue centré sur `md+`, feuille ancrée en bas sur mobile, `max-h-[92dvh]`.
- Focus piégé, Échap, clic sur fond, restitution du focus à l'élément déclencheur.
- Un seul dialogue à la fois.

---

## 4. Mouvement

| Interaction | Durée | Courbe |
|---|---|---|
| Apparition de panneau | 240 ms | `ease-out` |
| Transition d'étape | 280 ms (opacité + 8 px) | `ease-out` |
| Survol de bouton | 150 ms | `ease-out` |
| Bandeau de connexion | 200 ms | `linear` |
| Fondu de fond décoratif | 1400 ms | `ease-in-out` |

**Interdit :** rebond, rotation continue, clignotement, animation de texte, parallaxe sur
les surfaces terrain.
**Obligatoire :** `prefers-reduced-motion: reduce` supprime toute transition non
essentielle et affiche l'état final immédiatement.

---

## 5. Iconographie

- Jeu unique : `lucide-react` côté web, `lucide-vue-next` côté console.
- Taille : `16` (inline), `20` (bouton), `24` (en-tête), `32–40` (tuile).
- Un pictogramme accompagne **toujours** un libellé dans les états critiques
  (succès, alerte, hors-ligne) — jamais seul.
- Les icônes décoratives sont `aria-hidden`.

---

## 6. Branding par tenant

| Élément | Contrôlé par | Bornes |
|---|---|---|
| Logo | `PATCH /company/branding` | SVG ou PNG ≤ 512 Ko, fond transparent recommandé |
| Favicon | branding tenant | 32×32 et 180×180 générés |
| Couleur d'accent | branding tenant | **un seul** accent ; contraste AA vérifié contre le fond |
| Nom affiché | branding tenant | ≤ 60 caractères |
| Écran de connexion | branding tenant + global | logo + accent ; la marque Leopardo reste visible en pied |

**Garde-fous :** un tenant ne peut pas :
- remplacer la marque Leopardo sur la page de connexion globale ;
- injecter du HTML ou du CSS libre ;
- produire un contraste inférieur à AA (vérification côté serveur au moment de l'enregistrement).

---

## 7. Pages d'erreur

| Page | Contenu | Interdits |
|---|---|---|
| 404 | titre, explication d'une ligne, retour à l'accueil, recherche | trace, nom de fichier, version |
| 403 | « votre rôle ne permet pas cette action », demande d'accès, retour | identifiant technique de permission |
| 500 | message neutre, **identifiant de corrélation**, bouton réessayer, contact support | stack trace, SQL, nom de serveur |
| Hors-ligne | « vous êtes hors ligne », N en attente, réessayer | écran blanc |

Chaque page porte le logo (global ou tenant) et respecte les tokens ci-dessus.

---

## 8. Ce que ce système n'autorise pas

- Un nouveau jeu de couleurs par verticale : une verticale n'a **pas** de palette propre,
  elle a une **icône et un dégradé** dans les limites des tokens existants.
- Une seconde bibliothèque de composants : tout composant nouveau vit dans
  `front/web/src/components` ou `front/web/src/modules/vitrine/components` et réutilise
  les tokens ci-dessus.
- Un composant implémenté uniquement en `hover` : tout état doit être atteignable au clavier.
