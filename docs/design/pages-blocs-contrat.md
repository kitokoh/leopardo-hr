# Contrat de blocs Pages — référence pour les BC verticaux

> **Issue :** #8724 (BC PAGES). **Statut :** implémenté. **Propriétaire du contenant :** BC Pages.
> **Propriétaire d'un type :** le BC vertical qui le déclare.

Ce document est la référence normative du contrat de blocs. Il décrit ce qu'un
BC vertical doit livrer pour que ses blocs soient validés, stockés et rendus, et
ce que Pages garantit en retour.

---

## 1. Pourquoi un contrat plutôt qu'un registre fermé

Showcase (BC-27) valide son contenu contre un registre **fermé** : sept types
connus à la compilation, un `enum` central, et pour ajouter un type il faut
modifier Showcase. C'est acceptable pour une vitrine unique par tenant, mais
incompatible avec Pages : chaque vertical (restaurant, retail, fuel, travel) doit
publier ses propres blocs **sans que Pages importe ses tables ni son code**.

Pages possède donc le **contenant** (la Page, l'ordre, le stockage) et
l'**orchestrateur** (validation, sélection du renderer). Le vertical possède son
**schéma** et son **renderer**.

---

## 2. Le port `PageBlockProvider`

```php
interface PageBlockProvider
{
    public function ownerBc(): string;                     // ex. 'restaurant'
    /** @return array<string, PageBlockTypeDefinition> */
    public function blockTypes(): array;
}
```

Enregistrement, dans le service provider **du vertical** :

```php
$this->app->tag([RestaurantBlocks::class], 'pages.block_providers');
```

Pages ne fait que résoudre le tag. Un vertical non installé ⇒ tag vide ⇒ le
registre ne contient que `core.*`. Aucun `class_exists`, aucune dépendance dure
entre Pages et les verticaux.

---

## 3. `PageBlockTypeDefinition` — ce qui est obligatoire

| Champ | Contrainte | Pourquoi |
|---|---|---|
| `id` | `<bc>.<nom>`, minuscules `[a-z0-9_]`, séparés par `.` | Un identifiant non qualifié (`hero`) garantit une collision entre deux verticaux ; une collision de type = un contenu interprété par le mauvais schéma. |
| `ownerBc` | doit **préfixer** `id` | Empêche un vertical de déclarer `core.hero` et de détourner le renderer du socle. |
| `schemaVersion` | entier ≥ 1 | Un type sans version ne peut pas évoluer sans réinterpréter les blocs existants. |
| `schema` | `{"type": "object", …}` à la racine | Seule forme que le validateur et les renderers savent traiter. Un schéma racine `string` accepterait du HTML. |
| `renderer` | nom de renderer ou `null` | `null` = type connu mais **non rendable** : le bloc est stocké et omis du rendu avec télémétrie. |
| `isCore` | booléen | Distingue le socle des contributions. |
| `label` | chaîne | Libellé éditeur (traduit côté client). |

Deux fournisseurs qui déclarent le même `id` lèvent
`DuplicatePageBlockTypeException` **à la construction du registre** (au boot),
jamais un remplacement silencieux.

---

## 4. Schéma strict — les cinq règles

1. **`additionalProperties: false` est le défaut.** Contrairement à JSON Schema
   (permissif par défaut), l'absence de la clé **refuse** les propriétés
   inconnues. Un fournisseur qui oublie de borner son schéma se fait fermer, pas
   ouvrir.
2. **Profondeur ≤ 6** (`PageBlockSchemaValidator::MAX_DEPTH`).
3. **Tableaux ≤ 100 éléments** (`MAX_ARRAY_ITEMS`) — y compris si le schéma
   déclare `maxItems: 10000`, le plafond dur prime.
4. **Chaînes ≤ 5000 caractères** (`MAX_STRING_LENGTH`) — même logique.
5. **URL : allowlist de protocoles.**
   - `format: "uri"` → `https`, `http`, et les chemins relatifs (`/p/slug`).
   - `format: "contact-uri"` → idem + `mailto`, `tel`.
   - **Refusés** : `javascript:`, `data:`, `vbscript:`, tout autre protocole, et
     une URL sans protocole non relative.
   - Un `javascript:` dans un `cta_url` est un XSS stocké qui contourne les
     protections de rendu : c'est la raison d'être de cette règle.

Pas de nettoyage HTML : le modèle ne contient **pas** de HTML. Le contenu est
une structure de données, le renderer décide du markup. `strip_tags` donnerait
une fausse sécurité sur des champs destinés à des attributs.

---

## 5. Forme persistée d'un bloc

```json
{
  "id": "b_01J...",
  "type": "restaurant.menu",
  "schema_version": 1,
  "position": 2,
  "content": { "sections": [ … ] },
  "locales": { "ar": { "sections": [ … ] } }
}
```

- `schema_version` est **stocké par bloc**, pas seulement par type : des blocs
  v1 et v2 coexistent pendant une migration. Interpréter un contenu v1 comme v2
  produit un rendu silencieusement faux.
- `position` est explicite et **normalisée 0..n-1** avant écriture (tri stable
  sur `(position, index)`) : un échange de blocs ne laisse jamais de trou, et
  deux réponses API ne peuvent pas présenter deux ordres différents.
- `locales` porte les traductions ; le repli sur la langue par défaut est le
  comportement attendu d'une page publique (mieux vaut le français qu'un vide
  sur une page arabe incomplète).

