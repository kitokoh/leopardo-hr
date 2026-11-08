# Leopardo Toolbox

Écosystème d'outils numériques universels (calculateurs, convertisseurs),
accessible **sans compte** depuis la vitrine publique.

- Catalogue : `/toolbox`
- Outil : `/toolbox/<slug>`
- Documentation de conception : `docs/leopardo-toolbox/`

## Structure

```
src/modules/toolbox/
├── types/            # ToolDefinition, ToolCategory, ToolWarningId
├── data/
│   ├── catalog.ts    # 🗂 Catalogue déclaratif (métadonnées pures)
│   ├── copy.ts       # Copie i18n fr/en/tr/ar
│   └── tool-options.ts # Libellés d'options (catégories IMC, unités)
├── lib/
│   ├── registry.ts   # Résolution slug, recherche, slugs
│   ├── validation.ts # parseNumber, safeDivide, bornes, arrondi
│   └── format.ts     # Formatage Intl (nombres, devises, %)
├── components/       # UI (ToolShell, ToolCard, ToolboxCatalog, formulaires)
└── tools/<slug>/     # logique pure + tests, un dossier par outil
```

## Outils du premier lot

| Slug | Catégorie | Formule | Avertissement |
|---|---|---|---|
| `imc` | Santé | `poids ÷ taille²` (OMS) | non-conseil |
| `tva` | Finance | `TTC = HT × (1 + taux)` | — |
| `salaire-net` | Travail | `net ≈ brut × (1 − taux)` | estimation + non-conseil |
| `convertisseur-unites` | Conversion | facteurs SI exacts | — |
| `pourcentage` | Calcul | `%`, évolution, remise | — |

## Outils du deuxième lot

| Slug | Catégorie | Formule | Avertissement |
|---|---|---|---|
| `regle-de-trois` | Calcul | `X = B × C ÷ A` (directe) / `A × B ÷ C` (inverse) | — |
| `surface-volume` | Calcul | aires et volumes usuels (π) | théorique |
| `cbm-poids-volumetrique` | Logistique | `CBM = L × l × H` ; poids taxable = max(réel, volumétrique) | estimation |
| `marge-prix-vente` | Commerce | marge, taux de marge/marque, coefficient | — |
| `tva-multi-taux` | Finance | récapitulatif TVA regroupé par taux | estimation |

## Vérifications

```bash
cd front/web
npx tsc --noEmit
npx eslint src/modules/toolbox --max-warnings 0
npx jest src/modules/toolbox
```

Voir `AGENTS.md` pour la procédure d'ajout d'un outil.
