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

## État de formulaire et partage par lien

L'état d'un formulaire est synchronisé avec l'URL (`?s=…`) par
`lib/useToolState.ts` : un calcul est partageable **sans compte**, et le calcul
est rejoué localement à l'ouverture.

### Outil à état de LISTE (longueur variable)

Un outil dont l'état est une liste (ex. `tva-multi-taux`) aplatit ses lignes sous
des clés indexées `amount0`, `rate0`, `amount1`, … Quatre règles, verrouillées par
`tools/tva-multi-taux/lines.test.ts` :

1. **Numérotation contiguë** — de 0 à n-1, sans trou. `stateToLines` s'arrête au
   premier indice incomplet : une clé orpheline **détourne** la lecture au lieu
d'être ignorée.
2. **Suppression réelle** — retirer une ligne exige de **supprimer** ses clés
   (`pruneOrphanKeys` + `removeValue`), pas seulement de réécrire les autres.
   Sinon la ligne supprimée ressuscite en duplicata de la suivante et le total
   affiché est faux (défaut corrigé en #9531).
3. **Plafond unique** — `MAX_SHARED_LINES` borne à la fois l'encodage de l'URL
   **et** l'interface (`canAddLine`). Les désynchroniser produit un lien partagé
   qui recalcule un autre total, sans avertissement.
4. **Motif déclaré** — `useToolState(defaults, { extraKeyPattern })` : sans ce
   motif, `mergeWithDefaults` n'accepte que les clés des valeurs par défaut, donc
   un lien portant plus d'entrées que celles-ci en **perd** silencieusement.

La conversion lignes ↔ état vit dans `tools/<slug>/lines.ts` (fonctions pures),
**pas** dans le composant : c'est ce qui la rend testable sans React.

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
