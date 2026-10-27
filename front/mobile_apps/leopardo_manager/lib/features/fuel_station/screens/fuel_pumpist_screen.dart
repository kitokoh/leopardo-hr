import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/fuel_station/data/fuel_station_repository.dart';
import 'package:leopardo_manager/features/fuel_station/providers/fuel_pumpist_selection_provider.dart';
import 'package:leopardo_manager/features/fuel_station/providers/fuel_station_provider.dart';

/// Parcours pompiste FuelStation (FUEL-013, #5807 / #8585) : shift actif →
/// choix station → pompe → compteur → saisie de relevé → confirmation.
///
/// Ce qui change par rapport à la version gelée : les identifiants ne sont
/// plus codés en dur. La version précédente postait
/// `stationId: 0, pumpId: 0, meterId: 0` — le backend rejetait la requête
/// (`exists` sur une station inexistante) et le pompiste voyait une erreur
/// incompréhensible. La sélection est désormais guidée et sans état « prêt » :
/// le bouton d'envoi reste inactif tant que les trois identifiants ne sont pas
/// réels.
class FuelPumpistScreen extends ConsumerStatefulWidget {
  const FuelPumpistScreen({super.key});

  @override
  ConsumerState<FuelPumpistScreen> createState() => _FuelPumpistScreenState();
}

class _FuelPumpistScreenState extends ConsumerState<FuelPumpistScreen> {
  final _readingController = TextEditingController();
  String? _lastResult;
  bool _isAnomaly = false;
  bool _submitting = false;

  @override
  void dispose() {
    _readingController.dispose();
    super.dispose();
  }

  /// Les trois identifiants doivent être réels pour pouvoir envoyer.
  bool get _canSubmit {
    final stationId = ref.read(fuelSelectedStationProvider);
    final pumpId = ref.read(fuelSelectedPumpProvider);
    final meterId = ref.read(fuelSelectedMeterProvider);
    return !_submitting &&
        stationId != null &&
        pumpId != null &&
        meterId != null;
  }

