<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Mail\MailTransportDiagnostics;
use App\Modules\Notification\Infrastructure\Services\ProductionDeliveryGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Sondes d'infrastructure de `/health`.
 *
 * Extrait de `HealthController` (667 lignes) : les huit vérifications
 * (base, Redis, stockage, couche web, livraison, file d'attente, mail,
 * mémoire) ne portent AUCUNE décision HTTP — elles renvoient un tableau
 * `{ok, …}` et c'est le contrôleur qui choisit 200/503 et la forme publique.
 * Les garder dans la couche HTTP rendait impossible de les tester sans monter
 * une requête, et mêlait deux responsabilités : « sonder une dépendance » et
 * « décider du code de statut ».
 *
 * ## Ce que ce service ne fait PAS
 *
 * Il ne décide jamais du statut global ni de la criticité : `ready()` sait que
 * la base, Redis et la file sont critiques alors que le mail est seulement
 * dégradant. Cette politique reste dans le contrôleur, à un seul endroit.
 *
 * ## Honnêteté des verdicts
 *
 * Chaque sonde distingue trois états plutôt que deux : `ok`, `degraded` et
 * `skipped`. Une dépendance volontairement absente (Redis non configuré,
 * transport mail local) n'est JAMAIS un échec — on ne transforme pas une
 * absence de dépendance en incident.
 */
class HealthProbeService
{
    /**
     * #7385 — délai maximal du `tcp connect` de la sonde mail. Court :
     * `/health/ready` est interrogé par la supervision, il ne doit pas
     * transformer une panne SMTP en lenteur d'endpoint.
     */
    private const MAIL_CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * @return array{ok: bool, latency_ms?: int, error?: string}
     */
    public function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');

