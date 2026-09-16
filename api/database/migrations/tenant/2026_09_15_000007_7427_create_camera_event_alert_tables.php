<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7427 (BC-19) — événements caméra et alertes manager.
 *
 * Avant cette migration, la chaîne vidéo (MediaMTX, #7424) savait ouvrir un
 * flux mais **rien** ne remontait au manager : aucun modèle d'événement, aucune
 * table d'alerte, donc aucun push possible sur le téléphone.
 *
 * - `camera_events` : détection brute (mouvement/personne/véhicule) telle que
 *   rapportée par la chaîne vidéo. Aucune donnée n'est inventée : une ligne
 *   existe seulement si un événement a été détecté et ingéré.
 * - `camera_alerts` : alerte manager calquée sur `fuel_alerts` (#5813) —
 *   `alert_key` **unique par tenant** pour le dédoublonnage (une rafale de
 *   mouvement ne produit pas 400 push), cycle `open → acknowledged → resolved`.
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, **aucune FK vers
 * `public.companies`** (conventions migrations tenant §2.6) ; les FK internes
 * pointent vers `cameras` (table tenant, même schéma). `down()` complet,
 * migration réentrante (`schemaTableExists`, garde #1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('camera_events')) {
            Schema::create('camera_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('camera_id');

                $table->string('type', 40);           // motion|person|vehicle|line_crossing|tamper
                $table->string('severity', 12);       // info|warning|high|critical
                $table->dateTime('detected_at');
                $table->string('snapshot_path', 255)->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                // Index de lecture du flux d'événements d'une caméra.
                $table->index(['company_id', 'camera_id', 'detected_at'], 'camera_events_company_camera_detected_idx');

                $table->foreign('camera_id')->references('id')->on('cameras')->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('camera_alerts')) {
            Schema::create('camera_alerts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('camera_id');
                // Référence l'événement déclencheur : une alerte sans événement
                // est structurellement impossible (critère 6 de l'issue).
                $table->unsignedBigInteger('camera_event_id')->nullable();

                $table->string('type', 40);
                $table->string('severity', 12);
                $table->string('alert_key', 120);
                $table->jsonb('payload');
                $table->string('status', 16)->default('open'); // open|acknowledged|resolved

                $table->unsignedInteger('acknowledged_by')->nullable();
                $table->dateTime('acknowledged_at')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'alert_key'], 'camera_alerts_key_unique');
                $table->index(['company_id', 'status'], 'camera_alerts_company_status_idx');
                $table->index('camera_event_id', 'camera_alerts_event_idx');

                $table->foreign('camera_id')->references('id')->on('cameras')->cascadeOnDelete();
                $table->foreign('camera_event_id')->references('id')->on('camera_events')->nullOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_alerts');
        Schema::dropIfExists('camera_events');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        foreach ([
            'camera_events' => [
                'camera_events_type_check' => "type IN ('motion', 'person', 'vehicle', 'line_crossing', 'tamper')",
                'camera_events_severity_check' => "severity IN ('info', 'warning', 'high', 'critical')",
            ],
            'camera_alerts' => [
                'camera_alerts_type_check' => "type IN ('motion', 'person', 'vehicle', 'line_crossing', 'tamper')",
                'camera_alerts_severity_check' => "severity IN ('info', 'warning', 'high', 'critical')",
                'camera_alerts_status_check' => "status IN ('open', 'acknowledged', 'resolved')",
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
