import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/core/widgets/shimmer_loading.dart';
import 'package:leopardo_rh/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

class HistoryScreen extends ConsumerStatefulWidget {
  const HistoryScreen({super.key});

  @override
  ConsumerState<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends ConsumerState<HistoryScreen> {
  final ScrollController _scrollController = ScrollController();
  bool _isLoadingMore = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >=
              _scrollController.position.maxScrollExtent &&
          !_isLoadingMore) {
        setState(() => _isLoadingMore = true);
        Future.delayed(const Duration(seconds: 1), () {
          if (mounted) setState(() => _isLoadingMore = false);
        });
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final historyAsync = ref.watch(
      historyProvider(DateTime(now.year, now.month)),
    );

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        title: const Text('Historique'),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Parametres',
            onPressed: () => context.push('/settings'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async =>
            ref.refresh(historyProvider(DateTime(now.year, now.month)).future),
        child: historyAsync.when(
          loading: () => ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: 6,
            separatorBuilder: (_, __) => const SizedBox(height: 16),
            itemBuilder: (_, __) => Row(
              children: [
                const ShimmerLoading(width: 40, height: 40, borderRadius: 20),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      ShimmerLoading(width: 100, height: 16),
                      SizedBox(height: 8),
                      ShimmerLoading(width: double.infinity, height: 16),
                    ],
                  ),
                ),
              ],
            ),
          ),
          error: (err, stack) {
            final errorText = err.toString();

            if (errorText.contains('401') ||
                errorText.contains('UNAUTHENTICATED')) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                ref.read(authProvider.notifier).logout();
              });
              return const Center(child: CircularProgressIndicator());
            }

            if (errorText.contains('403') || errorText.contains('FORBIDDEN')) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 100),
                  Center(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('Compte suspendu ou acces refuse.'),
                    ),
                  ),
                ],
              );
            }

            if (errorText.contains('NOT_IMPLEMENTED')) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 100),
                  Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(
                          Icons.build_circle_outlined,
                          size: 64,
                          color: AppColors.textMuted,
                        ),
                        const SizedBox(height: 16),
                        const Text(
                          'Fonction bientôt disponible',
                          style: TextStyle(fontSize: 20),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () => ref.refresh(
                            historyProvider(DateTime(now.year, now.month)),
                          ),
                          child: const Text('Réessayer'),
                        ),
                      ],
                    ),
                  ),
                ],
              );
            }

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: 100),
                Center(child: Text('Erreur : $err')),
              ],
            );
          },
          data: (logs) {
            if (logs.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 80),
                  EmptyState(
                    icon: Icons.history_toggle_off,
                    title: 'Aucun historique',
                    description:
                        'Rien ici pour le moment. Vos pointages apparaitront au fur et a mesure.',
                  ),
                ],
              );
            }

            final totalJours = logs.length;
            final totalHeures = logs.fold<double>(
              0,
              (sum, log) => sum + (log.workedHours ?? 0),
            );

            return Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Mois actuel',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                    ],
                  ),
                ),
                const Divider(),
                Expanded(
                  child: ListView.builder(
                    controller: _scrollController,
                    itemCount: logs.length + (_isLoadingMore ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index == logs.length) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(child: CircularProgressIndicator()),
                        );
                      }

                      final log = logs[index];
                      Color statusColor = AppColors.textMuted;
                      switch (log.status) {
                        case 'ontime':
                          statusColor = AppColors.success;
                          break;
                        case 'late':
                          statusColor = AppColors.warning;
                          break;
                        case 'absent':
                          statusColor = AppColors.danger;
                          break;
                      }

                      String statusLabel;
                      switch (log.status) {
                        case 'ontime':
                          statusLabel = "à l'heure";
                          break;
                        case 'late':
                          statusLabel = "en retard";
                          break;
                        case 'absent':
                          statusLabel = "absent";
                          break;
                        default:
                          statusLabel = log.status;
                      }

                      final timeRange = log.checkIn != null
                          ? 'de ${log.checkIn!.hour.toString().padLeft(2, '0')}:${log.checkIn!.minute.toString().padLeft(2, '0')} à '
                              '${log.checkOut != null ? "${log.checkOut!.hour.toString().padLeft(2, '0')}:${log.checkOut!.minute.toString().padLeft(2, '0')}" : "en cours"}'
                          : 'pas de pointage';

                      final hours = log.workedHours ?? 0;
                      final hoursLabel =
                          '$hours ${hours < 2 ? "heure travaillée" : "heures travaillées"}';

                      return Semantics(
                        label:
                            'Journée du ${log.date.day.toString().padLeft(2, '0')}/${log.date.month.toString().padLeft(2, '0')}, '
                            'statut $statusLabel, $timeRange, $hoursLabel.',
                        container: true,
                        child: ExcludeSemantics(
                          child: ListTile(
                            leading: CircleAvatar(
                              backgroundColor:
                                  statusColor.withValues(alpha: 0.2),
                              child: Icon(
                                Icons.circle,
                                color: statusColor,
                                size: 12,
                              ),
                            ),
                            title: Text(
                              '${log.date.day.toString().padLeft(2, '0')}/${log.date.month.toString().padLeft(2, '0')}',
                            ),
                            subtitle: Text(
                              log.checkIn != null
                                  ? '${log.checkIn!.hour.toString().padLeft(2, '0')}:${log.checkIn!.minute.toString().padLeft(2, '0')} -> '
                                      '${log.checkOut != null ? "${log.checkOut!.hour.toString().padLeft(2, '0')}:${log.checkOut!.minute.toString().padLeft(2, '0')}" : "En cours"}'
                                  : 'Absence',
                            ),
                            trailing: Text(
                              '${log.workedHours ?? 0}h',
                              style:
                                  const TextStyle(fontWeight: FontWeight.bold),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(24),
                    ),
                  ),
                  child: SafeArea(
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total jours'),
                            Text(
                              '$totalJours',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total heures'),
                            Text(
                              '${totalHeures.toStringAsFixed(1)}h',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Heures supplémentaires',
                              style: TextStyle(color: AppColors.textMuted),
                            ),
                            Text(
                              '${(totalHeures > 160 ? totalHeures - 160 : 0).toStringAsFixed(1)}h',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
