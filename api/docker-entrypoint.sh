#!/bin/sh
set -e

# ─── Contrat DEPLOY_TIER (issue #7647) ────────────────────────────────────────
# DEPLOY_TIER identifie le TIER de déploiement (`dev` | `prod`), découplé
# d'APP_ENV (histoire : le tier dev a tourné en APP_ENV=production — dette
# soldée par #7648, le dev est désormais en APP_ENV=staging, cf.
# docs/ops/RENDER_DEV_PROD_TOPOLOGY.md). APP_ENV ne doit PAS servir à
# distinguer dev et prod : c'est le rôle de DEPLOY_TIER. La variable est
# posée par les blueprints
# (render.yaml → dev, render.prod.yaml → prod). Effets FAIL-CLOSED ici :
#   - RESET_TEST_DB_ONCE (DROP total de la base) exige DEPLOY_TIER=dev
#     explicite — absente, vide ou toute autre valeur => refus de démarrer,
#     EN PLUS des gardes APP_ENV existantes (#6537, conservées).
#   - FORCE_SUPER_ADMIN_PASSWORD_RESET n'est propagée aux seeders
#     (SuperAdminSeeder) que si DEPLOY_TIER=dev ; sinon la variable est
#     vidée AVANT `db:seed`, avec warning — le hash super-admin n'est plus
#     re-forcé à chaque boot hors du tier dev.
# ──────────────────────────────────────────────────────────────────────────────

echo "Optimizing Laravel at startup..."

# Probe availability (meilleur -> pire, decision 2026-08-21) : Redis (Upstash)
# si joignable, sinon file. La queue reste sur database (pas de quota,
# drainable par GitHub Actions #5204/#5205). On fige les drivers AVANT
# config:cache pour que le cache de config soit coherent avec la realite.
PROBE_ENV=/tmp/leopardo-availability.env
php artisan infra:probe-availability --format=env > "$PROBE_ENV" 2>/dev/null || true
if [ -s "$PROBE_ENV" ]; then
    set -a
    . "$PROBE_ENV"
    set +a
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

db_pdo_bootstrap() {
    php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$dbUrl = getenv('DB_URL') ?: '';

if ($dbUrl) {
    $parts = parse_url($dbUrl);
    if ($parts !== false) {
        $host = $host ?: ($parts['host'] ?? '');
        $port = $port ?: (isset($parts['port']) ? (string) $parts['port'] : '');
        $database = $database ?: ltrim($parts['path'] ?? '', '/');
        $username = $username ?: ($parts['user'] ?? '');
        $password = $password ?: ($parts['pass'] ?? '');
    }
}

$host = $host ?: '127.0.0.1';
$port = $port ?: '5432';

if ($database === '') {
    fwrite(STDERR, "DB bootstrap error: missing DB_DATABASE/DB_URL database name.\n");
    exit(1);
}

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $ddl = <<<'SQL'
CREATE SCHEMA IF NOT EXISTS shared_tenants;
CREATE TABLE IF NOT EXISTS public.migrations (
    id serial PRIMARY KEY,
    migration varchar(255) NOT NULL,
    batch integer NOT NULL
);
CREATE TABLE IF NOT EXISTS shared_tenants.migrations (
    id serial PRIMARY KEY,
    migration varchar(255) NOT NULL,
    batch integer NOT NULL
);
CREATE TABLE IF NOT EXISTS public.seed_locks (
    lock_key varchar(255) PRIMARY KEY,
    ran_at timestamp with time zone null,
    created_at timestamp with time zone not null default now(),
    updated_at timestamp with time zone not null default now()
);
SQL;

    $pdo->exec($ddl);
    echo "Migration repositories ensured (public/shared_tenants).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "DB bootstrap error: {$e->getMessage()}\n");
    exit(1);
}
PHP
}

ensure_migration_repository() {
    db_pdo_bootstrap
}

