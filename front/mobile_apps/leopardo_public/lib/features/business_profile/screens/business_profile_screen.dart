import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/widgets/state_widgets.dart';
import '../../discovery/data/public_discovery_repository.dart';
import '../../discovery/models/discovery_item.dart';
import '../../discovery/providers/discovery_providers.dart';
import '../data/business_profile_repository.dart';
import '../models/business_profile.dart';
import '../widgets/action_buttons.dart';
import '../widgets/business_hours_card.dart';
import '../widgets/business_menu_card.dart';

/// Fiche d'un établissement — LECTURE SEULE, sans compte.
///
/// ## Le point le plus important de cet écran
///
/// Les boutons « Contacter / Commander / Réserver » viennent EXCLUSIVEMENT de
/// `supportedActions()` du provider, transmis par la découverte dans
/// `meta.actions`. Aucun bouton n'est ajouté « au cas où » : un bouton mort
/// fait perdre la confiance au premier tap et fait croire que le commerçant
/// n'est pas joignable alors qu'il l'est par un autre canal.
///
/// Quand une capacité existe mais que sa destination n'est pas exposée à l'app
/// (pas d'endpoint mobile), le bouton est affiché DÉSACTIVÉ avec l'explication
/// `businessActionsUnavailable`. C'est différent d'un bouton absent : la
/// personne sait que le commerce accepte les commandes, et que ce n'est pas
/// encore disponible ici.
///
/// ## Ce que l'écran ne fait pas
///
/// Il n'affiche AUCUN téléphone de client, ne collecte aucune donnée, ne
/// demande pas de compte. Le contact (M2) passera par la conversation et
/// demandera le numéro au moment où il devient nécessaire — jamais avant.
class BusinessProfileScreen extends ConsumerWidget {
  const BusinessProfileScreen({
    super.key,
    required this.type,
    required this.slug,
    this.previewItem,
  });

  final String type;
  final String slug;

  /// Item de découverte déjà chargé, pour un premier rendu immédiat.
  final DiscoveryItem? previewItem;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);
    final discoveryState = ref.watch(discoveryControllerProvider);

    final args = BusinessProfileArgs(
      type: type,
      slug: slug,
      name: previewItem?.name ?? '',
      fallbackItem: previewItem == null
          ? null
          : {
              'slug': previewItem!.slug,
              'name': previewItem!.name,
              'type': previewItem!.type,
              'category': previewItem!.category,
              'city': previewItem!.city,
              'description': previewItem!.description,
              'image_url': previewItem!.imageUrl,
              'latitude': previewItem!.latitude,
              'longitude': previewItem!.longitude,
              'rating_avg': previewItem!.ratingAvg,
              'reviews_count': previewItem!.reviewsCount,
            },
      supportedActions: discoveryState.actionsFor(type),
      actionUrls: discoveryState.actionUrls[type] ?? const {},
    );

    final profileAsync = ref.watch(businessProfileProvider(args));

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        body: SafeArea(
          child: profileAsync.when(
            loading: () => Column(
              children: [
                _HeaderBar(
                  strings: strings,
                  title: previewItem?.name ?? '',
                  onBack: () => context.pop(),
                ),
                Expanded(
                    child: LoadingSkeleton(strings: strings, itemCount: 3)),
              ],
            ),
            error: (error, _) => _ErrorView(
              strings: strings,
              error: error,
              onRetry: () => ref.invalidate(businessProfileProvider(args)),
              onBack: () => context.pop(),
            ),
            data: (profile) => _Content(
              strings: strings,
              profile: profile,
              fallbackName: previewItem?.name ?? '',
            ),
          ),
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({
    required this.strings,
    required this.error,
    required this.onRetry,
    required this.onBack,
  });

  final AppStrings strings;
  final Object error;
  final VoidCallback onRetry;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final failure =
        error is PublicApiFailure ? error as PublicApiFailure : null;

    // 404 : le commerce n'est plus publié. C'est une information, pas une
    // panne — surtout quand la personne arrive par un QR code imprimé il y a
    // six mois.
    if (failure?.kind == PublicFailureKind.notFound) {
      return NotFoundState(strings: strings, onBack: onBack);
    }

    // Hors ligne sans cache : bandeau + retour possible.
    if (failure?.kind == PublicFailureKind.network ||
        failure?.kind == PublicFailureKind.timeout) {
      return OfflineEmptyState(strings: strings, onRetry: onRetry);
    }

    return ErrorState(strings: strings, onRetry: onRetry);
  }
}

