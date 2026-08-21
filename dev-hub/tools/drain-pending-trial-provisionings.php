#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * drain-pending-trial-provisionings.php — Issue #5172 / #4948.
 *
 * Outil ops standalone : liste les `trial_provisionings` bloqués en « pending »
 * (worker de queue jamais exécuté sur Render → jobs jamais traités) et permet :
 *
 *   --action=list     (défaut)   lister les lignes bloquées (dry-run, aucune écriture) ;
 *   --action=fail                marquer proprement `failed` (statut terminal : le
 *                                prospect poll GET /trial/status et reçoit enfin un
 *                                état honnête au lieu d'attendre indéfiniment) ;
 *   --action=requeue             re-dispatcher le VRAI job
 *                                (App\Jobs\ProvisionDemoTenantJob) avec les arguments
 *                                d'origine (company_name/country, migration
 *                                2026_08_18_000001) — retries + hook failed() de l'app.
 *
 * Mode dry-run PAR DÉFAUT : sans --apply, aucune écriture en base / aucune file.
 *
 * Usage :
 *   php dev-hub/tools/drain-pending-trial-provisionings.php [--action=list|fail|requeue]
 *       [--max-age-minutes=30] [--limit=50] [--apply] [--help]
 *
 * Connexion DB (par ordre de priorité) :
 *   1. Bootstrap Laravel — si api/vendor + api/.env présents (connexion app, env
 *      du service Render injecté automatiquement) ; requis pour --action=requeue ;
 *   2. PDO direct via variables d'env DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/
 *      DB_PASSWORD (ou api/.env parsé si les variables ne sont pas posées) —
 *      liste + fail uniquement.
 *
 * Relations avec les commandes existantes de l'app (inchangées) :
 *   - `php artisan trial-provisionings:sweep`       → fail des lignes > 30 min
 *                                                    (planifiée toutes les 15 min) ;
 *   - `php artisan trial:provisioning-sweep`        → re-dispatch auto (max 3
 *                                                    tentatives, colonne attempts).
 *   Ce script regroupe les deux usages en un outil ops unique, exécutable hors de
 *   l'app (ex. machine locale avec accès DB), avec dry-run par défaut.
 *
 * Schéma (migrations public/2026_08_15_000001 + 2026_08_15_000003 +
 * 2026_08_15_000012 + 2026_08_18_000001) :
 *   status ∈ {pending, ready, failed}  ·  colonnes de reprise : company_name,
 *   country, attempts  ·  index unique partiel (email) WHERE status='pending' (#3951).
 */

use Illuminate\Support\Facades\DB;

// ─────────────────────────────────────────────────────────────────────────────
// 1. CLI
// ─────────────────────────────────────────────────────────────────────────────

$options = [
    'action' => 'list',        // list | fail | requeue
    'maxAgeMinutes' => 30,     // lignes plus vieilles que X minutes (updated_at)
    'limit' => 0,              // 0 = pas de limite
    'apply' => false,          // dry-run par défaut
    'help' => false,
];

$argv = array_slice($argv, 1);
foreach ($argv as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
        continue;
    }
    if (! str_starts_with($arg, '--')) {
        fwrite(STDERR, "Argument inconnu : {$arg} (voir --help)\n");
        exit(2);
    }
    [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, 'true');
    switch ($name) {
        case 'action':
            if (! in_array($value, ['list', 'fail', 'requeue'], true)) {
                fwrite(STDERR, "--action doit être list|fail|requeue (reçu : {$value})\n");
                exit(2);
            }
            $options['action'] = $value;
            break;
        case 'max-age-minutes':
            $options['maxAgeMinutes'] = max(1, (int) $value);
            break;
        case 'limit':
            $options['limit'] = max(0, (int) $value);
            break;
        case 'apply':
            $options['apply'] = true;
            break;
        default:
            fwrite(STDERR, "Option inconnue : --{$name} (voir --help)\n");
            exit(2);
    }
}

