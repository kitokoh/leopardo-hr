import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_trip.dart';
import 'package:leopardo_travel_agent/features/travel/providers/travel_providers.dart';

/// Détail d'un trajet (GET /travel/trips/{id}) — infos, tarifs et accès à
/// la vente guichet (TRAVEL-701).
class TripDetailScreen extends ConsumerWidget {
  const TripDetailScreen({required this.tripId});

  final int tripId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final tripState = ref.watch(tripProvider(tripId));
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('tripDetailTitle'))),
      body: SafeArea(
        child: tripState.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.t('loadError'),
                  style: AppTypography.caption.copyWith(color: muted),
                ),
                const SizedBox(height: 12),
                OutlinedButton(
                  onPressed: () => ref.invalidate(tripProvider(tripId)),
                  child: Text(l10n.t('retry')),
                ),
              ],
            ),
          ),
          data: (trip) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _InfoRow(label: l10n.t('tripCode'), value: trip.code),
              _InfoRow(
                label: l10n.t('from'),
                value: trip.originLabel ?? l10n.t('unknown'),
              ),
              _InfoRow(
                label: l10n.t('to'),
                value: trip.destinationLabel ?? l10n.t('unknown'),
              ),
              _InfoRow(
                label: l10n.t('date'),
                value: trip.departureDate,
              ),
              _InfoRow(
                label: l10n.t('time'),
                value: trip.departureTime ?? '',
              ),
              _InfoRow(
                label: l10n.t('meansOfTransport'),
                value: trip.meansOfTransport ?? '',
              ),
              _InfoRow(
                label: l10n.t('totalSeats'),
                value: trip.totalSeats?.toString() ?? '',
              ),
              _InfoRow(
                label: l10n.t('status'),
                value: _tripStatusLabel(l10n, trip.status),
              ),
              if (trip.prices.isNotEmpty) ...[
                const SizedBox(height: 16),
                Text(
                  l10n.t('priceAdult'),
                  style: AppTypography.subtitle.copyWith(color: text),
                ),
                const SizedBox(height: 8),
                ...trip.prices.map(
                  (price) => Card(
                    elevation: 0,
                    color: AppColors.surfaceFor(context),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: ListTile(
                      dense: true,
                      title: Text(
                        '${l10n.t('priceAdult')} : '
                        '${_minorToAmount(price.adultPriceMinor)} '
                        '${price.currency ?? ''}',
                        style: AppTypography.body.copyWith(color: text),
                      ),
                      subtitle: Text(
                        '${l10n.t('priceChild')} : '
                        '${_minorToAmount(price.childPriceMinor)} '
                        '${price.currency ?? ''}',
                        style: AppTypography.caption.copyWith(color: muted),
                      ),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: () => context.push('/trip/$tripId/sell'),
                icon: const Icon(Icons.point_of_sale),
                label: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(l10n.t('sell')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _tripStatusLabel(AppStrings l10n, String? status) {
    switch (status) {
      case 'scheduled':
      case 'published':
        return l10n.t('tripStatus_$status');
      case 'cancelled':
        return l10n.t('tripStatus_cancelled');
      case 'completed':
        return l10n.t('tripStatus_completed');
      default:
        return status ?? '';
    }
  }

  static String _minorToAmount(int? minor) {
    if (minor == null) {
      return '0';
    }
    return (minor / 100).toStringAsFixed(2);
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final muted = AppColors.textSecondaryFor(context);
    final text = AppColors.textPrimaryFor(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(
              label,
              style: AppTypography.caption.copyWith(color: muted),
            ),
          ),
          Expanded(
            child: Text(value, style: AppTypography.body.copyWith(color: text)),
          ),
        ],
      ),
    );
  }
}
