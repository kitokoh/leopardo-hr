<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\HealthProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint /api/v1/health : sonde "live + ready" consommee par :
 *
 * - le deploy hook Render (`DEFAULT_API_HEALTHCHECK_URL` cherche `"status":"ok"`)
 * - les futurs scrapers de supervision (UptimeRobot, Better Uptime, etc.)
 *
 * La reponse inclut toujours `status`, `version` et la matrice `checks`.
 * Le HTTP code est 200 tant qu'au moins la base de donnees repond ; si la DB
 * tombe on renvoie 503 pour que Render detecte immediatement une instance
 * non disponible.
 *
 * Les checks secondaires (Redis, storage, queue, delivery) sont `degraded` en
 * cas d'echec mais ne declenchent pas un 503 sur `/health` : l'API reste
 * partiellement servable (les jobs/queues peuvent etre degrades, pas l'auth),
 * et une sonde de deploiement ne doit pas redemarrer une instance saine parce
 * qu'une dependance secondaire est tombee.
 *
 * #7255 — la sonde honnete de disponibilite est `/health/ready` : elle couvre
 * les dependances CRITIQUES (base + Redis + queue) et renvoie 503 des que
 * l'une d'elles est en panne. `/health/live` reste un liveness pur et n'est
 * plus la sonde recommandee au monitoring externe. Semantique complete
 * (ok / degraded / fail / skipped) : docs/ops/HEALTH_ENDPOINTS.md.
 *
 * ## Ce que ce controleur ne fait plus
 *
 * Les huit sondes d'infrastructure (base, Redis, stockage, couche web,
 * livraison, file, mail, memoire) vivaient ici et portaient 667 lignes. Elles
 * ne prennent AUCUNE decision HTTP : elles renvoient `{ok, …}`. Elles sont
 * desormais dans `HealthProbeService`, et cette classe ne garde que ce qui
 * lui appartient — la POLITIQUE : quel check est critique, quel code de
 * statut en decoule, et quelle surface est publique.
 */
class HealthController extends Controller
{
    public function __construct(
        private readonly HealthProbeService $probes,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $version = $this->probes->stringConfigValue(config('app.version'));

        $database = $this->probes->checkDatabase();
        $redis = $this->probes->checkRedis();
        $storage = $this->probes->checkStorage();
        $queue = $this->probes->checkQueue();
        $scheduler = $this->probes->checkScheduler();
        $memory = $this->probes->checkMemory();
        $web = $this->probes->checkWeb();
        $delivery = $this->probes->checkDeliveryConfiguration();
        $mail = $this->probes->checkMail();

        $globalOk = $database['ok'];
        $statusCode = $globalOk ? 200 : 503;

        // #8125 — surface publique minimale pour les sondes (Render cherche
        // `"status":"ok"`) : la matrice detaillee (drivers, memoire, latences,
        // environnement) cartographierait la stack pour un attaquant. Elle est
        // reservee aux super admins plateforme (guard `super_admin_api`).
        if (! $request->user('super_admin_api') instanceof SuperAdmin) {
            return new JsonResponse([
                'status' => $globalOk ? 'ok' : 'fail',
                'version' => $version,
                'timestamp' => now()->toIso8601String(),
            ], $statusCode);
        }

        $payload = [
            'status' => $globalOk ? 'ok' : 'fail',
            'version' => $version,
            'environment' => app()->environment(),
            'checks' => [
                'database' => $database,
                'redis' => $redis,
                'storage' => $storage,
                'queue' => $queue,
                'scheduler' => $scheduler,
                'memory' => $memory,
                'web' => $web,
                'delivery' => $delivery,
                'mail' => $mail,
            ],
            'uptime_seconds' => defined('LARAVEL_START')
                ? (int) round(microtime(true) - LARAVEL_START)
                : null,
            'timestamp' => now()->toIso8601String(),
        ];

        // #9741 — `new JsonResponse` et non `response()->json()` : le helper
        // resout la ResponseFactory, qui resout le Redirector, qui resout la
        // session, qui resout l'Encrypter — et leve MissingAppKeyException
        // quand APP_KEY est absente. La sonde qui doit PRECISEMENT signaler
        // `app_key_missing` repondait donc 500, exactement le faux negatif
        // qu'elle existe pour eviter.
        return new JsonResponse($payload, $statusCode);
    }

    /**
     * GET /api/v1/health/live — 200 if the process is running.
     *
     * Liveness pur : aucune I/O, donc toujours 200 tant que PHP repond.
     * #7255 — ce n'est PAS la sonde a brancher sur un monitoring de
     * disponibilite : elle ne verra jamais une degradation (Redis, queue,
     * mailer). Pour cela, utiliser `/health/ready`.
     */
    public function live(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/health/ready — 200 si les dependances CRITIQUES repondent.
     *
     * #7255 — la readiness ne regardait que la base : Redis HS, queue bloquee
     * et mailer muet restaient invisibles (HTTP 200 + `status: ok`) alors que
     * l'instance n'assurait plus ses jobs, ses sessions ni ses e-mails (constat
     * live du 2026-09-11). Les dependances critiques sont desormais la base
     * (auth, tenants), Redis (cache/sessions/queues) et la file d'attente :
     * des qu'une seule tombe, `status: fail`, HTTP 503 et `failed_checks`
     * nomme la dependance — un probe Render ou un monitoring externe detecte
     * donc reellement la panne.
     *
     * Une dependance volontairement absente (`status: skipped`, ex. Redis non
     * configure) n'est jamais un echec : on ne transforme pas une absence de
     * dependance en incident.
     */
    public function ready(): JsonResponse
    {
        $database = $this->probes->checkDatabase();
        $redis = $this->probes->checkRedis();
        $queue = $this->probes->checkQueue();
        $scheduler = $this->probes->checkScheduler();
        $mail = $this->probes->checkMail();

        $checks = [
            'database' => $database,
            'redis' => $redis,
            'queue' => $queue,
            'scheduler' => $scheduler,
            'mail' => $mail,
        ];

        $critical = [
            'database' => $database['ok'],
            'redis' => $redis['ok'],
            'queue' => $queue['ok'],
        ];

        $failed = array_keys(array_filter($critical, static fn (bool $ok): bool => ! $ok));

        // #7385 — le mail est un check NON critique : sans SMTP l'API reste
        // servable (connexion, donnees, pointage), donc pas de 503. Mais il
        // doit sortir du silence : « mailer muet » rendait la reinitialisation
        // de mot de passe morte pendant que la sonde repondait `ok`.
        $degraded = $mail['ok'] ? [] : ['mail'];

        $ok = $failed === [];

        return new JsonResponse([
            'status' => ! $ok ? 'fail' : ($degraded === [] ? 'ok' : 'degraded'),
            'checks' => $checks,
            'failed_checks' => $failed,
            'degraded_checks' => $degraded,
            'timestamp' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
