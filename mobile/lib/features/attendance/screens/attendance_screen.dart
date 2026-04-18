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

    return Scaffold(
      body: SafeArea(
        child: attState.error != null && attState.error!.contains('NOT_IMPLEMENTED')
            ? _buildStubScreen(context, ref)
            : RefreshIndicator(
                onRefresh: () => ref.read(attendanceProvider.notifier).loadTodayData(),
                child: ListView(
                  padding: const EdgeInsets.all(24.0),
                  children: [
                    _buildHeader(context, authState),
                    const SizedBox(height: 32),
                    _buildActionCard(context, ref, attState),
                    const SizedBox(height: 32),
                    _buildSummaryCard(context, attState),
                    const SizedBox(height: 32),
                    _buildActions(context, ref, attState),
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
          const Text('Fonction bientôt disponible', style: TextStyle(fontSize: 20)),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () {
              ref.read(attendanceProvider.notifier).loadTodayData();
            },
            child: const Text('Réessayer'),
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

  Widget _buildHeader(BuildContext context, AuthState state) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Bonjour ${state.employee?.firstName ?? ''}',
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          const Text('Leopardo RH', style: TextStyle(color: Colors.grey)),
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
            const SizedBox(
              height: 20,
              width: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            const SizedBox(height: 16),
            const Text(
              'Chargement de votre pointage du jour...',
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

  Widget _buildSummaryCard(BuildContext context, AttendanceState state) {
    if (state.summary == null) return const SizedBox.shrink();
    
    final currencyFormat = NumberFormat.currency(locale: 'fr_DZ', symbol: state.summary!.currency, decimalDigits: 2);
    
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
              Text(currencyFormat.format(state.summary!.totalEstimated),
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Theme.of(context).primaryColor)),
            ],
          ),
          const SizedBox(height: 16),
          Text('Heures sup : ${state.summary!.overtimeGain > 0 ? (state.summary!.overtimeGain).toStringAsFixed(0) : "0h"}',
              style: const TextStyle(color: Colors.grey)),
          const SizedBox(height: 8),
          const Text('Estimation - net final calcule en fin de mois',
              style: TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic)),
        ],
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref, AttendanceState state) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (state.isLoading && state.todayLog == null) ...[
          const Text(
            'L ecran est disponible pendant le chargement. Vous pouvez attendre quelques secondes ou tirer pour actualiser.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 16),
        ],
        OutlinedButton(
          onPressed: () => context.push('/history'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          child: const Text('Voir historique'),
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
