library;

import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../i18n/app_strings.dart';

/// Les états obligatoires, dans UN seul endroit.
///
/// ## Pourquoi un fichier dédié plutôt que des `if` dans chaque écran
///
/// Le cahier des charges impose 7 états sur CHAQUE écran. Recopiés écran par
/// écran, ils divergent : ici le 404 s'affiche en rouge « erreur », là en gris
/// « rien à voir ». Pire, le 404 devient une panne — exactement ce que la
/// consigne interdit (« 404 : jamais présenté comme une panne »).
///
/// Chaque widget ci-dessous porte son état en propre et ne propose une action
/// QUE si elle a un sens : « Réessayer » sur un 404 est un piège (on rejoue une
/// requête qui ne peut pas réussir), « Élargir » sur une erreur réseau aussi.
///
/// ## Accessibilité
///
/// Chaque état est un noeud de sémantique unique : un lecteur d'écran annonce
/// « Aucun résultat. Essayez un autre mot » d'un bloc, pas trois fragments. Le
/// squelette de chargement est marqué `liveRegion` pour être annoncé.
/// Squelette de chargement — forme de la liste finale, pas un spinner plein
/// écran : l'écran ne « saute » pas quand les données arrivent.
class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({
    super.key,
    required this.strings,
    this.itemCount = 6,
    this.height = 96,
  });

  final AppStrings strings;
  final int itemCount;
  final double height;

  @override
  Widget build(BuildContext context) {
    final base = AppColors.isDark(context)
        ? AppColors.surfaceFor(context)
        : AppColors.borderFor(context);

    return Semantics(
      label: strings.t('stateLoading'),
      liveRegion: true,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: itemCount,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) => _ShimmerBox(height: height, base: base),
      ),
    );
  }
}

class _ShimmerBox extends StatefulWidget {
  const _ShimmerBox({required this.height, required this.base});

  final double height;
  final Color base;

  @override
  State<_ShimmerBox> createState() => _ShimmerBoxState();
}

class _ShimmerBoxState extends State<_ShimmerBox>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween<double>(begin: 0.45, end: 0.9).animate(_controller),
      child: Container(
        height: widget.height,
        decoration: BoxDecoration(
          color: widget.base,
          borderRadius: BorderRadius.circular(16),
        ),
      ),
    );
  }
}

/// État vide : la ville n'a rien publié. Ton bienveillant, pas « aucune donnée ».
class EmptyContentState extends StatelessWidget {
  const EmptyContentState({super.key, required this.strings, this.onRetry});

  final AppStrings strings;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return _StateScaffold(
      icon: Icons.storefront_outlined,
      tone: _StateTone.neutral,
      title: strings.t('stateEmptyTitle'),
      body: strings.t('stateEmptyBody'),
      action: onRetry == null
          ? null
          : _StateAction(
              label: strings.t('stateRetry'),
              onPressed: onRetry!,
              primary: false,
            ),
    );
  }
}

/// Aucun résultat pour la recherche courante — propose d'ÉLARGIR, ce qui est
/// une action différente de « réessayer ».
class NoResultsState extends StatelessWidget {
  const NoResultsState({super.key, required this.strings, this.onWiden});

  final AppStrings strings;
  final VoidCallback? onWiden;

  @override
  Widget build(BuildContext context) {
    return _StateScaffold(
      icon: Icons.search_off_outlined,
      tone: _StateTone.neutral,
      title: strings.t('stateNoResultsTitle'),
      body: strings.t('stateNoResultsBody'),
      action: onWiden == null
          ? null
          : _StateAction(
              label: strings.t('stateNoResultsWiden'),
              onPressed: onWiden!,
              primary: true,
            ),
    );
  }
}

/// Erreur récupérable (réseau rétabli, serveur de nouveau disponible).
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.strings, this.onRetry});

  final AppStrings strings;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return _StateScaffold(
      icon: Icons.cloud_off_outlined,
      tone: _StateTone.error,
      title: strings.t('stateErrorTitle'),
      body: strings.t('stateErrorBody'),
      action: onRetry == null
          ? null
          : _StateAction(
              label: strings.t('stateRetry'),
              onPressed: onRetry!,
              primary: true,
            ),
    );
  }
}

