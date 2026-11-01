import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../models/discovery_item.dart';

/// Tuile d'un résultat de découverte.
///
/// ## Ce que la tuile ne fait pas
///
/// Elle n'affiche aucun badge « Ouvert / Fermé ». La liste (`/public/discover`)
/// ne porte pas d'indicateur d'ouverture ; l'inventer depuis les horaires
/// exigerait un appel par item. Afficher un statut faux sur une carte de resto
/// est pire que de n'en afficher aucun : la personne se déplace pour rien.
///
/// Le badge de type (`Restaurant`, `Boutique`…) vient de `item.type`, valeur
/// serveur — pas d'une table de correspondance locale qui oublierait une
/// verticale ajoutée demain.
class DiscoveryTile extends StatelessWidget {
  const DiscoveryTile({
    super.key,
    required this.strings,
    required this.item,
    required this.onTap,
  });

  final AppStrings strings;
  final DiscoveryItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final meta = <String>[
      if (item.distanceKm != null) _distance(item.distanceKm!),
      if (item.city != null) item.city!,
      if (item.ratingAvg != null) '★ ${item.ratingAvg!.toStringAsFixed(1)}',
    ];

    return Semantics(
      button: true,
      label: [item.name, if (meta.isNotEmpty) meta.join(' · ')].join('. '),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.surfaceFor(context),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.borderFor(context)),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Thumb(imageUrl: item.imageUrl),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.title.copyWith(
                        fontSize: 15,
                        color: AppColors.textPrimaryFor(context),
                      ),
                    ),
                    if (item.category != null || item.type.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        item.category ?? item.type,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                    ],
                    if (item.description != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        item.description!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                    ],
                    if (meta.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        meta.join('  ·  '),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 4),
              Icon(
                Icons.chevron_right,
                color: AppColors.textSecondaryFor(context),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _distance(double km) {
    if (km < 1) return '${(km * 1000).round()} m';
    return '${km.toStringAsFixed(1)} km';
  }
}

/// Vignette : `Image.network` avec repli local.
///
/// Trois choses que le repli évite, tous observables sur connexion lente :
/// une icône « image cassée » bleue, un trou qui fait sauter la hauteur de la
/// tuile, et une requête réseau qui bloque le premier rendu. Le motif de repli
/// est dessiné, pas chargé.
class _Thumb extends StatelessWidget {
  const _Thumb({this.imageUrl});

  final String? imageUrl;

  static const double _size = 64;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(12);
    final fallback = Container(
      width: _size,
      height: _size,
      decoration: BoxDecoration(
        color: AppColors.tint(context, AppColors.info),
        borderRadius: radius,
      ),
      child: Icon(
        Icons.storefront_outlined,
        color: AppColors.info,
        size: 26,
      ),
    );

    if (imageUrl == null) return fallback;

    return ClipRRect(
      borderRadius: radius,
      child: Image.network(
        imageUrl!,
        width: _size,
        height: _size,
        fit: BoxFit.cover,
        // Budget de poids : la vignette de liste n'a aucune raison de charger
        // l'image pleine résolution. `cacheWidth` fait décoder à la taille
        // d'affichage (×2 pour les écrans denses), ce qui divise la mémoire
        // par 4 à 16 sur les photos de commerce.
        cacheWidth: (_size * 2).round(),
        errorBuilder: (_, __, ___) => fallback,
        loadingBuilder: (context, child, progress) =>
            progress == null ? child : fallback,
      ),
    );
  }
}
