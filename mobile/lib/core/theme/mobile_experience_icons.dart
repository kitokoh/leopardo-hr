import 'package:flutter/material.dart';

class MobileExperienceIcons {
  MobileExperienceIcons._();

  static IconData forModule(String key) {
    switch (key) {
      case 'attendance':
        return Icons.fingerprint;
      case 'absences':
        return Icons.event_note_outlined;
      case 'salary_advances':
        return Icons.payments_outlined;
      case 'payrolls':
        return Icons.receipt_long_outlined;
      case 'evaluations':
        return Icons.insights_outlined;
      case 'notifications':
        return Icons.notifications_active_outlined;
      case 'team':
        return Icons.groups_2_outlined;
      case 'finance':
        return Icons.account_balance_wallet_outlined;
      case 'cameras':
        return Icons.shield_outlined;
      case 'cabinet':
        return Icons.door_sliding_outlined;
      case 'leo_ai':
        return Icons.auto_awesome;
      default:
        return Icons.dashboard_customize_outlined;
    }
  }

  static IconData forAction(String key, String icon) {
    switch (icon) {
      case 'fingerprint':
        return Icons.fingerprint;
      case 'stacked_bar_chart':
        return Icons.stacked_bar_chart;
      case 'history':
        return Icons.history;
      case 'dashboard_customize':
        return Icons.dashboard_customize_outlined;
      case 'group':
        return Icons.groups_2_outlined;
      case 'settings':
        return Icons.settings_outlined;
      default:
        return forModule(key);
    }
  }
}