wait_for_db_bootstrap() {
    attempt=1
    max_attempts=30

    while [ "$attempt" -le "$max_attempts" ]; do
        if ensure_migration_repository; then
            return 0
        fi

        echo "Database not ready for bootstrap ($attempt/$max_attempts), retry in 2s..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "Database bootstrap failed after $max_attempts attempts."
    return 1
}

# Issue #6916 : les migrations du boot doivent transiter par l'hôte DIRECT de
# la base, jamais par le pooler Neon en mode transaction — toute migration DDL
# y est abortée (SQLSTATE 25P02) → deploy `update_failed` (constat 2026-09-06,
# aucun live dev depuis le 08-20). `DB_MIGRATE_URL` (explicite) prime ; sinon
# on dérive l'hôte direct de `DB_URL` en retirant le jeton "-pooler"
# (convention d'hôte Neon : `ep-<id>[-pooler].<region>.aws.neon.tech`). La
# prod (DB_URL hôte direct) et le local (DB_HOST/...) ne sont pas affectés.
resolve_migrate_db_url() {
    url="${DB_MIGRATE_URL:-${DB_URL:-}}"
    case "$url" in
        *-pooler.*)
            echo "  [migrate] pooler détecté dans DB_URL — bascule sur l'hôte direct pour artisan migrate (#6916)." >&2
            url=$(printf '%s' "$url" | sed 's/-pooler\././')
            ;;
    esac
    printf '%s' "$url"
}

run_migrate_with_retry() {
    path="$1"
    search_path="$2"
    migrate_url="$(resolve_migrate_db_url)"
    attempt=1
    max_attempts=3

    # Sous-processus migrate avec DB_URL surchargée (hôte direct, #6916) ;
    # si aucune URL n'est disponible (ex. local via DB_HOST), config inchangée.
    run_migrate_once() {
        if [ -n "$migrate_url" ]; then
            DB_URL="$migrate_url" DB_SEARCH_PATH="$search_path" php artisan migrate --path="$path" --force --isolated
        else
            DB_SEARCH_PATH="$search_path" php artisan migrate --path="$path" --force --isolated
        fi
    }

    while [ "$attempt" -le "$max_attempts" ]; do
        if run_migrate_once >/tmp/render-migrate.log 2>&1; then
            cat /tmp/render-migrate.log
            return 0
        fi

        cat /tmp/render-migrate.log

        if grep -Eq 'SQLSTATE\[42P07\]|relation "migrations" already exists' /tmp/render-migrate.log; then
            if [ "$attempt" -lt "$max_attempts" ]; then
                echo "Migrations table race detected for $path ($attempt/$max_attempts), retrying in 2s..."
                sleep 2
            else
                echo "Migrations table race persisted for $path after $max_attempts attempts."
                echo "Running isolated global catch-up migrations..."
                if run_migrate_once >/tmp/render-migrate-catchup.log 2>&1; then
                    cat /tmp/render-migrate-catchup.log
                    return 0
                fi
                cat /tmp/render-migrate-catchup.log
                return 1
            fi
        elif [ "$attempt" -lt "$max_attempts" ]; then
            echo "Migration failed for $path ($attempt/$max_attempts), retry in 5s..."
            sleep 5
        else
            echo "Final migration failure for $path."
            return 1
        fi

        attempt=$((attempt + 1))
    done

    return 1
}

