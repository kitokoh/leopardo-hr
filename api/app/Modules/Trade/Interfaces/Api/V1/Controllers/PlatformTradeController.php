<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Http\Middleware\Trade\TradeRoleResolver;
use App\Modules\Trade\Domain\Manifests\TradeManifest;
use App\Modules\Trade\Infrastructure\Services\TradeIntegrationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TRADE-010 (#8485, BC-36 TRADE) — suivi de la verticale Trade depuis la
 * console plateforme EXISTANTE (aucune seconde console creee).
 *
 * PLACEMENT : ce controleur vit dans le module PROPRIETAIRE de la verticale
 * et est route sous le groupe `/platform` (auth:super_admin_api + permission
 * `platform.permission:companies.view`). C'est le precedent etabli par la
 * verticale Fundraising (`PlatformFundraisingSettingsController`, #8441) : la
 * surface d'administration est plateforme, mais le code reste chez le module
 * metier, ce qui satisfait la garde d'isolation (`Platform -> Trade` interdit,
 * issue #5584) sans affaiblir ni l'allowlist ni la regle.
 *
 * SEPARATION STRICTE (exigence de mission) : ces endpoints ne renvoient que
 * des COMPTEURS et des etats d'adaptateurs. Aucun contenu commercial prive
 * d'un tenant (prix, offres, messages, documents) ne transite — un superadmin
 * ne doit pas lire les affaires de ses clients par defaut. L'acces a un
 * dossier precis reste un acte exceptionnel et audite (impersonation), hors
 * perimetre de cet ecran.
 *
 * @see \App\Modules\Trade\Infrastructure\Services\TradeIntegrationRegistry
 */
final class PlatformTradeController extends Controller
{
    /** Nombre de tenants agreges au maximum par appel (borne #6562). */
    private const DEFAULT_TENANT_LIMIT = 200;

    private const MAX_TENANT_LIMIT = 500;

    /**
     * Tables de la verticale dont on agrege des compteurs par statut. La
     * presence est verifiee avant lecture : un tenant dont le schema n'a pas
     * encore ete migre ne doit pas faire echouer l'ecran.
     *
     * @var array<string, string>
     */
    private const COUNTED_TABLES = [
        'trade_deals' => 'deals',
        'trade_rfqs' => 'rfqs',
        'trade_quotes' => 'quotes',
        'trade_orders' => 'orders',
    ];

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TradeIntegrationRegistry $integrations,
    ) {}

    /**
     * `GET /platform/trade/overview` — compteurs agreges de la verticale.
     */
    public function overview(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', self::DEFAULT_TENANT_LIMIT);
        $limit = min(max($limit, 1), self::MAX_TENANT_LIMIT);

        $companies = Company::query()
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'features']);

        $totalCompanies = $companies->count();
        $withTrade = 0;
        $companiesWithActivity = 0;
        $totals = [
            'deals' => [],
            'rfqs' => [],
            'quotes' => [],
            'orders' => [],
        ];

        foreach ($companies as $company) {
            if (! $this->hasTradeEnabled($company)) {
                continue;
            }

            $withTrade++;

            $counts = $this->countersFor($company);
            $activity = 0;

            foreach (['deals', 'rfqs', 'quotes', 'orders'] as $bucket) {
                foreach ($counts[$bucket] as $status => $count) {
                    $totals[$bucket][$status] = ($totals[$bucket][$status] ?? 0) + $count;
                    $activity += $count;
                }
            }

            if ($activity > 0) {
                $companiesWithActivity++;
            }
        }

        return new JsonResponse([
            'data' => [
                'companies' => [
                    'scanned' => $totalCompanies,
                    'with_trade' => $withTrade,
                    'with_activity' => $companiesWithActivity,
                ],
                'counts' => [
                    'deals' => $this->asStatusList($totals['deals']),
                    'rfqs' => $this->asStatusList($totals['rfqs']),
                    'quotes' => $this->asStatusList($totals['quotes']),
                    'orders' => $this->asStatusList($totals['orders']),
                ],
                'roles' => TradeRoleResolver::SUPPORTED_ROLES,
                'window' => [
                    'tenant_limit' => $limit,
                    'scope' => 'counts_only',
                ],
                // Rappel explicite du perimetre : l'ecran ne sert PAS a lire le
                // contenu commercial des tenants.
                'notice' => 'Compteurs uniquement : les prix, offres, messages et documents des clients ne sont pas exposes a l\'administration plateforme. L\'acces a un dossier precis releve d\'une impersonation auditee.',
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * `GET /platform/trade/integrations` — etat reel des adaptateurs externes.
     *
     * Jamais de secret : uniquement `configured` / `available` / `mode` /
     * `reason` (cf. TradeIntegrationRegistry).
     */
    public function integrations(): JsonResponse
    {
        $manifest = new TradeManifest;
        $all = $this->integrations->all();

        $configured = 0;
        foreach ($all as $integration) {
            if ($integration['configured']) {
                $configured++;
            }
        }

        return new JsonResponse([
            'data' => [
                'solution' => [
                    'code' => $manifest->code(),
                    'name' => $manifest->name(),
                    'maturity' => $manifest->maturity(),
                    'industry' => $manifest->industry()->value,
                ],
                'integrations' => $all,
                'summary' => [
                    'total' => count($all),
                    'configured' => $configured,
                    'unconfigured' => count($all) - $configured,
                ],
                'notice' => 'Une integration non configuree est presentee comme indisponible sur toutes les surfaces : aucune donnee n\'est simulee pour la masquer.',
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Vrai si le tenant a la cle `trade` active dans `companies.features`.
     *
     * Lecture du tableau `features` deja caste par le modele Company — aucun
     * acces au contenu metier.
     */
    private function hasTradeEnabled(Company $company): bool
    {
        // `features` peut etre null en base : le cast explicite garantit un
        // tableau (et satisfait l'analyse statique, qui le voit deja type
        // `array<mixed>` — un `is_array()` y serait toujours vrai).
        $features = (array) $company->features;

        return ($features['trade'] ?? false) === true;
    }

    /**
     * Compteurs par statut pour UN tenant, lus dans son schema via
     * `TenantManager::withinTenant()` (pas de `withoutGlobalScope` brut).
     *
     * @return array{deals: array<string,int>, rfqs: array<string,int>, quotes: array<string,int>, orders: array<string,int>}
     */
    private function countersFor(Company $company): array
    {
        $empty = ['deals' => [], 'rfqs' => [], 'quotes' => [], 'orders' => []];

        try {
            return $this->tenantManager->withinTenant($company, function () use ($empty): array {
                $result = $empty;

                foreach (self::COUNTED_TABLES as $table => $bucket) {
                    if (! schemaTableExists($table)) {
                        continue;
                    }

                    $rows = DB::table($table)
                        ->select('status', DB::raw('count(*) as aggregate'))
                        ->groupBy('status')
                        ->pluck('aggregate', 'status');

                    foreach ($rows as $status => $count) {
                        $result[$bucket][(string) $status] = (int) $count;
                    }
                }

                return $result;
            });
        } catch (Throwable $exception) {
            // Un tenant dont le schema est incomplet ne doit pas casser la
            // console : on journalise SANS donnee sensible et on renvoie des
            // compteurs vides plutot qu'un ecran d'erreur.
            Log::warning('PlatformTradeController: compteurs indisponibles pour un tenant', [
                'company_id' => $company->id,
                'exception' => $exception::class,
            ]);

            return $empty;
        }
    }

    /**
     * @param  array<string,int>  $byStatus
     * @return list<array{status:string,count:int}>
     */
    private function asStatusList(array $byStatus): array
    {
        ksort($byStatus);

        return array_map(
            static fn (string $status, int $count): array => ['status' => $status, 'count' => $count],
            array_keys($byStatus),
            $byStatus,
        );
    }
}