if ($options['help']) {
    echo <<<HELP
    drain-pending-trial-provisionings.php — drain des trial_provisionings bloqués (#5172/#4948)

    Usage :
      php dev-hub/tools/drain-pending-trial-provisionings.php [options]

    Options :
      --action=list|fail|requeue   action à exécuter (défaut : list)
      --max-age-minutes=30         âge minimal (updated_at) pour considérer une ligne
                                   bloquée (défaut : 30 — très au-delà du backoff max
                                   du job : 5 tries × [30,60,120,300] ≈ 8,5 min)
      --limit=50                   borne le nombre de lignes traitées (0 = illimité)
      --apply                      exécute réellement (sans : dry-run, aucune écriture)
      --help                       cette aide

    Actions :
      list      liste les lignes bloquées (status pending/provisioning_sandbox) ;
      fail      passe les lignes bloquées en status=failed (erreur tracée) ;
      requeue   re-dispatche App\\Jobs\\ProvisionDemoTenantJob avec les arguments
                d'origine (exige api/vendor — bootstrap Laravel ; sinon utiliser
                `php artisan trial:provisioning-sweep` depuis le shell Render).

    Exemples :
      php dev-hub/tools/drain-pending-trial-provisionings.php --action=list
      php dev-hub/tools/drain-pending-trial-provisionings.php --action=requeue --apply
      php dev-hub/tools/drain-pending-trial-provisionings.php --action=fail --max-age-minutes=45 --apply

    Connexion : bootstrap Laravel si dispo (api/vendor + api/.env), sinon PDO via
    DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD (ou api/.env).
    Sortie : exit 0 = succès · 1 = erreur · 2 = usage invalide.
    HELP;
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Bootstrap Laravel (prioritaire) ou PDO fallback
// ─────────────────────────────────────────────────────────────────────────────

$apiDir = dirname(__DIR__, 2) . '/api';
$bootstrapped = false;

if (is_file($apiDir . '/vendor/autoload.php') && is_file($apiDir . '/bootstrap/app.php')) {
    require $apiDir . '/vendor/autoload.php';
    /** @var Illuminate\Foundation\Application $app */
    $app = require $apiDir . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $bootstrapped = true;
}

/**
 * Retourne le PDO à utiliser (connexion Laravel si bootstrappée, sinon PDO direct).
 *
 * @throws RuntimeException si aucune connexion n'est configurable
 */
function drainPdo(bool $bootstrapped, string $apiDir): PDO
{
    if ($bootstrapped) {
        return DB::connection()->getPdo();
    }

    $env = loadDotEnv($apiDir . '/.env');

    $host = envValue('DB_HOST', $env, '127.0.0.1');
    $port = envValue('DB_PORT', $env, '5432');
    $db = envValue('DB_DATABASE', $env, '');
    $user = envValue('DB_USERNAME', $env, '');
    $pass = envValue('DB_PASSWORD', $env, '');
    $driver = envValue('DB_CONNECTION', $env, 'pgsql');

    if ($db === '' || $user === '') {
        throw new RuntimeException(
            "Connexion DB impossible : ni api/vendor (bootstrap Laravel) ni variables ".
            "DB_HOST/DB_DATABASE/DB_USERNAME définies. Voir --help."
        );
    }

    $dsn = $driver === 'mysql'
        ? "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4"
        : "pgsql:host={$host};port={$port};dbname={$db}";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Lit une variable d'env : priorité aux vraies variables d'env du process,
 * puis aux valeurs du fichier .env Laravel.
 */
function envValue(string $key, array $dotEnv, string $default): string
{
    $raw = getenv($key);
    if ($raw !== false && $raw !== '') {
        return $raw;
    }

    return $dotEnv[$key] ?? $default;
}

/** Parseur minimal de .env (KEY=VALUE, commentaires #, guillemets simples/doubles). */
function loadDotEnv(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '') {
            continue;
        }
        $val = trim($val);
        if (strlen($val) >= 2 && (($val[0] === '"' && str_ends_with($val, '"')) || ($val[0] === "'" && str_ends_with($val, "'")))) {
            $val = substr($val, 1, -1);
        }
        $values[$key] = $val;
    }

    return $values;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Accès données
// ─────────────────────────────────────────────────────────────────────────────

/** Nom de table qualifié selon le driver (public.* en PostgreSQL, schéma par défaut sinon). */
function drainTableName(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? 'public.trial_provisionings'
        : 'trial_provisionings';
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchStuckRows(PDO $pdo, int $maxAgeMinutes, int $limit): array
{
    $table = drainTableName($pdo);
    $cutoff = date('Y-m-d H:i:s', time() - $maxAgeMinutes * 60);

    $sql = "SELECT id, email, provisioning_token, status, company_name, country, attempts, updated_at, error
            FROM {$table}
            WHERE status IN ('pending', 'provisioning_sandbox')
              AND updated_at < ?
            ORDER BY updated_at ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit; // $limit est un entier validé (int), pas une entrée utilisateur libre
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cutoff]);

    return $stmt->fetchAll();
}

function countStuckRows(PDO $pdo, int $maxAgeMinutes): int
{
    $table = drainTableName($pdo);
    $cutoff = date('Y-m-d H:i:s', time() - $maxAgeMinutes * 60);

    $stmt = $pdo->prepare("SELECT count(*) FROM {$table} WHERE status IN ('pending','provisioning_sandbox') AND updated_at < ?");
    $stmt->execute([$cutoff]);

    return (int) $stmt->fetchColumn();
}

function markFailed(PDO $pdo, int $id, string $error): void
{
    $table = drainTableName($pdo);
    $stmt = $pdo->prepare("UPDATE {$table} SET status = 'failed', error = ?, updated_at = now() WHERE id = ?");
    $stmt->execute([mb_substr($error, 0, 500), $id]);
}