maybe_reset_test_database_once() {
    reset_once=$(php -r "echo filter_var(getenv('RESET_TEST_DB_ONCE') ?: false, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';")

    # #6537 (audit-secu E-1) : RESET_TEST_DB_ONCE est une variable documentée
    # pour les TESTS ; la positionner sur un environnement de production doit
    # faire échouer le boot (fail-closed), jamais détruire la base (DROP de
    # toutes les tables/séquences/vues + DROP SCHEMA shared_tenants CASCADE).
    if [ "$reset_once" = "true" ] && [ "${APP_ENV:-}" = "production" ]; then
        echo "FATAL: RESET_TEST_DB_ONCE=true is forbidden when APP_ENV=production (destructive one-shot DB reset). Aborting startup." >&2
        exit 1
    fi

    if [ "$reset_once" != "true" ]; then
        return 0
    fi

    # Issue #6537 (audit securite E-1) : fail-closed — RESET_TEST_DB_ONCE
    # exécute un DROP de TOUTES les tables/séquences/vues + DROP SCHEMA
    # shared_tenants CASCADE. Cette variable est documentée pour les
    # environnements de TEST uniquement ; positionnée sur un environnement
    # de production (APP_ENV=production, défaut du Dockerfile.prod), on
    # REFUSE de démarrer plutôt que de risquer l'effacement total de la base.
    if [ "${APP_ENV:-production}" = "production" ]; then
        echo "REFUSED (fail-closed): RESET_TEST_DB_ONCE=true est interdit en production (APP_ENV=production). Abandon du demarrage." >&2
        exit 1
    fi

    # Issue #7647 : APP_ENV ne distingue PAS les tiers — le critère de tier
    # est DEPLOY_TIER (render.yaml → dev, render.prod.yaml → prod ; depuis
    # #7648 le tier dev est en APP_ENV=staging), et
    # la garde est fail-closed : DEPLOY_TIER absente, vide ou différente de
    # 'dev' => refus catégorique du reset destructif, EN PLUS des gardes
    # APP_ENV #6537 ci-dessus (conservées).
    if [ "${DEPLOY_TIER:-}" != "dev" ]; then
        echo "REFUSED (fail-closed, #7647): RESET_TEST_DB_ONCE=true exige DEPLOY_TIER=dev explicite (valeur actuelle: '${DEPLOY_TIER:-<absente>}'). Poser DEPLOY_TIER=dev sur le service Render dev (render.yaml) avant tout reset. Abandon du demarrage." >&2
        exit 1
    fi

    reset_key="${RESET_TEST_DB_LOCK_KEY:-render_test_db_reset_v1}"

    if php <<PHP
<?php
try {
    \$pdo = new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_HOST) ?: '127.0.0.1',
            getenv('DB_PORT') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PORT) ?: '5432',
            getenv('DB_DATABASE') ?: ltrim(parse_url(getenv('DB_URL') ?: '', PHP_URL_PATH) ?: '', '/')
        ),
        getenv('DB_USERNAME') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_USER) ?: '',
        getenv('DB_PASSWORD') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PASS) ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    \$pdo->exec("CREATE TABLE IF NOT EXISTS public.seed_locks (lock_key varchar(255) PRIMARY KEY, ran_at timestamp with time zone null, created_at timestamp with time zone not null default now(), updated_at timestamp with time zone not null default now())");
    \$stmt = \$pdo->prepare('SELECT 1 FROM public.seed_locks WHERE lock_key = :lock_key LIMIT 1');
    \$stmt->execute(['lock_key' => '${reset_key}']);
    exit(\$stmt->fetchColumn() ? 0 : 1);
} catch (Throwable \$e) {
    fwrite(STDERR, "Reset check error: {\$e->getMessage()}\n");
    exit(2);
}
PHP
    then
        echo "One-shot test DB reset already applied for lock ${reset_key}, skipping destructive reset."
        return 0
    else
        status=$?
        if [ "$status" -ne 1 ]; then
            echo "Reset check failed with unexpected status ${status}."
            return 1
        fi
    fi

    echo "RESET_TEST_DB_ONCE=true detected. Performing one-shot full reset of test database..."

    php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_HOST) ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PORT) ?: '5432';
