<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5813 (FUEL-019) — notifications et alertes.
 *
 * - `fuel_notification_preferences` : préférences tenant par (event_type,
 *   canal) — canaux désactivables (in_app|email|push), `station_id`
 *   nullable (NULL = toutes les stations du tenant). Index unique avec
 *   COALESCE : une seule ligne par combinaison.
 * - `fuel_alerts` : alertes dédupliquées par `alert_key` unique par
 *   tenant (anomalie compteur, écart de stock, clôture manquante,
 *   maintenance due, incident). Cycle open → acknowledged → resolved,
 *   sans PII dans le payload (jamais de secrets).
 *
 * Tenant-scoped (company_id uuid indexé), FK composite sur les stations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_notification_preferences')) {
            Schema::create('fuel_notification_preferences', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                $table->string('event_type', 40); // reading_anomaly|stock_variance|missing_cash_session_close|maintenance_due|incident
                $table->string('channel', 10); // in_app|email|push
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->foreign(['station_id', 'company_id'], 'fuel_prefs_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            // Une seule ligne par (tenant, station ou 0, type, canal).
            DB::statement(
                'CREATE UNIQUE INDEX fuel_notification_preferences_unique
                 ON fuel_notification_preferences (company_id, event_type, channel, COALESCE(station_id, 0))'
            );
        }

        if (! schemaTableExists('fuel_alerts')) {
            Schema::create('fuel_alerts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                $table->string('event_type', 40);
                $table->string('severity', 12); // info|warning|high|critical
                $table->string('alert_key', 120);
                $table->jsonb('payload');
                $table->string('status', 16)->default('open'); // open|acknowledged|resolved
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'alert_key'], 'fuel_alerts_key_unique');
                $table->index(['company_id', 'status'], 'fuel_alerts_company_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_alerts_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_alerts');
        Schema::dropIfExists('fuel_notification_preferences');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        foreach ([
            'fuel_notification_preferences' => [
                'fuel_prefs_event_type_check' => "event_type IN ('reading_anomaly', 'stock_variance', 'missing_cash_session_close', 'maintenance_due', 'incident')",
                'fuel_prefs_channel_check' => "channel IN ('in_app', 'email', 'push')",
            ],
            'fuel_alerts' => [
                'fuel_alerts_event_type_check' => "event_type IN ('reading_anomaly', 'stock_variance', 'missing_cash_session_close', 'maintenance_due', 'incident')",
                'fuel_alerts_severity_check' => "severity IN ('info', 'warning', 'high', 'critical')",
                'fuel_alerts_status_check' => "status IN ('open', 'acknowledged', 'resolved')",
            ],
        ] as $table => $constraints) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            foreach ($constraints as $name => $check) {
                if ($this->constraintExists($name)) {
                    continue;
                }

                DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$check})");
            }
        }
    }
};
