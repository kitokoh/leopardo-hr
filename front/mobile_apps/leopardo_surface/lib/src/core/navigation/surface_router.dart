import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

import '../../features/activity/screens/activity_tab.dart';
import '../../features/business_profile/screens/business_profile_route.dart';
import '../../features/journeys/domain/journey_registry.dart';
import '../../features/journeys/screens/careers_journey_screen.dart';
import '../../features/journeys/screens/pharmacy_journey_screen.dart';
import '../../features/journeys/screens/restaurant_order_screen.dart';
import '../../features/journeys/screens/retail_order_screen.dart';
import '../../features/journeys/screens/stay_reservation_screen.dart';
import '../../features/journeys/screens/travel_journey_screen.dart';
import '../../features/journeys/screens/vertical_directory_screen.dart';
import '../../features/journeys/screens/vtc_journey_screen.dart';
import '../../features/surface/domain/surface_identity.dart';
import '../../features/tabs/secondary_tabs.dart';
import '../providers/app_providers.dart';

/// Coque à 4 onglets d'une application de verticale.
///
/// ## Ce qui change par rapport à l'app multi-verticales
///
/// L'onglet Accueil ne s'appelle plus « Accueil » mais le nom de la verticale
/// (« Restaurants », « Pharmacies », …) et porte son icône. C'est la différence
/// visible entre une app qui parle de tout et une app qui parle d'une chose :
/// l'utilisateur qui ouvre l'app Pharmacies ne doit pas se demander s'il est au
/// bon endroit.
///
/// ## Pourquoi `IndexedStack` et pas un `PageView`
///
/// Le parcours quotidien est « ouvrir → reprendre où vous étiez ». Avec un
/// `PageView`, revenir sur l'accueil reconstruit l'écran et relance la
/// recherche ; avec `IndexedStack`, l'état de l'onglet survit au changement
/// d'onglet. C'est la différence entre une app qui « se souvient » et une qui
/// recharge.
///
/// ## Navigation imbriquée
///
/// Chaque onglet a son propre `Navigator` via `StatefulNavigationShell` de
/// GoRouter : la fiche commerce s'ouvre PAR-DESSUS l'onglet Accueil et le
/// bouton retour ramène à la liste, sans perdre la recherche en cours.
class SurfaceShell extends ConsumerWidget {
  const SurfaceShell({
    super.key,
    required this.navigationShell,
    required this.vertical,
  });

  final StatefulNavigationShell navigationShell;

