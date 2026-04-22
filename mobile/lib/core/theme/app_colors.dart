import 'package:flutter/material.dart';

/// APV L.05 — Couleur = domaine. L.07 — Grille partagee.
///
/// Source de verite cote mobile des couleurs Leopardo RH.
/// Toute modification doit etre repercutee dans :
///   - api/tailwind.config.js (web)
///   - docs/COULEURS.md (doc)
///
/// Jamais de valeur hex hardcodee dans les ecrans : toujours passer par
/// AppColors.* pour garder l'alignement mobile <-> web.
class AppColors {
  AppColors._();

  // ─── Domaines (immuables une fois publies) ──────────────────────────────
  /// RH — module de base, toujours actif.
  static const Color rh = Color(0xFF10B981); // emerald-500
  static const Color rhLight = Color(0xFFD1FAE5); // emerald-100
  static const Color rhDark = Color(0xFF047857); // emerald-700

  /// Finance — Phase 2, activable par company.
  static const Color finance = Color(0xFFF59E0B); // amber-500
  static const Color financeLight = Color(0xFFFEF3C7); // amber-100
  static const Color financeDark = Color(0xFFB45309); // amber-700

  /// Securite / Cameras — Phase 2.
  static const Color security = Color(0xFF3B82F6); // blue-500
  static const Color securityLight = Color(0xFFDBEAFE); // blue-100
  static const Color securityDark = Color(0xFF1D4ED8); // blue-700

  /// Leo IA / Intelligence — Phase 2.
  static const Color ia = Color(0xFF7C3AED); // violet-600
  static const Color iaLight = Color(0xFFEDE9FE); // violet-100
  static const Color iaDark = Color(0xFF5B21B6); // violet-800

  // ─── Semantique (alerte / succes / info) ────────────────────────────────
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);

  // ─── Neutres ─────────────────────────────────────────────────────────────
  static const Color bgLight = Color(0xFFFFFFFF);
  static const Color cardLight = Color(0xFFF8FAFC); // slate-50
  static const Color borderLight = Color(0xFFE2E8F0); // slate-200

  static const Color border = Color(0xFFE2E8F0); // slate-200 (alias de borderLight)

  static const Color bgDark = Color(0xFF0F172A); // slate-900
  static const Color cardDark = Color(0xFF1E293B); // slate-800
  static const Color borderDark = Color(0xFF334155); // slate-700

  static const Color textLight = Color(0xFF0F172A); // slate-900 sur bg clair
  static const Color textMuted = Color(0xFF64748B); // slate-500 (muted clair)
  static const Color textDark = Color(0xFFF1F5F9); // slate-100 sur bg sombre
  static const Color textMutedDark = Color(0xFF94A3B8); // slate-400 (muted sombre)

  /// @Deprecated Utilisez [textDark]. Conserve pour compat avec [AppTheme.textPrimary].
  static const Color textOnDark = textDark;

  // ─── Helpers domaine ────────────────────────────────────────────────────
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
