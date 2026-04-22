import 'package:leopardo_rh/core/widgets/pulse_button.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

class AttendanceScreen extends ConsumerWidget {
  const AttendanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final attState = ref.watch(attendanceProvider);
    final isManager = authState.employee?.isManager == true || attState.context?['mode'] == 'collection';

    return Scaffold(
      body: SafeArea(
        child: attState.error != null && attState.error!.contains('NOT_IMPLEMENTED')
            ? _buildStubScreen(context, ref)
            : RefreshIndicator(
                onRefresh: () => ref.read(attendanceProvider.notifier).loadTodayData(),
                child: ListView(
                  padding: const EdgeInsets.all(24.0),
                  children: [
                    _buildHeader(context, authState, isManager),
                    const SizedBox(height: 32),
                    isManager
                        ? _buildManagerOverviewCard(context, ref, attState)
                        : _buildActionCard(context, ref, attState),
                    const SizedBox(height: 32),
                    if (!isManager) _buildSummaryCard(context, attState),
                    if (attState.notice != null) ...[
                      _buildNoticeCard(context, attState.notice!),
                      const SizedBox(height: 32),
                    ],
                    const SizedBox(height: 32),
                    _buildActions(context, ref, attState, isManager),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildStubScreen(BuildContext context, WidgetRef ref) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.build_circle_outlined, size: 64, color: Colors.grey),
          const SizedBox(height: 16),
          const Text('Fonction bientot disponible', style: TextStyle(fontSize: 20)),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () {
              ref.read(attendanceProvider.notifier).loadTodayData();
            },
            child: const Text('Reessayer'),
          ),
          const SizedBox(height: 16),
          TextButton(
            onPressed: () {
              ref.read(authProvider.notifier).logout();
            },
            child: const Text('Deconnexion', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, AuthState state, bool isManager) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Bonjour ${state.employee?.firstName ?? ''}',
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            isManager ? 'Espace RH / manager' : 'Espace employe',
            style: const TextStyle(color: Colors.grey),
          ),
        ],
      ),
    );
  }

  Widget _buildActionCard(BuildContext context, WidgetRef ref, AttendanceState state) {
    final isCheckedIn = state.todayLog?.checkIn != null && state.todayLog?.checkOut == null;

    return Container(
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          if (state.isLoading && state.todayLog == null)
            Container(
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Theme.of(context).primaryColor.withValues(alpha: 0.12),
              ),
              child: const Center(
                child: SizedBox(
                  height: 28,
                  width: 28,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            )
          else
            PulseButton(
              isCheckedIn: isCheckedIn,
              isLoading: state.isLoading,
              onTap: () {
                if (isCheckedIn) {
                  ref.read(attendanceProvider.notifier).checkOut();
                } else {
                  ref.read(attendanceProvider.notifier).checkIn();
                }
              },
            ),
          const SizedBox(height: 32),
          if (state.isLoading && state.todayLog == null) ...[
            const Text(
              'Chargement de votre presence du jour...',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
          ] else if (state.error != null && state.todayLog == null) ...[
            Text(
              state.error!,
              textAlign: TextAlign.center,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: () => ref.read(attendanceProvider.notifier).loadTodayData(),
              child: const Text('Reessayer'),
            ),
          ] else if (state.todayLog?.checkIn != null)
            Text(
              'Arrivee : ${state.todayLog!.checkIn!.hour.toString().padLeft(2, '0')}:${state.todayLog!.checkIn!.minute.toString().padLeft(2, '0')}',
              style: const TextStyle(fontSize: 18),
            )
          else
            const Text(
              'Pret pour le pointage du jour.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
        ],
      ),
    );
  }

  Widget _buildManagerOverviewCard(BuildContext context, WidgetRef ref, AttendanceState state) {
    final items = state.context?['items'];
    final employees = items is List ? items : const [];
    final checkedInCount = employees.whereType<Map>().where((item) {
      final status = item['status']?.toString();
      return status != null && status != 'absent';
    }).length;

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Suivi de l equipe',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          if (state.isLoading && employees.isEmpty) ...[
            const Row(
              children: [
                SizedBox(
                  height: 18,
                  width: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Chargement du suivi d equipe...',
                    style: TextStyle(color: Colors.grey),
                  ),
                ),
              ],
            ),
          ] else ...[
            Text(
              employees.isEmpty
                  ? 'Le suivi du jour sera disponible apres actualisation.'
                  : '${employees.length} collaborateurs charges, $checkedInCount deja pointes aujourd hui.',
              style: const TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: () => ref.read(attendanceProvider.notifier).loadTodayData(),
              child: const Text('Actualiser le suivi'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildSummaryCard(BuildContext context, AttendanceState state) {
    if (state.summary == null) return const SizedBox.shrink();

    final currencyFormat = NumberFormat.currency(
      locale: 'fr_DZ',
      symbol: state.summary!.currency,
      decimalDigits: 2,
    );

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text('Gain estime aujourd\'hui', style: TextStyle(fontSize: 16)),
              const Spacer(),
              Text(
                currencyFormat.format(state.summary!.totalEstimated),
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: Theme.of(context).primaryColor,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'Heures sup : ${state.summary!.overtimeGain > 0 ? (state.summary!.overtimeGain).toStringAsFixed(0) : "0h"}',
            style: const TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 8),
          const Text(
            'Estimation - net final calcule en fin de mois',
            style: TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic),
          ),
        ],
      ),
    );
  }

  Widget _buildNoticeCard(BuildContext context, String notice) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.amber.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.amber.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline, color: Colors.amber),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              notice,
              style: const TextStyle(color: Colors.white70),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref, AttendanceState state, bool isManager) {
    final employee = ref.read(authProvider).employee;
    final canManageTeam = employee?.canManageTeam == true;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (!isManager && state.isLoading && state.todayLog == null) ...[
          const Text(
            'L ecran est disponible pendant le chargement. Vous pouvez attendre quelques secondes ou tirer pour actualiser.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 16),
        ],
        OutlinedButton.icon(
          onPressed: () => context.push('/me/monthly'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          icon: const Icon(Icons.calendar_month),
          label: const Text('Mon mois (heures, heures sup, du)'),
        ),
        const SizedBox(height: 16),
        OutlinedButton(
          onPressed: () => context.push('/history'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          child: const Text('Voir historique'),
        ),
        if (canManageTeam) ...[
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: () => context.push('/team'),
            style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
            icon: const Icon(Icons.groups),
            label: const Text('Equipe (ajouter, inviter, archiver)'),
          ),
        ],
        const SizedBox(height: 16),
        OutlinedButton(
          onPressed: () => context.push('/settings'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          child: const Text('Parametres'),
        ),
        const SizedBox(height: 16),
        TextButton(
          onPressed: () {
            ref.read(authProvider.notifier).logout();
          },
          child: const Text('Deconnexion', style: TextStyle(color: Colors.red)),
        ),
      ],
    );
  }
}
