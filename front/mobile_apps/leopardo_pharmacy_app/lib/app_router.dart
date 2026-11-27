import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Routeur de **Leopardo Pharmacies** — une seule verticale.
///
/// ## Ce que ce fichier ne fait pas
///
/// Il n'écrit AUCUNE route à la main. `buildSurfaceRouter` produit la coque à
/// 4 onglets, la fiche commerce hors coque, l'URL publique de la verticale
/// (`/pharmacies/{{slug}}`) et son alias interne, plus le 404. C'est ce qui
/// garantit qu'une URL publique ajoutée au socle apparaît ici
/// automatiquement — et qu'elle ne peut pas manquer dans cette app.
///
/// ## `initialLocation: '/'`
///
/// Sur un domaine dédié (`pharmacies.leopardo.app`), la racine EST l'accueil de la verticale.
/// L'app multi-verticales conserve `/home` pour ne pas casser ses liens
/// existants ; ici il n'y a rien à préserver.
final appRouterProvider = Provider<GoRouter>((ref) {
  {
    return buildSurfaceRouter(
      vertical: SurfaceVertical.pharmacy,
      initialLocation: '/',
    );
  }
});
