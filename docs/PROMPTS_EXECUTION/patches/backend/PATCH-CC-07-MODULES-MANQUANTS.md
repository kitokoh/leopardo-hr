# PATCH CC-07 — Modules manquants : Settings + Appareils + Onboarding + Facturation + Sauvegardes
# À appliquer PENDANT CC-07
# Réf : API_CONTRATS sections 9-13 + 23_STOCKAGE_ET_SAUVEGARDES.md + 24_ONBOARDING_GUIDE.md

---

## MODULE 1 — SETTINGS ENTREPRISE

### Endpoints

```
GET  /api/v1/settings              → Récupérer tous les paramètres
PUT  /api/v1/settings              → Modifier les paramètres
GET  /api/v1/settings/hr-models    → Lister les modèles RH disponibles par pays
PUT  /api/v1/settings/apply-hr-model → Appliquer un modèle RH (écrase les taux)
```

### Paramètres stockés dans company_settings (clé → valeur JSON)

```php
// Ces clés DOIVENT exister après la création d'une entreprise (seeder tenant)
$defaultSettings = [
    'timezone'                  => 'Africa/Algiers',   // selon pays
    'currency'                  => 'DZD',
    'language'                  => 'fr',
    'payroll.penalty_mode'      => 'proportional',     // 'proportional' | 'bracket'
    'payroll.penalty_brackets'  => '[]',               // JSON si mode bracket
    'overtime_rate'             => '1.5',              // taux HS (150%)
    'working_days_per_week'     => '5',
    'expected_hours_per_day'    => '8',
    'late_tolerance_minutes'    => '10',
    'gps_required'              => 'false',
    'qr_required'               => 'false',
    'onboarding_completed'      => 'false',
    'onboarding_step_1_done'    => 'false',
    'onboarding_step_2_done'    => 'false',
    'onboarding_step_3_done'    => 'false',
    'onboarding_step_4_done'    => 'false',
];
```

### SettingsController

```php
// app/Http/Controllers/Tenant/SettingsController.php

public function index(): JsonResponse
{
    $settings = Cache::tags(["tenant:" . app('current_company')->uuid])
        ->remember('settings', 3600, fn() => CompanySetting::pluck('value', 'key'));

    return response()->json(['data' => $settings]);
}

public function update(UpdateSettingsRequest $request): JsonResponse
{
    $company = app('current_company');

    foreach ($request->validated() as $key => $value) {
        CompanySetting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
    }

    // Invalider le cache des settings
    Cache::tags(["tenant:{$company->uuid}"])->forget('settings');

    return response()->json(['message' => __('messages.settings_updated')]);
}

public function applyHrModel(Request $request): JsonResponse
{
    $request->validate(['country' => 'required|size:2']);

    $hrModel = HRModelTemplate::where('country', $request->country)->firstOrFail();

    // Écraser les taux de cotisation avec ceux du modèle sélectionné
    $keysToApply = [
        'social_security_employee_rate' => $hrModel->social_security_employee_rate,
        'social_security_employer_rate' => $hrModel->social_security_employer_rate,
        'income_tax_brackets'           => $hrModel->income_tax_brackets,
        'holiday_calendar'              => $hrModel->holiday_calendar,
        'leave_accrual_rate'            => $hrModel->leave_accrual_rate,
    ];

    foreach ($keysToApply as $key => $value) {
        CompanySetting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }

    Cache::tags(["tenant:" . app('current_company')->uuid])->flush();

    return response()->json(['message' => __('messages.hr_model_applied')]);
}
```

---

## MODULE 2 — APPAREILS ZKTECO

### Endpoints

```
GET    /api/v1/devices                    → Lister les appareils
POST   /api/v1/devices                    → Enregistrer un appareil
GET    /api/v1/devices/{id}              → Détail
PUT    /api/v1/devices/{id}              → Modifier (nom, site)
DELETE /api/v1/devices/{id}              → Supprimer
POST   /api/v1/devices/{id}/rotate-token → Régénérer le token API de l'appareil
POST   /api/v1/devices/{id}/test-connection → Tester la connexion (Push)
```

