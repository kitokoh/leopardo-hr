import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_marketing/features/auth/providers/auth_provider.dart';

/// Écran d'accueil provisoire de l'app Marketing.
///
/// Les écrans Calendrier éditorial / Stats / Création de post référençaient une
/// API de design system qui n'existe plus dans `leopardo_core`
/// (MobileSurface-widget, AppColors.primary, AppTypography.headlineMedium…,
/// issue #3155) et ne compilaient pas. Ils sont retirés ; l'app reste un shell
/// navigable en attendant la reprise du produit marketing (#2661).
///
/// Issue #3006 : l'app exige désormais une session (routeur → /login) ;
/// le bouton de déconnexion permet de sortir proprement de la session.
class MarketingHomeScreen extends ConsumerWidget {
  const MarketingHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Leopardo Marketing'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Se déconnecter',
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      body: const Center(
        child: EmptyState(
          icon: Icons.campaign,
          title: 'Espace marketing',
          description:
              'Le calendrier éditorial, les statistiques et la création de post '
              'seront réintégrés dans une prochaine version.',
        ),
      ),
      backgroundColor: AppColors.mobileDarkBg,
    );
  }
}
