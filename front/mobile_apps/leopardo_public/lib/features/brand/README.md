# Multi-brand mobile — brancher la feature `brand`

> **BC-41 BRANDING** — issue #9581.
> Spécification complète : [`docs/architecture/MULTIBRAND_PUBLIC_SURFACES.md`](../../../../docs/architecture/MULTIBRAND_PUBLIC_SURFACES.md) §8.

## Ce que cette feature fait

Elle affiche l'identité d'une **enseigne** (couleurs, nom public, logo, sens
d'écriture) servie par l'API, dans une application mobile.

## Ce qu'elle ne fait PAS — et c'est volontaire

**Elle ne modifie aucune application verticale existante.** `leopardo_pos_restaurant`,
`leopardo_travel_agent`, `leopardo_pos_retail`, `leopardo_hr`, `leopardo_manager`
gardent leur thème propre et leur comportement actuel. La feature est **additive
et optionnelle** : une app qui ne la branche pas ne change pas d'un pixel.

C'est la garantie de non-régression de la mission : on ajoute, on ne remplace pas.

## Structure

```
lib/features/brand/
├── domain/
│   └── brand.dart              Brand, BrandPalette, BrandIdentity, BrandSurfaceProfile
│                               + gardes de parsing. PUR : aucun import réseau,
│                               aucun Color(...).
├── application/
│   ├── brand_controller.dart   BrandController (Riverpod 3 Notifier) + BrandState
│   └── brand_theme.dart        brandTheme() — SEUL fichier qui convertit en Color
└── infrastructure/
    ├── brand_api.dart          loadBrand() + BrandLoadOutcome (found/notFound/
    │                           unavailable/invalid)
    └── dio_brand_loader.dart   adaptateur ApiClient → BrandJsonLoader
```

## Brancher dans une application

### 1. Fournir le chargeur au démarrage

```dart
// main.dart (ou le bootstrap de l'app)
runApp(
  ProviderScope(
    overrides: [
      // `apiClient` est celui du core (leopardo_core).
      brandLoaderProvider.overrideWithValue(dioBrandLoader(apiClient)),
    ],
    child: const MyApp(),
  ),
);
```

Sans cette surcharge, `brandLoaderProvider` répond « pas de marque » : l'app
fonctionne normalement, simplement sans marque dynamique. **Aucun crash** — c'est
délibéré, un oubli de branchement ne doit pas casser une app.

### 2. Afficher une marque sur un écran

```dart
class EstablishmentScreen extends ConsumerWidget {
  const EstablishmentScreen({super.key, required this.brandSlug});

  final String brandSlug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(brandControllerProvider(brandSlug));

    // Pas de marque (chargement, 404, réseau) → thème de l'app, jamais un
    // écran d'erreur : la fiche de l'établissement reste utilisable.
    final brand = state.value;

    final content = EstablishmentBody(slug: brandSlug);

    if (brand == null) {
      return content;
    }

    return Theme(
      data: brandTheme(brand, base: Theme.of(context)),
      child: Directionality(
        textDirection: textDirectionOf(brand),
        child: content,
      ),
    );
  }
}
```

### 3. Charger une marque sans état réactif

Pour un en-tête ou un écran qui n'a pas besoin de réagir :

```dart
final brand = await loadBrandOnce(ref, 'chez-lea', requestedLocale: 'ar');
```

## Règles à respecter

| Règle | Pourquoi |
|---|---|
| **Aucun `Color(0x...)` hors de `AppColors`** | garde CI `validate-mobile-color-tokens.ps1`. Les couleurs de marque viennent du réseau : elles se convertissent dans `brand_theme.dart`, jamais en littéral. |
| **`onPrimary` vient du serveur** | le contraste WCAG est calculé une fois côté API. Le recalculer ici créerait une seconde formule qui divergerait. |
| **Une marque absente est un état valide** | la feature est additive : son indisponibilité ne doit jamais dégrader l'app. |
| **`Theme(data:)` local, pas de thème global** | une app peut afficher plusieurs enseignes ; remplacer le thème global ferait clignoter toute l'app. |
| **`autoDispose` sur le contrôleur** | une marque ne doit pas survivre à l'écran qui l'affiche, sinon les couleurs de l'enseigne précédente réapparaissent. |

## Tests

```bash
cd front/mobile_apps/leopardo_public
flutter test test/features/brand/
```

Les tests couvrent le parsing (DTO complet, slug vide, identité sans nom,
palette incomplète, couleur invalide, CTA non déclaré, RTL), la normalisation de
locale, l'ordre des capacités, et les quatre issues de chargement
(`found` / `notFound` / `unavailable` / `invalid`).

Aucun test ne monte de serveur : le chargeur est injecté.
