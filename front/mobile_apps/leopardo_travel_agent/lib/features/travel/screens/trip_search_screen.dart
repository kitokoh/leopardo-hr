import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/data/travel_repository.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_trip.dart';
import 'package:leopardo_travel_agent/features/travel/providers/travel_providers.dart';

/// Recherche de trajets (GET /travel/trips/search) — filtres ville de
/// départ/arrivée + date (TRAVEL-701).
class TripSearchScreen extends ConsumerStatefulWidget {
  const TripSearchScreen({super.key});

  @override
  ConsumerState<TripSearchScreen> createState() => _TripSearchScreenState();
}

class _TripSearchScreenState extends ConsumerState<TripSearchScreen> {
  TravelCity? _origin;
  TravelCity? _destination;
  DateTime? _date;

  Future<void> _search() async {
    final repository = ref.read(travelRepositoryProvider);
    ref.read(tripSearchResultsProvider.notifier).search(
          repository: repository,
          originCityId: _origin?.id,
          destinationCityId: _destination?.id,
          departureDate:
              _date == null ? null : DateFormat('yyyy-MM-dd').format(_date!),
        );
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? now,
      firstDate: now.subtract(const Duration(days: 1)),
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked != null) {
      setState(() {
        _date = picked;
      });
    }
  }

  TravelCity? _cityById(List<TravelCity> items, int? id) {
    for (final city in items) {
      if (city.id == id) {
        return city;
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final cities = ref.watch(citiesProvider);
    final results = ref.watch(tripSearchResultsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('tripSearchTitle'))),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  cities.when(
                    loading: () => const LinearProgressIndicator(),
                    error: (e, _) => Text(
                      l10n.t('loadError'),
                      style: AppTypography.caption.copyWith(
                        color: AppColors.danger,
                      ),
                    ),
                    data: (items) => Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<int?>(
                            initialValue: _origin?.id,
                            decoration: InputDecoration(
                              labelText: l10n.t('origin'),
                              border: const OutlineInputBorder(),
                              isDense: true,
                            ),
                            items: [
                              const DropdownMenuItem<int?>(
                                value: null,
                                child: Text('—'),
                              ),
                              ...items.map(
                                (city) => DropdownMenuItem<int?>(
                                  value: city.id,
                                  child: Text(
                                    city.name ?? '',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ),
                            ],
                            onChanged: (id) {
                              setState(() {
                                _origin = _cityById(items, id);
                              });
                            },
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: DropdownButtonFormField<int?>(
                            initialValue: _destination?.id,
                            decoration: InputDecoration(
                              labelText: l10n.t('destination'),
                              border: const OutlineInputBorder(),
                              isDense: true,
                            ),
                            items: [
                              const DropdownMenuItem<int?>(
                                value: null,
                                child: Text('—'),
                              ),
                              ...items.map(
                                (city) => DropdownMenuItem<int?>(
                                  value: city.id,
                                  child: Text(
                                    city.name ?? '',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ),
                            ],
                            onChanged: (id) {
                              setState(() {
                                _destination = _cityById(items, id);
                              });
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _pickDate,
                          icon: const Icon(Icons.calendar_today, size: 18),
                          label: Text(
                            _date == null
                                ? l10n.t('departureDate')
                                : DateFormat.yMMMd(
                                    AppStrings.of(
                                      ref
                                          .watch(appPreferencesProvider)
                                          .preferredLanguage,
                                    ).locale,
                                  ).format(_date!),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      FilledButton.icon(
                        onPressed: _search,
                        icon: const Icon(Icons.search),
                        label: Text(l10n.t('search')),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: results.loading
                  ? const Center(child: CircularProgressIndicator())
                  : results.error
                      ? _Message(
                          icon: Icons.error_outline,
                          label: l10n.t('loadError'),
                          onRetry: _search,
                        )
                      : results.trips.isEmpty
                          ? _Message(
                              icon: Icons.search_off,
                              label: l10n.t('noTrips'),
                            )
                          : ListView.separated(
                              padding: const EdgeInsets.all(12),
                              itemCount: results.trips.length,
                              separatorBuilder: (context, index) =>
                                  const SizedBox(height: 8),
                              itemBuilder: (context, index) =>
                                  _TripCard(trip: results.trips[index]),
                            ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TripCard extends ConsumerWidget {
  const _TripCard({required this.trip});

  final TravelTrip trip;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    final origin = trip.originLabel ?? l10n.t('unknown');
    final destination = trip.destinationLabel ?? l10n.t('unknown');
    final price = trip.firstAdultPriceMinor;
    final currency = trip.currency ?? '';

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => context.push('/trip/${trip.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$origin → $destination',
                      style: AppTypography.subtitle.copyWith(color: text),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${trip.departureDate} · ${trip.departureTime ?? ''}'
                      ' · ${trip.status ?? ''}',
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                ),
              ),
              if (price != null)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '${(price / 100).toStringAsFixed(2)} $currency',
                      style: AppTypography.subtitle.copyWith(
                        color: AppColors.success,
                      ),
                    ),
                    Text(
                      l10n.t('priceAdult'),
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Message extends ConsumerWidget {
  const _Message({
    required this.icon,
    required this.label,
    this.onRetry,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final muted = AppColors.textSecondaryFor(context);
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 42, color: muted),
          const SizedBox(height: 8),
          Text(label, style: AppTypography.caption.copyWith(color: muted)),
          if (onRetry != null) ...[
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: onRetry,
              child: Text(l10n.t('retry')),
            ),
          ],
        ],
      ),
    );
  }
}
