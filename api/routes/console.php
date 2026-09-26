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

// ──────────────────────────────────────────────────────────────────────────
// Scheduled Jobs — SOURCE UNIQUE DE PLANIFICATION (BOS-006A, issue #8139)
// ──────────────────────────────────────────────────────────────────────────
//
// Le scheduler Laravel n'est défini QU'ICI. Le bloc `->withSchedule()` de
// `bootstrap/app.php` a été supprimé (#8139) : Laravel chargeait les DEUX
// définitions, donc 8 commandes tournaient en double avec des horaires
// contradictoires — dont `leave:accrue` (daily + monthly → double
// acquisition le 1er du mois, la commande n'est PAS idempotente sur la
// période) et `billing:generate-invoices` (02:00 + 03:00 → factures en
// double). Toute nouvelle entrée s'ajoute ICI, jamais dans bootstrap/app.php.
//
// Règle des verrous : toute commande à effets (écritures, notifications,
// emails, webhooks, provisioning, purge, sync) porte `withoutOverlapping()` ;
// les consommateurs d'outbox / dispatchers à la minute ajoutent
// `onOneServer()`. Seuls les monitors en lecture seule en sont exempts.
//
// Inventaire (commande — fréquence — verrou) :
//
//   Billing & abonnements
//   - billing:check-trials            — quotidien 08:00        — lock
//   - billing:check-overdue           — quotidien 09:00        — lock
//   - billing:generate-invoices       — mensuel (1er, 02:00)   — lock
//   - billing:reconcile-payments      — quotidien              — lock
//   - billing:report --json           — quotidien              — lock
//   - app:send-drip-emails            — quotidien 10:00        — lock
//   RH / congés / présence / paie
//   - leave:accrue                    — mensuel (1er, 03:00)   — lock
//   - leave:carry-forward             — annuel (1er jan, 04:00)— lock
//   - contracts:alert-expiring        — quotidien 07:00        — lock
//   - attendance:auto-close           — horaire                — lock + onOneServer
//   - payroll:precalculate            — quotidien 02:00        — lock
//   - manager:weekly-digest           — hebdo (lundi 07:00)    — lock
//   - onboarding:send-reminders       — quotidien 09:00        — lock
//   - islamic:check-unconfirmed       — annuel (15 nov, 09:00) — lock
//   Travel (BC-24) / Hospitality (BC-32)
//   - travel:outbox-dispatch          — minute                 — lock + onOneServer
//   - travel:settle-sales             — mensuel (1er, 02:30)   — lock + onOneServer
//   - travel:expire-pending-bookings  — 5 min                  — lock + onOneServer
//   - hospitality:expire-pending-res. — 5 min                  — lock + onOneServer
//   - travel:expire-adverts           — horaire                — lock + onOneServer
//   - travel:webhook-dispatch         — minute                 — lock + onOneServer
//   - travel:rebuild-report-readmodels— horaire                — lock
//   Fuel (BC-15) / Restaurant (BC-25)
//   - fuel:outbox-dispatch            — minute                 — lock + onOneServer
//   - fuel:alerts-dispatch            — quotidien 06:30        — lock
//   - restaurant:outbox-dispatch      — minute                 — lock + onOneServer
//   CRM / Communication / Marketing / Growth / Annonces
//   - crm:process-campaign-sends      — 5 min                  — lock + onOneServer
//   - crm:tasks:send-overdue-reminders— 30 min                 — lock + onOneServer
//   - communication:sync-mailboxes    — 5 min                  — lock + onOneServer
//   - communication:send-follow-ups   — 15 min                 — lock + onOneServer
//   - marketing:publish-scheduled-posts— minute                — lock + onOneServer
//   - announcements:publish-scheduled — minute                 — lock + onOneServer
//   - growth:approve-commissions      — quotidien 04:00        — lock
//   - growth:archive-clicks --days=90 — hebdo                  — lock
//   Plateforme / ops / RGPD
//   - sanctum:prune-expired --hours=24— quotidien 04:30        — lock
//   - queue:health-check              — 5 min (si queue redis/database) — lock
//   - monitor:slow-queries            — 15 min                 — monitor lecture seule
//   - trial-provisionings:sweep       — 15 min                 — lock
//   - edge:monitor                    — 30 min                 — lock
//   - tts:purge                       — horaire                — lock
//   - accounting:purge-expired-shares — quotidien              — lock
//   - leopardo:fleet:sync             — N min (config)         — lock (30 min)
//   - audit:purge                     — hebdo                  — lock + onOneServer
//   - biometric:purge-expired         — hebdo                  — lock + onOneServer
//
// Vérification : `php artisan schedule:list` ne doit montrer aucun doublon ;
// le test `SchedulerSingleSourceTest` le garantit en CI.
// ──────────────────────────────────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