            return [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * Issue #1768 : normalise la réponse de `ping()` quel que soit le client
     * (PhpRedis : true / 'PONG' / '+PONG' ; Predis : objet Status __toString 'PONG').
     */
    public static function isPongResponse(mixed $response): bool
    {
        return $response === true
            || in_array(strtoupper((string) $response), ['PONG', '+PONG'], true);
    }

    /**
     * @return array{ok: bool, status?: string, latency_ms?: int, error?: string}
     */
    public function checkRedis(): array
    {
        // Redis est optionnel (cache/sessions/queues). On considère Redis
        // "vraiment voulu" si :
        //   (a) REDIS_URL est explicitement defini -> `config('database.redis.default.url')`
        //       n'a PAS de default, il vaut `null` tant que REDIS_URL n'est pas pose ;
        //   (b) ou un driver applicatif (cache/queue/session) utilise redis.
        //
        // On NE regarde PAS `database.redis.default.host` : son default `127.0.0.1`
        // est indistinguable d'une config explicite, et on veut justement eviter de
        // bloquer quelques secondes sur un `tcp connect` vers un Redis inexistant
        // sur chaque requete `/health`. `env()` est volontairement evite : apres
        // `php artisan config:cache` (prod), `env()` renvoie null.
        $urlConfigured = ! empty(config('database.redis.default.url'));
        $driverUsesRedis = config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';

        if (! $urlConfigured && ! $driverUsesRedis) {
            return ['ok' => true, 'status' => 'skipped'];
        }

        $start = microtime(true);
        try {
            $response = Redis::connection()->ping();
            // Issue #1768 : Predis retourne un objet Predis\Response\Status
            // (__toString() = 'PONG') — les comparaisons strictes échouaient
            // toujours → « unexpected » alors que Redis est sain.
            $ok = self::isPongResponse($response);

            return [
                'ok' => $ok,
                'status' => $ok ? 'pong' : 'unexpected',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => 'degraded',
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function checkStorage(): array
    {
        try {
            $disk = Storage::disk($this->filesystemDiskName());
            // Laravel 11 : les disques sont en `throw => false` par defaut,
            // donc `put()` retourne `false` au lieu de lever. On verifie
            // explicitement la valeur de retour pour vraiment detecter un
            // disque non inscriptible.
            $written = $disk->put('.healthcheck', (string) now()->timestamp);
            $disk->delete('.healthcheck');

            return ['ok' => (bool) $written];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
            ];
        }
    }

    public function filesystemDiskName(): string|UnitEnum
    {
        $disk = config('filesystems.default', 'local');

        return is_string($disk) || $disk instanceof UnitEnum ? $disk : 'local';
    }

    /**
     * #6957 : sonde « couche web » sans I/O — permet à l'observabilité de
     * détecter un environnement dont toutes les routes web (session/cookies)
     * répondent 500 alors que l'API (stateless) reste verte. Deux causes
     * fréquentes, détectées ici sans effet de bord :
     *  - `APP_KEY` absente → `EncryptCookies`/`StartSession` lèvent sur chaque
     *    requête web ;
     *  - clé présente mais invalide pour le cipher → aller-retour
     *    encrypt/decrypt impossible.
     * Check non bloquant (dégradé, jamais de 503) : l'API peut rester servable
     * même si la surface web est cassée.
     *
     * @return array{ok: bool, driver: string, app_key_set: bool, reason?: string}
     */
    public function checkWeb(): array
    {
        $driver = (string) config('session.driver', 'file');
        $appKey = config('app.key');

        if (! is_string($appKey) || $appKey === '') {
            return [
                'ok' => false,
                'driver' => $driver,
                'app_key_set' => false,
                'reason' => 'app_key_missing',
            ];
        }

        $roundTrip = null;

        try {
            $probe = Crypt::encryptString('leopardo-health-web-probe');
            $roundTrip = Crypt::decryptString($probe);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'driver' => $driver,
                'app_key_set' => true,
                'reason' => class_basename($e),
            ];
        }

        if ($roundTrip !== 'leopardo-health-web-probe') {
            return [
                'ok' => false,
                'driver' => $driver,
                'app_key_set' => true,
                'reason' => 'encryption_roundtrip_mismatch',
            ];
        }

        return [
            'ok' => true,
            'driver' => $driver,
            'app_key_set' => true,
        ];
    }

    /**
     * #7014 (part of #6919) : garde de configuration « livraison » pour la
     * production — un mailer `log`/`array`, un hôte SMTP ou un domaine
     * Mailgun sandbox, ou un provider WhatsApp configuré sans secrets Meta
     * (fallback audit silencieux, PA2-COMM-008) rendent les envois
     * transactionnels factices tout en les rapportant comme envoyés.
     * Check non bloquant et sans effet de bord : hors production il est
     * `skipped` ; en production il est `ok` quand la configuration est
     * saine, `degraded` + `issues[]` (slugs machine) sinon. Le 503 du
     * health reste piloté par le check base de données uniquement, donc
     * les deploy gates existants ne sont pas affectés.
     *
     * @return array{ok: bool, status: string, issues?: list<string>}
     */
    public function checkDeliveryConfiguration(): array
    {
        $guard = new ProductionDeliveryGuard;

        if (! $guard->isApplicable()) {
            return ['ok' => true, 'status' => 'skipped'];
        }

        $issues = $guard->issues();

        if ($issues === []) {
            return ['ok' => true, 'status' => 'ok'];
        }

        return [
            'ok' => false,
            'status' => 'degraded',
            'issues' => $issues,
        ];
    }

    /**
     * @return array{ok: bool, driver?: string, size?: int, queues?: array<string, int>,
     *     pending?: array<string, int>, scheduled?: array<string, int>, reserved?: array<string, int>,
     *     pending_total?: int, scheduled_total?: int, failed_jobs?: int|null,
     *     oldest_reserved_seconds?: int|null}
     */
    public function checkQueue(): array
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === 'sync') {
            return ['ok' => true, 'driver' => 'sync'];
        }

        try {
            $connection = app('queue')->connection();
            $queues = [];

            foreach (QueueObservabilityService::QUEUES as $queue) {
                $queues[$queue] = (int) $connection->size($queue);
            }

            // #7540 — `size` et `queues` comptent **toutes** les lignes de la
            // queue, `available_at` inclus : un job planifié à +7 jours y pèse
            // autant qu'un job bloqué depuis 7 jours. C'est ce qui a fait
            // ouvrir #7540 (« la file `notifications` ne se draine pas ») alors
            // que les 23 jobs en cause étaient le planning du drip d'essai
            // (`SendTrialDripEmailJob`, +1/+3/+7 jours). On publie donc en plus
            // — et sans toucher au contrat `size`/`queues` lu par la garde de
            // dérive et les tableaux de bord — ce qui est éligible maintenant,
            // planifié, et réservé. Sur un driver non `database`, la
            // ventilation n'est pas mesurable : les trois cartes restent vides
            // plutôt que d'afficher un 0 trompeur.
            $breakdown = QueueObservabilityService::queueBreakdown(QueueObservabilityService::QUEUES);

            $pending = [];
            $scheduled = [];
            $reserved = [];

            foreach ($breakdown ?? [] as $queue => $row) {
                $pending[(string) $queue] = $row['pending'];
                $scheduled[(string) $queue] = $row['scheduled'];
                $reserved[(string) $queue] = $row['reserved'];
            }

            return [
                'ok' => true,
                'driver' => $driver,
                'size' => array_sum($queues),
                'queues' => $queues,
                'pending' => $pending,
                'scheduled' => $scheduled,
                'reserved' => $reserved,
                'pending_total' => array_sum($pending),
                'scheduled_total' => array_sum($scheduled),
                'failed_jobs' => $this->failedJobsCount(),
                // #7694 — âge de la plus vieille réservation : permet à la
                // supervision externe (queue-supervision.yml, sonde HTTP sans
                // credentials) de détecter un worker mort ; le seuil « stale »
                // appartient à la sonde. `null` = aucune réservation en cours
                // ou non mesurable (driver non `database`).
                'oldest_reserved_seconds' => QueueObservabilityService::oldestReservedAgeSeconds(),
            ];
        } catch (Throwable) {
            return ['ok' => false, 'driver' => $driver];
        }
    }

