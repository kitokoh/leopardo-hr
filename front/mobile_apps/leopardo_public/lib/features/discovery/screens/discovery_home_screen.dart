import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/location/public_location.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/widgets/state_widgets.dart';
import '../models/discovery_category.dart';
import '../models/discovery_item.dart';
import '../providers/discovery_providers.dart';
import '../widgets/category_chips.dart';
import '../widgets/discovery_tile.dart';
import '../widgets/search_field.dart';

/// Accueil — l'onglet par défaut, et le seul écran que voit un visiteur venu
/// d'un QR code.
///
/// ## Le parcours que cet écran doit rendre possible
///
/// Ouverture → recherche (toujours visible, jamais derrière une icône) →
/// fiche. Aucun compte demandé, aucune inscription suggérée : le compte
/// n'arrive qu'au moment du contact, et c'est le contrat du couloir.
///
/// ## Décisions visibles dans le code
///
/// - **Barre de recherche toujours visible et fixe** : elle ne défile pas avec
///   la liste. Un visiteur qui scrolle 6 fiches puis veut chercher autre chose
///   ne doit pas remonter en haut.
/// - **Catégories dynamiques** : `GET /public/discovery/categories` ne renvoie
///   que les catégories au-dessus du seuil Superadmin. L'écran n'invente AUCUNE
///   catégorie locale — une puce qui ne renverrait jamais rien est pire que
///   pas de puce.
/// - **Bascule liste/carte** : la carte n'affiche que les fiches géolocalisées.
///   Sans position, elle montre les points de la ville choisie ; sans ville ni
///   position, elle explique quoi faire au lieu d'afficher un océan vide.
/// - **« Reprendre où vous étiez »** : lit la dernière recherche en cache. Ce
///   n'est pas une fonctionnalité de rétention intrusive — c'est le geste
///   quotidien (rouvrir l'app pour retrouver le commerce d'hier).
class DiscoveryHomeScreen extends ConsumerStatefulWidget {
  const DiscoveryHomeScreen({super.key});

  @override
  ConsumerState<DiscoveryHomeScreen> createState() =>
      _DiscoveryHomeScreenState();
}

