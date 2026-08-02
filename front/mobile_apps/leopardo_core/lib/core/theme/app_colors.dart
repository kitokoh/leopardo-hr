import 'package:flutter/material.dart';

/// APV L.05 â€” Couleur = domaine. L.07 â€” Grille partagee.
///
/// Source de verite cote mobile des couleurs Leopardo RH.
/// Toute modification doit etre repercutee dans :
///   - la couche de tokens web
///   - docs/REFERENTIEL_PRODUIT/COULEURS.md (doc)
///
/// Jamais de valeur hex hardcodee dans les ecrans : toujours passer par
/// AppColors.* pour garder l'alignement mobile <-> web.
class AppColors {
  AppColors._();

  // â”€â”€â”€ Domaines (immuables une fois publies) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  /// RH â€” module de base, toujours actif.
  static const Color rh = Color(0xFF10B981); // emerald-500
  static const Color rhLight = Color(0xFFD1FAE5); // emerald-100
  static const Color rhDark = Color(0xFF047857); // emerald-700

  /// Finance â€” Phase 2, activable par company.
  static const Color finance = Color(0xFFF59E0B); // amber-500
  static const Color financeLight = Color(0xFFFEF3C7); // amber-100
  static const Color financeDark = Color(0xFFB45309); // amber-700

  /// Securite / Cameras â€” Phase 2.
  static const Color security = Color(0xFF3B82F6); // blue-500
  static const Color securityLight = Color(0xFFDBEAFE); // blue-100
  static const Color securityDark = Color(0xFF1D4ED8); // blue-700

  /// Leo IA / Intelligence â€” Phase 2.
  static const Color ia = Color(0xFF7C3AED); // violet-600
  static const Color iaLight = Color(0xFFEDE9FE); // violet-100
  static const Color iaDark = Color(0xFF5B21B6); // violet-800

  /// Cabinet numerique â€” documents et dossiers.
  static const Color cabinet = Color(0xFF8B6914); // gold/amber
  static const Color cabinetLight = Color(0xFFFEF3C7); // amber-100
  static const Color cabinetDark = Color(0xFF6B4F10); // darker gold

  // â”€â”€â”€ Semantique (alerte / succes / info) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);

  // â”€â”€â”€ Neutres â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  static const Color bgLight = Color(0xFFFFFFFF);
  static const Color cardLight = Color(0xFFF8FAFC); // slate-50
  static const Color borderLight = Color(0xFFE2E8F0); // slate-200

  static const Color border = Color(
    0xFFE2E8F0,
  ); // slate-200 (alias de borderLight)

  static const Color bgDark = Color(0xFF0F172A); // slate-900
  static const Color cardDark = Color(0xFF1E293B); // slate-800
  static const Color borderDark = Color(0xFF334155); // slate-700

  static const Color textLight = Color(0xFF0F172A); // slate-900 sur bg clair
  static const Color textMuted = Color(0xFF64748B); // slate-500 (muted clair)
  static const Color textDark = Color(0xFFF1F5F9); // slate-100 sur bg sombre
  static const Color textMutedDark = Color(
    0xFF94A3B8,
  ); // slate-400 (muted sombre)

  /// @Deprecated Utilisez [textDark]. Conserve pour compat avec [AppTheme.textPrimary].
  static const Color textOnDark = textDark;

  // â”€â”€â”€ Palette mobile sombre "pointage" (PA2-MOB-011) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // Ecrans attendance/ + smart_attendance/ + widgets core partages sur les 3
  // apps mobiles (employee/manager/hr) redefinissaient chacun les memes
  // Color(0x...) litteraux au lieu de partager une seule source de verite.
  // Ces tokens couvrent le theme sombre dedie "pointage" utilise par ces
  // ecrans (distinct de [bgDark]/[cardDark]/[borderDark] generiques ci-dessus,
  // qui restent le theme sombre par defaut de l'app). Toute nouvelle valeur
  // hex sur un ecran de pointage doit etre ajoutee ici plutot que hardcodee.
  static const Color mobileDarkBg = Color(0xFF0B1326);
  static const Color mobileDarkSurface = Color(0xFF171F33);
  static const Color mobileDarkSurfaceAlt = Color(0xFF2A3C5A);
  static const Color mobileDarkField = Color(0xFF0C1525);
  static const Color mobileDarkFieldAlt = Color(0xFF1E3050);
  static const Color mobileDarkBorder = Color(0xFF1A2B44);
  static const Color mobileDarkText = Color(0xFFE2EAF6);
  static const Color mobileDarkTextSoft = Color(0xFFC8D8F0);
  static const Color mobileDarkMuted = Color(0xFF7A9CC0);
  static const Color mobileDarkMutedAlt = Color(0xFF8EA9C8);
  static const Color mobileDarkSecondary = Color(0xFFB8C7DA);
  static const Color mobileDarkDisabled = Color(0xFF6F86A5);