// ── Billing & abonnements ─────────────────────────────────────────────────
Schedule::command('billing:check-trials')->daily()->at('08:00')->withoutOverlapping();
Schedule::command('billing:check-overdue')->daily()->at('09:00')->withoutOverlapping();
Schedule::command('billing:generate-invoices')->monthlyOn(1, '02:00')->withoutOverlapping();
// DEP-BC21 (#6251) : supervision recouvrement — réconciliation
// (dry-run) et métriques quotidiennes.
Schedule::command('billing:reconcile-payments')->daily()->withoutOverlapping();
Schedule::command('billing:report --json')->daily()->withoutOverlapping();
Schedule::command('app:send-drip-emails')->daily()->at('10:00')->withoutOverlapping();

// ── RH / congés / présence / paie ─────────────────────────────────────────
// leave:accrue est MENSUELLE par conception (garde interne « 1er du mois »,
// acquisition non idempotente sur la période) : la planifier aussi en daily
// provoquait une double acquisition le 1er (#8139).
Schedule::command('leave:accrue')->monthlyOn(1, '03:00')->withoutOverlapping();
// L'option --year est explicite (défaut de la commande = année précédente).
Schedule::command('leave:carry-forward --year='.(now()->year - 1))->yearlyOn(1, 1, '04:00')->withoutOverlapping();
Schedule::command('contracts:alert-expiring')->daily()->at('07:00')->withoutOverlapping();
// Fermeture automatique unique (ADR-0016 Phase 4, #5355) : pointages sans
// check-out + sessions GPS orphelines — une seule commande, même cycle.
Schedule::command('attendance:auto-close --threshold=12 --hours=14')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
// PA2-PAY-012 — Nightly progressive payroll pre-calculation
Schedule::command('payroll:precalculate')->dailyAt('02:00')->withoutOverlapping();
// Digest hebdomadaire manager (issue #5695) — chaque lundi à 07:00.
Schedule::command('manager:weekly-digest')->weeklyOn(1, '07:00')->withoutOverlapping();
// #R12 — Rappel d'onboarding J+1 : envoyé chaque jour à 09:00 UTC.
// Cible les managers dont la société a été créée il y a 20h–28h et
// dont l'onboarding comporte encore des étapes requises non complétées.
Schedule::command('onboarding:send-reminders')->dailyAt('09:00')->withoutOverlapping();
// Issue #1812 — rappel annuel : fêtes islamiques de l'année suivante à
// confirmer avant la clôture (novembre).
Schedule::command('islamic:check-unconfirmed')->yearlyOn(11, 15, '09:00')->withoutOverlapping();

// ── Travel (BC-24) / Hospitality (BC-32) ──────────────────────────────────
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
// SEUL expireur de réservations : la commande legacy `travel:expire-bookings`
// (TravelExpireBookingsCommand) a été supprimée (#8139).
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