class _HeaderBar extends StatelessWidget {
  const _HeaderBar({
    required this.strings,
    required this.title,
    required this.onBack,
  });

  final AppStrings strings;
  final String title;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Row(
        children: [
          IconButton(
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back),
            tooltip: strings.t('commonBack'),
          ),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.title.copyWith(fontSize: 16),
            ),
          ),
        ],
      ),
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({
    required this.strings,
    required this.profile,
    required this.fallbackName,
  });

  final AppStrings strings;
  final BusinessProfile profile;
  final String fallbackName;

  @override
  Widget build(BuildContext context) {
    final isOpen = profile.isOpenAt(DateTime.now());

    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(
          child: Stack(
            children: [
              AspectRatio(
                aspectRatio: 16 / 9,
                child: _CoverImage(url: profile.imageUrl),
              ),
              SafeArea(
                child: IconButton(
                  onPressed: () {
                    if (context.canPop()) context.pop();
                  },
                  icon: const CircleAvatar(
                    backgroundColor: Colors.black45,
                    child:
                        Icon(Icons.arrow_back, color: Colors.white, size: 20),
                  ),
                  tooltip: strings.t('commonBack'),
                ),
              ),
            ],
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              Row(
                children: [
                  Expanded(
                    child: Text(
                      profile.name.isEmpty ? fallbackName : profile.name,
                      style: AppTypography.title.copyWith(
                        color: AppColors.textPrimaryFor(context),
                      ),
                    ),
                  ),
                  if (profile.verified) _VerifiedBadge(strings: strings),
                ],
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  if (isOpen != null)
                    _StatusBadge(
                      label: isOpen
                          ? strings.t('businessOpenNow')
                          : strings.t('businessClosedNow'),
                      color: isOpen ? AppColors.success : AppColors.danger,
                    ),
                  if (profile.category != null)
                    _StatusBadge(
                      label: profile.category!,
                      color: AppColors.textSecondaryFor(context),
                    ),
                  if (profile.ratingAvg != null)
                    _StatusBadge(
                      label:
                          '★ ${profile.ratingAvg!.toStringAsFixed(1)}${profile.reviewsCount != null ? ' (${profile.reviewsCount})' : ''}',
                      color: AppColors.warning,
                    ),
                ],
              ),
              const SizedBox(height: 14),

              // Les actions : SOURCE UNIQUE = supportedActions().
              BusinessActionRow(
                strings: strings,
                profile: profile,
              ),
              const SizedBox(height: 18),

              if (profile.description != null) ...[
                Text(
                  strings.t('businessAbout'),
                  style: AppTypography.title.copyWith(fontSize: 15),
                ),
                const SizedBox(height: 6),
                Text(
                  profile.description!,
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondaryFor(context),
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 18),
              ],

              if (profile.address != null) ...[
                Text(
                  strings.t('businessAddress'),
                  style: AppTypography.title.copyWith(fontSize: 15),
                ),
                const SizedBox(height: 6),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.place_outlined,
                        size: 18, color: AppColors.textSecondaryFor(context)),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        [profile.address, profile.city]
                            .whereType<String>()
                            .join(', '),
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
              ],

              if (profile.hours.isNotEmpty) ...[
                BusinessHoursCard(strings: strings, hours: profile.hours),
                const SizedBox(height: 18),
              ],

              if (profile.menu.isNotEmpty) ...[
                BusinessMenuCard(
                  strings: strings,
                  sections: profile.menu,
                  currency: profile.currency,
                ),
                const SizedBox(height: 18),
              ],
            ]),
          ),
        ),
        SliverToBoxAdapter(
          child: _SecondaryActions(
            strings: strings,
            profile: profile,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: 32)),
      ],
    );
  }
}

