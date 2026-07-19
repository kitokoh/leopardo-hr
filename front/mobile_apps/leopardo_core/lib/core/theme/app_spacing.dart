/// APV Design v4 — Échelle d'espacement et de rayons partagée.
///
/// Objectif : arrêter les valeurs "magiques" (12, 14, 16, 18, 20, 24...)
/// dispersées dans les écrans mobiles. Chaque nouvel écran doit utiliser
/// [AppSpacing] et [AppRadius] pour garder une grille cohérente entre
/// Employee / Manager / HR / Platform Admin — condition nécessaire pour
/// qu'une démo commerciale paraisse "un seul produit" et pas quatre
/// prototypes assemblés.
class AppSpacing {
  AppSpacing._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
}

class AppRadius {
  AppRadius._();

  /// Chips, pills, badges de statut.
  static const double pill = 999;

  /// Petits éléments : inputs, boutons secondaires compacts.
  static const double sm = 12;

  /// Boutons standards, champs de formulaire.
  static const double md = 16;

  /// Cartes de contenu (liste, module, résumé).
  static const double lg = 18;

  /// Grandes surfaces héro (en-tête d'accueil, bottom sheets).
  static const double xl = 24;
}
