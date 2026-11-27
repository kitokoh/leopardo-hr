import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../models/business_profile.dart';

/// Les boutons d'action d'une fiche — pilotés par `supportedActions()`.
///
/// ## La règle, rendue mécanique
///
/// Chaque bouton est construit à partir d'un `ActionSpec` dont la présence
/// dépend de `profile.supports(action)`. Il n'y a AUCUN `if (type == 'restaurant')`
/// dans ce fichier : le jour où une nouvelle verticale déclare `supportedActions()
/// = ['contact', 'booking']`, ses deux boutons apparaissent sans toucher à ce
/// widget — et le jour où une verticale retire une capacité, le bouton
/// disparaît sans intervention.
///
/// C'est exactement l'inverse d'un écran qui afficherait toujours trois boutons
/// et masquerait les morts par un `onPressed: null` codé en dur.
///
/// ## Trois états par bouton
///
/// 1. **Capable + destination exposée** → bouton actif.
/// 2. **Capable + pas de destination mobile** → bouton affiché, désactivé, avec
///    l'explication : la personne sait que le commerce accepte, et que ce n'est
///    pas encore disponible ici.
/// 3. **Non capable** → aucune trace. Pas de bouton grisé qui évoque une
///    fonctionnalité inexistante.
class BusinessActionRow extends StatelessWidget {
  const BusinessActionRow({
    super.key,
    required this.strings,
    required this.profile,
    this.onContact,
    this.onAction,
    this.nativeActions = const <String>{},
  });

  final AppStrings strings;
  final BusinessProfile profile;

  /// Ouvre la passerelle de demande (`POST /public/contact-requests`).
  final VoidCallback? onContact;

  /// Action de fiche servie par un écran natif (`order`, `booking`).
  final ValueChanged<String>? onAction;

  /// Actions natives déclarées par le registre des parcours (#9907).
  ///
  /// C'est lui, et non `actionUrls`, qui décide qu'une commande est jouable
  /// dans l'app : une verticale peut exposer une URL web sans avoir de contrat
  /// public appelable (`restaurant`).
  final Set<String> nativeActions;

  /// Ordre d'affichage STABLE : le geste principal du parcours critique
  /// (« Contacter ») est toujours à la même place, sinon la personne le cherche
  /// et dépasse les 3 touches autorisées.
  static const List<String> _order = ['contact', 'order', 'booking'];

  @override
  Widget build(BuildContext context) {
    final specs = <_ActionSpec>[];

    for (final action in _order) {
      if (!profile.supports(action)) continue;

      final label = switch (action) {
        'contact' => strings.t('businessContact'),
        'order' => strings.t('businessOrder'),
        'booking' => strings.t('businessBooking'),
        _ => action,
      };

      final icon = switch (action) {
        'contact' => Icons.chat_bubble_outline,
        'order' => Icons.shopping_bag_outlined,
        'booking' => Icons.event_available_outlined,
        _ => Icons.touch_app_outlined,
      };

      final hasDestination = profile.actionUrls[action] != null;
      // « contact » a une destination native (la passerelle de demande) ; les
      // autres dépendent soit d'une URL exposée par le serveur, soit d'un
      // écran natif déclaré par le registre des parcours.
      final reachable = action == 'contact'
          ? onContact != null
          : (hasDestination || nativeActions.contains(action));

      specs.add(
        _ActionSpec(
          action: action,
          label: label,
          icon: icon,
          onPressed:
              reachable ? () => _handle(context, action, hasDestination) : null,
          primary: specs.isEmpty,
        ),
      );
    }

    if (specs.isEmpty) {
      // Aucune capacité : on le DIT. Une fiche sans aucun bouton laisse croire
      // à un bug de chargement.
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.tint(context, AppColors.textSecondaryFor(context)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          strings.t('businessActionsUnavailable'),
          style: AppTypography.caption.copyWith(
            color: AppColors.textSecondaryFor(context),
          ),
        ),
      );
    }

    return Column(
      children: [
        // Premier bouton en pleine largeur : c'est l'action attendue
        // (Contacter dans l'écrasante majorité des cas).
        _Button(spec: specs.first),
        if (specs.length > 1) ...[
          const SizedBox(height: 8),
          Row(
            children: [
              for (var i = 1; i < specs.length; i++) ...[
                if (i > 1) const SizedBox(width: 8),
                Expanded(child: _Button(spec: specs[i], compact: true)),
              ],
            ],
          ),
        ],
      ],
    );
  }

  void _handle(BuildContext context, String action, bool hasDestination) {
    if (action == 'contact') {
      onContact?.call();
      return;
    }

    // Commande / réservation : l'écran appelant sait s'il existe un parcours
    // natif pour cette verticale. Sinon, la destination est web (ou absente) :
    // on le dit plutôt que d'ouvrir un écran qui n'existe pas.
    final handler = onAction;
    if (handler != null && (nativeActions.contains(action) || hasDestination)) {
      handler(action);
      return;
    }

    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(strings.t('commonNoModule'))));
  }
}

class _ActionSpec {
  const _ActionSpec({
    required this.action,
    required this.label,
    required this.icon,
    required this.onPressed,
    required this.primary,
  });

  final String action;
  final String label;
  final IconData icon;
  final VoidCallback? onPressed;
  final bool primary;
}

class _Button extends StatelessWidget {
  const _Button({required this.spec, this.compact = false});

  final _ActionSpec spec;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    // Un bouton sans destination n'est pas « caché » : il est visible et
    // désactivé, ce qui est une information honnête sur l'état du service.
    final enabled = spec.onPressed != null;

    final label = Text(spec.label, overflow: TextOverflow.ellipsis);
    final icon = Icon(spec.icon, size: 18);

    if (compact) {
      return SizedBox(
        height: 44,
        child: OutlinedButton.icon(
          onPressed: spec.onPressed,
          icon: icon,
          label: label,
        ),
      );
    }

    return SizedBox(
      width: double.infinity,
      height: 48,
      child: enabled
          ? FilledButton.icon(
              onPressed: spec.onPressed,
              icon: icon,
              label: label,
            )
          : FilledButton.tonalIcon(onPressed: null, icon: icon, label: label),
    );
  }
}
