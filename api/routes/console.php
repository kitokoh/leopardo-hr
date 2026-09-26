<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'leopardo:migrate {--fresh : Drop all tables before migrating} {--seed : Run base seeders after migrating} {--demo : Also seed DemoCompanySeeder (local/dev only)} {--force : Skip the interactive --fresh confirmation (never valid in production)}',
    function () {
        $fresh = (bool) $this->option('fresh');
        $seed = (bool) $this->option('seed');
        $demo = (bool) $this->option('demo');

        if ($fresh) {
            // #7974 — garde d'environnement : DROP SCHEMA CASCADE ne doit JAMAIS
            // pouvoir s'exécuter contre la production (erreur de terminal,
            // runbook copié-collé, exec Render sur le mauvais service).
            if (app()->environment('production')) {
                $this->error('leopardo:migrate --fresh est INTERDIT en production : l\'option détruit intégralement les schémas public et shared_tenants (DROP SCHEMA CASCADE).');
                $this->error('En production, utilisez le pipeline de déploiement : php artisan migrate --path=database/migrations/public --force puis --path=database/migrations/tenant --force.');

                return 1;
            }

            if (! (bool) $this->option('force')) {
                $connection = DB::connection()->getConfig();
                $target = sprintf(
                    '%s/%s (env: %s)',
                    $connection['host'] ?? '?',
                    $connection['database'] ?? '?',
                    app()->environment()
                );

                if (! $this->confirm("--fresh va DÉTRUIRE les schémas public et shared_tenants sur {$target}. Continuer ?", false)) {
                    $this->warn('Abandon — aucune donnée n\'a été supprimée. Passez --force pour confirmer sans interaction.');

                    return 1;
                }
            }

            $this->warn('--fresh : suppression du schema public et shared_tenants');
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP SCHEMA IF EXISTS shared_tenants CASCADE');
                DB::statement('DROP SCHEMA public CASCADE');
                DB::statement('CREATE SCHEMA public');
                DB::statement('CREATE SCHEMA shared_tenants');
            } else {
                $this->call('migrate:fresh', ['--force' => true]);
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS public');
            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');

            // La table migrations doit vivre dans public (pas shared_tenants).
            config(['database.connections.pgsql.search_path' => 'public']);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            DB::statement('SET search_path TO public');
        }

        $this->info('Migrations schema public...');
        $publicCode = $this->call('migrate', [
            '--path' => 'database/migrations/public',
            '--force' => true,
        ]);

        if ($publicCode !== 0) {
            $this->error('Echec des migrations public.');

            return $publicCode;
        }

        if (DB::getDriverName() === 'pgsql') {
            // Keep tenant migrations on the tenant schema only: some public
            // tables have tenant-like names and can make Schema::hasTable()
            // skip creating the real shared_tenants table.
            config(['database.connections.pgsql.search_path' => 'shared_tenants']);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            DB::statement('SET search_path TO shared_tenants');
        }

        $this->info('Migrations schema shared_tenants...');
        $tenantCode = $this->call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        if ($tenantCode !== 0) {
            $this->error('Echec des migrations tenant.');

            return $tenantCode;
        }

        if ($seed) {
            $this->info('Seeders de base...');
            if (DB::getDriverName() === 'pgsql') {
                config(['database.connections.pgsql.search_path' => 'shared_tenants,public']);
                DB::purge('pgsql');
                DB::reconnect('pgsql');
                DB::statement('SET search_path TO shared_tenants,public');
            }

            $seedCode = $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\DatabaseSeeder',
                '--force' => true,
            ]);

            if ($seedCode !== 0) {
                return $seedCode;
            }
        }

        if ($demo) {
            $this->info('Seed des donnees de demo...');
            if (DB::getDriverName() === 'pgsql') {
                config(['database.connections.pgsql.search_path' => 'shared_tenants,public']);
                DB::purge('pgsql');
                DB::reconnect('pgsql');
                DB::statement('SET search_path TO shared_tenants,public');
            }

            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\DemoCompanySeeder',
                '--force' => true,
            ]);
        }

        $this->info('Leopardo migrate : OK');

        return 0;
    }
)->purpose('Run both public and tenant migrations (and optionally seeders) in one shot.');