$database = getenv('DB_DATABASE') ?: ltrim(parse_url(getenv('DB_URL') ?: '', PHP_URL_PATH) ?: '', '/');
$username = getenv('DB_USERNAME') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_USER) ?: '';
$password = getenv('DB_PASSWORD') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PASS) ?: '';

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$database}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->beginTransaction();
    $pdo->exec(<<<'SQL'
DO $$
DECLARE r RECORD;
BEGIN
    FOR r IN
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP TABLE IF EXISTS public.%I CASCADE', r.tablename);
    END LOOP;

    FOR r IN
        SELECT sequencename
        FROM pg_sequences
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP SEQUENCE IF EXISTS public.%I CASCADE', r.sequencename);
    END LOOP;

    FOR r IN
        SELECT matviewname
        FROM pg_matviews
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP MATERIALIZED VIEW IF EXISTS public.%I CASCADE', r.matviewname);
    END LOOP;

    FOR r IN
        SELECT viewname
        FROM pg_views
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP VIEW IF EXISTS public.%I CASCADE', r.viewname);
    END LOOP;
END $$;
DROP SCHEMA IF EXISTS shared_tenants CASCADE;
CREATE SCHEMA shared_tenants;
SQL);
    $pdo->commit();
    echo "Test database schemas reset completed.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Test database reset failed: {$e->getMessage()}\n");
    exit(1);
}
PHP

    touch /tmp/render-reset-db-once-ran
    return 0
}

