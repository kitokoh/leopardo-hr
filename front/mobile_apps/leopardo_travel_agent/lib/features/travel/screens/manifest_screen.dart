import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/providers/travel_providers.dart';

/// Manifeste d'un trajet (GET /travel/trips/{id}/manifest) — liste des
/// passagers triée par siège (TRAVEL-701).
class ManifestScreen extends ConsumerStatefulWidget {
  const ManifestScreen({super.key});

  @override
  ConsumerState<ManifestScreen> createState() => _ManifestScreenState();
}

class _ManifestScreenState extends ConsumerState<ManifestScreen> {
  final _tripIdController = TextEditingController();

  @override
  void dispose() {
    _tripIdController.dispose();
    super.dispose();
  }

  void _load() {
    final tripId = int.tryParse(_tripIdController.text.trim());
    if (tripId == null) {
      return;
    }
    ref.invalidate(manifestProvider(tripId));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final tripId = int.tryParse(_tripIdController.text.trim());
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('manifestTitle'))),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _tripIdController,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(
                        labelText: l10n.t('tripCode'),
                        hintText: 'ID',
                        border: const OutlineInputBorder(),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  FilledButton.icon(
                    onPressed: _load,
                    icon: const Icon(Icons.receipt_long_outlined),
                    label: Text(l10n.t('search')),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: tripId == null
                  ? Center(
                      child: Text(
                        l10n.t('noData'),
                        style: AppTypography.caption.copyWith(color: muted),
                      ),
                    )
                  : ref.watch(manifestProvider(tripId)).when(
                        loading: () => const Center(
                          child: CircularProgressIndicator(),
                        ),
                        error: (e, _) => Center(
                          child: Text(
                            l10n.t('loadError'),
                            style: AppTypography.caption.copyWith(
                              color: AppColors.danger,
                            ),
                          ),
                        ),
                        data: (passengers) {
                          if (passengers.isEmpty) {
                            return Center(
                              child: Text(
                                l10n.t('noPassengers'),
                                style: AppTypography.caption.copyWith(
                                  color: muted,
                                ),
                              ),
                            );
                          }
                          return ListView.separated(
                            padding: const EdgeInsets.all(12),
                            itemCount: passengers.length,
                            separatorBuilder: (context, index) =>
                                const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final passenger = passengers[index];
                              return Card(
                                elevation: 0,
                                color: AppColors.surfaceFor(context),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: ListTile(
                                  dense: true,
                                  leading: CircleAvatar(
                                    backgroundColor:
                                        AppColors.rh.withValues(alpha: 0.15),
                                    child: Text(
                                      (index + 1).toString(),
                                      style: AppTypography.caption.copyWith(
                                        color: AppColors.rhDark,
                                      ),
                                    ),
                                  ),
                                  title: Text(
                                    passenger.fullName ?? '',
                                    style: AppTypography.body.copyWith(
                                      color: text,
                                    ),
                                  ),
                                  subtitle: Text(
                                    '${l10n.t('seat')} : '
                                    '${passenger.seatNumber ?? '—'}',
                                    style: AppTypography.caption.copyWith(
                                      color: muted,
                                    ),
                                  ),
                                ),
                              );
                            },
                          );
                        },
                      ),
            ),
          ],
        ),
      ),
    );
  }
}
