<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Http\Controllers\Controller;
use App\Jobs\ProvisionDemoTenantJob;
use App\Mail\TrialVerificationMail;
use App\Modules\Billing\Application\Actions\RequestTrialSignup;
use App\Modules\Billing\Application\Actions\VerifyTrialSignup;
use App\Rules\SupportedCountry;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $email = strtolower(trim($validated['email']));

        $existingManager = $this->requestTrialSignup->findExistingManager($email);
        if ($existingManager) {
            return new JsonResponse([
                'success' => false,
                'error' => 'EMAIL_ALREADY_REGISTERED',
                'message' => 'Un compte avec cet email existe déjà. Connectez-vous directement.',
                'data' => [
                    'login_url' => '/auth/login',
                ],
            ], 409);
        }

        if (($validated['requestedWorkflow'] ?? '') === 'guided_trial') {
            // MULTI-PAYS (#1950) : le pays validé du signup est transmis au job
            // (plus de fallback silencieux DZ — invariant 10 de la spec).
            // #2437 : un provisioning_token est créé pour permettre au prospect
            // de poller GET /trial/status sans exposer l'email brut.
            $provisioningToken = Str::random(64);

            DB::table('trial_provisionings')->insert([
                'email' => $email,
                'provisioning_token' => $provisioningToken,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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

        $this->requestTrialSignup->execute($validated);

        return new JsonResponse([
            'success' => true,
            'message' => 'Code de vérification envoyé.',
            'data' => [
                'email' => $email,
                'status' => 'pending_verification',
            ],
        ], 200);
    }

    /**
     * POST /api/v1/trial/resend
     *
     * Renvoie un nouveau code OTP pour une demande d'essai en attente
     * (#3057) : l'ancien code est invalidé (nouveau jeton + expiration 30 min).
     */
    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        $companyRequest = CompanyRequest::query()
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if ($companyRequest === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'NO_PENDING_REQUEST',
                'message' => __('billing.trial_otp_no_pending'),
            ], 404);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $companyRequest->update([
            'verification_token' => $otp,
            'verification_expires_at' => now()->addMinutes(30),
        ]);

        try {
            $language = strtolower((string) (CountryDefaults::find(strtoupper((string) $companyRequest->country))['language'] ?? 'fr'));
            Mail::to($email)->send(
                new TrialVerificationMail((string) $companyRequest->manager_name, $otp, $language)
            );
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Failed to resend OTP email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            if (! in_array((string) config('mail.default'), ['array', 'log'], true)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'OTP_SEND_FAILED',
                    'message' => __('billing.trial_otp_send_failed'),
                ], 502);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => __('billing.trial_otp_resent'),
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
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $row = DB::table('trial_provisionings')
            ->where('provisioning_token', $validated['token'])
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
        $provisionedAt = $row->provisioned_at
            ? Carbon::parse($row->provisioned_at)->toIso8601String()
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
                'message' => 'La vérification de votre demande est temporairement indisponible. Réessayez dans quelques instants.',
            ], 503);
        }

        if ($result['success'] === false) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
                'message' => $result['message'],
            ], $result['status']);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Votre espace Leopardo est prêt !',
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
                    // Alignement réel : VerifyTrialSignup provisionne
                    // subscription_end = now()->addDays(14) et le plan
                    // fallback porte trial_days = 14 (constat QA live
                    // 2026-08-15 : la réponse annonçait 30 jours mais
                    // l'essai durait 14 — incohérence #3012/#3056).
                    'days' => 14,
                    'ends_at' => now()->addDays(14)->toIso8601String(),
                ],
                'next_steps' => [
                    'login' => 'Connectez-vous avec votre email et le mot de passe ci-dessus.',
                    'change_password' => 'Changez votre mot de passe dès la première connexion.',
                    'add_employees' => 'Ajoutez vos premiers employés via QR ou manuellement.',
                ],
            ],
        ], 201);
    }
}