/// Hors ligne : on AFFICHE ce qu'on avait, avec un bandeau qui le dit.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key, required this.strings});

  final AppStrings strings;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: strings.t('stateOfflineTitle'),
      child: Container(
        width: double.infinity,
        color: AppColors.tint(context, AppColors.warning, lightAlpha: 0.16),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            Icon(Icons.wifi_off_rounded, size: 18, color: AppColors.warning),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    strings.t('stateOfflineTitle'),
                    style: AppTypography.bodySmall.copyWith(
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimaryFor(context),
                    ),
                  ),
                  Text(
                    strings.t('stateOfflineBody'),
                    style: AppTypography.caption.copyWith(
                      color: AppColors.textSecondaryFor(context),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Hors ligne SANS cache : l'état lui-même, pas une liste vide trompeuse.
class OfflineEmptyState extends StatelessWidget {
  const OfflineEmptyState({super.key, required this.strings, this.onRetry});

  final AppStrings strings;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return _StateScaffold(
      icon: Icons.wifi_off_rounded,
      tone: _StateTone.warning,
      title: strings.t('stateOfflineTitle'),
      body: strings.t('stateErrorBody'),
      action: onRetry == null
          ? null
          : _StateAction(
              label: strings.t('stateRetry'),
              onPressed: onRetry!,
              primary: false,
            ),
    );
  }
}

/// Localisation refusée — propose le choix manuel de ville.
///
/// Ce n'est PAS une erreur : c'est une décision légitime de la personne. Le ton
/// est neutre et l'action est « choisir ma ville », jamais « autoriser ».
class LocationDeniedState extends StatelessWidget {
  const LocationDeniedState({
    super.key,
    required this.strings,
    this.onChooseCity,
  });

  final AppStrings strings;
  final VoidCallback? onChooseCity;

  @override
  Widget build(BuildContext context) {
    return _StateScaffold(
      icon: Icons.location_off_outlined,
      tone: _StateTone.neutral,
      title: strings.t('discoveryCityLabel'),
      body: strings.t('discoveryLocationDenied'),
      action: onChooseCity == null
          ? null
          : _StateAction(
              label: strings.t('discoveryCityHint'),
              onPressed: onChooseCity!,
              primary: true,
            ),
    );
  }
}

/// 404 — présenté comme une INFORMATION, pas comme une panne.
///
/// Aucune icône d'alerte rouge, aucun « réessayer » : la ressource n'existe
/// plus, réessayer ne la fera pas revenir. L'action proposée ramène vers un
/// endroit qui fonctionne.
class NotFoundState extends StatelessWidget {
  const NotFoundState({super.key, required this.strings, this.onBack});

  final AppStrings strings;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return _StateScaffold(
      icon: Icons.travel_explore_outlined,
      tone: _StateTone.neutral,
      title: strings.t('stateNotFoundTitle'),
      body: strings.t('stateNotFoundBody'),
      action: onBack == null
          ? null
          : _StateAction(
              label: strings.t('stateNotFoundAction'),
              onPressed: onBack!,
              primary: true,
            ),
    );
  }
}

enum _StateTone { neutral, error, warning }

class _StateScaffold extends StatelessWidget {
  const _StateScaffold({
    required this.icon,
    required this.title,
    required this.body,
    this.action,
    this.tone = _StateTone.neutral,
  });

  final IconData icon;
  final String title;
  final String body;
  final Widget? action;
  final _StateTone tone;

  @override
  Widget build(BuildContext context) {
    final iconColor = switch (tone) {
      _StateTone.error => AppColors.danger,
      _StateTone.warning => AppColors.warning,
      _StateTone.neutral => AppColors.textSecondaryFor(context),
    };

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: Semantics(
          container: true,
          label: '$title. $body',
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ExcludeSemantics(
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: AppColors.tint(context, iconColor),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, size: 34, color: iconColor),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                title,
                textAlign: TextAlign.center,
                style: AppTypography.title.copyWith(
                  color: AppColors.textPrimaryFor(context),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                body,
                textAlign: TextAlign.center,
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
              if (action != null) ...[const SizedBox(height: 20), action!],
            ],
          ),
        ),
      ),
    );
  }
}

class _StateAction extends StatelessWidget {
  const _StateAction({
    required this.label,
    required this.onPressed,
    required this.primary,
  });

  final String label;
  final VoidCallback onPressed;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    if (!primary) {
      return OutlinedButton(onPressed: onPressed, child: Text(label));
    }
    return FilledButton(onPressed: onPressed, child: Text(label));
  }
}