class _DiscoveryHomeScreenState extends ConsumerState<DiscoveryHomeScreen> {
  bool _mapView = false;
  LocationOutcome? _locationOutcome;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Premier chargement : les catégories + la liste par défaut. On ne
      // demande PAS la position ici (voir PublicLocation: consentement).
      ref.read(discoveryControllerProvider.notifier).load();
    });
  }

  Future<void> _chooseCity() async {
    final strings = ref.read(appStringsProvider);
    final current = ref.read(discoveryControllerProvider).city;

    final city = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => CityPickerSheet(strings: strings, currentCity: current),
    );

    if (city != null && city.isNotEmpty) {
      ref.read(discoveryControllerProvider.notifier).setCity(city);
    }
  }

  Future<void> _useLocation() async {
    final service = ref.read(locationServiceProvider);
    final result = await service.request();

    if (!mounted) return;

    setState(() => _locationOutcome = result.outcome);

    if (result.outcome == LocationOutcome.granted && result.nearParam != null) {
      ref
          .read(discoveryControllerProvider.notifier)
          .useLocation(result.nearParam!);
    }
    // Refus, service coupé, refus définitif : on ne fait RIEN de plus que
    // laisser l'écran proposer le choix de ville. Insister serait hostile.
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);
    final state = ref.watch(discoveryControllerProvider);
    final controller = ref.read(discoveryControllerProvider.notifier);

    final categoriesAsync = ref.watch(discoveryCategoriesProvider(state.city));

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        body: SafeArea(
          child: Column(
            children: [
              // ── Barre de recherche + ville : FIXES, jamais dans le scroll ──
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: DiscoverySearchField(
                            strings: strings,
                            initialQuery: state.query,
                            onChanged: controller.search,
                            onSubmitted: (_) => controller.load(),
                          ),
                        ),
                        const SizedBox(width: 8),
                        _CityButton(
                          strings: strings,
                          city: state.city,
                          onTap: _chooseCity,
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    _Toolbar(
                      strings: strings,
                      mapView: _mapView,
                      openNowOnly: state.openNowOnly,
                      usedLocation: state.usedLocation,
                      onToggleView: () => setState(() => _mapView = !_mapView),
                      onToggleOpenNow: controller.toggleOpenNow,
                      onUseLocation: _useLocation,
                      onClearLocation: controller.clearLocation,
                    ),
                  ],
                ),
              ),

              // ── Catégories dynamiques (au-dessus du seuil Superadmin) ──
              categoriesAsync.maybeWhen(
                data: (categories) => categories.visible.isEmpty
                    ? const SizedBox.shrink()
                    : CategoryChips(
                        strings: strings,
                        categories: categories.visible,
                        selected: state.categoryKey,
                        onSelect: (key) => controller.setCategory(
                          key == state.categoryKey ? null : key,
                        ),
                      ),
                orElse: () => const SizedBox.shrink(),
              ),

              // ── Bandeau hors ligne ──
              if (state.isOffline) OfflineBanner(strings: strings),

              // ── Corps ──
              Expanded(
                child: _body(strings, state, controller, categoriesAsync),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _body(
    AppStrings strings,
    DiscoveryState state,
    DiscoveryController controller,
    AsyncValue<DiscoveryCategories> categoriesAsync,
  ) {
    if (state.isLoading && state.items.isEmpty) {
      return LoadingSkeleton(strings: strings);
    }

    // Localisation refusée ET aucune ville choisie : c'est le seul cas où
    // l'écran ne peut rien montrer d'utile, et la réponse est un choix de
    // ville — pas un message d'erreur.
    if (state.failure != null &&
        state.failure!.kind.name == 'network' &&
        state.city == null &&
        _locationOutcome != null) {
      return LocationDeniedState(strings: strings, onChooseCity: _chooseCity);
    }

    if (state.failure == null && state.items.isEmpty && !state.isLoading) {
      // Distinguer « rien dans la ville » de « aucun résultat pour cette
      // recherche » : deux textes, deux actions.
      if (state.query.trim().isNotEmpty || state.categoryKey != null) {
        return NoResultsState(
          strings: strings,
          onWiden: () => controller.search(''),
        );
      }
      return EmptyContentState(
        strings: strings,
        onRetry: () => controller.load(),
      );
    }

    if (state.failure != null && state.items.isEmpty) {
      return ErrorState(strings: strings, onRetry: () => controller.load());
    }

    if (_mapView) {
      return _MapPlaceholder(
        strings: strings,
        items: state.items
            .where((i) => i.latitude != null && i.longitude != null)
            .toList(),
        onSwitchToList: () => setState(() => _mapView = false),
      );
    }

    return RefreshIndicator(
      onRefresh: controller.load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
        itemCount: state.items.length + 1,
        itemBuilder: (context, index) {
          if (index == state.items.length) {
            return _Footer(
              strings: strings,
              state: state,
              onLoadMore: controller.loadMore,
            );
          }

          final item = state.items[index];
          return DiscoveryTile(
            strings: strings,
            item: item,
            onTap: () => context.push(
              '/business/${item.type}/${item.slug}',
              extra: item,
            ),
          );
        },
      ),
    );
  }
}

/// Bouton de ville — affiche la ville courante ou invite à en choisir une.
class _CityButton extends StatelessWidget {
  const _CityButton({
    required this.strings,
    required this.city,
    required this.onTap,
  });