    /**
     * #7385 — « mailer muet » : le trou que #7255 avait identifié sans le
     * combler. `/health/ready` couvrait la base, Redis et la queue, mais pas
     * le transport mail. Une instance dont le SMTP ne répond plus renvoyait
     * donc `status: ok` alors que la réinitialisation de mot de passe était
     * DÉJÀ MORTE — le contrôleur avale l'échec d'envoi par anti-énumération
     * (cf. #6751), donc aucun utilisateur ni aucune sonde ne le voyait.
     *
     * On ne teste que les transports RÉSEAU : `log` et `array` n'ont aucune
     * dépendance externe, les sonder n'aurait pas de sens (même logique que
     * `checkRedis()` qui renvoie `skipped` quand Redis n'est pas voulu).
     *
     * #9317 — le transport RÉELLEMENT résolu est sondé, quel qu'il soit :
     * `smtp` par `tcp connect`, les transports HTTP-API (Resend) par la
     * présence de la clé + l'existence DNS du domaine d'envoi. Avant ce
     * correctif la garde était `$mailer !== 'smtp'`, donc le transport de
     * production (`resend`) était rapporté `skipped`/`ok` — un mailer cassé
     * produisait un faux vert (mesure du 2026-11-10).
     *
     * Le test SMTP est un `tcp connect` court, PAS un envoi : il attrape
     * l'hôte injoignable et le port fermé — la panne de transport, celle qui
     * a effectivement bloqué la production. Il n'attrape PAS un mot de passe
     * SMTP invalide, d'où `status: reachable` et non `sent`.
     *
     * Ni l'hôte SMTP ni l'adresse expéditeur ne sont exposés : `/health` est
     * public, et un nom d'hôte interne est une information d'infrastructure.
     *
     * @return array{ok: bool, status: string, mailer?: string, port?: int, latency_ms?: int, error?: string}
     */
    public function checkMail(): array
    {
        $diagnostics = app(MailTransportDiagnostics::class);
        $mailer = $diagnostics->resolvedMailer();

        // #9317 — transports LOCAUX uniquement : aucune dépendance externe,
        // `skipped` est l'état honnête. Tout autre transport est sondé.
        //
        // Avant ce correctif, la garde était `$mailer !== 'smtp'` : le
        // transport de PRODUCTION (`resend`) tombait donc dans le `skipped`
        // et un mailer cassé était rapporté `ok: true` — le faux vert que
        // #7385 prétendait avoir comblé, mesuré le 2026-11-10.
        if ($diagnostics->isLocalTransport()) {
            return ['ok' => true, 'status' => 'skipped', 'mailer' => $mailer];
        }

        if ($diagnostics->requiresVerifiedSendingDomain()) {
            return $this->checkApiMailer($diagnostics, $mailer);
        }

        if ($mailer === 'smtp') {
            return $this->checkSmtpMailer($mailer);
        }

        // Transport réseau hors périmètre de sonde : on vérifie au moins la
        // présence des credentials connus, et on le DIT (`unprobed`) au lieu
        // de le déclarer sain par défaut.
        if ($diagnostics->missingRequiredEnv() !== []) {
            return [
                'ok' => false,
                'status' => 'misconfigured',
                'mailer' => $mailer,
                'error' => $diagnostics->missingCredentialSlug() ?? 'MAIL_CREDENTIALS_MISSING',
            ];
        }

        return ['ok' => true, 'status' => 'unprobed', 'mailer' => $mailer];
    }