  // Accents statut ecrans pointage (valeurs historiques distinctes des
  // couleurs semantiques generiques success/warning/danger/info ci-dessus ;
  // conservees a l'identique pour ne pas changer le rendu visuel existant).
  static const Color mobileAccentBlue = Color(0xFF2196F3);
  static const Color mobileAccentSkyBlue = Color(0xFF38BDF8);
  static const Color mobileAccentGreen = Color(0xFF4CAF50);
  static const Color mobileAccentPurple = Color(0xFF9C27B0);
  static const Color mobileAccentRed = Color(0xFFF44336);
  static const Color mobileAccentRedLight = Color(0xFFEF5350);
  static const Color mobileAccentRedSoft = Color(0xFFEF9A9A);
  static const Color mobileAccentOrange = Color(0xFFFFA726);
  static const Color mobileAccentGrey = Color(0xFF607D8B);
  static const Color mobileAccentTeal = Color(0xFF14B8A6);

  // Degrades bouton de pointage (PulseButton) entree/sortie.
  static const Color mobilePunchInGradientStart = Color(0xFF0D5C3A);
  static const Color mobilePunchOutGradientStart = Color(0xFFB91C1C);
  static const Color mobilePunchOutGradientEnd = Color(0xFF7F1D1D);

  static bool isDark(BuildContext context) =>
      Theme.of(context).brightness == Brightness.dark;

  static Color backgroundFor(BuildContext context) =>
      isDark(context) ? bgDark : bgLight;

  static Color surfaceFor(BuildContext context) =>
      isDark(context) ? cardDark : cardLight;

  static Color borderFor(BuildContext context) =>
      isDark(context) ? borderDark : borderLight;

  static Color textPrimaryFor(BuildContext context) =>
      isDark(context) ? textDark : textLight;

  static Color textSecondaryFor(BuildContext context) =>
      isDark(context) ? textMutedDark : textMuted;

  static Color tint(
    BuildContext context,
    Color color, {
    double lightAlpha = 0.12,
    double darkAlpha = 0.18,
  }) {
    return color.withValues(alpha: isDark(context) ? darkAlpha : lightAlpha);
  }

  // â”€â”€â”€ Helpers domaine â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  /// Retourne la couleur principale d'un domaine module.
  static Color forDomain(String domain) {
    switch (domain) {
      case 'rh':
        return rh;
      case 'finance':
        return finance;
      case 'security':
      case 'cameras':
        return security;
      case 'ia':
      case 'leo_ai':
        return ia;
      case 'cabinet':
      case 'documents':
        return cabinet;
      default:
        return textMuted;
    }
  }

  /// Retourne la couleur claire (badge/fond) d'un domaine.
  static Color forDomainLight(String domain) {
    switch (domain) {
      case 'rh':
        return rhLight;
      case 'finance':
        return financeLight;
      case 'security':
      case 'cameras':
        return securityLight;
      case 'ia':
      case 'leo_ai':
        return iaLight;
      case 'cabinet':
      case 'documents':
        return cabinetLight;
      default:
        return borderLight;
    }
  }

  /// Couleur semantique pour un statut de pointage / invitation / employe.
  /// Voir docs/STATUTS.md pour la table complete.
  static Color forStatus(String status) {
    switch (status) {
      case 'present':
      case 'accepted':
      case 'active':
      case 'enabled':
        return success;
      case 'late':
      case 'early_leave':
      case 'pending':
      case 'trial':
      case 'suspended':
        return warning;
      case 'absent':
      case 'expired':
        return danger;
      case 'half_day':
      case 'holiday':
      case 'weekend':
      case 'sent':
      case 'on_leave':
        return info;
      case 'archived':
      case 'revoked':
      case 'disabled':
      default:
        return textMuted;
    }
  }
}

