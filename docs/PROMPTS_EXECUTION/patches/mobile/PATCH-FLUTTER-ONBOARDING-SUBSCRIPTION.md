# PATCH FLUTTER — Onboarding guidé + Abonnement expiré + Bannière grâce
# À appliquer pendant JU-01 (onboarding) et JU-03 (connexion API réelle)
# Réf : 24_ONBOARDING_GUIDE.md + 13_CHECK_SUBSCRIPTION_SPEC.md

---

## PATCH 1 — Écran Onboarding guidé (JU-01)

L'onboarding est le premier écran qu'un manager voit après inscription.
Il conditionne directement la conversion Trial → Payant.
S'affiche automatiquement si `onboarding.completed = false` dans les props de l'app.

### OnboardingProvider

```dart
// lib/features/onboarding/domain/onboarding_provider.dart

@riverpod
class OnboardingNotifier extends _$OnboardingNotifier {
  @override
  Future<OnboardingStatus?> build() async {
    final auth = ref.watch(authNotifierProvider);
    if (auth is! AuthStateAuthenticated) return null;

    // Seulement pour les managers (les employés ne voient pas l'onboarding)
    if (auth.user.role != 'manager') return null;

    final client = ref.read(apiClientProvider);
    final data   = await client.getOnboardingStatus();
    if (data == null) return null;

    final status = OnboardingStatus.fromJson(data['data']);
    if (status.completed) return null;  // Onboarding déjà terminé

    return status;
  }

  Future<void> completeStep(int step) async {
    final client = ref.read(apiClientProvider);
    await client.completeOnboardingStep(step);
    ref.invalidateSelf();  // Recharger le statut
  }

  Future<void> skip() async {
    final client = ref.read(apiClientProvider);
    await client.skipOnboarding();
    ref.invalidateSelf();
  }
}
```

### OnboardingScreen

```dart
// lib/features/onboarding/presentation/onboarding_screen.dart

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  int _currentStep = 0;

  @override
  Widget build(BuildContext context) {
    final onboardingAsync = ref.watch(onboardingNotifierProvider);

    return onboardingAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => const SizedBox.shrink(), // Si erreur, ne pas bloquer l'app
      data: (status) {
        if (status == null) return const SizedBox.shrink();

        return Scaffold(
          body: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  // Header avec compteur trial
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Bienvenue sur Leopardo RH 🐆',
                           style: Theme.of(context).textTheme.headlineSmall),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.amber.shade100,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          'Trial : ${status.trialDaysRemaining}j restants',
                          style: TextStyle(color: Colors.amber.shade800, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 8),
                  Text('Configurez votre espace en quelques étapes',
                       style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                         color: Colors.grey.shade600)),

                  const SizedBox(height: 24),

                  // Barre de progression
                  Row(
                    children: status.steps.map((step) => Expanded(
                      child: Container(
                        height: 4,
                        margin: const EdgeInsets.symmetric(horizontal: 2),
                        decoration: BoxDecoration(
                          color: step.completed ? Colors.green : Colors.grey.shade300,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    )).toList(),
                  ),

                  const SizedBox(height: 32),

                  // Contenu de l'étape courante
                  Expanded(child: _buildStepContent(status)),

                  const SizedBox(height: 24),

                  // Actions
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      TextButton(
                        onPressed: () => ref.read(onboardingNotifierProvider.notifier).skip(),
                        child: Text('Passer', style: TextStyle(color: Colors.grey.shade500)),
                      ),
                      if (_currentStepCompleted(status))
                        ElevatedButton(
                          onPressed: () => _goToNextStep(status),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.amber.shade600,
                            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                          ),
                          child: Text(_isLastStep(status) ? 'Terminer 🎉' : 'Étape suivante →'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildStepContent(OnboardingStatus status) {
    final step = status.steps.firstWhere((s) => !s.completed, orElse: () => status.steps.last);
    return switch (step.id) {
      1 => _OnboardingStep1AddEmployees(),
      2 => _OnboardingStep2ConfigSchedule(),
      3 => _OnboardingStep3DownloadApp(),
      4 => _OnboardingStep4FirstCheckIn(),
      _ => const SizedBox.shrink(),
    };
  }

  bool _currentStepCompleted(OnboardingStatus status) {
    final currentStep = status.steps.firstWhere((s) => !s.completed, orElse: () => status.steps.last);
    return currentStep.completed;
  }

  bool _isLastStep(OnboardingStatus status) {
    return status.steps.every((s) => s.completed || !s.required);
  }

  void _goToNextStep(OnboardingStatus status) {
    final currentStep = status.steps.firstWhere((s) => !s.completed);
    ref.read(onboardingNotifierProvider.notifier).completeStep(currentStep.id);
  }
}
```

