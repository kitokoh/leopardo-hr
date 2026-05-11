<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->string('plate_number', 20);
                $table->string('brand', 100)->nullable();
                $table->string('model', 100)->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->enum('type', ['car', 'van', 'truck', 'motorcycle', 'bus'])->default('car');
                $table->string('vin', 17)->nullable();
                $table->enum('fuel_type', ['diesel', 'gasoline', 'electric', 'hybrid', 'lpg'])->default('diesel');
                $table->enum('status', ['active', 'maintenance', 'decommissioned'])->default('active');
                $table->unsignedInteger('mileage')->default(0);
                $table->date('insurance_expiry')->nullable();
                $table->date('technical_control_expiry')->nullable();
                $table->unsignedInteger('traccar_device_id')->nullable();
                $table->string('traccar_unique_id', 50)->nullable();
                $table->unsignedInteger('assigned_driver_id')->nullable();
                $table->unsignedBigInteger('assigned_site_id')->nullable();
                $table->jsonb('metadata')->default('{}');
                $table->timestampsTz();

                $table->unique(['company_id', 'plate_number']);
                $table->index('status');
                $table->index('assigned_driver_id');
            });
        }

        if (! Schema::hasTable('vehicle_assignments')) {
            Schema::create('vehicle_assignments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id');
                $table->unsignedInteger('employee_id');
                $table->uuid('company_id')->nullable()->index();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('reason', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->index(['vehicle_id', 'start_date']);
            });
        }

        if (! Schema::hasTable('vehicle_trips')) {
            Schema::create('vehicle_trips', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('driver_id')->nullable();
                $table->timestampTz('start_time');
                $table->timestampTz('end_time')->nullable();
                $table->decimal('start_lat', 10, 7)->nullable();
                $table->decimal('start_lng', 10, 7)->nullable();
                $table->string('start_address', 500)->nullable();
                $table->decimal('end_lat', 10, 7)->nullable();
                $table->decimal('end_lng', 10, 7)->nullable();
                $table->string('end_address', 500)->nullable();
                $table->decimal('distance_km', 10, 2)->default(0);
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->decimal('max_speed_kmh', 6, 2)->default(0);
                $table->decimal('avg_speed_kmh', 6, 2)->default(0);
                $table->decimal('fuel_consumed', 8, 2)->nullable();
                $table->unsignedInteger('traccar_trip_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
                $table->index(['vehicle_id', 'start_time']);
                $table->index(['company_id', 'start_time']);
            });
        }

        if (! Schema::hasTable('vehicle_alerts')) {
            Schema::create('vehicle_alerts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->nullable()->index();
                $table->enum('type', ['speeding', 'geofence_exit', 'geofence_enter', 'idle', 'maintenance_due', 'insurance_expiry', 'low_fuel', 'sos'])->default('speeding');
                $table->string('message', 500);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('speed', 6, 2)->nullable();
                $table->boolean('acknowledged')->default(false);
                $table->unsignedInteger('acknowledged_by')->nullable();
                $table->unsignedInteger('traccar_event_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
                $table->index(['company_id', 'created_at']);
                $table->index(['vehicle_id', 'acknowledged']);
            });
        }

        if (! Schema::hasTable('vehicle_maintenances')) {
            Schema::create('vehicle_maintenances', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->nullable()->index();
                $table->enum('type', ['oil_change', 'tire', 'brake', 'battery', 'inspection', 'repair', 'other'])->default('other');
                $table->text('description')->nullable();
                $table->decimal('cost', 12, 2)->default(0);
                $table->string('currency', 3)->default('DZD');
                $table->unsignedInteger('mileage_at_service')->default(0);
                $table->date('service_date');
                $table->date('next_service_date')->nullable();
                $table->unsignedInteger('next_service_mileage')->nullable();
                $table->string('provider', 200)->nullable();
                $table->string('invoice_path', 500)->nullable();
                $table->timestampsTz();

                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
                $table->index(['vehicle_id', 'service_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
        Schema::dropIfExists('vehicle_alerts');
        Schema::dropIfExists('vehicle_trips');
        Schema::dropIfExists('vehicle_assignments');
        Schema::dropIfExists('vehicles');
    }
};
