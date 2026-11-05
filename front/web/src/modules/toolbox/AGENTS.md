# Leopardo Toolbox — module autonome

Catégorie autonome d'outils numériques universels (calculateurs, convertisseurs),
**publique et sans authentification**, intégrée à la vitrine Next.js.

## Principes non négociables

1. **Autonome** — ce module ne dépend d'aucun module métier (RH, paie, compta…).
   La seule dépendance externe autorisée est `@/lib/locale-core` (locale) et
   `@/lib/site-url` (SEO). Aucun appel API, aucun accès base de données.
2. **Calcul 100 % côté client** — pas d'endpoint backend par calculatrice.
3. **Métadonnées séparées de la logique** — `data/catalog.ts` ne contient que des
   métadonnées ; les formules vivent dans `tools/<slug>/logic.ts`.
4. **Aucune copie en dur** — toute chaîne visible vient de `data/copy.ts`
   (fr/en/tr/ar). Les gardes `check:mojibake` et `check:french-copy` s'appliquent.
5. **Aucune donnée personnelle collectée** — les saisies ne quittent pas le
   navigateur.

## Ajouter un outil (procédure)

1. **Logique** : créer `tools/<slug>/logic.ts` — une fonction **pure** qui
   retourne `null` sur entrée invalide (jamais `NaN`/`Infinity`). Documenter en
   tête : formule, unités, hypothèses, référence.
2. **Tests** : créer `tools/<slug>/logic.test.ts` — cas connus calculés à la main
   + cas limites (zéro, négatif, `NaN`, `Infinity`, division par zéro, extrêmes).
3. **Catalogue** : ajouter une entrée dans `data/catalog.ts` (slug kebab-case,
   catégorie, mots-clés, avertissements, références, version sémantique).
4. **Copie** : ajouter l'entrée `tools.<slug>` dans les **4** locales de
   `data/copy.ts` (nom, description, champs, résultats, notes).
5. **Formulaire** : créer `components/tools/<Name>Form.tsx` (client) et
   l'enregistrer dans `components/ToolRenderer.tsx`.
6. **Vérifier** : `npx tsc --noEmit`, `npx eslint src/modules/toolbox --max-warnings 0`,
   `npx jest src/modules/toolbox`.

Aucune autre modification n'est nécessaire : les routes `/toolbox` et
`/toolbox/[slug]` sont générées depuis le catalogue, et le sitemap les expose
automatiquement.

## Interdictions

- ❌ Ajouter `/toolbox` à `PROTECTED_PREFIXES` (casserait l'accès anonyme).
- ❌ Ajouter une dépendance de calcul externe (les formules sont écrites et testées ici).
- ❌ Appeler un service externe payant (devises = taux statiques documentés).
- ❌ Présenter un résultat théorique/estimatif comme une validation officielle.
- ❌ Mélanger du code métier Leopardo dans ce module.
