# SPÉCIFICATION CheckSubscription MIDDLEWARE — LEOPARDO RH
# Version 1.0 | Mars 2026
# Référence : bootstrap/app.php alias 'sub.check'

---

## RÈGLES D'EXPIRATION PAR STATUT

### Statut `trial`
```
J0 → J14   : Accès complet (période d'essai)
J15+ sans upgrade vers payant :
  → companies.status = 'suspended' (cron quotidien 02h00)
  → Données conservées 30 jours
  → J+30 après suspension : suppression complète (cron mensuel)
```

### Statut `active` (abonnement payant)
```
subscription_end ≥ NOW()              : Accès complet
subscription_end < NOW() ≤ J+3       : PÉRIODE DE GRÂCE — accès lecture seule
                                        API retourne header : X-Subscription-Grace: true
                                        Banner d'avertissement dans le dashboard web
subscription_end + 3 jours < NOW()   : Accès bloqué
                                        → companies.status = 'suspended'
                                        → API retourne 403 SUBSCRIPTION_EXPIRED
                                        → Données conservées 60 jours
                                        → J+60 après suspension : purge (cron mensuel)
```

---

## IMPLÉMENTATION LARAVEL

```php
// app/Http/Middleware/CheckSubscription.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    // Jours de grâce après expiration avant blocage complet
    private const GRACE_DAYS = 3;

    public function handle(Request $request, Closure $next): mixed
    {
        $company = app('current_company'); // Injecté par TenantMiddleware avant

        // Toujours bloquer si suspendu manuellement par Super Admin
        if ($company->status === 'suspended') {
            return response()->json([
                'error'   => 'ACCOUNT_SUSPENDED',
                'message' => __('errors.ACCOUNT_SUSPENDED'),
            ], 403);
        }

        $now  = now();
        $end  = $company->subscription_end;
        $diff = $now->diffInDays($end, false); // négatif si dépassé

        // Expiré depuis plus de GRACE_DAYS jours → bloquer
        if ($diff < -self::GRACE_DAYS) {
            // Mettre à jour le statut si pas encore fait
            if ($company->status !== 'suspended') {
                $company->update(['status' => 'suspended']);
            }
            return response()->json([
                'error'   => 'SUBSCRIPTION_EXPIRED',
                'message' => __('errors.SUBSCRIPTION_EXPIRED'),
            ], 403);
        }

        // Dans la période de grâce : autoriser mais avertir
        if ($diff < 0) {
            $response = $next($request);
            $response->headers->set('X-Subscription-Grace', 'true');
            $response->headers->set('X-Subscription-Grace-Days-Left', (string)(self::GRACE_DAYS + $diff));
            return $response;
        }

        // Abonnement valide
        return $next($request);
    }
}
```

---

## CRON — CheckExpiredSubscriptions

```php
// app/Console/Commands/CheckExpiredSubscriptions.php
// Planifié dans routes/console.php : Schedule::command('subscriptions:check')->dailyAt('02:00')

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    public function handle(): void
    {
        // 1. Suspendre les trials expirés (> 14 jours sans conversion)
        Company::where('status', 'trial')
            ->where('created_at', '<', now()->subDays(14))
            ->update(['status' => 'suspended']);

        // 2. Suspendre les abonnements expirés depuis > GRACE_DAYS
        Company::where('status', 'active')
            ->where('subscription_end', '<', now()->subDays(3))
            ->update(['status' => 'suspended']);

        // 3. Purger les données des comptes suspendus depuis > 30 jours (trial)
        //    et > 60 jours (payant) — SOFT DELETE puis suppression schéma
        // → Implémenté dans TenantService::purgeExpiredTenant()

        $this->info('Subscription check completed at ' . now());
    }
}
```

---

## COMPORTEMENT CÔTÉ FLUTTER

```dart
// lib/shared/services/dio_service.dart — Intercepteur d'erreurs

// Cas 403 SUBSCRIPTION_EXPIRED
if (response.statusCode == 403 &&
    response.data['error'] == 'SUBSCRIPTION_EXPIRED') {
  // Naviguer vers un écran dédié (pas logout — données accessibles en lecture)
  router.go('/subscription-expired');
  return;
}

// Cas header X-Subscription-Grace
if (response.headers['x-subscription-grace']?.first == 'true') {
  final daysLeft = int.parse(
    response.headers['x-subscription-grace-days-left']?.first ?? '0'
  );
  // Afficher un SnackBar d'avertissement non bloquant
  showGracePeriodBanner(daysLeft);
}
```

---

## ÉCRAN FLUTTER — SubscriptionExpiredScreen

```dart
// lib/features/subscription/presentation/subscription_expired_screen.dart
// Affiché quand API retourne 403 SUBSCRIPTION_EXPIRED
// Contient : message explicatif + bouton "Contacter le support" + numéro/email
// L'employé peut encore consulter ses bulletins PDF déjà téléchargés (locaux)
// Mais ne peut plus pointer, soumettre d'absences, etc.
```

---

## ENREGISTREMENT DANS LES ROUTES

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'tenant', 'sub.check'])->group(function () {
    // Toutes les routes tenant passent par sub.check
    // sub.check vient APRÈS tenant (besoin de current_company injecté)
});

// Routes exemptées de sub.check (auth uniquement) :
// POST /auth/login
// POST /auth/register
// POST /auth/forgot-password
// POST /auth/reset-password
```

---

## TEST OBLIGATOIRE

```php
// tests/Feature/SubscriptionTest.php

it('blocks access after grace period', function () {
    $company = Company::factory()->create([
        'status'           => 'active',
        'subscription_end' => now()->subDays(4), // 4 jours après expiration = hors grâce
    ]);
    $token = Employee::factory()->for($company)->createWithToken();

    $this->withToken($token)
         ->getJson('/api/v1/employees')
         ->assertStatus(403)
         ->assertJson(['error' => 'SUBSCRIPTION_EXPIRED']);
});

it('allows read access during grace period with warning header', function () {
    $company = Company::factory()->create([
        'status'           => 'active',
        'subscription_end' => now()->subDays(2), // 2 jours après = dans la grâce
    ]);
    $token = Employee::factory()->for($company)->createWithToken();

    $response = $this->withToken($token)->getJson('/api/v1/employees');

    $response->assertStatus(200);
    $response->assertHeader('X-Subscription-Grace', 'true');
});

it('suspends trial after 14 days without upgrade', function () {
    $company = Company::factory()->create([
        'status'     => 'trial',
        'created_at' => now()->subDays(15),
    ]);

    $this->artisan('subscriptions:check');

    expect($company->fresh()->status)->toBe('suspended');
});
```