  final AppStrings strings;
  final String? city;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: strings.t('discoveryCityLabel'),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: AppColors.surfaceFor(context),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.borderFor(context)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.place_outlined,
                size: 18,
                color: AppColors.textSecondaryFor(context),
              ),
              const SizedBox(width: 4),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 84),
                child: Text(
                  city ?? strings.t('discoveryCityLabel'),
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.bodySmall,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Toolbar extends StatelessWidget {
  const _Toolbar({
    required this.strings,
    required this.mapView,
    required this.openNowOnly,
    required this.usedLocation,
    required this.onToggleView,
    required this.onToggleOpenNow,
    required this.onUseLocation,
    required this.onClearLocation,
  });

  final AppStrings strings;
  final bool mapView;
  final bool openNowOnly;
  final bool usedLocation;
  final VoidCallback onToggleView;
  final VoidCallback onToggleOpenNow;
  final VoidCallback onUseLocation;
  final VoidCallback onClearLocation;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _Pill(
            icon: mapView ? Icons.view_list_outlined : Icons.map_outlined,
            label: mapView
                ? strings.t('discoveryViewList')
                : strings.t('discoveryViewMap'),
            onTap: onToggleView,
            active: mapView,
          ),
          const SizedBox(width: 8),
          _Pill(
            icon: Icons.schedule_outlined,
            label: strings.t('discoveryOpenNow'),
            onTap: onToggleOpenNow,
            active: openNowOnly,
          ),
          const SizedBox(width: 8),
          _Pill(
            icon: usedLocation ? Icons.my_location : Icons.location_searching,
            label: usedLocation
                ? strings.t('discoveryNearby')
                : strings.t('discoveryUseLocation'),
            onTap: usedLocation ? onClearLocation : onUseLocation,
            active: usedLocation,
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.icon,
    required this.label,
    required this.onTap,
    required this.active,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final fg = active
        ? AppColors.textPrimaryFor(context)
        : AppColors.textSecondaryFor(context);

    return Semantics(
      button: true,
      selected: active,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: active
                ? AppColors.tint(
                    context,
                    AppColors.info,
                    lightAlpha: 0.18,
                    darkAlpha: 0.24,
                  )
                : AppColors.surfaceFor(context),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: active ? AppColors.info : AppColors.borderFor(context),
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: active ? AppColors.info : fg),
              const SizedBox(width: 6),
              Text(label, style: AppTypography.caption.copyWith(color: fg)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Carte : pas de SDK cartographique dans les dépendances M1.
///
/// On ne prétend PAS afficher une carte : on affiche la projection
/// approximative des fiches géolocalisées, avec le nombre exact, et un retour
/// à la liste. Ajouter un SDK de cartes est une décision (coût, clé API, poids)
/// qui appartient à M4, pas un choix silencieux d'implémentation.
class _MapPlaceholder extends StatelessWidget {
  const _MapPlaceholder({
    required this.strings,
    required this.items,
    required this.onSwitchToList,
  });

  final AppStrings strings;
  final List<DiscoveryItem> items;
  final VoidCallback onSwitchToList;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.map_outlined,
              size: 48,
              color: AppColors.textSecondaryFor(context),
            ),
            const SizedBox(height: 12),
            Text(
              '${items.length}',
              style: AppTypography.display.copyWith(
                color: AppColors.textPrimaryFor(context),
              ),
            ),
            Text(
              strings.t('discoveryNearby'),
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: onSwitchToList,
              child: Text(strings.t('discoveryViewList')),
            ),
          ],
        ),
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({
    required this.strings,
    required this.state,
    required this.onLoadMore,
  });

  final AppStrings strings;
  final DiscoveryState state;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) {
    if (state.isLoading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 24),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (state.hasMore) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Center(
          child: OutlinedButton(
            onPressed: () => onLoadMore(),
            child: Text(
              strings.t('discoveryResultsCount').replaceFirst('%s', '+'),
            ),
          ),
        ),
      );
    }

    // ── Invitation commerçant (Couloir 16, M1 — section H) ──────────────
    //
    // Placée en FIN de liste, après les résultats : le visiteur a d'abord vu
    // ce que le portail contient, l'invitation a alors un sens. La destination
    // est la route RÉELLE de la vitrine business (`/business`), pas une page
    // de publication inventée — le parcours de publication de Page vit dans
    // l'espace business, et y renvoyer est honnête.
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 12),
      child: _MerchantInvite(strings: strings),
    );
  }
}

/// Carte discrète « Vous êtes commerçant ? » — section H du cahier des charges.
///
/// Volontairement sobre : bordure fine, pas d'ombre, pas de dégradé. C'est une
/// invitation, pas une publicité — elle ne doit pas concurrencer les résultats
/// que le visiteur est venu chercher.
class _MerchantInvite extends StatelessWidget {
  const _MerchantInvite({required this.strings});

  final AppStrings strings;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: theme.dividerColor.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            strings.t('merchantInviteTitle'),
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            strings.t('merchantInviteBody'),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 12),
          // Cible tactile ≥ 44 px (WCAG 2.5.5).
          SizedBox(
            height: 44,
            child: OutlinedButton(
              onPressed: () => context.push('/business'),
              child: Text(strings.t('merchantInviteCta')),
            ),
          ),
        ],
      ),
    );
  }
}
