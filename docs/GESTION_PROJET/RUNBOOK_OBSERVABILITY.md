# RUNBOOK - OBSERVABILITY (SENTRY)

Version 4.1.67 | 2026-04-22

## 1. Objectif

Tracer les erreurs (back-end + mobile) avec Sentry pour permettre une reaction
rapide aux incidents P1/P2.

Sentry est **optionnel** tant que le DSN n'est pas fourni. L'app fonctionne
identiquement sans. Cela evite de forcer une dependance externe non consentie
sur les environnements dev/pre-prod.

## 2. Sentry backend (Laravel)

### 2.1 Installation

```bash
cd api
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=${SENTRY_DSN_BACKEND}
```

### 2.2 Variables d'environnement a ajouter dans `.env` (prod)

```env
SENTRY_LARAVEL_DSN=https://public@sentry.example.com/1
SENTRY_TRACES_SAMPLE_RATE=0.1       # 10% des requetes (ajuster selon volume)
SENTRY_PROFILES_SAMPLE_RATE=0.0     # profiling desactive par defaut
SENTRY_SEND_DEFAULT_PII=false       # RGPD : jamais de PII par defaut
SENTRY_RELEASE=${RENDER_GIT_COMMIT} # traque la version
SENTRY_ENVIRONMENT=production
```

### 2.3 Contexte tenant (obligatoire)

Ajouter dans `app/Http/Middleware/TenantMiddleware.php`, apres le
`SET search_path` :

```php
if (app()->bound('sentry') && isset($company)) {
    app('sentry')->configureScope(function ($scope) use ($company): void {
        $scope->setTag('tenant.id', (string) $company->id);
        $scope->setTag('tenant.slug', $company->slug);
        $scope->setTag('tenant.type', $company->tenancy_type);
    });
}
```

Les evenements Sentry seront taggues par tenant sans exposer de PII.

### 2.4 Exclure les exceptions metier attendues

Dans `bootstrap/app.php` (Laravel 11) :

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->dontReport([
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ]);
})
```

### 2.5 Verification

Apres deploy :
1. Deployer avec `SENTRY_LARAVEL_DSN` set
2. Exposer un test : `php artisan tinker` -> `throw new \RuntimeException('sentry drill');`
3. Verifier que l'evenement apparait dans le projet Sentry avec les tags
   `tenant.*` presents (si tenant resolu)

## 3. Sentry mobile (Flutter)

### 3.1 Installation

```yaml
# mobile/pubspec.yaml
dependencies:
  sentry_flutter: ^8.9.0
```

```bash
cd mobile && flutter pub get
```

### 3.2 Initialisation

```dart
// mobile/lib/main.dart
import 'package:sentry_flutter/sentry_flutter.dart';

Future<void> main() async {
  const dsn = String.fromEnvironment('SENTRY_DSN_MOBILE', defaultValue: '');

  if (dsn.isEmpty) {
    // DSN non fourni (dev local) : on saute l'init pour eviter de forcer
    // le sandboxing hors ligne.
    runApp(const ProviderScope(child: LeopardoApp()));
    return;
  }

  await SentryFlutter.init(
    (options) {
      options.dsn = dsn;
      options.tracesSampleRate = 0.1;
      options.environment = const String.fromEnvironment(
        'SENTRY_ENVIRONMENT',
        defaultValue: 'production',
      );
      options.release = const String.fromEnvironment('APP_VERSION');
      options.sendDefaultPii = false;
    },
    appRunner: () => runApp(const ProviderScope(child: LeopardoApp())),
  );
}
```

### 3.3 Build

```bash
flutter build apk --release \
  --dart-define=SENTRY_DSN_MOBILE=$SENTRY_DSN_MOBILE \
  --dart-define=SENTRY_ENVIRONMENT=production \
  --dart-define=APP_VERSION=$(git rev-parse --short HEAD)
```

### 3.4 Contexte employee

Une fois l'employe hydrate via `AuthRepository.checkAuth()` :

```dart
Sentry.configureScope((scope) {
  scope.setUser(SentryUser(
    id: employee.id.toString(),
    // pas d'email / pas de nom : RGPD first
  ));
  scope.setTag('tenant.id', employee.companyId ?? 'unknown');
  scope.setTag('tenant.role', employee.role ?? 'employee');
  scope.setTag('tenant.manager_role', employee.managerRole ?? 'none');
});
```

## 4. Alertes recommandees

Configurer dans Sentry :

| Alerte | Seuil | Canal |
|---|---|---|
| > 10 erreurs / min sur le backend | 5 min | PagerDuty / mobile |
| > 5 erreurs / min sur mobile | 10 min | Slack |
| `/api/v1/auth/login` > 5% erreurs | 5 min | PagerDuty |
| Nouvelle exception jamais vue | 1 occurrence | Slack |
| Regression (issue reouverte apres resolution) | 1 occurrence | Slack + email |

## 5. Retention / RGPD

- `sendDefaultPii = false` sur backend et mobile (pas d'IP, pas d'email)
- Les tags `tenant.*` et `user.id` suffisent pour router un incident
- Retention Sentry : 30 jours pour le plan actuel (ajuster selon contrat)
- Aucun payload applicatif n'est envoye (pas de salaire, pas de pointages)

## 6. References

- `/api/v1/health` : checks live+ready DB/Redis/storage (cf. `HealthController`)
- Incident P1 : `RUNBOOK_INCIDENT_P1.md`
- Rollback : `RUNBOOK_ROLLBACK.md`
