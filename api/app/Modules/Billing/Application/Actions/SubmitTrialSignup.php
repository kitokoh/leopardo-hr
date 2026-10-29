<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProvisionDemoTenantJob;
use App\Rules\SupportedCountry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Corps de l'inscription self-service / trial (#7962).
 *
 * Extrait de `SelfServiceTrialController` (859 lignes) : la validation, la
 * resolution du nom d'entreprise, les deux parcours (guided_trial et
 * self-service) et la creation de la ligne `trial_provisionings` formaient un
 * bloc de 374 lignes dans un controller. Le controller ne garde que le
 * transport HTTP (idempotence, delegation, reponse).
 *
 * Aucune regle metier n'est modifiee : le corps est deplace a l'identique.
 */
final class SubmitTrialSignup
{
    /** Offres souscriptibles a l'inscription (#7238). */
    public const SUPPORTED_PLANS = ['free', 'pilot', 'operations', 'enterprise'];

    public function __construct(
        private readonly RequestTrialSignup $requestTrialSignup,
        private readonly SolutionCatalogue $solutionCatalogue,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        // Audit 2026-09-13 — un navigateur DÉJÀ connecté ne doit pas pouvoir
        // créer un second espace : `/signup` restait accessible et cet endpoint
        // répondait 201 avec une session Sanctum active, provisionnant un
        // second tenant pour le même utilisateur.
        // Le garde est posé ICI (et non seulement dans le middleware Next, qui
        // n'est qu'un filtre cosmétique) : les clients directs — mobile, cURL,
        // SDK — ne traversent pas le middleware web.
        if ($request->user('sanctum') !== null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'SESSION_ALREADY_ACTIVE',
                'message' => __('errors.SESSION_ALREADY_ACTIVE'),
            ], 409);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            // #7853 — inscription par e-mail SEUL : le nom d'entreprise devient
            // optionnel (il migre vers l'entretien de préparation #7493). S'il
            // est fourni, le contrat historique (2..120) reste appliqué.
            'company' => ['nullable', 'string', 'min:2', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'in:founder,manager,hr,operations,other'],
            'employees' => ['nullable', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            // Langue d'INTERFACE choisie par l'utilisateur. Elle pilote la
            // langue de l'e-mail de vérification et devient la langue du tenant
            // au provisioning. Absente = repli sur la langue par défaut du pays
            // (comportement historique, jamais de régression dure).
            'locale' => ['nullable', 'string', 'in:fr,en,ar,tr'],
            // MULTI-PAYS (#1867) : le pays est obligatoire et doit être un
            // pays supporté du registre (plus de fallback silencieux DZ).
            'country' => ['required', 'string', 'size:2', new SupportedCountry],
            'phone' => ['nullable', 'string', 'max:40'],
            // #7238 : un compte est créé POUR une offre — on n'accepte que les
            // codes d'offres connus (le choix est obligatoire côté UI, l'API
            // reste tolérante à l'absence pour les clients historiques).
            'plan' => ['nullable', 'string', 'max:80', Rule::in(self::SUPPORTED_PLANS)],
            'source' => ['nullable', 'string', 'max:120'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'requestedWorkflow' => ['nullable', 'string', 'in:guided_trial,self_service'],
            // BC-25 (#6693) : solutions sectorielles demandées à l'inscription
            // (ex. « restaurant » depuis le wizard vitrine). Codes vérifiés
            // contre le catalogue serveur (fail-closed : code inconnu → 422,
            // pas de provisioning partiel). Idempotent : une solution déjà
            // active est un no-op.
            'solutions' => ['nullable', 'array', 'max:20'],
            'solutions.*' => [
                'string',
                'max:40',
                'distinct',
                function (string $attribute, string $value, \Closure $fail): void {
                    $code = strtolower(trim($value));
                    if (! App::make(SolutionCatalogue::class)->has($code)) {
                        $fail(__('errors.INVALID_SOLUTION', ['solution' => $code]));
                    }
                },
            ],
            // #6693 (BC-25) — solution sectorielle demandée au signup (ex.
            // `restaurant` depuis le wizard vitrine). Fail-closed : un code
            // hors allowlist du catalogue (SolutionCatalogue) est refusé en
            // 422 — jamais de résolution dynamique côté provisioning.
            'solution' => ['nullable', 'string', 'max:60', Rule::in($this->solutionCatalogue->codes())],
            // #7235 — Profil d'activité déclaré à l'inscription :
            //   `company` (défaut) = entreprise traditionnelle ;
            //   `solo`             = indépendant, sans outils d'équipe.
            'company_type' => ['nullable', 'string', 'in:'.Company::TYPE_COMPANY.','.Company::TYPE_SOLO],
            // #7235 — Outils HORIZONTAUX choisis par le client (allowlist
            // `Company::HORIZONTAL_TOOLS`, fail-closed : une clé inconnue est
            // refusée en 422 plutôt que stockée silencieusement). Absent =
            // aucune sélection déclarée → comportement historique préservé
            // (inscription rapide depuis le hero de la vitrine).
            'modules' => ['nullable', 'array', 'max:20'],
            'modules.*' => ['string', 'max:40', 'distinct', Rule::in(Company::HORIZONTAL_TOOLS)],
            // BOS-O-01 / #8625 — intention de marché du point d'entrée
            // « Trade ». Deux dimensions distinctes, validées ENSEMBLE : un
            // `importer` sur un marché domestique est refusé (422) plutôt que
            // stocké comme une intention incohérente. Absence des deux =
            // inscription hors parcours Trade, comportement historique.
            'market_type' => ['nullable', 'string', Rule::in(Company::MARKET_TYPES)],
            'trade_profile' => ['nullable', 'string', Rule::in(Company::TRADE_PROFILES)],
        ]);