  /// Verticale servie par cette application.
  final SurfaceVertical vertical;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);
    final labelKey = journeyByCode(vertical.code)?.labelKey;

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        body: navigationShell,
        bottomNavigationBar: NavigationBar(
          selectedIndex: navigationShell.currentIndex,
          onDestinationSelected: (index) {
            // `initialLocation: true` uniquement quand on retape l'onglet
            // courant : cela ramène à sa racine (geste attendu), sans casser
            // la pile des autres onglets.
            navigationShell.goBranch(
              index,
              initialLocation: index == navigationShell.currentIndex,
            );
          },
          destinations: [
            NavigationDestination(
              icon: Icon(surfaceHomeIcon(vertical, selected: false)),
              selectedIcon: Icon(surfaceHomeIcon(vertical, selected: true)),
              label:
                  labelKey == null ? strings.t('tabHome') : strings.t(labelKey),
            ),
            NavigationDestination(
              icon: const Icon(Icons.forum_outlined),
              selectedIcon: const Icon(Icons.forum),
              label: strings.t('tabMessages'),
            ),
            NavigationDestination(
              icon: const Icon(Icons.receipt_long_outlined),
              selectedIcon: const Icon(Icons.receipt_long),
              label: strings.t('tabActivity'),
            ),
            NavigationDestination(
              icon: const Icon(Icons.person_outline),
              selectedIcon: const Icon(Icons.person),
              label: strings.t('tabProfile'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Icône de l'onglet principal, propre à la verticale.
///
/// Une app dédiée qui garderait la loupe générique de l'app multi-verticales
/// ne se distinguerait pas dans le tiroir d'applications. L'icône est donc
/// dérivée de la verticale, jamais d'une constante partagée.
IconData surfaceHomeIcon(SurfaceVertical vertical, {required bool selected}) {
  switch (vertical) {
    case SurfaceVertical.restaurant:
      return selected ? Icons.restaurant : Icons.restaurant_outlined;
    case SurfaceVertical.retail:
      return selected ? Icons.storefront : Icons.storefront_outlined;
    case SurfaceVertical.hospitality:
      return selected ? Icons.hotel : Icons.hotel_outlined;
    case SurfaceVertical.trade:
      return selected ? Icons.factory : Icons.factory_outlined;
    case SurfaceVertical.travel:
      return selected
          ? Icons.confirmation_number
          : Icons.confirmation_number_outlined;
    case SurfaceVertical.pharmacy:
      return selected ? Icons.local_pharmacy : Icons.local_pharmacy_outlined;
    case SurfaceVertical.vtc:
      return selected ? Icons.local_taxi : Icons.local_taxi_outlined;
    case SurfaceVertical.careers:
      return selected ? Icons.work : Icons.work_outline;
  }
}

/// Écran d'accueil d'une application de verticale.
///
/// ## Pourquoi un aiguillage plutôt qu'un écran par verticale
///
/// Les huit verticales n'ont pas huit accueils différents : elles en ont
/// **deux**. Cinq d'entre elles (restaurant, retail, hospitality, trade)
/// partagent le même contrat de lecture (`GET /public/discover?type=…`) et donc
/// le même annuaire ; les trois autres (pharmacie, mobilité, billets, emploi)
/// ont un parcours autonome qui n'a pas besoin d'une fiche commerce pour
/// démarrer.
///
/// Dupliquer l'annuaire cinq fois multiplierait par cinq les occasions
/// d'oublier la distinction panne / annuaire vide. L'aiguillage garde un seul
/// écran par famille et rend l'appartenance d'une verticale à une famille
/// **explicite et exhaustive** : un `switch` incomplet ne compile pas.
Widget surfaceHomeScreen(SurfaceVertical vertical) {
  switch (vertical) {
    case SurfaceVertical.restaurant:
    case SurfaceVertical.retail:
    case SurfaceVertical.hospitality:
    case SurfaceVertical.trade:
      return VerticalDirectoryScreen(verticalCode: vertical.code);
    case SurfaceVertical.pharmacy:
      return const PharmacyJourneyScreen();
    case SurfaceVertical.vtc:
      return const VtcJourneyScreen();
    case SurfaceVertical.travel:
      return const TravelJourneyScreen();
    case SurfaceVertical.careers:
      return const CareersJourneyScreen();
  }
}

/// Routes de détail d'une verticale — l'URL PUBLIQUE et son alias interne.
///
/// ## Pourquoi deux chemins pour un écran
///
/// - `{basePath}/{slug}` (ex. `/restaurants/le-bistrot`) est l'URL que la
///   vitrine web expose déjà (`front/web/src/app/restaurants/[slug]`). C'est
///   celle qu'on partage, qu'on met dans un QR code, et qu'un crawler indexe.
///   La servir à l'identique côté mobile est ce qui rend un lien
///   interchangeable entre les deux fronts.
/// - `/journey/…` reste la convention interne du dépôt, cohérente entre
///   verticales.
///
/// Deux routes, un seul écran, zéro duplication de code.
List<RouteBase> surfaceDetailRoutes(SurfaceVertical vertical) {
  switch (vertical) {
    case SurfaceVertical.restaurant:
      return [
        GoRoute(
          path: '/restaurants/:slug',
          builder: (context, state) => RestaurantOrderScreen(
            slug: state.pathParameters['slug'] ?? '',
          ),
        ),
        GoRoute(
          path: '/journey/restaurant/:slug',
          builder: (context, state) => RestaurantOrderScreen(
            slug: state.pathParameters['slug'] ?? '',
          ),
        ),
      ];
    case SurfaceVertical.retail:
      return [
        GoRoute(
          path: '/boutiques/:slug',
          builder: (context, state) => RetailOrderScreen(
            sellerSlug: state.pathParameters['slug'] ?? '',
          ),
        ),
        GoRoute(
          path: '/journey/order/:slug',
          builder: (context, state) => RetailOrderScreen(
            sellerSlug: state.pathParameters['slug'] ?? '',
          ),
        ),
      ];
    case SurfaceVertical.hospitality:
      return [
        GoRoute(
          path: '/stay/:slug',
          builder: (context, state) => StayReservationScreen(
            slug: state.pathParameters['slug'] ?? '',
          ),
        ),
        GoRoute(
          path: '/journey/stay/:slug',
          builder: (context, state) => StayReservationScreen(
            slug: state.pathParameters['slug'] ?? '',
          ),
        ),
      ];
    case SurfaceVertical.trade:
      // La verticale B2B n'a pas d'écran transactionnel : sa fiche est
      // l'annuaire lui-même, avec une demande de contact. Le chemin public
      // `/entreprises/{slug}` ouvre donc l'annuaire pré-filtré.
      return [
        GoRoute(
          path: '/entreprises/:slug',
          builder: (context, state) => VerticalDirectoryScreen(
            verticalCode: vertical.code,
            initialSlug: state.pathParameters['slug'],
          ),
        ),
      ];
    case SurfaceVertical.pharmacy:
      return [
        GoRoute(
          path: '/pharmacies/:slug',
          builder: (context, state) => PharmacyJourneyScreen(
            initialSlug: state.pathParameters['slug'],
          ),
        ),
        GoRoute(
          path: '/journey/pharmacy',
          builder: (context, state) => PharmacyJourneyScreen(
            initialSlug: state.uri.queryParameters['slug'],
          ),
        ),
      ];
    case SurfaceVertical.vtc:
      return [
        GoRoute(
          path: '/operateurs/:slug',
          builder: (context, state) => const VtcJourneyScreen(),
        ),
        GoRoute(
          path: '/journey/vtc',
          builder: (context, state) => const VtcJourneyScreen(),
        ),
      ];
    case SurfaceVertical.travel:
      // Cette verticale n'a pas de slug d'établissement : la recherche est
      // autonome. L'URL publique porte donc l'identifiant de trajet dans le
      // CHEMIN (`/billets/42`) et non en query (`?trip=42`) : un paramètre de
      // requête n'est pas une URL canonique partageable, et les crawlers le
      // traitent comme la même page que `/billets`.
      return [
        GoRoute(
          path: '/billets',
          builder: (context, state) => const TravelJourneyScreen(),
          routes: [
            GoRoute(
              path: ':id',
              builder: (context, state) => TravelJourneyScreen(
                initialTripId: int.tryParse(state.pathParameters['id'] ?? ''),
              ),
            ),
          ],
        ),
        GoRoute(
          path: '/journey/travel',
          builder: (context, state) => TravelJourneyScreen(
            initialTripId:
                int.tryParse(state.uri.queryParameters['trip'] ?? ''),
          ),
        ),
      ];
    case SurfaceVertical.careers:
      return [
        GoRoute(
          path: '/emploi',
          builder: (context, state) => CareersJourneyScreen(
            initialCompanySlug: state.uri.queryParameters['company'],
          ),
          routes: [
            GoRoute(
              path: ':companySlug',
              builder: (context, state) => CareersJourneyScreen(
                initialCompanySlug: state.pathParameters['companySlug'],
              ),
            ),
          ],
        ),
        GoRoute(
          path: '/journey/careers',
          builder: (context, state) => CareersJourneyScreen(
            initialCompanySlug: state.uri.queryParameters['company'],
          ),
        ),
      ];
  }
}

/// Construit le routeur d'une application de verticale.
///
/// ## Ce que la fabrique garantit
///
/// Les huit apps partagent la MÊME structure de navigation : coque à 4 onglets,
/// fiche commerce atteignable hors coque (deep link / QR), routes publiques de
/// la verticale, 404 présenté comme une information. Seule la verticale change.
///
/// Écrire huit routeurs à la main produirait huit occasions d'oublier la route
/// publique d'une verticale — exactement le défaut que
/// `test/vertical_web_routes_test.dart` a été écrit pour attraper (#9997-#9999).
/// Ici la route publique est produite par la fabrique, donc elle ne peut pas
/// manquer.
///
/// ## `initialLocation`
///
/// `/` par défaut : sur un domaine dédié (`restaurants.leopardo.app`), la
/// racine EST l'accueil de la verticale. L'app multi-verticales conserve
/// `/home` pour ne pas changer ses liens existants.
GoRouter buildSurfaceRouter({
  required SurfaceVertical vertical,
  String initialLocation = '/',
}) {
  return GoRouter(
    initialLocation: initialLocation,
    routes: [
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => SurfaceShell(
          navigationShell: navigationShell,
          vertical: vertical,
        ),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/',
                builder: (context, state) => surfaceHomeScreen(vertical),
                routes: [
                  GoRoute(
                    path: 'business/:type/:slug',
                    builder: (context, state) =>
                        BusinessProfileRoute(state: state),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/messages',
                builder: (context, state) => const MessagesTab(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/activity',
                builder: (context, state) => const ActivityTab(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/profile',
                builder: (context, state) => const ProfileTab(),
              ),
            ],
          ),
        ],
      ),

      // Fiche atteignable hors barre d'onglets (deep link / QR).
      GoRoute(
        path: '/business/:type/:slug',
        builder: (context, state) => BusinessProfileRoute(state: state),
      ),

      // Routes de la verticale : URL publique + alias interne.
      ...surfaceDetailRoutes(vertical),

      // Toute route inconnue : 404 présenté comme une information, jamais
      // comme une panne.
      GoRoute(
        path: '/not-found',
        builder: (context, state) => const SurfaceNotFoundScreen(),
      ),
    ],
    errorBuilder: (context, state) => const SurfaceNotFoundScreen(),
  );
}

/// 404 d'une application de verticale.
class SurfaceNotFoundScreen extends ConsumerWidget {
  const SurfaceNotFoundScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        backgroundColor: AppColors.backgroundFor(context),
        body: SafeArea(
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.travel_explore_outlined, size: 44),
                const SizedBox(height: 12),
                Text(strings.t('stateNotFoundTitle')),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: () => context.go('/'),
                  child: Text(strings.t('stateNotFoundAction')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
