import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/l10n/l10n.dart';

class WelcomeScreen extends StatefulWidget {
  const WelcomeScreen({super.key});

  @override
  State<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends State<WelcomeScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  static List<_StoryCardData> _stories(BuildContext context) {
    final l10n = context.l10n;

    return <_StoryCardData>[
      _StoryCardData(
        title: l10n.welcomeStoryClarityTitle,
        body: l10n.welcomeStoryClarityBody,
        domain: 'ia',
        icon: Icons.forum_outlined,
      ),
      _StoryCardData(
        title: l10n.welcomeStoryFieldTitle,
        body: l10n.welcomeStoryFieldBody,
        domain: 'rh',
        icon: Icons.phone_android_outlined,
      ),
      _StoryCardData(
        title: l10n.welcomeStoryModulesTitle,
        body: l10n.welcomeStoryModulesBody,
        domain: 'finance',
        icon: Icons.dashboard_customize_outlined,
      ),
    ];
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final background = AppColors.backgroundFor(context);
    final compact = MediaQuery.of(context).size.height < 740;
    final stories = _stories(context);
    final l10n = context.l10n;

    return Scaffold(
      backgroundColor: background,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.10),
              background,
              AppColors.tint(context, AppColors.ia, lightAlpha: 0.05),
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 18, 24, 0),
                child: _WelcomeHero(compact: compact),
              ),
              SizedBox(height: compact ? 14 : 22),
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  itemCount: stories.length,
                  onPageChanged: (index) {
                    setState(() {
                      _currentPage = index;
                    });
                  },
                  itemBuilder: (context, index) {
                    return _StoryCard(story: stories[index]);
                  },
                ),
              ),
              const SizedBox(height: 12),
              _Dots(count: stories.length, current: _currentPage),
              Padding(
                padding: EdgeInsets.fromLTRB(
                    24, compact ? 10 : 16, 24, compact ? 14 : 24),
                child: Column(
                  children: [
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () => context.go('/login'),
                        child: Text(l10n.login),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton(
                        onPressed: () => context.go('/register'),
                        child: Text(l10n.employeeInvitationAccess),
                      ),
                    ),
                    const SizedBox(height: 10),
                    SizedBox(
                      width: double.infinity,
                      child: TextButton.icon(
                        onPressed: () => context.go('/user-register'),
                        icon: const Icon(Icons.person_add_outlined, size: 18),
                        label: Text(l10n.createPersonalAccount),
                        style: TextButton.styleFrom(
                          foregroundColor: AppColors.ia,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      l10n.personalAccountExplanation,
                      textAlign: TextAlign.center,
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
      ),
    );
  }
}

class _WelcomeHero extends StatelessWidget {
  const _WelcomeHero({required this.compact});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: EdgeInsets.all(compact ? 16 : 22),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: AppColors.borderFor(context)),
        boxShadow: [
          BoxShadow(
            color: AppColors.rh.withValues(alpha: 0.06),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: compact ? 46 : 58,
                height: compact ? 46 : 58,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppColors.rh, AppColors.rhDark],
                  ),
                ),
                child: Center(
                  child: Text(
                    'L',
                    style: TextStyle(
                      fontFamily: AppTypography.fontFamily,
                      fontWeight: FontWeight.w700,
                      fontSize: compact ? 22 : 28,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.appTitle,
                      style: AppTypography.title.copyWith(color: text),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      context.l10n.welcomeBrandSubtitle,
                      style: AppTypography.bodySmall.copyWith(color: muted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: compact ? 12 : 22),
          Text(
            context.l10n.welcomeHeroTitle,
            style: AppTypography.display.copyWith(
              color: text,
              fontSize: compact ? 24 : 30,
            ),
          ),
          SizedBox(height: compact ? 6 : 10),
          Text(
            context.l10n.welcomeHeroDescription,
            style: AppTypography.body.copyWith(color: muted),
          ),
        ],
      ),
    );
  }
}

class _StoryCardData {
  const _StoryCardData({
    required this.title,
    required this.body,
    required this.domain,
    required this.icon,
  });

  final String title;
  final String body;
  final String domain;
  final IconData icon;
}

class _StoryCard extends StatelessWidget {
  const _StoryCard({required this.story});

  final _StoryCardData story;

  @override
  Widget build(BuildContext context) {
    final compact = MediaQuery.of(context).size.height < 740;
    final color = AppColors.forDomain(story.domain);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 0),
      child: Container(
        padding: EdgeInsets.all(compact ? 16 : 22),
        decoration: BoxDecoration(
          color: AppColors.surfaceFor(context),
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: AppColors.borderFor(context)),
        ),
        child: SingleChildScrollView(
          physics: const NeverScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: compact ? 44 : 56,
                height: compact ? 44 : 56,
                decoration: BoxDecoration(
                  color: AppColors.tint(
                    context,
                    color,
                    lightAlpha: 0.18,
                    darkAlpha: 0.24,
                  ),
                  shape: BoxShape.circle,
                ),
                child: Icon(story.icon, color: color),
              ),
              SizedBox(height: compact ? 12 : 22),
              Text(story.title,
                  style: AppTypography.title.copyWith(color: text)),
              SizedBox(height: compact ? 6 : 10),
              Text(story.body,
                  style: AppTypography.body.copyWith(color: muted)),
              SizedBox(height: compact ? 14 : 22),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _SignalPill(label: 'RH', color: AppColors.rh),
                  _SignalPill(label: 'Finance', color: AppColors.finance),
                  _SignalPill(label: 'Leo', color: AppColors.ia),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SignalPill extends StatelessWidget {
  const _SignalPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.tint(
          context,
          color,
          lightAlpha: 0.16,
          darkAlpha: 0.24,
        ),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(label, style: AppTypography.caption.copyWith(color: color)),
    );
  }
}

class _Dots extends StatelessWidget {
  const _Dots({required this.count, required this.current});

  final int count;
  final int current;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List<Widget>.generate(count, (index) {
        final active = index == current;

        return AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          margin: const EdgeInsets.symmetric(horizontal: 4),
          width: active ? 24 : 8,
          height: 8,
          decoration: BoxDecoration(
            color: active ? AppColors.rh : AppColors.borderFor(context),
            borderRadius: BorderRadius.circular(999),
          ),
        );
      }),
    );
  }
}
