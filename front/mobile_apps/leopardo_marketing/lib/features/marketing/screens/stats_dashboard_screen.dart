import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';

// ─── Modèle ──────────────────────────────────────────────────────────────────

/// Statistiques agrégées retournées par l'API Marketing.
/// Connecter à GET /api/v1/marketing/posts/stats (à implémenter côté API).
class MarketingStats {
  final int impressions;
  final int likes;
  final int clicks;
  final int shares;
  final List<PlatformStats> byPlatform;

  const MarketingStats({
    required this.impressions,
    required this.likes,
    required this.clicks,
    required this.shares,
    required this.byPlatform,
  });
}

class PlatformStats {
  final String platform;
  final int impressions;
  final int likes;
  final int clicks;

  const PlatformStats({
    required this.platform,
    required this.impressions,
    required this.likes,
    required this.clicks,
  });
}

// ─── Provider Riverpod ────────────────────────────────────────────────────────

/// Fournit les statistiques marketing.
/// Remplacer le corps par un appel `api.get('/v1/marketing/stats')` une fois
/// l'endpoint backend disponible.
final marketingStatsProvider = FutureProvider<MarketingStats>((ref) async {
  // Simulation d'une latence réseau — à remplacer par un vrai appel API.
  await Future.delayed(const Duration(milliseconds: 800));

  return const MarketingStats(
    impressions: 24_310,
    likes: 1_847,
    clicks: 432,
    shares: 98,
    byPlatform: [
      PlatformStats(
        platform: 'LinkedIn',
        impressions: 12_540,
        likes: 923,
        clicks: 214,
      ),
      PlatformStats(
        platform: 'Facebook',
        impressions: 8_920,
        likes: 651,
        clicks: 138,
      ),
      PlatformStats(
        platform: 'X (Twitter)',
        impressions: 2_850,
        likes: 273,
        clicks: 80,
      ),
    ],
  );
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
              value: _format(stats.impressions),
              label: 'Impressions',
              color: AppColors.ia, // violet — couleur marketing
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: _format(stats.likes),
              label: 'Likes',
              color: AppColors.danger,
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            MobileMetricTile(
              value: _format(stats.clicks),
              label: 'Clics',
              color: AppColors.info,
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: _format(stats.shares),
              label: 'Partages',
              color: AppColors.success,
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

          // Métriques inline
          Row(
            children: [
              _InlineStat(
                label: 'Impressions',
                value: platform.impressions,
                color: AppColors.ia,
              ),
              const SizedBox(width: 16),
              _InlineStat(
                label: 'Likes',
                value: platform.likes,
                color: AppColors.danger,
              ),
              const SizedBox(width: 16),
              _InlineStat(
                label: 'Clics',
                value: platform.clicks,
                color: AppColors.info,
              ),
            ],
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
      'LinkedIn' => (Icons.work_outline_rounded, const Color(0xFF0A66C2)),
      'Facebook' => (Icons.facebook_rounded, const Color(0xFF1877F2)),
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