        $this->assertMarketIntentIsCoherent($request);

        /** @var array{email: string, company?: string|null, first_name?: string|null, last_name?: string|null, role?: string|null, employees?: string|null, country: string, phone?: string|null, plan?: string|null, source?: string|null, referral_code?: string|null, requestedWorkflow?: string|null, solutions?: list<string>|null, solution?: string|null, company_type?: string|null, modules?: list<string>|null, locale?: string|null, market_type?: string|null, trade_profile?: string|null} $validated */
        $email = strtolower(trim($validated['email']));

        // BOS-009 (#8227) — `solution` (singulier) etait valide puis JETE.
        //
        // Le wizard vitrine envoie `solution: "travelagency"` (singulier), et
        // la regle de validation l'acceptait explicitement — mais seul
        // `solutions` (pluriel) etait transmis au provisioning. Consequence :
        // le signup repondait 200, le tenant etait cree, et la verticale
        // n'etait JAMAIS activee — sans aucune erreur. Un proprietaire
        // d'agence arrivait donc sur un workspace sans sa solution, et le
        // diagnostic etait d'autant plus difficile que la validation, elle,
        // avait bien accepte le code.
        //
        // On normalise ici, en un seul point, pour que les DEUX parcours
        // (guided_trial et self-service) et le `signup_payload` persiste
        // voient la meme liste. `solutions` reste prioritaire si les deux
        // sont fournis ; les doublons sont ecartes.
        $requestedSolutions = $validated['solutions'] ?? [];
        if (($validated['solution'] ?? null) !== null) {
            $requestedSolutions[] = $validated['solution'];
        }
        $validated['solutions'] = array_values(array_unique(array_map(
            static fn (string $code): string => strtolower(trim($code)),
            $requestedSolutions
        )));

