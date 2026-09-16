<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6022 (TRAVEL-209) — travel_bookings + travel_passengers.
 *
 * Réservations multi-passagers (spec §5.3). `idempotency_key` unique par
 * tenant : une requête rejouée (retry réseau, double clic guichet) ne crée
 * jamais deux réservations. `document_number_encrypted`/`document_number_hash`
 * : le n° de pièce d'identité n'est jamais stocké en clair (RGPD, §V de la
 * Constitution) — le hash permet une recherche exacte sans déchiffrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_bookings')) {
            // Issue #7452 — la table est créée par 2026_08_29_000608_6022_create_travel_bookings_and_passengers_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_bookings', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_bookings', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_bookings', 'reference')) {
                    $table->string('reference', 40);
                }
                if (! schemaHasColumn('travel_bookings', 'trip_id')) {
                    $table->unsignedBigInteger('trip_id');
                }
                if (! schemaHasColumn('travel_bookings', 'status')) {
                    $table->string('status', 20)->default('pending');
                }
                if (! schemaHasColumn('travel_bookings', 'passenger_count')) {
                    $table->unsignedInteger('passenger_count');
                }
                if (! schemaHasColumn('travel_bookings', 'total_amount_minor')) {
                    $table->unsignedInteger('total_amount_minor');
                }
                if (! schemaHasColumn('travel_bookings', 'currency')) {
                    $table->char('currency', 3);
                }
                if (! schemaHasColumn('travel_bookings', 'booking_source')) {
                    $table->string('booking_source', 20)->default('office');
                }
                if (! schemaHasColumn('travel_bookings', 'customer_contact_id')) {
                    $table->unsignedBigInteger('customer_contact_id')->nullable();
                }
                if (! schemaHasColumn('travel_bookings', 'booked_by_user_id')) {
                    $table->unsignedBigInteger('booked_by_user_id')->nullable();
                }
                if (! schemaHasColumn('travel_bookings', 'payment_status')) {
                    $table->string('payment_status', 20)->default('pending');
                }
                if (! schemaHasColumn('travel_bookings', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable();
                }
                if (! schemaHasColumn('travel_bookings', 'idempotency_key')) {
                    $table->string('idempotency_key', 255);
                }
                if (! schemaHasColumn('travel_bookings', 'version')) {
                    $table->unsignedInteger('version')->default(1);
                }
                if (! schemaHasColumn('travel_bookings', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_passengers')) {
            // Issue #7452 — la table est créée par 2026_08_29_000608_6022_create_travel_bookings_and_passengers_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_passengers', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_passengers', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_passengers', 'booking_id')) {
                    $table->unsignedBigInteger('booking_id');
                }
                if (! schemaHasColumn('travel_passengers', 'full_name')) {
                    $table->string('full_name', 160);
                }
                if (! schemaHasColumn('travel_passengers', 'birth_date')) {
                    $table->date('birth_date')->nullable();
                }
                if (! schemaHasColumn('travel_passengers', 'document_type')) {
                    $table->string('document_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_passengers', 'document_number_encrypted')) {
                    $table->text('document_number_encrypted')->nullable();
                }
                if (! schemaHasColumn('travel_passengers', 'document_number_hash')) {
                    $table->string('document_number_hash', 64)->nullable();
                }
                if (! schemaHasColumn('travel_passengers', 'age_category')) {
                    $table->string('age_category', 20)->default('adult');
                }
                if (! schemaHasColumn('travel_passengers', 'class_id')) {
                    $table->unsignedBigInteger('class_id');
                }
                if (! schemaHasColumn('travel_passengers', 'seat_number')) {
                    $table->unsignedInteger('seat_number')->nullable();
                }
                if (! schemaHasColumn('travel_passengers', 'unit_price_minor')) {
                    $table->unsignedInteger('unit_price_minor');
                }
                if (! schemaHasColumn('travel_passengers', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_passengers', 'company_id')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_passengers', 'booking_id')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('booking_id');
            });
        }
        if (schemaHasColumn('travel_passengers', 'full_name')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('full_name');
            });
        }
        if (schemaHasColumn('travel_passengers', 'birth_date')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('birth_date');
            });
        }
        if (schemaHasColumn('travel_passengers', 'document_type')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('document_type');
            });
        }
        if (schemaHasColumn('travel_passengers', 'document_number_encrypted')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('document_number_encrypted');
            });
        }
        if (schemaHasColumn('travel_passengers', 'document_number_hash')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('document_number_hash');
            });
        }
        if (schemaHasColumn('travel_passengers', 'age_category')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('age_category');
            });
        }
        if (schemaHasColumn('travel_passengers', 'class_id')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('class_id');
            });
        }
        if (schemaHasColumn('travel_passengers', 'seat_number')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('seat_number');
            });
        }
        if (schemaHasColumn('travel_passengers', 'unit_price_minor')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('unit_price_minor');
            });
        }
        if (schemaHasColumn('travel_passengers', 'created_at')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_bookings', 'company_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_bookings', 'reference')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('reference');
            });
        }
        if (schemaHasColumn('travel_bookings', 'trip_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('trip_id');
            });
        }
        if (schemaHasColumn('travel_bookings', 'status')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_bookings', 'passenger_count')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('passenger_count');
            });
        }
        if (schemaHasColumn('travel_bookings', 'total_amount_minor')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('total_amount_minor');
            });
        }
        if (schemaHasColumn('travel_bookings', 'currency')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
        if (schemaHasColumn('travel_bookings', 'booking_source')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('booking_source');
            });
        }
        if (schemaHasColumn('travel_bookings', 'customer_contact_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('customer_contact_id');
            });
        }
        if (schemaHasColumn('travel_bookings', 'booked_by_user_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('booked_by_user_id');
            });
        }
        if (schemaHasColumn('travel_bookings', 'payment_status')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('payment_status');
            });
        }
        if (schemaHasColumn('travel_bookings', 'expires_at')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('expires_at');
            });
        }
        if (schemaHasColumn('travel_bookings', 'idempotency_key')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('idempotency_key');
            });
        }
        if (schemaHasColumn('travel_bookings', 'version')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('version');
            });
        }
        if (schemaHasColumn('travel_bookings', 'created_at')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
