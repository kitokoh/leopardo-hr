library;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../core/providers/app_providers.dart';

/// Onglets secondaires du M1.
///
/// ## Pourquoi ils existent alors que M2/M3 les rempliront
///
/// L'onglet Messages et l'onglet Activités sont dans la barre de navigation
/// depuis le premier jour : les retirer puis les rajouter ferait apprendre deux
/// fois la même interface. Ce qu'ils contiennent ici, c'est un ÉTAT VIDE HONNÊTE
/// — pas un faux contenu, pas un « bientôt disponible » vague.
///
/// Chaque écran dit exactement ce qui déclenchera son contenu. C'est la
/// différence entre « rien pour l'instant » (rassurant) et « chargement bloqué »
/// (inquiétant).

/// Onglet Messages — vide tant que le compte public n'existe pas (M2).
class MessagesTab extends ConsumerWidget {
  const MessagesTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        appBar: AppBar(title: Text(strings.t('tabMessages'))),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.forum_outlined,
                    size: 44, color: AppColors.textSecondaryFor(context)),
                const SizedBox(height: 14),
                Text(
                  strings.t('messagesEmptyTitle'),
                  textAlign: TextAlign.center,
                  style: AppTypography.title.copyWith(
                    color: AppColors.textPrimaryFor(context),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  strings.t('messagesEmptyBody'),
                  textAlign: TextAlign.center,
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondaryFor(context),
                  ),
                ),
                const SizedBox(height: 18),
                // La séparation Léa / humains est annoncée dès maintenant :
                // découvrir plus tard que l'assistante est une IA serait une
                // surprise désagréable, et la consigne l'interdit.
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppColors.tint(context, AppColors.info,
                        lightAlpha: 0.10),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.smart_toy_outlined,
                          size: 15, color: AppColors.info),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          '${strings.t('messagesAssistant')} · ${strings.t('messagesAssistantBadge')}',
                          style: AppTypography.caption.copyWith(
                            color: AppColors.info,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Onglet Profil — langue, confidentialité, sessions.
///
/// ## Ce que le profil fait VRAIMENT en M1
///
/// Le changement de langue est fonctionnel (écriture de préférence + rebuild).
/// Les autres entrées ouvrent un écran d'explication qui dit ce qui arrivera
/// avec un compte. Aucune entrée ne promet une action impossible : « Supprimer
/// mon compte » sur un utilisateur sans compte afficherait une erreur, on
/// l'explique donc avant.
///
/// ## Aucune mention de tenant, module, BC
///
/// La consigne est explicite : aucun jargon d'architecture visible. Ce profil
/// ne parle que de « votre compte », « vos données », « cet appareil ».
class ProfileTab extends ConsumerWidget {
  const ProfileTab({super.key});

  static const List<({String code, String label})> _languages = [
    (code: 'fr', label: 'Français'),
    (code: 'en', label: 'English'),
    (code: 'ar', label: 'العربية'),
    (code: 'tr', label: 'Türkçe'),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strings = ref.watch(appStringsProvider);

    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        appBar: AppBar(title: Text(strings.t('tabProfile'))),
        body: ListView(
          padding: const EdgeInsets.symmetric(vertical: 8),
          children: [
            // Bandeau « sans compte » : explicite, non anxiogène.
            Container(
              margin: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color:
                    AppColors.tint(context, AppColors.info, lightAlpha: 0.10),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    strings.t('profileNotSignedIn'),
                    style: AppTypography.title.copyWith(
                      fontSize: 15,
                      color: AppColors.textPrimaryFor(context),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    strings.t('profileNotSignedInBody'),
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textSecondaryFor(context),
                    ),
                  ),
                ],
              ),
            ),

            _SectionTitle(label: strings.t('profileLanguage')),
            for (final language in _languages)
              RadioListTile<String>(
                value: language.code,
                // ignore: deprecated_member_use
                groupValue: strings.locale,
                // ignore: deprecated_member_use
                onChanged: (value) => _setLanguage(context, ref, value),
                title: Text(language.label),
                dense: true,
              ),

            const Divider(height: 24),
            _SectionTitle(label: strings.t('profilePrivacy')),
            _InfoTile(
              icon: Icons.my_location_outlined,
              title: strings.t('discoveryUseLocation'),
              subtitle: strings.t('discoveryLocationDenied'),
            ),
            _InfoTile(
              icon: Icons.download_outlined,
              title: strings.t('profileExport'),
              subtitle: strings.t('profileComingSoon'),
            ),
            _InfoTile(
              icon: Icons.delete_outline,
              title: strings.t('profileDelete'),
              subtitle: strings.t('profileComingSoon'),
            ),

            const Divider(height: 24),
            _SectionTitle(label: strings.t('profileSessions')),
            _InfoTile(
              icon: Icons.devices_outlined,
              title: strings.t('profileSessions'),
              subtitle: strings.t('profileComingSoon'),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  /// Écrit la préférence PUIS invalide : le prochain rebuild relit la valeur
  /// persistée, donc l'affichage et le disque ne peuvent pas diverger.
  Future<void> _setLanguage(
    BuildContext context,
    WidgetRef ref,
    String? code,
  ) async {
    if (code == null) return;

    final preferences = ref.read(appPreferencesProvider);
    // `saveLocaleSettings` écrit la langue ET le drapeau RTL : les deux vont
    // ensemble, et une écriture séparée laisserait un état où l'arabe est
    // choisi mais l'interface reste en LTR au prochain lancement.
    await preferences.saveLocaleSettings(
      preferredLanguage: code,
      isRtl: code == 'ar',
    );

    // On invalide le catalogue : il relira la préférence persistée. Aucune
    // valeur n'est posée en mémoire ici, donc l'affichage et le disque ne
    // peuvent pas diverger.
    ref.invalidate(appStringsProvider);
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
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

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, size: 20),
      title: Text(title, style: AppTypography.bodySmall),
      subtitle: Text(
        subtitle,
        style: AppTypography.caption.copyWith(
          color: AppColors.textSecondaryFor(context),
        ),
      ),
      dense: true,
    );
  }
}