mark_test_database_reset_complete() {
    if [ ! -f /tmp/render-reset-db-once-ran ]; then
        return 0
    fi

    reset_key="${RESET_TEST_DB_LOCK_KEY:-render_test_db_reset_v1}"

    php <<PHP
<?php
try {
    \$pdo = new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_HOST) ?: '127.0.0.1',
            getenv('DB_PORT') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PORT) ?: '5432',
            getenv('DB_DATABASE') ?: ltrim(parse_url(getenv('DB_URL') ?: '', PHP_URL_PATH) ?: '', '/')
        ),
        getenv('DB_USERNAME') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_USER) ?: '',
        getenv('DB_PASSWORD') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PASS) ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    \$pdo->exec("CREATE TABLE IF NOT EXISTS public.seed_locks (lock_key varchar(255) PRIMARY KEY, ran_at timestamp with time zone null, created_at timestamp with time zone not null default now(), updated_at timestamp with time zone not null default now())");
    \$stmt = \$pdo->prepare('
        INSERT INTO public.seed_locks (lock_key, ran_at, created_at, updated_at)
        VALUES (:lock_key, NOW(), NOW(), NOW())
        ON CONFLICT (lock_key) DO UPDATE SET ran_at = EXCLUDED.ran_at, updated_at = EXCLUDED.updated_at
    ');
    \$stmt->execute(['lock_key' => '${reset_key}']);
    echo "Recorded one-shot test DB reset lock ${reset_key}.\n";
} catch (Throwable \$e) {
    fwrite(STDERR, "Reset lock write error: {\$e->getMessage()}\n");
    exit(1);
}
PHP

    rm -f /tmp/render-reset-db-once-ran
}

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Ensuring migration repository tables..."
    wait_for_db_bootstrap
    maybe_reset_test_database_once
    wait_for_db_bootstrap

    echo "Running public schema migrations..."
    run_migrate_with_retry "database/migrations/public" "public"

    echo "Running tenant schema migrations..."
    # Issue #6924 : search_path du runtime applicatif (défaut config/database.php :
    # shared_tenants,public) et non `shared_tenants` strict — les CREATE restent
    # dans shared_tenants (1er schéma), mais les ALTER résolvent les tables
    # héritées placées historiquement dans public (ex. billing.invoices, constat
    # pré-flight prod 2026-09-06). Un search_path strict faisait échouer le boot
    # (SQLSTATE 42P01) sur toute migration tenant ALTÉRANT une table héritée.
    run_migrate_with_retry "database/migrations/tenant" "shared_tenants,public"

    # Warn operators when SUPER_ADMIN_PASSWORD is not set in production:
    # the seeder will generate a random password that is never displayed in
    # production logs → the admin dashboard will be inaccessible.
    # See docs/deployment/RUNBOOK_SUPER_ADMIN.md for fix instructions.
    # #7648 : le tier dev est passé en APP_ENV=staging — le critère réel de
    # ce warning est « tier hébergé » (DEPLOY_TIER posée, dev OU prod), pas
    # APP_ENV. Garde APP_ENV conservée (additive) pour les déploiements
    # hors blueprint qui n'ont pas DEPLOY_TIER.
    if { [ "${APP_ENV:-}" = "production" ] || [ -n "${DEPLOY_TIER:-}" ]; } && [ -z "${SUPER_ADMIN_PASSWORD:-}" ]; then
        echo "⚠️  WARNING: SUPER_ADMIN_PASSWORD is not set."
        echo "   SuperAdminSeeder will generate a random password that cannot be"
        echo "   recovered from production logs → admin@leopardo-rh.com login will fail."
        echo "   Fix: set SUPER_ADMIN_PASSWORD in the Render dashboard and redeploy."
        echo "   See: docs/deployment/RUNBOOK_SUPER_ADMIN.md"
    fi

    # Issue #7647 : FORCE_SUPER_ADMIN_PASSWORD_RESET fait re-forcer le hash
    # du super-admin par SuperAdminSeeder à CHAQUE déploiement depuis une env
    # var (un accès dashboard Render = takeover admin permanent). Fail-closed :
    # hors du tier dev explicite (DEPLOY_TIER=dev), la variable est vidée
    # AVANT les seeders — le force-reset ne se propage pas, warning bruyant.
    if [ "${DEPLOY_TIER:-}" != "dev" ] && [ -n "${FORCE_SUPER_ADMIN_PASSWORD_RESET:-}" ]; then
        echo "⚠️  WARNING (#7647): FORCE_SUPER_ADMIN_PASSWORD_RESET est posee mais DEPLOY_TIER != 'dev' (valeur: '${DEPLOY_TIER:-<absente>}')."
        echo "   La variable est videe avant les seeders : le hash super-admin ne sera PAS re-force a ce boot."
        echo "   Rotation hors tier dev : php artisan super-admin:reset-password via un canal securise"
        echo "   (docs/deployment/RUNBOOK_SUPER_ADMIN.md), jamais via une env var permanente."
        unset FORCE_SUPER_ADMIN_PASSWORD_RESET
    fi

    echo "Running base seeders (idempotent)..."
    php artisan db:seed --class=DatabaseSeeder --force

    # Audit #1697 : la démo ne doit jamais être seedée en production
    # (garde-fou additionnel à DISABLE_DEMO_SEEDING dans render.yaml).
    # #7648 : garde étendue au TIER prod (DEPLOY_TIER=prod, #7647) —
    # fail-closed même si APP_ENV changeait un jour côté prod. Sur le tier
    # dev (APP_ENV=staging désormais), le seeder tourne mais reste verrouillé
    # par DISABLE_DEMO_SEEDING=true + DEMO_MODE_ENABLED=false + DEMO_PASSWORD
    # absente (#7696) : seuls les backfills non destructifs des démos
    # existantes s'exécutent.
    if [ "${APP_ENV:-}" != "production" ] && [ "${DEPLOY_TIER:-}" != "prod" ]; then
        echo "Running gated demo seed (DEMO_SEED_ONCE)..."
        php artisan db:seed --class=DemoCompanyOnceSeeder --force
    else
        echo "Skipping demo seed (APP_ENV=${APP_ENV:-<absente>}, DEPLOY_TIER=${DEPLOY_TIER:-<absente>})."
    fi

    # QA onboarding 2026-09-14 : un BACKFILL ne doit jamais empêcher le
    # conteneur de démarrer. Constaté en dev : une erreur *transitoire* du
    # pooler Postgres (« SQLSTATE[0A000]: cached plan must not change result
    # type », survenue juste après une migration appliquée par un autre
    # chantier) faisait sortir la commande en erreur ; sous `set -e` le
    # démarrage s'arrêtait là et le déploiement Render partait en
    # `update_failed` — deux déploiements perdus d'affilée, sans aucun lien
    # avec le contenu livré. La commande est idempotente et rejouée à chaque
    # démarrage : on journalise bruyamment (statut + sortie d'erreur
    # conservées) et on poursuit le boot.
    echo "Backfilling notification preferences for active employees..."
    php artisan notifications:backfill-preferences || {
        echo "[entrypoint] ATTENTION : backfill des preferences de notification en echec (statut $?). Demarrage poursuivi — la commande sera rejouee au prochain demarrage." >&2
    }

    mark_test_database_reset_complete
