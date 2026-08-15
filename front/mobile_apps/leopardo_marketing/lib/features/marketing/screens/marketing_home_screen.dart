import 'package:flutter/material.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

/// Écran d'accueil provisoire de l'app Marketing.
///
/// Les écrans Calendrier éditorial / Stats / Création de post référençaient une
/// API de design system qui n'existe plus dans `leopardo_core`
/// (MobileSurface-widget, AppColors.primary, AppTypography.headlineMedium…,
/// issue #3155) et ne compilaient pas. Ils sont retirés ; l'app reste un shell
/// navigable en attendant la reprise du produit marketing (#2661).
class MarketingHomeScreen extends StatelessWidget {
  const MarketingHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Leopardo Marketing')),
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