/** Re-dispatch du vrai job — exige le bootstrap Laravel (file redis + retries + hook failed()). */
function requeueRow(array $row, bool $bootstrapped): void
{
    if (! $bootstrapped) {
        throw new RuntimeException(
            "--action=requeue --apply exige le bootstrap Laravel (api/vendor + api/.env). " .
            "Exécutez ce script sur une machine avec composer install, ou utilisez " .
            "`php artisan trial:provisioning-sweep` depuis le shell du service Render."
        );
    }

    DB::table(drainTableName(DB::connection()->getPdo()))
        ->where('id', $row['id'])
        ->increment('attempts');

    \App\Jobs\ProvisionDemoTenantJob::dispatch(
        (string) $row['email'],
        (string) $row['company_name'],
        (string) $row['country'],
        (string) $row['provisioning_token'],
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Exécution
// ─────────────────────────────────────────────────────────────────────────────

try {
    $pdo = drainPdo($bootstrapped, $apiDir);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}

$total = countStuckRows($pdo, $options['maxAgeMinutes']);
$rows = fetchStuckRows($pdo, $options['maxAgeMinutes'], $options['limit']);

$mode = $options['apply'] ? 'APPLY' : 'dry-run';
$prefix = $options['apply'] ? '[apply]' : '[dry-run]';

echo sprintf(
    "trial_provisionings bloquées (>= %d min, status pending/provisioning_sandbox) : %d trouvée(s), %d affichée(s) — mode %s\n",
    $options['maxAgeMinutes'],
    $total,
    count($rows),
    $mode,
);

if ($rows === []) {
    echo "Rien à faire.\n";
    exit(0);
}

$reconstructible = 0;
foreach ($rows as $row) {
    $hasContext = is_string($row['company_name']) && $row['company_name'] !== ''
        && is_string($row['country']) && $row['country'] !== '';
    if ($hasContext) {
        $reconstructible++;
    }
    $ageMin = (int) floor((time() - strtotime((string) $row['updated_at'])) / 60);
    $token = substr((string) $row['provisioning_token'], 0, 8) . '…';

    echo sprintf(
        "#%d  %-42s token=%s  status=%-8s age=%d min  attempts=%d  context=%s\n",
        (int) $row['id'],
        (string) $row['email'],
        $token,
        (string) $row['status'],
        $ageMin,
        (int) $row['attempts'],
        $hasContext ? 'oui' : 'NON (pre-2026_08_18)',
    );

    if ($options['action'] === 'list') {
        continue;
    }

    if ($options['action'] === 'fail') {
        if (! $options['apply']) {
            echo "  {$prefix} marquer #{$row['id']} en failed\n";
            continue;
        }
        markFailed($pdo, (int) $row['id'], 'DRAIN_FAIL: marqué failed par ops (worker de queue jamais exécuté) — issue #5172/#4948.');
        echo "  {$prefix} #{$row['id']} → failed\n";
        continue;
    }

    // action === 'requeue'
    if (! $hasContext) {
        if ($options['apply']) {
            markFailed($pdo, (int) $row['id'], 'DRAIN_REQUEUE_IMPOSSIBLE: company_name/country absents (ligne antérieure à la migration 2026_08_18_000001) — issue #4948.');
            echo "  {$prefix} #{$row['id']} non reconstructible → failed\n";
        } else {
            echo "  {$prefix} #{$row['id']} non reconstructible (company_name/country manquants) → prévoir --action=fail\n";
        }
        continue;
    }

    if ((int) $row['attempts'] >= 3) {
        // Miroir du sweeper de l'app (#4948) : max 3 tentatives, ensuite fail-loud.
        if ($options['apply']) {
            markFailed($pdo, (int) $row['id'], 'DRAIN_MAX_ATTEMPTS: 3 re-dispatches déjà tentés sans succès (worker toujours défaillant ?) — issue #4948.');
            echo "  {$prefix} #{$row['id']} attempts >= 3 → failed\n";
        } else {
            echo "  {$prefix} #{$row['id']} attempts >= 3 (max) → re-dispatch refusé, prévoir --action=fail\n";
        }
        continue;
    }

    if (! $options['apply']) {
        echo "  {$prefix} re-dispatcher ProvisionDemoTenantJob #{$row['id']} (attempt " . ((int) $row['attempts'] + 1) . ")\n";
        continue;
    }

    try {
        requeueRow($row, $bootstrapped);
        echo "  {$prefix} #{$row['id']} re-dispatched (attempt " . ((int) $row['attempts'] + 1) . ") — status reste pending jusqu'à exécution\n";
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

$summary = match ($options['action']) {
    'fail' => $options['apply'] ? 'Lignes marquées failed.' : 'Dry-run : aucune écriture. Relancer avec --apply pour marquer failed.',
    'requeue' => $options['apply'] ? 'Jobs re-dispatched.' : 'Dry-run : aucune écriture. Relancer avec --apply pour re-dispatcher.',
    default => sprintf('%d ligne(s) bloquée(s) (%d reconstructible(s) pour un re-queue).', count($rows), $reconstructible),
};
echo $summary . "\n";

exit(0);
