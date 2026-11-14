<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suivi de candidature par le candidat — route PUBLIQUE.
 *
 * Un candidat n'a pas de compte : il a déposé un CV avec une adresse email et
 * veut savoir où en est son dossier. Cette route lui rend l'état de ses
 * candidatures à partir de son email.
 *
 * ## Le problème de sécurité, et comment il est traité
 *
 * Une route qui rend des dossiers à partir d'un email est, par nature, une
 * surface d'énumération : n'importe qui connaissant l'adresse d'un candidat
 * pourrait lire son parcours. Trois mesures, cumulatives :
 *
 * 1. **Throttling strict** (`public-careers-track`, 5/min par IP ET par email)
 *    — balayer une liste d'adresses devient impraticable.
 * 2. **Réponse minimale** — on ne rend QUE ce qui est nécessaire au candidat :
 *    intitulé du poste, statut, date de dépôt, date de dernière mise à jour.
 *    Jamais la note interne, les notes du recruteur, le score ATS, l'identité
 *    des recruteurs ni le motif de refus : ce sont des données internes, et
 *    les exposer transformerait cette route en fuite d'information RH.
 * 3. **Réponse identique pour un email inconnu** — une liste vide, jamais un
 *    404. Un 404 confirmerait « cette adresse n'a jamais postulé », ce qui est
 *    déjà une information.
 *
 * Le tenant est résolu depuis `{companySlug}` : un candidat ne voit que les
 * candidatures déposées chez cette entreprise, jamais celles déposées ailleurs
 * avec la même adresse.
 */
class CandidateApplicationTrackingController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * POST /api/v1/public/careers/{companySlug}/applications/track
     *
     * POST et non GET : l'email est une donnée personnelle, et un GET la
     * ferait atterrir dans les journaux d'accès, l'historique du navigateur et
     * les en-têtes `Referer`. Un POST la garde dans le corps de la requête.
     */
    public function track(Request $request, string $companySlug): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $company = Company::query()
            ->where('slug', $companySlug)
            ->where('status', '!=', 'suspended')
            ->firstOrFail();

        $email = mb_strtolower(trim($validated['email']));

        return $this->tenantManager->withinTenant($company, function () use ($company, $email): JsonResponse {
            $applications = Applicant::query()
                ->where('company_id', $company->id)
                // `lower(email)` : un candidat qui saisit « Jean@Exemple.fr »
                // doit retrouver sa candidature déposée en minuscules. On ne
                // peut pas se contenter de `where('email', $email)`.
                ->whereRaw('LOWER(email) = ?', [$email])
                ->with('jobPosting:id,title,location,contract_type')
                ->orderByDesc('applied_at')
                ->limit(50)
                ->get();

            return new JsonResponse([
                'data' => $applications->map(fn (Applicant $applicant): array => [
                    'reference' => $this->reference($applicant),
                    'job_title' => $applicant->jobPosting?->title,
                    'job_location' => $applicant->jobPosting?->location,
                    'contract_type' => $applicant->jobPosting?->contract_type,
                    'status' => $applicant->status,
                    'applied_at' => $applicant->applied_at?->toIso8601String(),
                    'last_update' => $applicant->updated_at?->toIso8601String(),
                ])->values(),
                'meta' => [
                    'count' => $applications->count(),
                    // Le candidat doit savoir combien de temps son dossier est
                    // conservé : c'est une obligation d'information RGPD, et
                    // c'est aussi la réponse à « pourquoi ma candidature a
                    // disparu ».
                    'retention_days' => (int) config('recruitment.applicant_retention_days', 730),
                ],
            ]);
        });
    }

    /**
     * Référence lisible du dossier, pour que le candidat puisse en parler au
     * recruteur sans communiquer son identifiant interne de base.
     */
    private function reference(Applicant $applicant): string
    {
        return sprintf('CAND-%06d', $applicant->id);
    }
}