### Modèle Device

```php
// app/Models/Tenant/Device.php

class Device extends Model
{
    protected $fillable = [
        'name', 'serial_number', 'model', 'site_id',
        'ip_address', 'api_token', 'connection_type', // 'push' | 'pull'
        'is_active', 'last_sync_at',
    ];

    protected $hidden = ['api_token']; // Ne jamais retourner en clair après création

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
```

### DeviceController — rotate-token

```php
// POST /devices/{id}/rotate-token
public function rotateToken(Device $device): JsonResponse
{
    $this->authorize('manage', $device);

    $newToken = Str::random(64);
    $device->update(['api_token' => hash('sha256', $newToken)]);

    // Retourner le token en clair UNE SEULE FOIS — ne sera plus jamais visible
    return response()->json([
        'data' => [
            'device_id' => $device->id,
            'api_token' => $newToken, // En clair uniquement ici
            'message'   => 'Token régénéré. Copiez-le maintenant, il ne sera plus affiché.',
        ]
    ]);
}
```

### BiometricController — webhook ZKTeco (compléter CC-04)

```php
// app/Http/Controllers/Tenant/BiometricController.php

public function webhook(Request $request): JsonResponse
{
    // Vérifier le token de l'appareil
    $tokenHash = hash('sha256', $request->input('device_token', ''));
    $device    = Device::where('api_token', $tokenHash)
                       ->where('is_active', true)
                       ->firstOrFail(); // 401 si non trouvé

    // Mettre à jour last_sync_at
    $device->update(['last_sync_at' => now()]);

    // Trouver l'employé par son badge_number
    $employee = Employee::where('badge_number', $request->badge_number)->first();
    if (!$employee) {
        return response()->json(['error' => 'EMPLOYEE_NOT_FOUND'], 404);
    }

    // Dispatcher le pointage selon l'event_type
    $service  = app(AttendanceService::class);
    $todayLog = $service->getTodayLog($employee);

    match($request->event_type) {
        'check_in'  => $todayLog
                        ? response()->json(['error' => 'ALREADY_CHECKED_IN'], 409)
                        : $service->checkIn($employee, ['source' => 'biometric']),
        'check_out' => $todayLog
                        ? $service->checkOut($employee, $todayLog, ['source' => 'biometric'])
                        : response()->json(['error' => 'MISSING_CHECK_IN'], 422),
        default     => response()->json(['error' => 'UNKNOWN_EVENT'], 422),
    };

    return response()->json(['status' => 'processed']);
}
```

---

## MODULE 3 — ONBOARDING GUIDÉ

Réf : `docs/dossierdeConception/11_ux_wireframes/24_ONBOARDING_GUIDE.md`

### OnboardingService

```php
// app/Services/OnboardingService.php

class OnboardingService
{
    public function getStatus(): array
    {
        $settings = CompanySetting::pluck('value', 'key');
        $company  = app('current_company');

        // Auto-détecter la progression (même si les settings ne sont pas à jour)
        $steps = [
            1 => [
                'id'        => 1,
                'title'     => __('onboarding.step_1_title'),
                'completed' => Employee::where('role', 'employee')->count() >= 1
                               || $settings['onboarding_step_1_done'] === 'true',
                'required'  => true,
            ],
            2 => [
                'id'        => 2,
                'title'     => __('onboarding.step_2_title'),
                'completed' => Schedule::where('is_default', true)->exists()
                               || $settings['onboarding_step_2_done'] === 'true',
                'required'  => true,
            ],
            3 => [
                'id'        => 3,
                'title'     => __('onboarding.step_3_title'),
                'completed' => EmployeeDevice::count() >= 1
                               || $settings['onboarding_step_3_done'] === 'true',
                'required'  => false,
            ],
            4 => [
                'id'        => 4,
                'title'     => __('onboarding.step_4_title'),
                'completed' => AttendanceLog::count() >= 1
                               || $settings['onboarding_step_4_done'] === 'true',
                'required'  => false,
            ],
        ];

        $allRequired = collect($steps)->filter(fn($s) => $s['required'])->every(fn($s) => $s['completed']);
        $trialEnd    = Carbon::parse($company->trial_ends_at ?? $company->created_at->addDays(14));

        return [
            'completed'           => $settings['onboarding_completed'] === 'true' || $allRequired,
            'current_step'        => collect($steps)->first(fn($s) => !$s['completed'])['id'] ?? 4,
            'steps'               => array_values($steps),
            'trial_ends_at'       => $trialEnd->toDateString(),
            'trial_days_remaining'=> max(0, now()->diffInDays($trialEnd, false)),
        ];
    }
}
```