---

## 6. Rendu — la garantie de sécurité

`PageBlockCollection::renderableBlocks()` **n'échoue jamais** et **n'exécute
jamais** de markup brut. Trois cas d'omission, chacun avec un motif exploitable :

| Motif | Cause |
|---|---|
| `unknown_type` | type non enregistré (vertical désinstallé, faute de frappe) |
| `unsupported_schema_version` | bloc d'une version que le contrat courant ne sait pas lire |
| `renderer_missing` | contrat livré, renderer pas encore (le vertical est en cours) |

Le contenu omis **reste stocké** : rien n'est perdu, et la télémétrie (#8730)
peut alerter qu'un type non rendable apparaît dans des Pages publiées.

---

## 7. Compatibilité Showcase (migration #8727)

| Type Showcase | Type Pages |
|---|---|
| `hero` | `core.hero` |
| `features` | `core.features` |
| `gallery` | `core.gallery` |
| `testimonials` | `core.testimonials` |
| `products` | `core.products` |
| `contact` | `core.contact` |
| `footer` | `core.footer` |

Les **noms de champs sont identiques** (c'est la définition de « migré sans
perte »). Un test verrouille cette correspondance : si Showcase ajoute un type,
la CI échoue et force une décision explicite au lieu d'une perte silencieuse.

`core.products` ne stocke **que des références** (`product_refs`) : le prix est
résolu au rendu par le BC Catalog. Un prix figé dans une Page publique diverge
toujours du prix facturé — et un prix public erroné est un problème juridique,
pas cosmétique.

---

## 8. Checklist pour un BC vertical

- [ ] Une classe `XxxBlocks implements PageBlockProvider` avec `ownerBc()`.
- [ ] `blockTypes()` retourne des `PageBlockTypeDefinition` préfixés par
      `ownerBc().'.'`.
- [ ] Chaque schéma : `type: object`, `additionalProperties: false`,
      `maxLength` sur **chaque** chaîne, `maxItems` sur chaque tableau,
      `format: uri`/`contact-uri` sur chaque lien.
- [ ] `renderer` renseigné **seulement** si le composant de rendu existe
      réellement (sinon laissez `null` : omission propre + télémétrie).
- [ ] `$this->app->tag([XxxBlocks::class], 'pages.block_providers');` dans le
      service provider du vertical (pas dans Pages).
- [ ] Tests : un contenu valide, un contenu invalide par règle, un cas
      d'omission au rendu.

---

## 9. Fichiers

| Fichier | Rôle |
|---|---|
| `app/Modules/Pages/Domain/Blocks/PageBlockProvider.php` | le port |
| `app/Modules/Pages/Domain/Blocks/PageBlockTypeDefinition.php` | définition immuable + invariants d'identifiant |
| `app/Modules/Pages/Domain/Blocks/PageBlockRegistry.php` | catalogue figé, détection de collision |
| `app/Modules/Pages/Domain/Blocks/CorePageBlocks.php` | les 7 types du socle + table de correspondance Showcase |
| `app/Modules/Pages/Domain/Blocks/PageBlockSchemaValidator.php` | validation stricte + allowlist d'URL |
| `app/Modules/Pages/Domain/Blocks/PageBlock.php` | un bloc, sa version, ses traductions |
| `app/Modules/Pages/Domain/Blocks/PageBlockCollection.php` | ordre, validation agrégée, sélection des blocs rendables |
| `app/Modules/Pages/Providers/PagesServiceProvider.php` | câblage du registre (singleton) et du tag |
| `tests/Feature/Pages/PageBlockContractTest.php` | 32 tests du contrat |
