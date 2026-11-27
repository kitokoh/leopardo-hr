import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Routeur de l'application grand public MULTI-VERTICALES.
///
/// ## Pourquoi ce fichier existe encore
///
/// Chaque verticale a désormais son application dédiée, et ces apps utilisent
/// `buildSurfaceRouter(vertical: …)` du socle : une seule verticale, une seule
/// expérience. `leopardo_public` garde une raison d'être différente — servir le
/// visiteur qui ne sait pas encore ce qu'il cherche, et qui doit pouvoir passer
/// d'un restaurant à une pharmacie sans changer d'application.
///
/// Ce routeur est donc le SEUL du dépôt qui monte les huit verticales à la
/// fois. Il ne duplique aucune route : il agrège `surfaceDetailRoutes()` pour
/// chacune, ce qui garantit qu'une URL publique ajoutée au socle apparaît ici
/// automatiquement — et qu'elle ne peut pas manquer dans une app dédiée.
///
/// ## `initialLocation: '/home'`
///
/// Conservé (et non `/`) pour ne pas casser les liens existants : cette app est
/// déjà publiée, et un lien `/home` partagé doit continuer de fonctionner. Les
/// apps dédiées, elles, démarrent sur `/` — sur un domaine propre, la racine
/// EST l'accueil de la verticale.
final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/home',
    routes: [
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => MultiVerticalShell(
          navigationShell: navigationShell,
        ),
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

      // Les routes publiques ET internes des huit verticales, agrégées depuis
      // le socle. Aucune n'est écrite à la main ici : c'est ce qui empêche
      // l'app multi-verticales de diverger des apps dédiées.
      for (final vertical in SurfaceVertical.values)
        ...surfaceDetailRoutes(vertical),

      // Annuaire générique d'une verticale (utilisé par la découverte).
      GoRoute(
        path: '/journey/directory/:vertical',
        builder: (context, state) => VerticalDirectoryScreen(
          verticalCode: state.pathParameters['vertical'] ?? '',
        ),
      ),

      // Demande de contact universelle (le seul contrat public qui accepte
      // n'importe quel type de cible).
      GoRoute(
        path: '/journey/contact/:type/:slug',
        builder: (context, state) => ContactRequestScreen(
          type: state.pathParameters['type'] ?? '',
          slug: state.pathParameters['slug'] ?? '',
        ),
      ),

      GoRoute(
        path: '/not-found',
        builder: (context, state) => const SurfaceNotFoundScreen(),
      ),
    ],
    errorBuilder: (context, state) => const SurfaceNotFoundScreen(),
  );
});