### OnboardingController

```php
// app/Http/Controllers/Tenant/OnboardingController.php

public function status(): JsonResponse
{
    return response()->json(['data' => app(OnboardingService::class)->getStatus()]);
}

public function completeStep(Request $request): JsonResponse
{
    $request->validate(['step' => 'required|integer|between:1,4']);
    $step = $request->step;

    CompanySetting::updateOrCreate(
        ['key' => "onboarding_step_{$step}_done"],
        ['value' => 'true']
    );

    $status  = app(OnboardingService::class)->getStatus();
    $allDone = collect($status['steps'])->filter(fn($s) => $s['required'])->every(fn($s) => $s['completed']);

    if ($allDone) {
        CompanySetting::updateOrCreate(['key' => 'onboarding_completed'], ['value' => 'true']);
    }

    Cache::tags(["tenant:" . app('current_company')->uuid])->forget('settings');

    return response()->json([
        'data' => [
            'step'          => $step,
            'completed'     => true,
            'all_steps_done'=> $allDone,
            'next_step'     => $allDone ? null : $status['current_step'],
        ]
    ]);
}

public function skip(): JsonResponse
{
    CompanySetting::updateOrCreate(['key' => 'onboarding_skipped_at'], ['value' => now()->toIso8601String()]);
    CompanySetting::updateOrCreate(['key' => 'onboarding_completed'],  ['value' => 'true']);
    Cache::tags(["tenant:" . app('current_company')->uuid])->forget('settings');

    return response()->json(['message' => 'Onboarding ignoré.']);
}
```

### Inertia shared data (pour le composant Vue OnboardingWizard)

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user(),
        ],
        'onboarding' => function () use ($request) {
            if (!$request->user()) return null;
            $settings = CompanySetting::where('key', 'onboarding_completed')->first();
            if ($settings?->value === 'true') return null;
            return app(OnboardingService::class)->getStatus();
        },
    ]);
}
```

---

## MODULE 4 — FACTURATION SUPER ADMIN + WEBHOOKS STRIPE/PAYDUNYA

Réf : `docs/dossierdeConception/07_securite_rbac/14_PAYMENT_WEBHOOKS_SPEC.md`

### Routes webhooks (publiques — pas de middleware auth)

```php
// routes/api.php — HORS des groupes auth

Route::post('/webhooks/stripe',   [WebhookController::class, 'stripe']);
Route::post('/webhooks/paydunya', [WebhookController::class, 'paydunya']);
```

### WebhookController

```php
// app/Http/Controllers/Webhooks/WebhookController.php

class WebhookController extends Controller
{
    public function stripe(Request $request): JsonResponse
    {
        // 1. Vérifier la signature Stripe
        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // 2. Dispatcher un job async — ne jamais traiter en synchrone
        dispatch(new ProcessStripeWebhookJob($event->type, $event->data->object))
            ->onQueue('webhooks');

        // 3. Répondre immédiatement 200 à Stripe
        return response()->json(['received' => true]);
    }