// ──────────────────────────────────────────────
// Scheduled Jobs
// ──────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:check-trials')->daily()->at('08:00')->withoutOverlapping();
Schedule::command('billing:check-overdue')->daily()->at('09:00')->withoutOverlapping();
Schedule::command('app:send-drip-emails')->daily()->at('10:00');
Schedule::command('billing:generate-invoices')->monthlyOn(1, '02:00')->withoutOverlapping();
Schedule::command('leave:accrue')->monthlyOn(1, '03:00')->withoutOverlapping();
Schedule::command('leave:carry-forward --year='.(now()->year - 1))->yearlyOn(1, 1, '04:00')->withoutOverlapping();
Schedule::command('contracts:alert-expiring')->daily()->at('07:00')->withoutOverlapping();
// #7655 (tranche 2, ADR-0025) — hygiène des tokens Sanctum : purge quotidienne
// des tokens expirés. Le TTL est de 30 jours glissants (décision propriétaire
// #7491) : sans cette purge, les hash de tokens expirés s'accumulent
// indéfiniment dans personal_access_tokens.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->at('04:30');
// Digest hebdomadaire manager (issue #5695) — chaque lundi à 07:00.
Schedule::command('manager:weekly-digest')->weeklyOn(1, '07:00');
Schedule::command('fuel:alerts-dispatch')->daily()->at('06:30');
// Fermeture automatique unique (ADR-0016 Phase 4, #5355) : pointages sans
// check-out + sessions GPS orphelines — une seule commande, même cycle.
Schedule::command('attendance:auto-close --threshold=12 --hours=14')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
// Supervision queue (issue #5282) : le driver actif peut être `redis` ou
// `database` (prod 0 €). La détection < 15 min est garantie côté CI par
// `.github/workflows/queue-supervision.yml` (cron 5 min) ; ce schedule couvre
// les environnements où un scheduler tourne (worker dédié, local).
Schedule::command('queue:health-check')
    ->everyFiveMinutes()
    ->when(fn (): bool => in_array(config('queue.default'), ['redis', 'database'], true))
    ->withoutOverlapping();

Schedule::command('growth:approve-commissions')
    ->daily()
    ->at('04:00');