/// Badge « vérifié » : présent seulement si le serveur l'affirme.
class _VerifiedBadge extends StatelessWidget {
  const _VerifiedBadge({required this.strings});

  final AppStrings strings;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.tint(context, AppColors.info, lightAlpha: 0.14),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.verified, size: 13, color: AppColors.info),
          const SizedBox(width: 4),
          Text(
            strings.t('businessVerified'),
            style: AppTypography.caption.copyWith(
              color: AppColors.info,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.tint(context, color, lightAlpha: 0.14),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: AppTypography.caption.copyWith(
          color: color,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _CoverImage extends StatelessWidget {
  const _CoverImage({this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      color: AppColors.tint(context, AppColors.info, lightAlpha: 0.12),
      child: Icon(Icons.storefront_outlined, size: 56, color: AppColors.info),
    );

    if (url == null) return fallback;

    return Image.network(
      url!,
      fit: BoxFit.cover,
      // La couverture est large (pleine largeur × 16:9) : on plafonne le
      // décodage à 1080 px de large, suffisant sur tous les téléphones et
      // bien plus léger sur connexion lente.
      cacheWidth: 1080,
      errorBuilder: (_, __, ___) => fallback,
      loadingBuilder: (context, child, progress) =>
          progress == null ? child : fallback,
    );
  }
}

/// Actions secondaires : itinéraire, partage, signalement.
///
/// Elles sont TOUJOURS présentes (ce sont des actions de navigation, pas des
/// capacités du commerçant), mais aucune ne dépend de `supportedActions()`.
class _SecondaryActions extends StatelessWidget {
  const _SecondaryActions({required this.strings, required this.profile});

  final AppStrings strings;
  final BusinessProfile profile;

  Future<void> _openDirections(BuildContext context) async {
    final lat = profile.latitude;
    final lng = profile.longitude;

    // Deux destinations : coordonnées si on les a, sinon une recherche par
    // nom + adresse. Ouvrir Google Maps en dur exclurait les utilisateurs
    // d'autres app de cartes ; `geo:` laisse le téléphone choisir.
    final uri = (lat != null && lng != null)
        ? Uri.parse(
            'geo:$lat,$lng?q=$lat,$lng(${Uri.encodeComponent(profile.name)})')
        : Uri.parse(
            'geo:0,0?q=${Uri.encodeComponent('${profile.name} ${profile.address ?? ''}'.trim())}');

    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      // Aucune app de cartes : on ne laisse pas un bouton muet.
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(strings.t('stateNotAvailable'))),
      );
    }
  }

  Future<void> _share(BuildContext context) async {
    final text = profile.name.isEmpty ? '' : profile.name;
    final uri = Uri.parse(
      'https://leopardo.app/${profile.type}/${profile.slug}',
    );

    final message = strings
        .t('commonShareText')
        .replaceFirst('%s', text.isEmpty ? uri.toString() : '$text — $uri');

    // `share_plus` n'est pas une dépendance M1 : on partage par un intent
    // système minimal, sans ajouter un paquet pour un seul bouton.
    await Clipboard.setData(ClipboardData(text: message));
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(strings.t('businessShare'))),
    );
  }

  void _report(BuildContext context) {
    // Le signalement est une fonctionnalité MODÉRÉE (elle crée une entrée
    // côté plateforme). Elle est visible mais pas encore branchée : prétendre
    // l'inverse serait un faux succès. M3 la branche sur l'endpoint existant.
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(strings.t('commonNoModule'))),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _openDirections(context),
              icon: const Icon(Icons.directions_outlined, size: 18),
              label: Text(strings.t('businessItinerary')),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _share(context),
              icon: const Icon(Icons.ios_share, size: 18),
              label: Text(strings.t('businessShare')),
            ),
          ),
          const SizedBox(width: 8),
          IconButton(
            onPressed: () => _report(context),
            icon: const Icon(Icons.flag_outlined, size: 20),
            tooltip: strings.t('businessReport'),
          ),
        ],
      ),
    );
  }
}
