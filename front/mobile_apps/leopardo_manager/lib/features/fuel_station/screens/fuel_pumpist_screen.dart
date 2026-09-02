import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/fuel_station/data/fuel_station_repository.dart';
import 'package:leopardo_manager/features/fuel_station/providers/fuel_station_provider.dart';

/// Parcours pompiste FuelStation (FUEL-013, #5807) : shift actif → saisie
/// de relevé → confirmation / feedback anomalie. Champs stricts, saisie en
/// quelques secondes, clé d'idempotence générée côté client (rejeu sûr).
class FuelPumpistScreen extends ConsumerStatefulWidget {
  const FuelPumpistScreen({super.key});

  @override
  ConsumerState<FuelPumpistScreen> createState() => _FuelPumpistScreenState();
}

class _FuelPumpistScreenState extends ConsumerState<FuelPumpistScreen> {
  final _readingController = TextEditingController();
  String? _lastResult;
  bool _isAnomaly = false;

  @override
  void dispose() {
    _readingController.dispose();
    super.dispose();
  }

  Future<void> _submitReading() async {
    final raw = _readingController.text.trim();
    final value = int.tryParse(raw);
    if (value == null || value < 0) {
      setState(() {
        _lastResult = 'Valeur invalide — saisir un entier positif.';
        _isAnomaly = true;
      });
      return;
    }

    final idempotencyKey =
        'mob-${DateTime.now().microsecondsSinceEpoch.toRadixString(36)}';
    final input = FuelReadingInput(
      stationId: 0, // sélectionné depuis le shift actif (mapping id)
      pumpId: 0,
      meterId: 0,
      readingValueMinor: value,
      idempotencyKey: idempotencyKey,
    );

    final result = await ref.read(fuelRecordReadingProvider(input).future);
    if (!mounted) return;

    setState(() {
      _isAnomaly = result.isAnomaly;
      _lastResult = result.replayed
          ? 'Relevé déjà enregistré (rejeu) — aucun doublon.'
          : result.isAnomaly
          ? 'Anomalie détectée (delta ${result.deltaMinor}) — signalée au manager.'
          : 'Relevé enregistré (delta ${result.deltaMinor}).';
    });
    _readingController.clear();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final shiftsAsync = ref.watch(fuelMyShiftsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          l10n.fuelPumpistTitle,
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: l10n.fuelPumpistBackTooltip,
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => await ref.refresh(fuelMyShiftsProvider.future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              l10n.fuelPumpistActiveShift,
              style: AppTypography.label.copyWith(color: AppColors.textDark),
            ),
            const SizedBox(height: 8),
            shiftsAsync.when(
              data: (shifts) => shifts.isEmpty
                  ? EmptyState(
                      icon: Icons.local_gas_station_outlined,
                      title: l10n.fuelPumpistNoShiftTitle,
                      description: l10n.fuelPumpistNoShiftDescription,
                    )
                  : Column(
                      children: shifts
                          .map(
                            (s) => GlassCard(
                              color: AppColors.cardDark,
                              margin: const EdgeInsets.only(bottom: 12),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Row(
                                  children: [
                                    Icon(
                                      Icons.schedule,
                                      color: AppColors.accentDark,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            s.shiftName ?? '#${s.id}',
                                            style: AppTypography.body.copyWith(
                                              color: AppColors.textDark,
                                            ),
                                          ),
                                          Text(
                                            s.assignmentDate ?? '',
                                            style: AppTypography.caption
                                                .copyWith(
                                                  color:
                                                      AppColors.textMutedDark,
                                                ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          )
                          .toList(),
                    ),
              loading: () => const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: CircularProgressIndicator(),
                ),
              ),
              error: (e, _) => EmptyState(
                icon: Icons.error_outline,
                title: l10n.fuelPumpistErrorTitle,
                description: '$e',
              ),
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _readingController,
              keyboardType: TextInputType.number,
              style: const TextStyle(color: AppColors.textDark),
              decoration: InputDecoration(
                labelText: l10n.fuelPumpistReadingLabel,
                hintText: l10n.fuelPumpistReadingHint,
                filled: true,
                fillColor: AppColors.cardDark,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _submitReading,
                child: Text(l10n.fuelPumpistSubmit),
              ),
            ),
            if (_lastResult != null) ...[
              const SizedBox(height: 16),
              GlassCard(
                color: _isAnomaly ? AppColors.dangerDark : AppColors.cardDark,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    _lastResult!,
                    style: AppTypography.body.copyWith(
                      color: AppColors.textDark,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