fi

export SERVER_NAME=":${PORT:-8080}"

# Audit #1698 : sur Render, `dockerCommand` (ex. `php artisan queue:work
# redis` ou `sh -c "while true; do php artisan schedule:run; ..."`) est passé
# comme arguments de l'ENTRYPOINT. Avant ce fix, ils étaient ignorés et les
# services « worker »/« scheduler » bootaient FrankenPHP au lieu de consommer
# la queue — les jobs Redis restaient en attente indéfiniment.
if [ "$#" -gt 0 ]; then
    echo "Starting ENTRYPOINT command: $*"
    exec "$@"
fi

# Déploiement mono-conteneur (plan gratuit, pas de service worker dédié) :
# on consomme la queue dans le conteneur web en arrière-plan. Sans cela, les
# jobs (webhooks, audit, notifications, emails…) s'accumulent sans jamais être
# traités. La connexion est la valeur de QUEUE_CONNECTION (database depuis le
# 2026-08-19 : l'Upstash gratuit a brûlé sa quota mensuelle de 500k requêtes
# avec le polling Redis — LPOP 3 s × 8 queues ≈ 230k req/jour → Redis refuse
# toute connexion. Le driver Postgres poll via SELECT FOR UPDATE SKIP LOCKED,
# sans quota). `block_for => null` reste compatible si on revient à Redis.
# Issue #7041 : `queue:work --max-time=3600` s'arrête proprement après 1 h — sans
# supervisor, le worker du conteneur web ne repartait JAMAIS (tier dev figé ~1 h
# après chaque boot, jobs pending sans signal). Boucle de respawn bornée : le
# worker est relancé après chaque sortie (max-time OU crash), avec un délai pour
# éviter un spin sur erreur fatale persistante. Le worker dédié prod
# (`render.prod.yaml`, leopardo-queue-worker) passe par `exec "$@"` ci-dessous
# et n'est PAS concerné par cette boucle.
# Issue #7649 : le drain intérim du conteneur web est gaté par WEB_QUEUE_DRAIN.
# Défaut « true » (comportement conservé) tant qu'aucun worker dédié n'existe
# (provisionnement bloqué billing : 402 API Render, 2026-09-19). Dès que le
# worker leopardo-queue-worker(-prod) est provisionné, poser
# WEB_QUEUE_DRAIN=false sur le web service — sinon deux consommateurs
# concurrents pollent la même table jobs (verrous inutiles sur l'OLTP).
# Quota (2026-09-22) : le worker en boucle interroge la base à vide — le driver
# `database` teste CHAQUE queue de la liste, soit 8 requêtes par cycle. À
# --sleep=5 c'est ~138 000 requêtes/jour, de quoi consommer à lui seul ~2 Go/mois
# du transfert du plan gratuit Neon (5 Go/mois partagés par TOUS les projets du
# compte) : c'est ce qui a fait tomber la prod le 2026-09-20 puis le secours.
# `WEB_QUEUE_SLEEP` permet de ralentir ce polling sans redéployer de code :
#   - 5 (défaut, comportement historique) : latence max 5 s sur un job en file ;
#   - 10/15 en prod (posé côté Render) : moitié/tiers des requêtes, latence
#     dégradée d'autant (acceptable : les e-mails OTP partent par la file
#     `emails` — un utilisateur qui s'inscrit attend au pire la valeur du sleep) ;
#   - ne pas descendre sous 5 sans raison : c'est du quota pur.
# Idéal à terme : worker/scheduler dédiés (issues #7649 / #7845) — le web
# conteneurisé ne devrait pas porter le polling de la file.
if [ "${WEB_QUEUE_DRAIN:-true}" = "true" ]; then
echo "Starting background queue worker (web container, respawn loop)..."
(
    while true; do
        # QA onboarding 2026-09-14 : la sortie du worker était jetée
        # (`>/dev/null 2>&1`). Conséquence constatée en dev : un
        # ProvisionDemoTenantJob épuisait ses 5 essais (statut `failed`, ligne
        # `failed_jobs`) sans qu'AUCUNE exception ne soit exploitable dans les
        # logs Render — 16 failed_jobs indiagnosticables. Le worker écrit
        # désormais sur la sortie d'erreur du conteneur, collectée par la
        # plateforme (LOG_CHANNEL=stderr) ; ses lignes sont déjà identifiables
        # par le canal (`production.ERROR:`), et le `$?` ci-dessous reste celui
        # de `queue:work` (un `| sed` le remplacerait par celui du pipe).
        php artisan queue:work \
            --queue=webhooks,audit,notifications,emails,pdf,payroll,documents,default \
            --tries=3 --timeout=300 --sleep="${WEB_QUEUE_SLEEP:-5}" --max-jobs=500 --max-time=3600 \
            >&2
        echo "[entrypoint] queue worker exited ($?), respawn in 2s..." >&2
        sleep 2
    done
) &
else
    echo "[entrypoint] WEB_QUEUE_DRAIN=false : drain queue désactivé (worker dédié attendu, #7649)." >&2
