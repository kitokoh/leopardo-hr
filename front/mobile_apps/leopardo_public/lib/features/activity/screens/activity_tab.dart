import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/widgets/state_widgets.dart';
import '../../discovery/data/public_discovery_repository.dart';
import '../data/public_activity_repository.dart';
import '../providers/activity_providers.dart';

/// Onglet Activités — commandes, réservations, favoris, recherches récentes.
///
/// ## Ce qui a changé depuis M1
///
/// En M1 cet onglet était un état vide honnête : le contrat d'activité
/// n'existait pas. En M3 il se remplit — mais UNIQUEMENT avec ce que le
/// serveur confirme. `meta.sections` ne liste que les verticales qui ont un
/// provider tagué : une verticale sans contrat n'apparaît pas, et l'écran
/// n'affiche jamais une section vide « par construction ».
///
/// ## Les états obligatoires
///
/// Chargement (squelette), vide, erreur, hors ligne, 404, et « connectez-vous »
/// quand le jeton manque. Chacun est un widget dédié de `state_widgets.dart` :
/// aucun `if` ad hoc ici, donc aucun écran qui afficherait « erreur » sur un
/// 404 ou « réessayer » sur une absence de compte.
class ActivityTab extends ConsumerWidget {
  const ActivityTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);
    final activities = ref.watch(publicActivitiesProvider);

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        appBar: AppBar(title: Text(strings.t('tabActivity'))),
        body: RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(publicActivitiesProvider);
            ref.invalidate(publicFavoritesProvider);
            ref.invalidate(publicSearchHistoryProvider);
            await ref.read(publicActivitiesProvider.future);
          },
          child: activities.when(
            loading: () => LoadingSkeleton(strings: strings, itemCount: 4),
            error: (error, _) => _errorState(context, ref, strings, error),
            data: (page) => page.isEmpty
                ? _emptyState(context, ref, strings)
                : _activityList(context, ref, strings, page),
          ),
        ),
      ),
    );
  }

  /// Traduit une panne en l'état qui lui correspond — jamais un état générique.
  Widget _errorState(
    BuildContext context,
    WidgetRef ref,
    AppStrings strings,
    Object error,
  ) {
    if (error is PublicApiFailure) {
      switch (error.kind) {
        case PublicFailureKind.network:
        case PublicFailureKind.timeout:
          // Hors ligne : on ne prétend pas avoir des données fraîches.
          return OfflineEmptyState(
            strings: strings,
            onRetry: () => ref.invalidate(publicActivitiesProvider),
          );
        case PublicFailureKind.notFound:
          // 404 n'est PAS une panne : aucune icône d'alerte, aucun « réessayer ».
          return NotFoundState(strings: strings);
        case PublicFailureKind.invalidRequest:
          // 401/403 : le jeton manque ou a expiré. Dire « connectez-vous » est
          // utile ; dire « erreur » ne l'est pas.
          if (error.statusCode == 401 || error.statusCode == 403) {
            return _signInState(context, strings);
          }
          return ErrorState(
            strings: strings,
            onRetry: () => ref.invalidate(publicActivitiesProvider),
          );
        case PublicFailureKind.server:
          return ErrorState(
            strings: strings,
            onRetry: () => ref.invalidate(publicActivitiesProvider),
          );
      }
    }

    return ErrorState(
      strings: strings,
      onRetry: () => ref.invalidate(publicActivitiesProvider),
    );
  }

  Widget _signInState(BuildContext context, AppStrings strings) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.lock_outline,
              size: 40,
              color: AppColors.textSecondaryFor(context),
            ),
            const SizedBox(height: 14),
            Text(
              strings.t('activitySignInRequired'),
              textAlign: TextAlign.center,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Vide : aucune activité, mais les favoris et l'historique restent utiles.
  Widget _emptyState(BuildContext context, WidgetRef ref, AppStrings strings) {
    return ListView(
      // `ListView` et non `Center` : sans défilement, le `RefreshIndicator`
      // n'aurait aucun geste à intercepter et le rafraîchissement serait mort.
      children: [
        SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.55,
          child: EmptyContentState(
            strings: strings,
            onRetry: () => ref.invalidate(publicActivitiesProvider),
          ),
        ),
        const _FavoritesSection(),
        const _SearchHistorySection(),
      ],
    );
  }

  Widget _activityList(
    BuildContext context,
    WidgetRef ref,
    AppStrings strings,
    PublicActivityPage page,
  ) {
    return ListView(
      padding: const EdgeInsets.only(bottom: 24),
      children: [
        for (final section in page.sections)
          ..._sectionFor(context, strings, page, section),
        const _FavoritesSection(),
        const _SearchHistorySection(),
      ],
    );
  }

  /// Une section n'est rendue que si elle contient au moins une activité.
  ///
  /// C'est la traduction visuelle de « un module sans contrat n'apparaît pas » :
  /// le serveur ne déclare que les verticales à contrat, et une section sans
  /// ligne n'a rien à montrer.
  List<Widget> _sectionFor(
    BuildContext context,
    AppStrings strings,
    PublicActivityPage page,
    PublicActivitySection section,
  ) {
    final items = page.activities
        .where((a) => a.vertical == section.vertical)
        .toList(growable: false);

    if (items.isEmpty) return const <Widget>[];

    return [
      _SectionTitle(label: _sectionLabel(strings, section)),
      for (final activity in items) _ActivityTile(activity: activity),
    ];
  }

  String _sectionLabel(AppStrings strings, PublicActivitySection section) {
    if (section.types.length == 1) {
      return section.types.first == 'order'
          ? strings.t('activitySectionOrders')
          : strings.t('activitySectionBookings');
    }

    return strings.t('activitySectionOrders');
  }
}

