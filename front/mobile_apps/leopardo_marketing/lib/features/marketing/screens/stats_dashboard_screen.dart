import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_marketing/features/marketing/repositories/providers.dart';

// ─── Modèle ──────────────────────────────────────────────────────────────────

/// Statistiques agrégées réelles (#2595), calculées côté client à partir de
/// GET /marketing/posts (le backend n'expose pas de métriques d'engagement :
/// seuls les compteurs de posts par statut et par plateforme sont honnêtes).
class MarketingStats {
  final int total;
  final int published;
  final int scheduled;
  final int failed;
  final List<PlatformStats> byPlatform;

  const MarketingStats({
    required this.total,
    required this.published,
    required this.scheduled,
    required this.failed,
    required this.byPlatform,
  });

  factory MarketingStats.fromAggregation(Map<String, dynamic> agg) {
    return MarketingStats(
      total: (agg['total'] as num?)?.toInt() ?? 0,
      published: (agg['published'] as num?)?.toInt() ?? 0,
      scheduled: (agg['scheduled'] as num?)?.toInt() ?? 0,
      failed: (agg['failed'] as num?)?.toInt() ?? 0,
      byPlatform: ((agg['byPlatform'] as List?) ?? const [])
          .map((e) => PlatformStats(
                platform: (e as Map<String, dynamic>)['platform']?.toString() ?? '?',
                posts: ((e['posts'] as num?) ?? 0).toInt(),
              ))
          .toList(),
    );
  }
}

class PlatformStats {
  final String platform;
  final int posts;

  const PlatformStats({required this.platform, required this.posts});
}

// ─── Provider Riverpod ────────────────────────────────────────────────────────

/// Fournit les statistiques marketing agrégées depuis le référentiel
/// (fetchStats sur GET /marketing/posts). États d'erreur AsyncValue gérés
/// par l'écran (retry inclus) — plus aucune donnée fabriquée.
final marketingStatsProvider = FutureProvider<MarketingStats>((ref) async {
  final repository = ref.watch(socialPostRepositoryProvider);
  return MarketingStats.fromAggregation(await repository.fetchStats());
});

// ─── Écran ────────────────────────────────────────────────────────────────────

/// Tableau de bord statistiques du module Marketing.
///
/// Affiche les KPIs agrégés (Impressions, Likes, Clics, Partages) ainsi qu'une
/// ventilation par plateforme sociale (LinkedIn, Facebook/Meta, X).
class StatsDashboardScreen extends ConsumerWidget {
  const StatsDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statsAsync = ref.watch(marketingStatsProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Statistiques',
        subtitle: '30 derniers jours',
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(marketingStatsProvider),
            tooltip: 'Actualiser',
          ),
        ],
      ),
      children: [
        statsAsync.when(
          loading: () => const Padding(
            padding: EdgeInsets.symmetric(vertical: 48),
            child: MobileEmptyLoading(label: 'Chargement des statistiques…'),
          ),
          error: (err, _) => MobileErrorPanel(
            message: 'Impossible de charger les statistiques.',
            onRetry: () => ref.invalidate(marketingStatsProvider),
          ),
          data: (stats) => _StatsContent(stats: stats),
        ),
      ],
    );
  }
}

// ─── Contenu principal ────────────────────────────────────────────────────────

class _StatsContent extends StatelessWidget {
  const _StatsContent({required this.stats});

  final MarketingStats stats;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Section KPIs globaux
        const MobileSectionLabel('Vue d\'ensemble'),
        const SizedBox(height: 8),
        _KpiGrid(stats: stats),
        const SizedBox(height: 24),

        // Section par plateforme
        const MobileSectionLabel('Par plateforme'),
        const SizedBox(height: 8),
        ...stats.byPlatform.map(
          (p) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _PlatformCard(platform: p),
          ),
        ),
      ],
    );
  }
}

// ─── Grille KPIs ──────────────────────────────────────────────────────────────

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.stats});

  final MarketingStats stats;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            MobileMetricTile(
              value: _format(stats.total),
              label: 'Posts (30 j)',
              color: AppColors.ia, // violet — couleur marketing
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: _format(stats.published),
              label: 'Publiés',
              color: AppColors.success,
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            MobileMetricTile(
              value: _format(stats.scheduled),
              label: 'Planifiés',
              color: AppColors.info,
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: _format(stats.failed),
              label: 'Échecs',
              color: AppColors.danger,
            ),
          ],
        ),
      ],
    );
  }

  /// Formate un entier avec séparateur des milliers (ex. 24 310).
  static String _format(int n) {
    if (n >= 1000) {
      final thousands = n ~/ 1000;
      final remainder = n % 1000;
      return '$thousands ${remainder.toString().padLeft(3, '0')}';
    }
    return n.toString();
  }
}

// ─── Carte par plateforme ─────────────────────────────────────────────────────

class _PlatformCard extends StatelessWidget {
  const _PlatformCard({required this.platform});

  final PlatformStats platform;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // En-tête plateforme
          Row(
            children: [
              _PlatformIcon(name: platform.platform),
              const SizedBox(width: 10),
              Text(
                platform.platform,
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Nombre réel de posts ciblant cette plateforme (30 j)
          _InlineStat(
            label: 'Posts ciblés',
            value: platform.posts,
            color: AppColors.ia,
          ),
        ],
      ),
    );
  }
}

class _PlatformIcon extends StatelessWidget {
  const _PlatformIcon({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final (IconData icon, Color color) = switch (name) {
      'LinkedIn' => (Icons.work_outline_rounded, AppColors.socialLinkedIn),
      'Facebook' => (Icons.facebook_rounded, AppColors.socialFacebook),
      _ => (Icons.alternate_email_rounded, AppColors.mobileDarkText),
    };

    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, size: 18, color: color),
    );
  }
}

class _InlineStat extends StatelessWidget {
  const _InlineStat({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value >= 1000 ? '${value ~/ 1000}k' : value.toString(),
            style: AppTypography.bodySmall.copyWith(
              color: color,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
              fontSize: 10,
            ),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}