    /**
     * #9317 — sonde d'un transport HTTP-API (Resend) : la panne mesurée en
     * production n'était pas un hôte injoignable mais une chaîne
     * d'authentification/expédition incomplète. On vérifie donc, dans l'ordre
     * où le fournisseur les refuse :
     *
     *  1. la clé API est présente (sinon `misconfigured`, jamais `skipped`) ;
     *  2. le domaine de l'expéditeur EXISTE dans le DNS — un domaine
     *     inexistant (NXDOMAIN) ne peut pas être vérifié chez le fournisseur,
     *     donc aucun envoi ne partira quelle que soit la clé. C'est le cas
     *     mesuré : `MAIL_FROM_ADDRESS` pointait sur `leopardo-rh.com`,
     *     NXDOMAIN (cf. #8062).
     *
     * On ne teste PAS la validité de la clé (cela exigerait un appel réseau
     * sortant à chaque sonde) : d'où `status: configured` et non `sent`.
     * L'adresse expéditrice n'est jamais exposée — `/health` est public.
     *
     * @return array{ok: bool, status: string, mailer: string, error?: string}
     */
    private function checkApiMailer(MailTransportDiagnostics $diagnostics, string $mailer): array
    {
        if ($diagnostics->resendKey() === '') {
            return [
                'ok' => false,
                'status' => 'misconfigured',
                'mailer' => $mailer,
                'error' => 'RESEND_KEY_MISSING',
            ];
        }

        $domain = $diagnostics->fromDomain();

        if ($domain === '') {
            return [
                'ok' => false,
                'status' => 'misconfigured',
                'mailer' => $mailer,
                'error' => 'MAIL_FROM_ADDRESS_MISSING',
            ];
        }

        if (! $diagnostics->domainResolves($domain)) {
            return [
                'ok' => false,
                'status' => 'unverified_domain',
                'mailer' => $mailer,
                'error' => 'MAIL_FROM_DOMAIN_UNRESOLVED',
            ];
        }

        return ['ok' => true, 'status' => 'configured', 'mailer' => $mailer];
    }

    /**
     * Sonde SMTP : `tcp connect` court vers `MAIL_HOST:MAIL_PORT`.
     *
     * @return array{ok: bool, status: string, mailer: string, port?: int, latency_ms?: int, error?: string}
     */
    private function checkSmtpMailer(string $mailer): array
    {
        $host = $this->stringConfigValue(config('mail.mailers.smtp.host'));

        if ($host === '') {
            return ['ok' => false, 'status' => 'misconfigured', 'mailer' => $mailer, 'error' => 'MAIL_HOST_MISSING'];
        }

        $port = (int) config('mail.mailers.smtp.port', 587);
        $start = microtime(true);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, self::MAIL_CONNECT_TIMEOUT_SECONDS);

