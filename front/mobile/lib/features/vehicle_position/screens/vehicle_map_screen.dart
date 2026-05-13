import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/vehicle_position/providers/vehicle_position_provider.dart';

class VehicleMapScreen extends ConsumerWidget {
  const VehicleMapScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final vehiclesAsync = ref.watch(myVehiclesProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Position Vehicule',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => await ref.refresh(myVehiclesProvider.future),
        child: vehiclesAsync.when(
          data: (vehicles) => vehicles.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: const [
                    SizedBox(height: 80),
                    EmptyState(
                      icon: Icons.directions_car_outlined,
                      title: 'Aucun vehicule',
                      description:
                          'Vos vehicules assignes apparaitront ici.',
                    ),
                  ],
                )
              : ListView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(20),
                  itemCount: vehicles.length,
                  itemBuilder: (context, index) {
                    final v = vehicles[index];
                    return Card(
                      color: AppColors.cardDark,
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(
                                  Icons.directions_car,
                                  color: AppColors.info,
                                  size: 20,
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  v.plateNumber,
                                  style: AppTypography.subtitle.copyWith(
                                    color: AppColors.textDark,
                                  ),
                                ),
                              ],
                            ),
                            if (v.brand != null || v.model != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                '${v.brand ?? ""} ${v.model ?? ""}'.trim(),
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.textMutedDark,
                                ),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                const Icon(
                                  Icons.location_on,
                                  color: AppColors.success,
                                  size: 14,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '${v.latitude.toStringAsFixed(5)}, ${v.longitude.toStringAsFixed(5)}',
                                  style: AppTypography.bodySmall.copyWith(
                                    color: AppColors.textMutedDark,
                                  ),
                                ),
                                if (v.speed != null) ...[
                                  const SizedBox(width: 16),
                                  const Icon(
                                    Icons.speed,
                                    color: AppColors.warning,
                                    size: 14,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    '${v.speed!.toStringAsFixed(0)} km/h',
                                    style: AppTypography.bodySmall.copyWith(
                                      color: AppColors.textMutedDark,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            if (v.updatedAt != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                'Derniere MAJ: ${v.updatedAt}',
                                style: TextStyle(
                                  color: AppColors.textMutedDark,
                                  fontSize: 10,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    );
                  },
                ),
          loading: () => const SingleChildScrollView(
            physics: AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: 400,
              child: Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Chargement des vehicules...',
                ),
              ),
            ),
          ),
          error: (e, _) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: 400,
              child: Center(
                child:
                    Text(e.toString(), style: const TextStyle(color: Colors.red)),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
