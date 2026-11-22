<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Requests;

use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Rules\SupportedCountry;
use App\Shared\Rules\PasswordPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription self-service a l'essai (#9732).
 *
 * Extraite du `SelfServiceTrialController` : les regles vivaient dans une
 * closure anonyme de 60 lignes, non testable sans monter une requete HTTP
 * complete. Elles sont ici nommees, et `withValidator()` porte la regle de
 * coherence marche <-> profil qui etait un appel prive du controleur.
 *
 * ## Ce qui n'est PAS une regle de forme
 *
 * `market_type` et `trade_profile` sont deux listes validees separement :
 * separement valides, elles peuvent composer une intention absurde
 * (« grossiste » sur un marche international). La coherence est donc verifiee
 * APRES les regles de forme, via `Company::isValidMarketIntent()` — la regle
 * vit dans le modele pour etre partagee avec le provisioning et les tests.
 */
class SignupTrialRequest extends FormRequest
{
    /** Offres souscriptibles a l'inscription (#7238). */
    public const SUPPORTED_PLANS = ['free', 'pilot', 'operations', 'enterprise'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SolutionCatalogue $catalogue */
        $catalogue = app(SolutionCatalogue::class);

        return [
            'email' => ['required', 'email', 'max:255'],
            // #7853 — inscription par e-mail SEUL : le nom d'entreprise devient
            // optionnel (il migre vers l'entretien de preparation #7493). S'il
            // est fourni, le contrat historique (2..120) reste applique.
            'company' => ['nullable', 'string', 'min:2', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'in:founder,manager,hr,operations,other'],
            'employees' => ['nullable', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            // Langue d'INTERFACE choisie par l'utilisateur. Elle pilote la
            // langue de l'e-mail de verification et devient la langue du tenant
            // au provisioning. Absente = repli sur la langue par defaut du pays.
            'locale' => ['nullable', 'string', 'in:fr,en,ar,tr'],
            // MULTI-PAYS (#1867) : le pays est obligatoire et doit etre un pays
            // supporte du registre (plus de fallback silencieux DZ).
            'country' => ['required', 'string', 'size:2', new SupportedCountry],
            'phone' => ['nullable', 'string', 'max:40'],
            // #7238 : un compte est cree POUR une offre — on n'accepte que les
            // codes d'offres connus (le choix est obligatoire cote UI, l'API
            // reste tolerante a l'absence pour les clients historiques).
            'plan' => ['nullable', 'string', 'max:80', Rule::in(self::SUPPORTED_PLANS)],
            'source' => ['nullable', 'string', 'max:120'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'requestedWorkflow' => ['nullable', 'string', 'in:guided_trial,self_service'],
            // BC-25 (#6693) : solutions sectorielles demandees a l'inscription.
            // Codes verifies contre le catalogue serveur (fail-closed : code
            // inconnu -> 422, pas de provisioning partiel).
            'solutions' => ['nullable', 'array', 'max:20'],
            'solutions.*' => [
                'string',
                'max:40',
                'distinct',
                function (string $attribute, string $value, \Closure $fail) use ($catalogue): void {
                    $code = strtolower(trim($value));
                    if (! $catalogue->has($code)) {
                        $fail(__('errors.INVALID_SOLUTION', ['solution' => $code]));
                    }
                },
            ],
            // #6693 (BC-25) — solution sectorielle demandee au signup (ex.
            // `restaurant` depuis le wizard vitrine). Fail-closed : un code hors
            // allowlist du catalogue est refuse en 422.
            'solution' => ['nullable', 'string', 'max:60', Rule::in($catalogue->codes())],
            // #7235 — Profil d'activite declare a l'inscription :
            //   `company` (defaut) = entreprise traditionnelle ;
            //   `solo`             = independant, sans outils d'equipe.
            'company_type' => ['nullable', 'string', 'in:'.Company::TYPE_COMPANY.','.Company::TYPE_SOLO],
            // #7235 — Outils HORIZONTAUX choisis par le client (allowlist
            // `Company::HORIZONTAL_TOOLS`, fail-closed).
            'modules' => ['nullable', 'array', 'max:20'],
            'modules.*' => ['string', 'max:40', 'distinct', Rule::in(Company::HORIZONTAL_TOOLS)],
            // BOS-O-01 / #8625 — intention de marche du point d'entree « Trade ».
            'market_type' => ['nullable', 'string', Rule::in(Company::MARKET_TYPES)],
            'trade_profile' => ['nullable', 'string', Rule::in(Company::TRADE_PROFILES)],
            // #9323 — mot de passe choisi DES l'inscription. Optionnel : sans
            // lui, le parcours reste identique (OTP, ou repli guide avec
            // `activation_url`). Politique unique du depot (#7995).
            'password' => PasswordPolicy::optional(),
        ];
    }

    /**
     * BOS-O-01 / #8625 — coherence marche <-> profil, apres les regles de forme.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $market = $this->input('market_type');
            $profile = $this->input('trade_profile');

            if (Company::isValidMarketIntent(
                is_string($market) ? $market : null,
                is_string($profile) ? $profile : null,
            )) {
                return;
            }

            $validator->errors()->add('trade_profile', __('errors.INVALID_TRADE_PROFILE'));
        });
    }
}
