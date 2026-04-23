import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';

/// Page d'accueil non authentifiee (landing).
///
/// Objectif : presenter en quelques ecrans ce que Leopardo RH apporte a un
/// employe, puis l'orienter vers la connexion ou la demande d'invitation.
/// Les benefices affiches sont volontairement tournes vers l'employe lui-meme
/// (son parcours, ses documents, ses heures) pour que l'app ait de la valeur
/// meme en dehors d'une entreprise active.
class WelcomeScreen extends StatefulWidget {
  const WelcomeScreen({super.key});

  @override
  State<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends State<WelcomeScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  static const List<_WelcomeFeature> _features = <_WelcomeFeature>[
    _WelcomeFeature(
      icon: Icons.fingerprint,
      title: 'Pointez en un geste',
      description:
          'Check-in, check-out, pauses : votre pointage est sauvegarde meme hors ligne et synchronise des que vous retrouvez du reseau.',
      accent: AppColors.rh,
      accentDark: AppColors.rhDark,
    ),
    _WelcomeFeature(
      icon: Icons.insights,
      title: 'Suivez votre temps reel',
      description:
          'Total d\'heures travaillees, heures supplementaires, jours presents : gardez une vue claire de votre mois et de votre carriere.',
      accent: AppColors.info,
      accentDark: AppColors.securityDark,
    ),
    _WelcomeFeature(
      icon: Icons.folder_shared_outlined,
      title: 'Votre coffre personnel',
      description:
          'Classez vos diplomes, contrats et pieces d\'identite dans un espace securise qui vous suit d\'une entreprise a l\'autre.',
      accent: AppColors.finance,
      accentDark: AppColors.financeDark,
    ),
    _WelcomeFeature(
      icon: Icons.notifications_active_outlined,
      title: 'Restez connecte a vos employeurs',
      description:
          'Recevez les annonces et notifications des societes qui vous ont recrute, gardez votre historique meme si vous changez de travail.',
      accent: AppColors.ia,
      accentDark: AppColors.iaDark,
    ),
  ];

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final mediaQuery = MediaQuery.of(context);
    final screenHeight = mediaQuery.size.height;
    final isCompact = screenHeight < 720;

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      body: Stack(
        children: [
          const _AmbientBackground(),
          SafeArea(
            child: Column(
              children: [
                SizedBox(height: isCompact ? 12 : 24),
                const _BrandHeader(),
                SizedBox(height: isCompact ? 16 : 28),
                Expanded(
                  child: PageView.builder(
                    controller: _pageController,
                    itemCount: _features.length,
                    onPageChanged: (index) =>
                        setState(() => _currentPage = index),
                    itemBuilder: (context, index) =>
                        _FeatureSlide(feature: _features[index]),
                  ),
                ),
                const SizedBox(height: 16),
                _PageDots(count: _features.length, current: _currentPage),
                SizedBox(height: isCompact ? 16 : 24),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: _CallToAction(),
                ),
                SizedBox(height: isCompact ? 12 : 20),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _WelcomeFeature {
  const _WelcomeFeature({
    required this.icon,
    required this.title,
    required this.description,
    required this.accent,
    required this.accentDark,
  });

  final IconData icon;
  final String title;
  final String description;
  final Color accent;
  final Color accentDark;
}

class _AmbientBackground extends StatelessWidget {
  const _AmbientBackground();

  @override
  Widget build(BuildContext context) {
    return Positioned.fill(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.bgDark,
              AppColors.rhDark.withValues(alpha: 0.25),
              AppColors.bgDark,
              AppColors.iaDark.withValues(alpha: 0.18),
            ],
            stops: const [0.0, 0.35, 0.65, 1.0],
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              top: -120,
              left: -80,
              child: _GlowOrb(
                size: 260,
                color: AppColors.rh.withValues(alpha: 0.35),
              ),
            ),
            Positioned(
              bottom: -140,
              right: -100,
              child: _GlowOrb(
                size: 320,
                color: AppColors.ia.withValues(alpha: 0.28),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GlowOrb extends StatelessWidget {
  const _GlowOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [color, color.withValues(alpha: 0.0)],
            stops: const [0.0, 1.0],
          ),
        ),
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 72,
          height: 72,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.rh, AppColors.rhDark],
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.rh.withValues(alpha: 0.35),
                blurRadius: 24,
                spreadRadius: 2,
              ),
            ],
          ),
          child: const Center(
            child: Text(
              'L',
              style: TextStyle(
                fontFamily: AppTypography.fontFamily,
                fontSize: 34,
                fontWeight: FontWeight.w700,
                color: Colors.white,
                height: 1,
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Text(
          'Leopardo RH',
          style: AppTypography.title.copyWith(
            color: AppColors.textDark,
            letterSpacing: 0.2,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Votre carriere, a portee de main',
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textMutedDark,
          ),
        ),
      ],
    );
  }
}

class _FeatureSlide extends StatelessWidget {
  const _FeatureSlide({required this.feature});

  final _WelcomeFeature feature;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 280;
        final iconBoxSize = compact ? 108.0 : 156.0;
        final iconSize = compact ? 48.0 : 68.0;
        final gapAfterIcon = compact ? 18.0 : 32.0;
        return SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 4),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: iconBoxSize,
                  height: iconBoxSize,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        feature.accent.withValues(alpha: 0.22),
                        feature.accentDark.withValues(alpha: 0.15),
                      ],
                    ),
                    border: Border.all(
                      color: feature.accent.withValues(alpha: 0.35),
                      width: 1.5,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: feature.accent.withValues(alpha: 0.25),
                        blurRadius: 30,
                        spreadRadius: 2,
                      ),
                    ],
                  ),
                  child: Icon(
                    feature.icon,
                    size: iconSize,
                    color: feature.accent,
                  ),
                ),
                SizedBox(height: gapAfterIcon),
                Text(
                  feature.title,
                  textAlign: TextAlign.center,
                  style: AppTypography.display.copyWith(
                    color: AppColors.textDark,
                    fontSize: 24,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  feature.description,
                  textAlign: TextAlign.center,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textMutedDark,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _PageDots extends StatelessWidget {
  const _PageDots({required this.count, required this.current});

  final int count;
  final int current;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List<Widget>.generate(count, (i) {
        final active = i == current;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 240),
          curve: Curves.easeOut,
          margin: const EdgeInsets.symmetric(horizontal: 4),
          width: active ? 22 : 8,
          height: 8,
          decoration: BoxDecoration(
            color: active ? AppColors.rh : AppColors.borderDark,
            borderRadius: BorderRadius.circular(4),
          ),
        );
      }),
    );
  }
}

class _CallToAction extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: 52,
          child: ElevatedButton(
            onPressed: () => context.go('/login'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.rh,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              textStyle: AppTypography.subtitle,
            ),
            child: const Text('Se connecter'),
          ),
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 52,
          child: OutlinedButton(
            onPressed: () => context.go('/register'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.textDark,
              side: BorderSide(
                color: AppColors.borderDark.withValues(alpha: 0.8),
                width: 1.2,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              textStyle: AppTypography.subtitle,
            ),
            child: const Text('Creer un compte'),
          ),
        ),
        const SizedBox(height: 14),
        Text(
          'Invite par votre employeur ? Utilisez le lien recu par email.',
          textAlign: TextAlign.center,
          style: AppTypography.caption.copyWith(
            color: AppColors.textMutedDark,
          ),
        ),
      ],
    );
  }
}
