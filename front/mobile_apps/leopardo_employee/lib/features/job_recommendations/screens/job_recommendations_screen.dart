import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_employee/features/user_auth/data/user_auth_repository.dart';

class JobRecommendationsScreen extends ConsumerStatefulWidget {
  const JobRecommendationsScreen({super.key});

  @override
  ConsumerState<JobRecommendationsScreen> createState() => _JobRecommendationsScreenState();
}

class _JobRecommendationsScreenState extends ConsumerState<JobRecommendationsScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() {
    return ref.read(userAuthRepositoryProvider).getJobRecommendations();
  }

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Offres recommandées')),
      body: RefreshIndicator(
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Les recommandations sont momentanément indisponibles.', style: TextStyle(color: muted)),
                ),
              ]);
            }
            final jobs = snapshot.data ?? const [];
            if (jobs.isEmpty) {
              return ListView(children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Aucune offre ne correspond encore à votre profil.', textAlign: TextAlign.center, style: TextStyle(color: muted)),
                ),
              ]);
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
              itemCount: jobs.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final job = jobs[index];
                final company = (job['company'] as Map?)?.cast<String, dynamic>();
                final reasons = (job['match_reasons'] as List?)?.whereType<String>().toList() ?? const [];
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(children: [
                          Expanded(child: Text(job['title']?.toString() ?? 'Offre', style: AppTypography.subtitle.copyWith(color: text, fontWeight: FontWeight.w700))),
                          Text('${job['match_score'] ?? 0}%', style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w800)),
                        ]),
                        const SizedBox(height: 5),
                        Text('${company?['name'] ?? 'Entreprise'} · ${job['location'] ?? 'Localisation non précisée'}', style: AppTypography.caption.copyWith(color: muted)),
                        const SizedBox(height: 10),
                        Text(job['ai_reason']?.toString() ?? (reasons.isNotEmpty ? reasons.first : 'Offre compatible avec votre recherche.'), style: AppTypography.bodySmall.copyWith(color: text)),
                        const SizedBox(height: 12),
                        Align(
                          alignment: Alignment.centerRight,
                          child: OutlinedButton.icon(
                            onPressed: job['public_url'] == null ? null : () => launchUrl(Uri.parse(job['public_url'].toString()), mode: LaunchMode.externalApplication),
                            icon: const Icon(Icons.open_in_new, size: 16),
                            label: const Text('Voir l’offre'),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