// Module Marketing — publication des social_posts planifies devenus dus
Schedule::command('marketing:publish-scheduled-posts')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Module CRM — campagnes email (#7751) : auto-start des campagnes planifiees
// dues + drainage des envois pending des campagnes running (filet de securite
// du listener CampaignStarted -> ProcessCampaignSendsJob).
Schedule::command('crm:process-campaign-sends')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// PA2-COMM-011 — publish scheduled company announcements that are due
Schedule::command('announcements:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// BC-24 TRAVEL — dispatch des événements d'outbox TravelAgency (#6066,
// pattern crm:outbox-dispatch #5741) : consommation asynchrone idempotente.
Schedule::command('travel:outbox-dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// BC-24 TRAVEL — synthèse mensuelle des ventes pour Accounting (#6069) :
// période glissante = mois précédent, événement rejouable et idempotent.
Schedule::command('travel:settle-sales')
    ->monthlyOn(1, '02:30')
    ->withoutOverlapping()
    ->onOneServer();

// BC-24 TRAVEL — expiration des réservations pending (#6070) : annulation
// + libération des sièges + événement (idempotent, log borné).
Schedule::command('travel:expire-pending-bookings')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// BC-32 HOSPITALITY (HOSP-004 #7946) — expiration des réservations en ligne
// pending (+30 min sans confirmation) : annulation + libération de
// l'inventaire (idempotent, re-vérification sous verrou).
Schedule::command('hospitality:expire-pending-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// BC-24 TRAVEL — expiration des annonces validées (#6111) : durée de
// validité dépassée → invisible (idempotent).
Schedule::command('travel:expire-adverts')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// CRM V1 (issue #5720) — relances internes des tâches en retard, idempotentes
// (table crm_task_reminders, UNIQUE task_id+remind_date).
Schedule::command('crm:tasks:send-overdue-reminders')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// BC-29 COMMUNICATION — polling Gmail des boites connectees (R2 #7687,
// spec §3.2 : V1 = polling 5 min idempotent ; push Pub/Sub en V1.1). La
// commande ne fait que dispatcher les jobs (queue `communication`) —
// throttling par integration via WithoutOverlapping, backoff sur 429.
Schedule::command('communication:sync-mailboxes')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// BC-29 COMMUNICATION — relances automatiques (R4 #7689, spec §3.4 :
// pattern crm:tasks:send-overdue-reminders). Idempotente : table de
// deduplication `communication_follow_ups` (une relance par echeance) ;
// garde-fous (reponse, opt-out, consentement CRM, quiet hours, plafonds)
// evalues dans le job juste avant l'envoi.
Schedule::command('communication:send-follow-ups')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('growth:archive-clicks --days=90')
    ->weekly();

// RGPD / Loi 18-07 — rétention des audit logs (#5439 : par entreprise via
// CompanySetting `audit_retention_months`, défaut 36 mois — voir
// docs/security/POLITIQUE_RETENTION_DOCUMENTS.md, issue #1474).
Schedule::command('audit:purge')
    ->weekly()
    ->onOneServer();

// Spec S-1 (#1661) — RGPD / Loi 18-07 : purge des templates biométriques
// expirés (24 mois après fin de contrat / consentement — voir
// docs/security/POLITIQUE_RETENTION_DOCUMENTS.md v2).
Schedule::command('biometric:purge-expired')
    ->weekly()
    ->onOneServer();

Artisan::command('super-admin:reset-password {email} {password}', function (string $email, string $password) {
    DB::statement('SET search_path TO public');

    $affected = DB::table('super_admins')
        ->where('email', $email)
        ->update([
            'password_hash' => Hash::make($password),
        ]);

    if ($affected === 0) {
        $this->error("Aucun super admin trouvé pour {$email}");

        return 1;
    }

    $this->info("Mot de passe super admin mis à jour pour {$email}");

    return 0;
})->purpose('Reset a super admin password safely');

// Issue #1812 — rappel annuel : fêtes islamiques de l'année suivante à
// confirmer avant la clôture (novembre).
Schedule::command('islamic:check-unconfirmed')
    ->yearlyOn(11, 15, '09:00');

// ────────────────────────────────────────────────────────────────────────
// BOS-006A (#8139) — SOURCE UNIQUE DE PLANIFICATION
// ────────────────────────────────────────────────────────────────────────
// Le scheduler était défini DEUX FOIS : `bootstrap/app.php` (withSchedule)
// ET ce fichier — Laravel charge les deux, donc 8 commandes tournaient en
// double avec des horaires/paramètres contradictoires (double acquisition de
// congés, double génération de factures, deux expireurs Travel publiant deux
// événements différents). Ce fichier est désormais la SOURCE UNIQUE :
// `withSchedule()` a été retiré de `bootstrap/app.php`.
//
// Règle de résolution appliquée lors de la consolidation (arbitrage documenté) :
//   1. quand une commande était définie dans les deux fichiers, la définition
//      de CE fichier (source canonique) est conservée ;
//   2. quand `bootstrap/app.php` portait des arguments/verrous plus complets
//      (ex. `attendance:auto-close`, `travel:expire-pending-bookings`),
//      c'est la définition la PLUS COMPLÈTE qui est conservée ;
//   3. les entrées présentes UNIQUEMENT dans `bootstrap/app.php` sont migrées
//      ici à l'identique (+ `withoutOverlapping` pour les commandes sensibles) ;
//   4. l'expireur Travel LEGACY `travel:expire-bookings`
//      (`TravelExpireBookingsCommand`) est SUPPRIMÉ : il publiait
//      `travel.booking.expired.v1` en concurrence avec le canonique
//      `travel.booking.cancelled.v1` (`travel:expire-pending-bookings`, #6070).
//
// Tableau des entrées (source unique — aucun doublon, cf.
// tests/Feature/Console/SchedulerUniquenessTest.php) :
//
//   commande                                  fréquence                 verrou
//   --------------------------------------------------------------------------
//   leave:accrue                              mensuelle (1er, 03:00)    oui
//   leave:carry-forward --year=<n-1>          annuelle (1er jan, 04:00) oui
//   contracts:alert-expiring                  quotidienne (07:00)       oui
//   billing:check-trials                      quotidienne (08:00)       oui
//   billing:check-overdue                     quotidienne (09:00)       oui
//   billing:generate-invoices                 mensuelle (1er, 02:00)    oui
//   billing:reconcile-payments                quotidienne               oui
//   billing:report --json                     quotidienne               oui
//   sanctum:prune-expired --hours=24          quotidienne (04:30)       non
//   manager:weekly-digest                     hebdo (lundi 07:00)       non
//   fuel:alerts-dispatch                      quotidienne (06:30)       non
//   fuel:outbox-dispatch                      chaque minute             non
//   monitor:slow-queries --threshold=500      15 min                    non
//   trial-provisionings:sweep                 15 min                    non
//   attendance:auto-close --threshold=12 --hours=14  horaire             oui
//   queue:health-check                        5 min (si redis/database) oui
//   growth:approve-commissions                quotidienne (04:00)       non
//   marketing:publish-scheduled-posts         chaque minute             oui
//   crm:process-campaign-sends                5 min                     oui
//   announcements:publish-scheduled           chaque minute             oui
//   travel:outbox-dispatch                    chaque minute             oui
//   travel:settle-sales                       mensuelle (1er, 02:30)    oui
//   travel:expire-pending-bookings            5 min                     oui
//   travel:webhook-dispatch                   chaque minute             oui
//   travel:expire-adverts                     horaire                   oui
//   travel:rebuild-report-readmodels          horaire                   non
//   hospitality:expire-pending-reservations   5 min                     oui
//   crm:tasks:send-overdue-reminders          30 min                    oui
//   communication:sync-mailboxes              5 min                     oui
//   communication:send-follow-ups             15 min                    oui
//   growth:archive-clicks --days=90           hebdomadaire              non
//   accounting:purge-expired-shares           quotidienne               non
//   payroll:precalculate                      quotidienne (02:00)       non
//   edge:monitor                              30 min                    oui
//   onboarding:send-reminders                 quotidienne (09:00)       non
//   tts:purge                                 horaire                   non
//   ai:purge-audit-logs                       quotidienne (04:45)       oui (BOS-002 #8144, #8164 : ai_audit_logs + ai_tool_executions)
//   restaurant:outbox-dispatch                chaque minute             oui
//   leopardo:fleet:sync                       */TRACCAR_SYNC_INTERVAL    oui (30 min)
//   audit:purge                               hebdomadaire              non
//   biometric:purge-expired                   hebdomadaire              non
//   islamic:check-unconfirmed                 annuelle (15 nov, 09:00)  non
//
// Note de déploiement : les horaires unifiés suppriment les runs de minuit
// (`daily()` de l'ancien bloc bootstrap) qui doublonnaient les runs explicites
// de ce fichier. Aucune donnée n'est modifiée ; un déploiement hors fenêtre de
// run critique (02:00–04:30 UTC : factures, paie, congés, travel) est
// recommandé pour éviter qu'une commande en cours perde son verrou.

// Facturation — réconciliation recouvrement (DEP-BC21 #6251) et rapport.
Schedule::command('billing:reconcile-payments')->daily()->withoutOverlapping();
Schedule::command('billing:report --json')->daily()->withoutOverlapping();

// FuelStation (BC-15) — FUEL-015/019 : dispatch outbox idempotent.
Schedule::command('fuel:outbox-dispatch')->everyMinute();

// Observabilité — requêtes lentes et provisionings trial bloqués (#4948).
Schedule::command('monitor:slow-queries --threshold=500')->everyFifteenMinutes();
Schedule::command('trial-provisionings:sweep')->everyFifteenMinutes();

// TRAVEL-806 (#6097) — webhooks sortants transporteurs (idempotent, retry).
Schedule::command('travel:webhook-dispatch')->everyMinute()->withoutOverlapping();

// BC-24 TRAVEL — reconstruction des read-models de reporting.
Schedule::command('travel:rebuild-report-readmodels')->hourly();

// RGPD / espace disque — purge des partages comptables expirés.
Schedule::command('accounting:purge-expired-shares')->daily();

// PA2-PAY-012 — pré-calcul progressif de la paie (nuit).
Schedule::command('payroll:precalculate')->dailyAt('02:00');

// Edge (audit Mobile+Edge 2026-07-26, #1288/#1291) — nœuds silencieux et
// licences hors ligne expirées.
Schedule::command('edge:monitor')->everyThirtyMinutes()->withoutOverlapping();

// #R12 — rappel d'onboarding J+1 (sociétés créées il y a 20h–28h).
Schedule::command('onboarding:send-reminders')->dailyAt('09:00');

// BOS-002 (#8144) — rétention des traces de l'assistant IA (ai_audit_logs ;
// ai_tool_executions depuis #8164 — même commande, même rétention) : purge
// quotidienne au-delà de `ai.audit_log_retention_days` (défaut 90 j).
// Idempotente.
Schedule::command('ai:purge-audit-logs')->dailyAt('04:45')->withoutOverlapping();

// Issue #5616 — purge des fichiers TTS temporaires (RGPD + espace disque).
Schedule::command('tts:purge')->hourly();

// BC-25 RESTAURANT (RESTO-808/#6229) — consommation de l'outbox verticale.
Schedule::command('restaurant:outbox-dispatch')->everyMinute()->withoutOverlapping();

// #7401 — synchronisation Traccar de la flotte : `TRACCAR_SYNC_INTERVAL`
// (minutes) est enfin lu ; `withoutOverlapping(30)` borne le verrou.
$trackingIntervalMinutes = max(1, min(59, (int) config('tracking.sync_interval_minutes', 5)));
Schedule::command('leopardo:fleet:sync')
    ->cron("*/{$trackingIntervalMinutes} * * * *")
    ->withoutOverlapping(30);
