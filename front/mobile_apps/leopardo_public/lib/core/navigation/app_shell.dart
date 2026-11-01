import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

import '../../core/providers/app_providers.dart';
import '../../features/business_profile/screens/business_profile_route.dart';
import '../../features/discovery/screens/discovery_home_screen.dart';
import '../../features/tabs/secondary_tabs.dart';

/// Coque à 4 onglets.
///
/// ## Pourquoi `IndexedStack` et pas un `PageView`
///
/// Le parcours quotidien est « ouvrir → reprendre où vous étiez ». Avec un
/// `PageView`, revenir sur Accueil reconstruit l'écran et relance la recherche ;
/// avec `IndexedStack`, l'état de l'onglet survit au changement d'onglet. C'est
/// la différence entre une app qui « se souvient » et une qui recharge.
///
/// Contrepartie assumée : les 4 onglets restent en mémoire. Ils sont légers
/// (deux contiennent un état vide), donc le coût est nul — mais c'est un choix,
/// pas un oubli.
///
/// ## Navigation imbriquée
///
/// Chaque onglet a son propre `Navigator` via `StatefulNavigationShell` de
/// GoRouter : la fiche commerce s'ouvre PAR-DESSUS l'onglet Accueil et le
/// bouton retour ramène à la liste, sans perdre la recherche en cours.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);

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
              icon: const Icon(Icons.search_outlined),
              selectedIcon: const Icon(Icons.search),
              label: strings.t('tabHome'),
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

/// Branches de l'onglet Accueil (liste → fiche), et racines des autres onglets.
///
/// La fiche est déclarée DEUX FOIS (`/business/:type/:slug` global et sous
/// `/home/business/...`) : la première permet d'ouvrir une fiche depuis un QR
/// code ou un lien profond sans passer par la liste ; la seconde conserve la
/// barre d'onglets quand on vient de la liste. Deux routes, un seul écran —
/// aucune duplication de code.
final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/home',
    routes: [
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const DiscoveryHomeScreen(),
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
      // Toute route inconnue : 404 présenté comme une information (voir
      // NotFoundState), jamais comme une panne.
      GoRoute(
        path: '/not-found',
        builder: (context, state) => const _NotFoundScreen(),
      ),
    ],
    errorBuilder: (context, state) => const _NotFoundScreen(),
  );
});

class _NotFoundScreen extends ConsumerWidget {
  const _NotFoundScreen();

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
                  onPressed: () => context.go('/home'),
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