        if ($socket === false) {
            return [
                'ok' => false,
                'status' => 'unreachable',
                'mailer' => $mailer,
                'port' => $port,
                'error' => $errno !== 0 ? (string) $errno : 'CONNECT_FAILED',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        fclose($socket);

        return [
            'ok' => true,
            'status' => 'reachable',
            'mailer' => $mailer,
            'port' => $port,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /**
     * Issue #5282 : expose le nombre de jobs en échec sur /health pour les
     * scrapers d'uptime et la supervision manuelle.
     */
    public function failedJobsCount(): ?int
    {
        try {
            return DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * #9976 — fraîcheur du scheduler : le scheduler tourne-t-il encore ?
     *
     * ## Le trou que cette sonde comble
     *
     * `checkQueue()` mesure la file : backlog, jobs réservés, échecs. Il ne dit
     * RIEN du scheduler. Or sur le plan Render gratuit, le scheduler tourne
     * DANS le conteneur web (`WEB_SCHEDULER_LOOP`), qui s'endort après ~15 min
     * d'inactivité. Conséquence mesurée : les tâches dues pendant le sommeil
     * ne s'exécutent pas — acquisition de congés, relances de recouvrement,
     * rapprochement de paiements. La file reste vide, `checkQueue()` reste
     * vert, et le produit est silencieusement faux.
     *
     * `scheduler:catch-up` (#7967) rattrape les créneaux perdus, mais il est
     * lui-même planifié : si le scheduler est mort, le rattrapage ne tourne
     * pas non plus. Personne ne le voyait.
     *
     * ## Ce que la sonde mesure
     *
     * L'âge du dernier créneau enregistré dans `scheduler_slot_runs` (écrit
     * par `SchedulerRunLedger` à chaque passage). Si le scheduler tourne, cette
     * table avance à chaque minute. Si elle n'avance plus, le scheduler est
     * arrêté — quel qu'en soit le motif (spin-down, crash, gate à false sans
     * worker provisionné).
     *
     * ## Pourquoi `ok` reste vrai par défaut
     *
     * Une base fraîchement migrée n'a aucune ligne : ce n'est pas une panne.
     * `status: unknown` le dit sans faire échouer `/health/ready` — même
     * doctrine que `checkRedis()` (`skipped` n'est jamais un échec). Le seuil
     * d'alerte appartient à la sonde externe (`queue-supervision.yml`), qui
     * sait quelle topologie est déployée.
     *
     * @return array{ok: bool, status: string, last_slot_age_seconds?: int|null, last_slot_at?: string|null}
     */
    public function checkScheduler(): array
    {
        try {
            if (! Schema::hasTable('scheduler_slot_runs')) {
                return ['ok' => true, 'status' => 'unknown', 'last_slot_age_seconds' => null];
            }

            $last = DB::table('scheduler_slot_runs')->max('executed_at');

            if ($last === null) {
                return ['ok' => true, 'status' => 'unknown', 'last_slot_age_seconds' => null];
            }

            $lastAt = Carbon::parse((string) $last);
            $age = max(0, (int) $lastAt->diffInSeconds(now()));

            return [
                'ok' => true,
                'status' => 'observed',
                'last_slot_age_seconds' => $age,
                'last_slot_at' => $lastAt->toIso8601String(),
            ];
        } catch (Throwable) {
            // La sonde ne doit jamais faire tomber /health : une erreur ici
            // signifie « non mesurable », pas « scheduler mort ».
            return ['ok' => true, 'status' => 'unknown', 'last_slot_age_seconds' => null];
        }
    }

    /**
     * @return array{ok: bool, usage_mb: int, peak_mb: int, limit_mb: int|null}
     */
    public function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '-1');

        return [
            'ok' => $limit < 0 || $usage < $limit * 0.9,
            'usage_mb' => (int) round($usage / 1048576),
            'peak_mb' => (int) round($peak / 1048576),
            'limit_mb' => $limit > 0 ? (int) round($limit / 1048576) : null,
        ];
    }

    public function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return -1;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    public function stringConfigValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
