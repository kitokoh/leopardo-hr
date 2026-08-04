<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Actions\RequestTrialSignup;
use App\Modules\Billing\Application\Actions\VerifyTrialSignup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'country' => ['nullable', 'string', 'max:2'],
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
            \App\Jobs\ProvisionDemoTenantJob::dispatch($email, $validated['company']);
            
            return new JsonResponse([
                'success' => true,
                'message' => __('billing.trial_signup_received'),
                'data' => [
                    'email' => $email,
                    'status' => 'provisioning_sandbox',
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

        $result = $this->verifyTrialSignup->execute($email, $validated['code']);

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
                    'temp_password' => $result['temp_password'],
                ],
                'trial' => [
                    'days' => 30,
                    'ends_at' => now()->addDays(30)->toIso8601String(),
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
