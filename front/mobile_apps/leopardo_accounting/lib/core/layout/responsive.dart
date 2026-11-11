import 'package:flutter/widgets.dart';

/// Adaptation tablette (issue #9381).
///
/// L'app comptable n'avait **aucun** `LayoutBuilder` ni `MediaQuery` : elle
/// s'affichait en colonne pleine largeur sur un iPad, avec des cartes étirées
/// sur 1 000 px et une liste de documents à une seule colonne. Elle était donc
/// techniquement installable sur tablette mais inutilisable en pratique.
///
/// Ce fichier centralise les seuils pour qu'ils soient identiques partout :
///   - `compact`  : téléphone portrait (< 600 dp) — navigation par onglets ;
///   - `medium`   : tablette portrait / téléphone paysage (600–1023 dp) —
///                  grille à 2 colonnes, rail de navigation ;
///   - `expanded` : tablette paysage (>= 1024 dp) — grille à 3 colonnes, rail
///                  étendu.
///
/// Les seuils suivent Material 3 (`WindowSizeClass`), pas une valeur inventée :
/// 600 dp et 840 dp sont les bornes officielles ; on retient 1024 dp pour la
/// troisième classe afin de distinguer une tablette paysage d'un iPad portrait.
enum ScreenSize { compact, medium, expanded }

class Responsive {
  const Responsive._();

  static const double mediumBreakpoint = 600;
  static const double expandedBreakpoint = 1024;

  /// Largeur utile maximale du contenu : au-delà, on centre plutôt que
  /// d'étirer des lignes de texte sur toute la dalle.
  static const double maxContentWidth = 1200;

  static ScreenSize of(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= expandedBreakpoint) {
      return ScreenSize.expanded;
    }
    if (width >= mediumBreakpoint) {
      return ScreenSize.medium;
    }
    return ScreenSize.compact;
  }

  static bool isTablet(BuildContext context) => of(context) != ScreenSize.compact;

  /// Nombre de colonnes d'une grille de cartes.
  static int gridColumns(BuildContext context) {
    return switch (of(context)) {
      ScreenSize.compact => 1,
      ScreenSize.medium => 2,
      ScreenSize.expanded => 3,
    };
  }

  /// Marge latérale : plus généreuse sur grand écran pour garder une mesure de
  /// lecture confortable.
  static double horizontalPadding(BuildContext context) {
    return switch (of(context)) {
      ScreenSize.compact => 20,
      ScreenSize.medium => 28,
      ScreenSize.expanded => 40,
    };
  }

  /// Le rail de navigation remplace la barre d'onglets dès la classe medium.
  static bool useNavigationRail(BuildContext context) => isTablet(context);
}
