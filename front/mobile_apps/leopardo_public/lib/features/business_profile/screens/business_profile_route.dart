import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../discovery/widgets/discovery_helpers.dart';
import 'business_profile_screen.dart';

/// Adapte un `GoRouteState` à [BusinessProfileScreen].
///
/// ## Le cas qui casse sans ce fichier
///
/// Un visiteur scanne le QR code d'un commerce : GoRouter résout
/// `/business/restaurant/chez-nadia` sans aucun `extra`. Si l'écran faisait
/// `state.extra as DiscoveryItem`, il lèverait une exception de cast et
/// afficherait une page blanche — sur le parcours le plus important de
/// l'application.
///
/// Ici `extra` est OPTIONNEL : sans lui, la fiche se charge seule via l'API ;
/// avec lui, elle s'affiche immédiatement puis se rafraîchit.
class BusinessProfileRoute extends StatelessWidget {
  const BusinessProfileRoute({super.key, required this.state});

  final GoRouterState state;

  @override
  Widget build(BuildContext context) {
    final type = state.pathParameters['type'] ?? '';
    final slug = state.pathParameters['slug'] ?? '';

    return BusinessProfileScreen(
      type: type,
      slug: slug,
      previewItem: discoveryItemFromExtra(state.extra),
    );
  }
}
