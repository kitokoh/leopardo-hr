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

## Langue (fr / en / tr / ar)

La Toolbox suit la convention vitrine `?lang=` (aucune route préfixée) : le
proxy normalise le paramètre en `x-vitrine-lang`, que les layouts lisent pour
rendre le HTML déjà dans la bonne langue.

Deux règles, portées par `lib/locale-href.ts` :

1. **Tout lien interne passe par `withLang(href, locale)`.** `?lang=` ne survit
   pas à une navigation : un `<Link href="/toolbox/imc">` écrit en dur faisait
   repartir le visiteur en français dès le premier clic, quel que soit son
   choix. `fr` est la locale par défaut et n'est **pas** écrite dans l'URL
   (URL canonique, cohérente avec `sitemap.ts` et les `alternates.hreflang`).
2. **La bascule de langue passe par `localeSwitchHref(pathname, search, locale)`**,
   qui conserve les filtres du catalogue et le paramètre `s=` d'un calcul
   partagé, et ne remplace que `lang`.

Le contrôle est `components/ToolboxLangSwitcher.tsx`, monté dans
`app/toolbox/layout.tsx`. Il déclenche une **navigation complète**
(`window.location.assign`) et non un `router.push` : seul un aller-retour serveur
rejoue le proxy qui pose `x-vitrine-lang`.

## Vérifications

```bash
cd front/web
npx tsc --noEmit
npx eslint src/modules/toolbox src/app/toolbox --max-warnings 0
npx jest src/modules/toolbox
# E2E (nécessite un dev server) :
BASE_URL=http://localhost:3000 npx playwright test e2e/toolbox-langue.spec.ts --project=chromium
```

Voir `AGENTS.md` pour la procédure d'ajout d'un outil.