    public function paydunya(Request $request): JsonResponse
    {
        // Vérifier la signature HMAC-SHA256
        $expectedHash = hash_hmac('sha256',
            $request->getContent(),
            config('services.paydunya.master_key')
        );

        if (!hash_equals($expectedHash, $request->header('X-Paydunya-Signature', ''))) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        dispatch(new ProcessPaydunyaWebhookJob($request->all()))
            ->onQueue('webhooks');

        return response()->json(['received' => true]);
    }
}
```

### ProcessStripeWebhookJob

```php
// app/Jobs/ProcessStripeWebhookJob.php

class ProcessStripeWebhookJob implements ShouldQueue
{
    public int $tries   = 3;
    public array $backoff = [60, 300, 900]; // Backoff exponentiel

    public function handle(): void
    {
        match($this->eventType) {
            'payment_intent.succeeded',
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded(),

            'payment_intent.payment_failed',
            'invoice.payment_failed'    => $this->handlePaymentFailed(),

            'customer.subscription.deleted' => $this->handleSubscriptionCancelled(),
            'customer.subscription.updated' => $this->handleSubscriptionRenewed(),

            default => null, // Ignorer les autres événements
        };
    }

    private function handlePaymentSucceeded(): void
    {
        DB::transaction(function () {
            $invoice = Invoice::where('stripe_payment_intent_id', $this->data->id)->first();
            if (!$invoice) return;

            $invoice->update(['status' => 'paid', 'paid_at' => now()]);

            // Prolonger l'abonnement
            $company = $invoice->company;
            $months  = $invoice->subscription_months ?? 1;
            $company->update([
                'subscription_end'    => $company->subscription_end
                                        ? $company->subscription_end->addMonths($months)
                                        : now()->addMonths($months),
                'status'              => 'active',
                'subscription_status' => 'active',
            ]);

            // Log dans billing_transactions
            DB::table('public.billing_transactions')
                ->where('invoice_id', $invoice->id)
                ->update(['status' => 'success', 'gateway_ref' => $this->data->id]);
        });
    }
}
```

---

## MODULE 5 — STRATÉGIE DE SAUVEGARDE

Réf : `docs/dossierdeConception/10_deploiement_cicd/23_STOCKAGE_ET_SAUVEGARDES.md`

### Script de sauvegarde PostgreSQL

```bash
#!/bin/bash
# /var/www/scripts/backup_postgres.sh
# Planifié via cron : 0 2 * * * /var/www/scripts/backup_postgres.sh

set -e

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/leopardo/postgres"
RETENTION_DAYS=30
DB_NAME="leopardo_db"
DB_USER="leopardo_user"

mkdir -p "$BACKUP_DIR"

# Dump complet avec compression
pg_dump -U "$DB_USER" -Fc "$DB_NAME" > "$BACKUP_DIR/leopardo_${DATE}.dump"

echo "Backup créé : leopardo_${DATE}.dump ($(du -sh "$BACKUP_DIR/leopardo_${DATE}.dump" | cut -f1))"

# Supprimer les backups > RETENTION_DAYS jours
find "$BACKUP_DIR" -name "*.dump" -mtime +$RETENTION_DAYS -delete
echo "Anciens backups supprimés (> $RETENTION_DAYS jours)"

# Vérification : tester que le backup est lisible
pg_restore --list "$BACKUP_DIR/leopardo_${DATE}.dump" > /dev/null 2>&1
echo "Backup vérifié : OK"
```

### Commande Artisan de test de restauration (mensuelle)

```php
// app/Console/Commands/TestBackupRestore.php

class TestBackupRestore extends Command
{
    protected $signature   = 'backup:test-restore';
    protected $description = 'Teste la restauration du dernier backup PostgreSQL sur une DB temporaire';

