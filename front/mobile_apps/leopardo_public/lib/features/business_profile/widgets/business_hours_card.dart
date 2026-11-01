import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../models/business_profile.dart';

/// Carte des horaires de la fiche.
///
/// ## Le jour courant est mis en avant, pas trié en premier
///
/// Réordonner pour placer « aujourd'hui » en haut désorienterait : la personne
/// lit un tableau hebdomadaire. On marque donc la ligne du jour d'un fond et
/// d'un libellé d'appoint, sans toucher à l'ordre.
///
/// ## Jours fermés
///
/// Un jour sans créneau est affiché « — », jamais omis : une semaine de 6 lignes
/// pour 7 jours laisse croire à une donnée manquante.
class BusinessHoursCard extends StatelessWidget {
  const BusinessHoursCard({
    super.key,
    required this.strings,
    required this.hours,
  });

  final AppStrings strings;
  final List<BusinessHour> hours;

  /// Ordre d'affichage ISO : lundi → dimanche (0 = dimanche côté serveur).
  static const List<int> _displayOrder = [1, 2, 3, 4, 5, 6, 0];

  @override
  Widget build(BuildContext context) {
    final today = DateTime.now().weekday % 7;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          strings.t('businessHours'),
          style: AppTypography.title.copyWith(fontSize: 15),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: AppColors.surfaceFor(context),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.borderFor(context)),
          ),
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Column(
            children: [
              for (final day in _displayOrder)
                _Row(
                  strings: strings,
                  day: day,
                  isToday: day == today,
                  slots: hours.where((h) => h.dayOfWeek == day).toList(),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({
    required this.strings,
    required this.day,
    required this.isToday,
    required this.slots,
  });

  final AppStrings strings;
  final int day;
  final bool isToday;
  final List<BusinessHour> slots;

  @override
  Widget build(BuildContext context) {
    // Libellés de jours : 7 × 4 locales = 28 chaînes. Elles restent LOCALES au
    // widget car ce sont des noms communs immuables, pas du contenu éditorial
    // — l'éditeur de messages du Superadmin n'a rien à y faire.
    final label = _dayLabel(strings.locale, day);

    final value = slots.isEmpty
        ? '—'
        : slots
            .map((s) => '${_hhmm(s.opensAt)} – ${_hhmm(s.closesAt)}')
            .join(', ');

    return Container(
      color: isToday
          ? AppColors.tint(context, AppColors.info, lightAlpha: 0.10)
          : null,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textPrimaryFor(context),
                fontWeight: isToday ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ),
          Text(
            value,
            style: AppTypography.bodySmall.copyWith(
              color: slots.isEmpty
                  ? AppColors.textSecondaryFor(context)
                  : AppColors.textPrimaryFor(context),
              fontWeight: isToday ? FontWeight.w600 : FontWeight.w400,
            ),
          ),
        ],
      ),
    );
  }

  /// `"09:30:00"` → `"09:30"`. Une valeur absente devient `—` — on n'affiche
  /// jamais `null – null`.
  static String _hhmm(String? raw) {
    if (raw == null || raw.isEmpty) return '—';
    final parts = raw.split(':');
    if (parts.length < 2) return raw;
    return '${parts[0].padLeft(2, '0')}:${parts[1].padLeft(2, '0')}';
  }

  static String _dayLabel(String locale, int day) {
    const fr = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    const en = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const ar = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];
    const tr = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

    final table = switch (locale) {
      'en' => en,
      'ar' => ar,
      'tr' => tr,
      _ => fr,
    };

    return table[day % 7];
  }
}