  Future<void> _submitReading() async {
    final stationId = ref.read(fuelSelectedStationProvider);
    final pumpId = ref.read(fuelSelectedPumpProvider);
    final meterId = ref.read(fuelSelectedMeterProvider);

    // Garde de dernier recours : si un sélecteur a été remis à zéro entre le
    // rendu et le tap, on refuse proprement au lieu de poster des `0`.
    if (stationId == null || pumpId == null || meterId == null) {
      setState(() {
        _lastResult = context.l10n.fuelPumpistSelectRequired;
        _isAnomaly = true;
      });
      return;
    }

    final raw = _readingController.text.trim();
    final value = int.tryParse(raw);
    if (value == null || value < 0) {
      setState(() {
        _lastResult = 'Valeur invalide — saisir un entier positif.';
        _isAnomaly = true;
      });
      return;
    }

    setState(() {
      _submitting = true;
      _lastResult = null;
    });

    final idempotencyKey =
        'mob-${DateTime.now().microsecondsSinceEpoch.toRadixString(36)}';
    final input = FuelReadingInput(
      stationId: stationId,
      pumpId: pumpId,
      meterId: meterId,
      readingValueMinor: value,
      idempotencyKey: idempotencyKey,
    );

    try {
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
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isAnomaly = true;
        _lastResult = '${context.l10n.fuelPumpistReadingRejected} : $e';
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final shiftsAsync = ref.watch(fuelMyShiftsProvider);
    final stationsAsync = ref.watch(fuelStationsProvider);
    final pumpsAsync = ref.watch(fuelPumpsProvider);
    final metersAsync = ref.watch(fuelMetersProvider);

    final stationId = ref.watch(fuelSelectedStationProvider);
    final pumpId = ref.watch(fuelSelectedPumpProvider);
    final meterId = ref.watch(fuelSelectedMeterProvider);

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
        onRefresh: () async {
          await ref.refresh(fuelMyShiftsProvider.future);
          await ref.refresh(fuelStationsProvider.future);
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(20),
          children: [
            // ── Shift actif ────────────────────────────────────────────────
            Text(
              l10n.fuelPumpistActiveShift,
              style: AppTypography.bodySmall.copyWith(color: AppColors.textDark),
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
                                    Icon(Icons.schedule, color: AppColors.rh),
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
                                            style:
                                                AppTypography.caption.copyWith(
                                              color: AppColors.textMutedDark,
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

            // ── Sélection station → pompe → compteur ───────────────────────
            Text(
              l10n.fuelPumpSelectstation,
              style: AppTypography.bodySmall.copyWith(color: AppColors.textDark),
            ),
            const SizedBox(height: 8),
            stationsAsync.when(
              data: (stations) => _StationDropdown(
                stations: stations,
                value: stationId,
                onChanged: (value) {
                  // Changer de station invalide la pompe et le compteur :
                  // garder un `pumpId` d'une autre station produirait un 404.
                  ref.read(fuelSelectedStationProvider.notifier).state = value;
                  ref.read(fuelSelectedPumpProvider.notifier).state = null;
                  ref.read(fuelSelectedMeterProvider.notifier).state = null;
                },
              ),
              loading: () => const LinearProgressIndicator(),
              error: (e, _) => Text(
                '${l10n.fuelPumpistStationsError} : $e',
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              l10n.fuelPumpSelectpump,
              style: AppTypography.bodySmall.copyWith(color: AppColors.textDark),
            ),
            const SizedBox(height: 8),
            pumpsAsync.when(
              data: (pumps) => _PumpDropdown(
                pumps: pumps,
                value: pumpId,
                enabled: stationId != null,
                onChanged: (value) {
                  ref.read(fuelSelectedPumpProvider.notifier).state = value;
                  ref.read(fuelSelectedMeterProvider.notifier).state = null;
                },
              ),
              loading: () => const LinearProgressIndicator(),
              error: (e, _) => Text(
                '${l10n.fuelPumpistPumpsError} : $e',
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              l10n.fuelPumpSelectmeter,
              style: AppTypography.bodySmall.copyWith(color: AppColors.textDark),
            ),
            const SizedBox(height: 8),
            metersAsync.when(
              data: (meters) => _MeterDropdown(
                meters: meters,
                value: meterId,
                enabled: pumpId != null,
                onChanged: (value) {
                  ref.read(fuelSelectedMeterProvider.notifier).state = value;
                },
              ),
              loading: () => const LinearProgressIndicator(),
              error: (e, _) => Text(
                '${l10n.fuelPumpistMetersError} : $e',
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
            ),
            const SizedBox(height: 24),

            // ── Saisie du relevé ───────────────────────────────────────────
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
                onPressed: _canSubmit ? _submitReading : null,
                child: _submitting
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(l10n.fuelPumpistSubmit),
              ),
            ),
            if (_lastResult != null) ...[
              const SizedBox(height: 16),
              GlassCard(
                color: _isAnomaly ? AppColors.danger : AppColors.cardDark,
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

/// Liste déroulante des stations du parc.
class _StationDropdown extends StatelessWidget {
  const _StationDropdown({
    required this.stations,
    required this.value,
    required this.onChanged,
  });

  final List<FuelStationDto> stations;
  final int? value;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      initialValue: value,
      isExpanded: true,
      decoration: _decoration(),
      items: stations
          .map(
            (s) => DropdownMenuItem<int>(
              value: s.id,
              child: Text(s.label, overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: stations.isEmpty ? null : onChanged,
    );
  }
}

/// Liste déroulante des pompes de la station choisie.
class _PumpDropdown extends StatelessWidget {
  const _PumpDropdown({
    required this.pumps,
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  final List<FuelPumpDto> pumps;
  final int? value;
  final bool enabled;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      initialValue: value,
      isExpanded: true,
      decoration: _decoration(),
      hint: const Text('—'),
      items: pumps
          .map(
            (p) => DropdownMenuItem<int>(
              value: p.id,
              child: Text(p.label, overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: (!enabled || pumps.isEmpty) ? null : onChanged,
    );
  }
}

/// Liste déroulante des compteurs montés sur la pompe choisie.
class _MeterDropdown extends StatelessWidget {
  const _MeterDropdown({
    required this.meters,
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  final List<FuelMeterDto> meters;
  final int? value;
  final bool enabled;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      initialValue: value,
      isExpanded: true,
      decoration: _decoration(),
      hint: const Text('—'),
      items: meters
          .map(
            (m) => DropdownMenuItem<int>(
              value: m.id,
              child: Text(m.label, overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: (!enabled || meters.isEmpty) ? null : onChanged,
    );
  }
}

InputDecoration _decoration() {
  return InputDecoration(
    filled: true,
    fillColor: AppColors.cardDark,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide.none,
    ),
  );
}
