<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionDemoTenantJob;
use App\Modules\Billing\Application\Actions\RequestTrialSignup;
use App\Modules\Billing\Application\Actions\VerifyTrialSignup;
use App\Rules\SupportedCountry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Self-service trial provisioning endpoint.
 *
 * Transforms the guided trial signup into an instant self-service flow:
 * email + company name → tenant created → credentials returned → access in <30s.
 */
class SelfServiceTrialController extends Controller
{
    public function __construct(
        private readonly RequestTrialSignup $requestTrialSignup,
        private readonly VerifyTrialSignup $verifyTrialSignup,
    ) {}

    /**
     * POST /api/v1/trial/signup
     *
     * Creates a trial tenant with a manager account immediately.
     * Returns the credentials so the prospect can log in right away.
     */
    public function signup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'company' => ['required', 'string', 'min:2', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'in:founder,manager,hr,operations,other'],
            'employees' => ['nullable', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            // MULTI-PAYS (#1867) : le pays est obligatoire et doit être un
            // pays supporté du registre (plus de fallback silencieux DZ).
            'country' => ['required', 'string', 'size:2', new SupportedCountry],
            'phone' => ['nullable', 'string', 'max:40'],
            'plan' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:120'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'requestedWorkflow' => ['nullable', 'string', 'in:guided_trial,self_service'],
        ]);

        /** @var array{email: string, company: string, first_name?: string|null, last_name?: string|null, role?: string|null, employees?: string|null, country: string, phone?: string|null, plan?: string|null, source?: string|null, referral_code?: string|null, requestedWorkflow?: string|null} $validated */
        $email = strtolower(trim($validated['email']));

        // Anti-énumération (#3945) : la réponse de signup est UNIFORME que
        // l'email ait déjà un compte manager ou non — la détection
        // « compte déjà existant » est déplacée à l'étape verify, où le
        // client a prouvé la possession de la boîte mail (OTP valide).
        // L'existence est simplement loggée côté serveur ici.
        // Issue #4949 : l'existence d'un manager ne doit JAMAIS produire un
        // 500 brut — une erreur DB/schéma (ex. search_path prod) est convertie
        // en 503 localisé réessayable (même contrat que #4866/#4874). La
        // détection d'email existant est purement informative ici : la réponse
        // reste uniforme (anti-énumération #3945).
        $existingManager = null;
        try {
            $existingManager = $this->requestTrialSignup->findExistingManager($email);
            if ($existingManager) {
                Log::info('trial.signup_duplicate_email_uniform_response', ['email' => $email]);
            }
        } catch (\Throwable $e) {
            Log::error('trial.signup_duplicate_check_failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        if (($validated['requestedWorkflow'] ?? '') === 'guided_trial') {
            if ($existingManager) {
                // Issue #3945 : pas de double provisioning pour un email déjà
                // enregistré : réponse uniforme uniquement (token aléatoire,
                // aucun row/job) — l'anti-énumération prime.
                return new JsonResponse([
                    'success' => true,
                    'message' => __('billing.trial_signup_received'),
                    'data' => [
                        'email' => $email,
                        'status' => 'provisioning_sandbox',
                        'provisioning_token' => Str::random(64),
                    ],
                ], 200);
            }

            // Issue #3951 : un double POST guided_trial (retry réseau, double
            // clic, onglet dupliqué) ne doit PAS créer 2 lignes pending + 2
            // ProvisionDemoTenantJob → 2 tenants sandbox. On réutilise la
            // ligne pending existante (même token, idempotent).
            $existingPending = DB::table('trial_provisionings')
                ->where('email', $email)
                ->where('status', 'pending')
                ->first();

            if ($existingPending) {
                Log::info('trial.signup_existing_pending_reused', ['email' => $email]);

                return new JsonResponse([
                    'success' => true,
                    'message' => __('billing.trial_signup_received'),
                    'data' => [
                        'email' => $email,
                        'status' => 'provisioning_sandbox',
                        'provisioning_token' => $existingPending->provisioning_token,
                    ],
                ], 200);
            }

            // MULTI-PAYS (#1950) : le pays validé du signup est transmis au job
            // (plus de fallback silencieux DZ — invariant 10 de la spec).
            // #2437 : un provisioning_token est créé pour permettre au prospect
            // de poller GET /trial/status sans exposer l'email brut.
            $provisioningToken = Str::random(64);

            try {
                DB::table('trial_provisionings')->insert([
                    'email' => $email,
                    'provisioning_token' => $provisioningToken,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                // Issue #3951 : course entre le check ci-dessus et l'insert —
                // un POST concurrent a gagné (index partiel unique
                // trial_provisionings_pending_email_unique, migration
                // 2026_08_15_000012). 23505 = SQLSTATE unique_violation
                // (pattern PartnerService #3238) : on récupère la ligne
                // gagnante et on répond son token — jamais de 500.
                if ($e->getCode() === '23505') {
                    Log::warning("Trial provisioning race on {$email} — reusing winner row.");

                    $winner = DB::table('trial_provisionings')
                        ->where('email', $email)
                        ->where('status', 'pending')
                        ->firstOrFail();

                    return new JsonResponse([
                        'success' => true,
                        'message' => __('billing.trial_signup_received'),
                        'data' => [
                            'email' => $email,
                            'status' => 'provisioning_sandbox',
                            'provisioning_token' => $winner->provisioning_token,
                        ],
                    ], 200);
                }

                throw $e;
            }

            ProvisionDemoTenantJob::dispatch($email, $validated['company'], $validated['country'], $provisioningToken);

            return new JsonResponse([
                'success' => true,
                'message' => __('billing.trial_signup_received'),
                'data' => [
                    'email' => $email,
                    'status' => 'provisioning_sandbox',
                    'provisioning_token' => $provisioningToken,
                ],
            ], 200);
        }

        // Issue #4866 : le chemin legacy (sans requestedWorkflow ou
        // self_service) échouait en 500 INTERNAL_ERROR quand la création de
        // la CompanyRequest échouait (ex. schéma prod incomplet) — un échec
        // d'écriture n'est pas une erreur client à 422, mais il ne doit
        // JAMAIS devenir un 500 brut : on répond 503 SERVICE_UNAVAILABLE
        // avec un message localisé, le client peut réessayer.
        try {
            $sent = $this->requestTrialSignup->execute($validated);
        } catch (\Throwable $e) {
            Log::error('trial.signup_legacy_failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_SIGNUP_UNAVAILABLE',
                'message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
                'localized_message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
            ], 503);
        }

        // Issue #3057 / #4949 : `execute()` retourne `false` quand l'envoi de
        // l'email OTP a échoué (mailer KO) — la CompanyRequest est conservée
        // mais on ne doit JAMAIS répondre « code envoyé » pour un code jamais
        // parti (état honnête, pas d'écran OTP pour un mail perdu).
        if (! $sent) {
            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_OTP_SEND_FAILED',
                'message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
                'localized_message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
            ], 503);
        }

        return new JsonResponse([
            'success' => true,
            'message' => __('errors.VERIFICATION_CODE_SENT'),
            'data' => [
                'email' => $email,
                'status' => 'pending_verification',
            ],
        ], 200);
    }

    /**
     * GET /api/v1/trial/status?token=...
     *
     * Statut du provisioning d'un essai guidé (#2437). Le prospect reçoit un
     * provisioning_token au signup et peut poller ce endpoint sans exposer
     * son email brut : pending → ready (login_url émis) / failed (raison
     * générique). Le token est vérifié exactement (pas de lookup par email).
     */
    public function status(Request $request): JsonResponse
    {
        // #4931 : le provisioning_token ne doit plus voyager en query string.
        // Header X-Token privilégié (la vitrine passe par le proxy same-origin
        // /api/forms/trial-status qui le positionne) ; ?token= reste accepté
        // en fallback pour les clients déjà déployés (dépréciation).
        $token = trim((string) $request->header('X-Token', ''));
        if ($token === '') {
            $token = trim((string) $request->query('token', ''));
        }

        if ($token === '' || strlen($token) !== 64) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_TOKEN_INVALID',
                'message' => __('billing.trial_status_token_invalid'),
            ], 404);
        }

        $row = DB::table('trial_provisionings')
            ->where('provisioning_token', $token)
            ->first();

        if ($row === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_TOKEN_INVALID',
                'message' => __('billing.trial_status_token_invalid'),
            ], 404);
        }

        // #2903 : provisioned_at est stocké en string (insert DB::table) —
        // ne JAMAIS appeler ->toIso8601String() sur une string (500).
        $provisionedAt = is_scalar($row->provisioned_at) && (string) $row->provisioned_at !== ''
            ? Carbon::parse((string) $row->provisioned_at)->toIso8601String()
            : null;

        $payload = [
            'success' => true,
            'data' => [
                'status' => $row->status,
                'provisioned_at' => $provisionedAt,
            ],
        ];

        // Le lien d'accès n'est exposé qu'une fois le sandbox prêt (jamais
        // dans l'état pending — le prospect n'a rien à ouvrir avant).
        if ($row->status === 'ready' && is_string($row->login_url) && $row->login_url !== '') {
            $payload['data']['login_url'] = $row->login_url;
        }
        if ($row->status === 'failed') {
            $payload['data']['message'] = __('billing.trial_status_failed');
        }

        return new JsonResponse($payload, 200);
    }

    /**
     * POST /api/v1/trial/verify
     *
     * Verifies the OTP and provisions the trial tenant immediately.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var array{email: string, code: string} $validated */
        $email = strtolower(trim($validated['email']));

        // Issue #2903 : le parcours d'essai guidé ne doit JAMAIS exposer un
        // 500 brut « Server Error » (c'était le cas en prod v4.23.5 sur le
        // verify) : toute exception inattendue du provisioning est convertie
        // en réponse structurée réessayable.
        try {
            $result = $this->verifyTrialSignup->execute($email, $validated['code']);
        } catch (\Throwable $e) {
            Log::channel('structured')->error('trial.verify.unexpected', [
                'email' => $email,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_VERIFY_UNAVAILABLE',
                'message' => __('errors.VERIFICATION_TEMPORARILY_UNAVAILABLE'),
            ], 503);
        }

        if ($result['success'] === false) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
                'message' => $result['message'],
            ], $result['status']);
        }

        // La locale HTTP peut rester sur la valeur par défaut (souvent `en`)
        // puisque `/trial/verify` ne reçoit pas le pays. Le provisioning a
        // toutefois résolu et persisté la langue depuis le pays du signup;
        // utiliser cette langue pour le message de succès garantit un contrat
        // cohérent avec la société créée.
        App::setLocale((string) ($result['company']->language ?? config('app.locale', 'en')));

        return new JsonResponse([
            'success' => true,
            'message' => __('errors.TRIAL_SPACE_READY'),
            'data' => [
                'company' => [
                    'id' => $result['company']->id,
                    'name' => $result['company']->name,
                    'slug' => $result['company']->slug,
                ],
                'manager' => [
                    'email' => $result['manager_email'],
                    'first_name' => $result['first_name'],
                    'last_name' => $result['last_name'],
                ],
                'trial' => [
                    'days' => 14,
                    'ends_at' => now()->addDays(14)->toIso8601String(),
                ],
                'next_steps' => [
                    'login' => __('errors.TRIAL_STEP_LOGIN'),
                    'change_password' => __('errors.TRIAL_STEP_CHANGE_PASSWORD'),
                    'add_employees' => __('errors.TRIAL_STEP_ADD_EMPLOYEES'),
                ],
            ],
        ], 201);
    }
}