        // #7853 — nom d'entreprise résolu UNE fois pour les deux parcours
        // (guidé et self-service) : valeur fournie, sinon nom provisoire
        // dérivé de la partie locale de l'e-mail (jean.dupont → « Jean
        // Dupont »), sinon repli localisé « Mon entreprise ». Le nom définitif
        // est demandé dans l'entretien de préparation (#7493).
        $companyName = $this->requestTrialSignup->resolveCompanyName($validated, $email);
        $validated['company'] = $companyName;

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
                // BOS-002 (#8144) — jamais d'email en clair dans les logs :
                // identifiant haché (corrélation possible, PII non exposée).
                Log::info('trial.signup_duplicate_email_uniform_response', ['email_hash' => $this->emailLogId($email)]);
            }
        } catch (\Throwable $e) {
            Log::error('trial.signup_duplicate_check_failed', [
                'email_hash' => $this->emailLogId($email),
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
                Log::info('trial.signup_existing_pending_reused', ['email_hash' => $this->emailLogId($email)]);

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
                    Log::warning('Trial provisioning race on email_hash '.$this->emailLogId($email).' — reusing winner row.');

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

            ProvisionDemoTenantJob::dispatch(
                $email,
                $companyName,
                $validated['country'],
                $provisioningToken,
                // BOS-009 (#8227) — normalise plus haut : la cle existe
                // toujours (liste vide si aucune solution demandee).
                $validated['solutions'],
                // #7235 — profil + outils horizontaux choisis (null/[]
                // = inscription rapide : aucun verrouillage rétroactif).
                $validated['company_type'] ?? null,
                $validated['modules'] ?? [],
                // QA onboarding 2026-09-14 : le choix de langue de l'utilisateur
                // était validé puis jeté ici — le tenant naissait dans la langue
                // du pays, pas la sienne.
                $validated['locale'] ?? null,
                // BOS-O-01 / #8625 — l'intention Trade ne doit plus mourir
                // dans le sessionStorage du navigateur.
                $validated['market_type'] ?? null,
                $validated['trade_profile'] ?? null,
            );

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
                'email_hash' => $this->emailLogId($email),
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

        // Issue #2437 (parité guidé) : le parcours self-service doit lui aussi
        // émettre un `provisioning_token`. Sans lui, `GET /trial/status` et
        // `POST /trial/set-password` répondaient 404 PROVISIONING_TOKEN_INVALID
        // pour TOUT prospect self-service : impossible de suivre son espace ou
        // de définir son mot de passe, alors que la vitrine implémente déjà ce
        // chemin (sessionStorage + /api/forms/trial-status + /api/forms/trial-password).
        // La ligne est créée ici (status pending) puis passée à `ready` par
        // VerifyTrialSignup au moment du provisioning.
        $provisioningToken = Str::random(64);

        try {
            // `lock_timeout` BORNÉ : cette ligne est un CONFORT (suivi de statut
            // + définition du mot de passe), jamais une condition de l'essai.
            // Sans borne, un verrou résiduel sur l'index partiel unique
            // `trial_provisionings_pending_email_unique` (transaction avortée
            // laissée ouverte, migration en cours…) ferait ATTENDRE le signup
            // — voire bloquer la requête indéfiniment. On préfère l'échec
            // rapide (55P03) absorbé par le catch ci-dessous : le prospect
            // reçoit son code OTP et poursuit.
            DB::transaction(function () use ($email, $validated, $provisioningToken): void {
                DB::statement("SET LOCAL lock_timeout = '3s'");

                DB::table('trial_provisionings')->insert([
                    'email' => $email,
                    'company_name' => $validated['company'],
                    'country' => strtoupper($validated['country']),
                    'provisioning_token' => $provisioningToken,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Un POST concurrent a déjà créé la ligne pending pour cet email
            // (index partiel unique #3951) : on réutilise la ligne gagnante au
            // lieu de renvoyer un 500.
            if ($e->getCode() === '23505') {
                $winner = DB::table('trial_provisionings')
                    ->where('email', $email)
                    ->where('status', 'pending')
                    ->first();

                if ($winner !== null) {
                    $provisioningToken = (string) $winner->provisioning_token;
                } else {
                    Log::warning('trial.self_service.provisioning_row_race_unresolved', ['email_hash' => $this->emailLogId($email)]);
                }
            } else {
                // Jamais bloquant pour l'essai : l'OTP reste le chemin nominal.
                Log::error('trial.self_service.provisioning_row_failed', [
                    'email_hash' => $this->emailLogId($email),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => __('errors.VERIFICATION_CODE_SENT'),
            'data' => [
                'email' => $email,
                'status' => 'pending_verification',
                // La vitrine stocke ce token et poll /trial/status ; il sert
                // aussi à définir le mot de passe (POST /trial/set-password).
                'provisioning_token' => $provisioningToken,
            ],
        ], 200);
    }
    /**
     * BOS-002 (#8144) — identifiant de journalisation d'un email (pseudonyme).
     *
     * Les logs applicatifs du parcours trial ne doivent JAMAIS porter l'email
     * du prospect en clair (PII) : on journalise un hachage tronque, qui
     * conserve la correlation entre evenements d'un meme email sans exposer
     * la donnee.
     */
    private function emailLogId(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
    }

    /**
     * BOS-O-01 / #8625 — coherence marche <-> profil.
     *
     * `market_type` et `trade_profile` sont deux listes validees separement :
     * separement valides, elles peuvent composer une intention absurde
     * (« grossiste » sur un marche international). La regle de coherence vit
     * dans le modele (`Company::isValidMarketIntent`) pour etre partagee avec
     * le provisioning et les tests — pas dupliquee ici.
     */
    private function assertMarketIntentIsCoherent(Request $request): void
    {
        $market = $request->input('market_type');
        $profile = $request->input('trade_profile');

        if (Company::isValidMarketIntent(
            is_string($market) ? $market : null,
            is_string($profile) ? $profile : null,
        )) {
            return;
        }

        throw ValidationException::withMessages([
            'trade_profile' => [__('errors.INVALID_TRADE_PROFILE')],
        ]);
    }
}
