import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_employee/features/user_auth/data/user_auth_repository.dart';

class JobApplicationsScreen extends ConsumerStatefulWidget {
  const JobApplicationsScreen({super.key});

  @override
  ConsumerState<JobApplicationsScreen> createState() => _JobApplicationsScreenState();
}

class _JobApplicationsScreenState extends ConsumerState<JobApplicationsScreen> {
  late Future<List<Map<String, dynamic>>> _future;
  static const stages = ['new', 'screening', 'interview', 'offer', 'hired'];

  @override
  void initState() {
    super.initState();
    _future = ref.read(userAuthRepositoryProvider).getJobApplications();
  }

  String label(String status) => switch (status) {
        'new' => 'Envoyée',
        'screening' => 'Présélection',
        'interview' => 'Entretien',
        'offer' => 'Offre',
        'hired' => 'Embauchée',
        'rejected' => 'Refusée',
        'withdrawn' => 'Retirée',
        _ => status,
      };

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Mes candidatures')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('Impossible de charger vos candidatures.', style: TextStyle(color: muted)));
          }
          final applications = snapshot.data ?? const [];
          if (applications.isEmpty) {
            return Center(child: Text('Vous n’avez encore envoyé aucune candidature.', style: TextStyle(color: muted)));
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _future = ref.read(userAuthRepositoryProvider).getJobApplications()),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
              itemCount: applications.length,
              separatorBuilder: (_, __) => const SizedBox(height: 14),
              itemBuilder: (context, index) {
                final application = applications[index];
                final job = (application['job'] as Map?)?.cast<String, dynamic>();
                final history = (application['status_history'] as List?)?.whereType<Map>().map((item) => item.cast<String, dynamic>()).toList() ?? const [];
                final current = application['status']?.toString() ?? 'new';
                final currentIndex = stages.indexOf(current);
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text(job?['title']?.toString() ?? 'Candidature', style: AppTypography.subtitle.copyWith(color: text, fontWeight: FontWeight.w700))),
                        Text(label(current), style: const TextStyle(color: AppColors.info, fontWeight: FontWeight.w700)),
                      ]),
                      const SizedBox(height: 12),
                      Row(children: List.generate(stages.length, (stageIndex) => Expanded(child: Container(height: 6, margin: EdgeInsets.only(right: stageIndex == stages.length - 1 ? 0 : 4), decoration: BoxDecoration(color: stageIndex <= currentIndex ? AppColors.success : AppColors.borderFor(context), borderRadius: BorderRadius.circular(8))))),
                      const SizedBox(height: 12),
                      if (history.isEmpty)
                        Text('Candidature envoyée. Vous serez informé de la prochaine étape.', style: AppTypography.bodySmall.copyWith(color: muted))
                      else
                        ...history.reversed.map((event) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                const Padding(padding: EdgeInsets.only(top: 3), child: Icon(Icons.circle, size: 8, color: AppColors.success)),
                                const SizedBox(width: 8),
                                Expanded(child: Text('${label(event['to_status']?.toString() ?? '')}${event['note'] == null ? '' : ' — ${event['note']}', style: AppTypography.bodySmall.copyWith(color: text))),
                              ]),
                            )),
                    ]),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