/// Libellé de statut, traduit depuis le vocabulaire du tenant.
///
/// Un statut inconnu est affiché TEL QUEL plutôt que masqué : un statut qu'on
/// ne sait pas traduire est une information, pas une raison de mentir.
String _statusLabel(AppStrings strings, String status) {
  switch (status) {
    case 'open':
    case 'in_preparation':
    case 'draft':
      return strings.t('activityStatusOpen');
    case 'ready':
    case 'served':
      return strings.t('activityStatusReady');
    case 'paid':
    case 'closed':
      return strings.t('activityStatusClosed');
    case 'cancelled':
    case 'refunded':
      return strings.t('activityStatusCancelled');
    default:
      return status;
  }
}

class _ActivityTile extends ConsumerWidget {
  const _ActivityTile({required this.activity});

  final PublicActivity activity;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);

    return ListTile(
      leading: Icon(
        activity.isOrder
            ? Icons.receipt_long_outlined
            : Icons.event_available_outlined,
        size: 22,
      ),
      title: Text(
        '${activity.title} · ${activity.reference}',
        style: AppTypography.bodySmall,
      ),
      subtitle: Text(
        _statusLabel(strings, activity.status),
        style: AppTypography.caption.copyWith(
          color: AppColors.textSecondaryFor(context),
        ),
      ),
      dense: true,
    );
  }
}

/// Favoris — section autonome, chargée séparément des activités.
class _FavoritesSection extends ConsumerWidget {
  const _FavoritesSection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);
    final favorites = ref.watch(publicFavoritesProvider);

    return favorites.when(
      loading: () => const SizedBox.shrink(),
      // Un échec de favoris ne doit pas casser l'onglet : les activités
      // restent lisibles. On n'affiche donc rien plutôt qu'une erreur globale.
      error: (_, __) => const SizedBox.shrink(),
      data: (items) {
        if (items.isEmpty) return const SizedBox.shrink();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SectionTitle(label: strings.t('favoritesTitle')),
            for (final favorite in items)
              ListTile(
                leading: const Icon(Icons.favorite_border, size: 20),
                title: Text(
                  favorite.displayName ?? favorite.targetSlug,
                  style: AppTypography.bodySmall,
                ),
                subtitle: favorite.available
                    ? null
                    : Text(
                        strings.t('favoritesUnavailable'),
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                trailing: IconButton(
                  icon: const Icon(Icons.close, size: 18),
                  tooltip: strings.t('favoritesRemove'),
                  onPressed: () async {
                    await ref
                        .read(publicActivityRepositoryProvider)
                        .removeFavorite(
                          favorite.targetType,
                          favorite.targetSlug,
                        );
                    ref.invalidate(publicFavoritesProvider);
                  },
                ),
                dense: true,
              ),
          ],
        );
      },
    );
  }
}

/// Historique de recherche — local d'abord, synchronisé si compte.
class _SearchHistorySection extends ConsumerWidget {
  const _SearchHistorySection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);
    final history = ref.watch(publicSearchHistoryProvider);

    return history.when(
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
      data: (entries) {
        if (entries.isEmpty) return const SizedBox.shrink();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _SectionTitle(label: strings.t('searchHistoryTitle')),
                TextButton(
                  onPressed: () async {
                    await ref
                        .read(publicActivityRepositoryProvider)
                        .clearSearchHistory();
                    ref.invalidate(publicSearchHistoryProvider);
                  },
                  child: Text(strings.t('searchHistoryClear')),
                ),
              ],
            ),
            for (final entry in entries)
              ListTile(
                leading: const Icon(Icons.history, size: 20),
                title: Text(entry.query, style: AppTypography.bodySmall),
                subtitle: entry.city == null
                    ? null
                    : Text(
                        entry.city!,
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                dense: true,
              ),
          ],
        );
      },
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
      child: Text(
        label.toUpperCase(),
        style: AppTypography.caption.copyWith(
          color: AppColors.textSecondaryFor(context),
          fontWeight: FontWeight.w700,
          letterSpacing: 0.6,
        ),
      ),
    );
  }
}