fi

# Issue #7649 (intérim) : `php artisan schedule:run` ne tournait NULLE PART
# (ni worker ni cron dans les workspaces Render — vérifié API 2026-09-19) :
# accruals congés, relances billing/contrats, purges et réconciliations ne
# s'exécutaient jamais en dehors de workflows GitHub ponctuels. En attendant
# le service `leopardo-scheduler` dédié (bloqué : plan Render free, les
# background workers exigent un plan payant — voir #7649), le conteneur web
# exécute le scheduler. Limites assumées et documentées :
#   - plan free = spin-down : les tâches ne tournent que si le service est
#     éveillé (le keep-alive/health checks limitent la fenêtre morte) ;
#   - `schedule:run` est relancé chaque minute ; les tâches longues doivent
#     être `->runInBackground()` ou dispatcher des jobs (déjà la convention) ;
#   - le scheduler Laravel est idempotent par design (due-check par cron
#     expression) : aucun risque de double exécution tant qu'UN seul conteneur
#     web tourne (plan free = 1 instance). À retirer du web dès que le
#     service scheduler dédié existe (`onOneServer()` requis à ce moment-là).
# Gate WEB_SCHEDULER_LOOP (défaut true) : à passer à false dès que le worker
# dédié (qui lance `schedule:work`) est provisionné — sinon double
# exécution web+worker des tâches planifiées non `onOneServer()`.
if [ "${WEB_SCHEDULER_LOOP:-true}" = "true" ]; then
echo "Starting background scheduler loop (web container, interim #7649)..."
(
    while true; do
        php artisan schedule:run --no-interaction >&2 || \
            echo "[entrypoint] schedule:run failed ($?)" >&2
        sleep 60
    done
) &
else
    echo "[entrypoint] WEB_SCHEDULER_LOOP=false : scheduler web désactivé (worker dédié attendu, #7649)." >&2
fi

exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
