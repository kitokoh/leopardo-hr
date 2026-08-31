import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_booking.dart';
import 'package:leopardo_travel_agent/features/travel/providers/travel_providers.dart';

/// Ventes du guichet (GET /travel/bookings) — suivi des réservations du
/// jour (TRAVEL-701).
class BookingsScreen extends ConsumerWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final bookings = ref.watch(bookingsProvider);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('bookingsListTitle')),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: l10n.t('refresh'),
            onPressed: () => ref.invalidate(bookingsProvider),
          ),
        ],
      ),
      body: SafeArea(
        child: bookings.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: Text(
              l10n.t('loadError'),
              style: AppTypography.caption.copyWith(color: muted),
            ),
          ),
          data: (items) {
            if (items.isEmpty) {
              return Center(
                child: Text(
                  l10n.t('emptyBookings'),
                  style: AppTypography.caption.copyWith(color: muted),
                ),
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (context, index) =>
                  _BookingCard(booking: items[index]),
            );
          },
        ),
      ),
    );
  }
}

class _BookingCard extends ConsumerWidget {
  const _BookingCard({required this.booking});

  final TravelBooking booking;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final total = booking.totalAmountMinor ?? 0;

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    booking.reference ?? '',
                    style: AppTypography.subtitle.copyWith(color: text),
                  ),
                ),
                _StatusBadge(status: booking.status ?? ''),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              '${l10n.t('passengerCount')} : ${booking.passengerCount ?? 0}',
              style: AppTypography.caption.copyWith(color: muted),
            ),
            const SizedBox(height: 2),
            Text(
              '${l10n.t('totalAmount')} : '
              '${(total / 100).toStringAsFixed(2)} '
              '${booking.currency ?? ''}',
              style: AppTypography.caption.copyWith(color: text),
            ),
            if (booking.tickets.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                '${l10n.t('ticketsTitle')} : ${booking.tickets.length}',
                style: AppTypography.caption.copyWith(color: muted),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusBadge extends ConsumerWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final color = switch (status) {
      'confirmed' || 'completed' => AppColors.success,
      'pending' => AppColors.warning,
      'cancelled' || 'refunded' => AppColors.danger,
      _ => AppColors.textSecondaryFor(context),
    };
    final label = switch (status) {
      'pending' => l10n.t('bookingStatus_pending'),
      'confirmed' => l10n.t('bookingStatus_confirmed'),
      'cancelled' => l10n.t('bookingStatus_cancelled'),
      'refunded' => l10n.t('bookingStatus_refunded'),
      'completed' => l10n.t('bookingStatus_completed'),
      _ => status,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: AppTypography.caption.copyWith(color: color),
      ),
    );
  }
}