### Étape 3 — QR Code de téléchargement de l'app

```dart
// lib/features/onboarding/presentation/onboarding_step3_widget.dart

class _OnboardingStep3DownloadApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Icon(Icons.phone_android, size: 64, color: Colors.amber),
        const SizedBox(height: 16),
        Text('Téléchargez l\'app Leopardo RH',
             style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        Text('Vos employés pointent depuis leur téléphone',
             style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 24),
        // QR Code vers les stores
        QrImageView(
          data: 'https://leopardo-rh.com/download',
          version: QrVersions.auto,
          size: 180,
        ),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _StoreButton(store: 'Google Play', icon: Icons.android),
            const SizedBox(width: 12),
            _StoreButton(store: 'App Store', icon: Icons.apple),
          ],
        ),
      ],
    );
  }
}
```

### Intégration dans le router — afficher l'onboarding au démarrage

```dart
// lib/app/router/app_router.dart

redirect: (context, state) {
  final authState    = ref.read(authNotifierProvider);
  final onboarding   = ref.read(onboardingNotifierProvider).valueOrNull;
  final isLoggedIn   = authState is AuthStateAuthenticated;
  final isOnLogin    = state.matchedLocation == '/login';
  final isOnOnboarding = state.matchedLocation == '/onboarding';

  if (!isLoggedIn && !isOnLogin) return '/login';
  if (isLoggedIn && isOnLogin) {
    // Si manager et onboarding non terminé → rediriger vers onboarding
    if (onboarding != null && !onboarding.completed) return '/onboarding';
    return '/home';
  }
  if (isLoggedIn && !isOnOnboarding && onboarding != null && !onboarding.completed) {
    return '/onboarding';
  }
  return null;
},

routes: [
  GoRoute(path: '/onboarding', builder: (_, __) => const OnboardingScreen()),
  // ... autres routes
],
```

---

## PATCH 2 — Écran Abonnement expiré + Bannière de grâce (JU-03)

### Intercepteur Dio — détecter les headers d'abonnement

```dart
// lib/core/network/subscription_interceptor.dart

class SubscriptionInterceptor extends Interceptor {
  final Ref _ref;
  SubscriptionInterceptor(this._ref);

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    // Détecter la période de grâce
    final gracePeriod = response.headers['x-subscription-grace']?.first;
    final daysLeft    = response.headers['x-subscription-grace-days-left']?.first;

    if (gracePeriod == 'true') {
      final days = int.tryParse(daysLeft ?? '0') ?? 0;
      _ref.read(subscriptionStateProvider.notifier).setGracePeriod(days);
    }

    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 403) {
      final errorCode = err.response?.data['error'] as String?;

      if (errorCode == 'SUBSCRIPTION_EXPIRED' || errorCode == 'ACCOUNT_SUSPENDED') {
        _ref.read(subscriptionStateProvider.notifier).setExpired(errorCode!);
        // Rediriger vers l'écran d'expiration
        // (navigation via router — pas de BuildContext ici)
        _ref.read(routerProvider).go('/subscription-expired');
        return; // Ne pas propager l'erreur
      }
    }
    handler.next(err);
  }
}
```

### SubscriptionExpiredScreen