// TRAVEL-806/#6097 — webhooks sortants transporteurs (livraison idempotente, retry/backoff).
Schedule::command('travel:webhook-dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('travel:rebuild-report-readmodels')->hourly()->withoutOverlapping();

// ── Fuel (BC-15) / Restaurant (BC-25) ─────────────────────────────────────
// FuelStation (BC-15) — FUEL-015/019 : dispatch outbox idempotent.
Schedule::command('fuel:outbox-dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('fuel:alerts-dispatch')->daily()->at('06:30')->withoutOverlapping();

// BC-25 RESTAURANT (RESTO-808/#6229) — consommation de l'outbox
// de la verticale (notifications cuisine/service, fidélité…).
Schedule::command('restaurant:outbox-dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// ── CRM / Communication / Marketing / Growth / Annonces ───────────────────
// Module CRM — campagnes email (#7751) : auto-start des campagnes planifiees
// dues + drainage des envois pending des campagnes running (filet de securite
// du listener CampaignStarted -> ProcessCampaignSendsJob).
Schedule::command('crm:process-campaign-sends')
    ->everyFiveMinutes()
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

// Module Marketing — publication des social_posts planifies devenus dus
Schedule::command('marketing:publish-scheduled-posts')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// PA2-COMM-011 — publish scheduled company announcements that are due
Schedule::command('announcements:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('growth:approve-commissions')
    ->daily()
    ->at('04:00')
    ->withoutOverlapping();

Schedule::command('growth:archive-clicks --days=90')
    ->weekly()
    ->withoutOverlapping();

// ── Plateforme / ops / RGPD ───────────────────────────────────────────────
// #7655 (tranche 2, ADR-0025) — hygiène des tokens Sanctum : purge quotidienne
// des tokens expirés. Le TTL est de 30 jours glissants (décision propriétaire
// #7491) : sans cette purge, les hash de tokens expirés s'accumulent
// indéfiniment dans personal_access_tokens.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->at('04:30')->withoutOverlapping();
// Supervision queue (issue #5282) : le driver actif peut être `redis` ou
// `database` (prod 0 €). La détection < 15 min est garantie côté CI par
// `.github/workflows/queue-supervision.yml` (cron 5 min) ; ce schedule couvre
// les environnements où un scheduler tourne (worker dédié, local).
Schedule::command('queue:health-check')
    ->everyFiveMinutes()
    ->when(fn (): bool => in_array(config('queue.default'), ['redis', 'database'], true))
    ->withoutOverlapping();

Schedule::command('monitor:slow-queries --threshold=500')->everyFifteenMinutes();
// Issue #4948 : trial provisionings bloqués (worker de queue jamais
// exécuté) → fail-loud au lieu d'un pending silencieux.
Schedule::command('trial-provisionings:sweep')->everyFifteenMinutes()->withoutOverlapping();
// Audit Mobile+Edge 2026-07-26 (issue #1288) — Edge node silence /
// license-expiry monitoring was implemented but never scheduled; a
// silent/offline Edge node at a client site (or an expiring/expired
// offline license) went completely unnoticed in production.
//
// `edge:detect-silent-nodes` remains available as a non-scheduled
// compatibility command for legacy operational scripts/fixtures. It
// detects the old node_id schema only; the canonical UUID model is
// monitored by `edge:monitor` below. See issue #1291 and
// docs/audits/AUDIT_MOBILE_EDGE_2026-07-26.md sections 1.3/1.4.
Schedule::command('edge:monitor')->everyThirtyMinutes()->withoutOverlapping();
// Issue #5616 — Purge des fichiers TTS temporaires (RGPD + espace disque).
// Les URLs signées expirent en 60 s ; purger les fichiers > 60 min suffit
// pour garantir qu'aucun fichier accessible ne subsiste sur disque.
Schedule::command('tts:purge')->hourly()->withoutOverlapping();
Schedule::command('accounting:purge-expired-shares')->daily()->withoutOverlapping();
// #7401 — synchronisation Traccar de la flotte (devices → positions →
// trajets). Les trois endpoints `/tracking/sync-*` existaient mais
// n'étaient appelés par AUCUNE tâche planifiée : sans un humain qui
// clique, aucun appareil n'était appairé, aucune position relevée,
// aucun trajet enregistré, alors que TRACCAR_SYNC_INTERVAL (minutes)
// était défini et jamais lu. `withoutOverlapping(30)` : une passe lente
// (Traccar indisponible, gros historique) ne s'empile pas sur la
// suivante, avec une expiration de verrou de 30 min plutôt que les
// 24 h par défaut.
$trackingIntervalMinutes = max(1, min(59, (int) config('tracking.sync_interval_minutes', 5)));
Schedule::command('leopardo:fleet:sync')
    ->cron("*/{$trackingIntervalMinutes} * * * *")
    ->withoutOverlapping(30);

// RGPD / Loi 18-07 — rétention des audit logs (#5439 : par entreprise via
// CompanySetting `audit_retention_months`, défaut 36 mois — voir
// docs/security/POLITIQUE_RETENTION_DOCUMENTS.md, issue #1474).
Schedule::command('audit:purge')
    ->weekly()
    ->withoutOverlapping()
    ->onOneServer();

// Spec S-1 (#1661) — RGPD / Loi 18-07 : purge des templates biométriques
// expirés (24 mois après fin de contrat / consentement — voir
// docs/security/POLITIQUE_RETENTION_DOCUMENTS.md v2).
Schedule::command('biometric:purge-expired')
    ->weekly()
    ->withoutOverlapping()
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
