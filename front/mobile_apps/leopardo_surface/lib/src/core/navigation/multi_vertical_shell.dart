import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/app_providers.dart';

/// Coque à 4 onglets de l'application grand public MULTI-VERTICALES.
///
/// ## Pourquoi elle est distincte de `SurfaceShell`
///
/// `SurfaceShell` (dans `surface_router.dart`) sert une app DÉDIÉE : son onglet
/// principal porte le nom et l'icône de la verticale (« Restaurants »,
/// « Pharmacies »). Ici il n'y a pas de verticale unique — l'onglet principal
/// est la découverte, et il doit s'appeler « Accueil » avec une loupe.
///
/// Fusionner les deux coques obligerait à un `if (vertical == null)` dans le
/// widget, et la première verticale ajoutée sans identité afficherait
/// silencieusement « Accueil » dans une app dédiée. Deux coques, deux
/// intentions, aucune condition.
///
/// ## Pourquoi `IndexedStack` et pas un `PageView`
///
/// Le parcours quotidien est « ouvrir → reprendre où vous étiez ». Avec un
/// `PageView`, revenir sur Accueil reconstruit l'écran et relance la recherche ;
/// avec `IndexedStack`, l'état de l'onglet survit au changement d'onglet.
///
/// ## Navigation imbriquée
///
/// Chaque onglet a son propre `Navigator` via `StatefulNavigationShell` de
/// GoRouter : la fiche commerce s'ouvre PAR-DESSUS l'onglet Accueil et le
/// bouton retour ramène à la liste, sans perdre la recherche en cours.
class MultiVerticalShell extends ConsumerWidget {
  const MultiVerticalShell({super.key, required this.navigationShell});

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