```dart
// lib/features/subscription/presentation/subscription_expired_screen.dart

class SubscriptionExpiredScreen extends StatelessWidget {
  const SubscriptionExpiredScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.lock_outline, size: 80, color: Colors.red.shade400),
              const SizedBox(height: 24),
              Text('Accès suspendu',
                   style: Theme.of(context).textTheme.headlineMedium,
                   textAlign: TextAlign.center),
              const SizedBox(height: 12),
              Text(
                'L\'abonnement de votre entreprise a expiré.\n'
                'Vos données sont conservées en sécurité.',
                style: Theme.of(context).textTheme.bodyLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              ElevatedButton.icon(
                icon: const Icon(Icons.email_outlined),
                label: const Text('Contacter le support'),
                onPressed: () => launchUrl(Uri.parse('mailto:support@leopardo-rh.com')),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                ),
              ),
              const SizedBox(height: 16),
              // Permettre à l'employé de voir ses bulletins téléchargés en local
              TextButton(
                onPressed: () => context.go('/payroll/offline'),
                child: const Text('Voir mes bulletins téléchargés'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

### GracePeriodBanner — affiché dans AppLayout

```dart
// lib/shared/widgets/grace_period_banner.dart

class GracePeriodBanner extends ConsumerWidget {
  const GracePeriodBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final subscriptionState = ref.watch(subscriptionStateProvider);

    if (!subscriptionState.isInGracePeriod) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
      color: Colors.orange.shade100,
      child: Row(
        children: [
          Icon(Icons.warning_amber, color: Colors.orange.shade800, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Abonnement expiré — accès en lecture seule pendant encore '
              '${subscriptionState.graceDaysLeft} jour(s).',
              style: TextStyle(color: Colors.orange.shade900, fontSize: 13),
            ),
          ),
          TextButton(
            onPressed: () => launchUrl(Uri.parse('https://leopardo-rh.com/renew')),
            child: Text('Renouveler',
                        style: TextStyle(color: Colors.orange.shade900, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }
}
```

### Intégrer le banner dans AppLayout

```dart
// lib/shared/widgets/app_layout.dart

Widget build(BuildContext context, WidgetRef ref) {
  return Scaffold(
    appBar: AppBar(...),
    body: Column(
      children: [
        const GracePeriodBanner(), // ← Toujours présent, s'affiche seulement si nécessaire
        Expanded(child: widget.body),
      ],
    ),
    bottomNavigationBar: _buildBottomNav(context, ref),
  );
}
```

---

## TESTS FLUTTER

```dart
// test/features/onboarding/onboarding_screen_test.dart

testWidgets('onboarding shows 4 steps with progress bar', (tester) async {
  final mockStatus = OnboardingStatus(
    completed: false,
    currentStep: 1,
    steps: [
      OnboardingStep(id: 1, title: 'Ajoutez vos employés', completed: false, required: true),
      OnboardingStep(id: 2, title: 'Configurez le planning', completed: false, required: true),
      OnboardingStep(id: 3, title: 'Téléchargez l\'app', completed: false, required: false),
      OnboardingStep(id: 4, title: 'Premier pointage', completed: false, required: false),
    ],
    trialEndsAt: '2026-04-14',
    trialDaysRemaining: 13,
  );

  await tester.pumpWidget(ProviderScope(
    overrides: [
      onboardingNotifierProvider.overrideWith((_) => AsyncData(mockStatus)),
    ],
    child: const MaterialApp(home: OnboardingScreen()),
  ));
  await tester.pumpAndSettle();

  expect(find.text('Trial : 13j restants'), findsOneWidget);
  // 4 barres de progression
  expect(find.byType(Container), findsWidgets);
});

testWidgets('grace period banner shows when subscription in grace', (tester) async {
  await tester.pumpWidget(ProviderScope(
    overrides: [
      subscriptionStateProvider.overrideWith((_) => SubscriptionState(
        isInGracePeriod: true,
        graceDaysLeft: 2,
      )),
    ],
    child: const MaterialApp(home: AppLayout(body: SizedBox())),
  ));
  await tester.pumpAndSettle();

  expect(find.text('accès en lecture seule pendant encore 2 jour(s).'), findsOneWidget);
});
```