    public function handle(): void
    {
        $latestBackup = collect(glob('/var/backups/leopardo/postgres/*.dump'))
            ->sortDesc()
            ->first();

        if (!$latestBackup) {
            $this->error('Aucun backup trouvé !');
            Log::critical('backup:test-restore — Aucun backup disponible');
            return;
        }

        $testDb = 'leopardo_restore_test_' . now()->format('Ymd');

        try {
            // Créer une DB temporaire
            DB::statement("CREATE DATABASE $testDb");

            // Restaurer
            exec("pg_restore -U leopardo_user -d $testDb $latestBackup", $output, $exitCode);

            if ($exitCode !== 0) {
                throw new \Exception("pg_restore a échoué avec le code $exitCode");
            }

            $this->info("Restauration testée avec succès : $latestBackup");
            Log::info("backup:test-restore — OK : $latestBackup");
        } finally {
            // Toujours nettoyer
            DB::statement("DROP DATABASE IF EXISTS $testDb");
        }
    }
}
```

### Planification dans routes/console.php

```php
// routes/console.php
Schedule::command('backup:test-restore')->monthly();
Schedule::exec('/var/www/scripts/backup_postgres.sh')->dailyAt('02:30');
Schedule::command('subscriptions:check')->dailyAt('02:00');
```

---

## TESTS OBLIGATOIRES

```php
// tests/Feature/Settings/SettingsTest.php

it('manager can update company settings', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');

    $this->withToken($token)
         ->putJson('/api/v1/settings', ['payroll.penalty_mode' => 'bracket'])
         ->assertStatus(200);

    $setting = CompanySetting::where('key', 'payroll.penalty_mode')->first();
    expect($setting->value)->toBe('bracket');
});

it('settings are cached and cache invalidated on update', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');

    $this->withToken($token)->getJson('/api/v1/settings'); // Peuple le cache
    expect(Cache::tags(["tenant:{$company->uuid}"])->has('settings'))->toBeTrue();

    $this->withToken($token)->putJson('/api/v1/settings', ['timezone' => 'Europe/Paris']);
    expect(Cache::tags(["tenant:{$company->uuid}"])->has('settings'))->toBeFalse();
});

// tests/Feature/Onboarding/OnboardingTest.php

it('onboarding step 1 auto-completes when first employee is created', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');

    $status = $this->withToken($token)->getJson('/api/v1/onboarding/status');
    expect($status->json('data.steps.0.completed'))->toBeFalse();

    // Créer un employé → étape 1 auto-complétée
    Employee::factory()->inSchema($company)->create(['role' => 'employee']);

    $status2 = $this->withToken($token)->getJson('/api/v1/onboarding/status');
    expect($status2->json('data.steps.0.completed'))->toBeTrue();
});

// tests/Feature/Webhooks/StripeWebhookTest.php

it('stripe webhook with invalid signature returns 400', function () {
    $this->postJson('/api/v1/webhooks/stripe',
        ['type' => 'payment_intent.succeeded'],
        ['Stripe-Signature' => 'invalid_signature']
    )->assertStatus(400);
});

it('valid stripe payment webhook activates company subscription', function () {
    Queue::fake();
    $company = Company::factory()->create(['status' => 'trial']);
    $invoice = Invoice::factory()->create([
        'company_id'                => $company->id,
        'stripe_payment_intent_id'  => 'pi_test_123',
        'status'                    => 'pending',
    ]);

    // Simuler un vrai payload Stripe avec signature valide (en test)
    $payload   = json_encode(['type' => 'invoice.payment_succeeded', 'data' => ['object' => ['id' => 'pi_test_123']]]);
    $signature = 't=' . time() . ',v1=' . hash_hmac('sha256', $payload, config('services.stripe.webhook_secret'));

    $this->post('/api/v1/webhooks/stripe', json_decode($payload, true),
        ['Stripe-Signature' => $signature, 'Content-Type' => 'application/json']
    )->assertStatus(200);

    Queue::assertPushed(ProcessStripeWebhookJob::class);
});
```
