<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6023 (TRAVEL-210) — travel_tickets + travel_payments.
 *
 * Billets nominatifs (spec §5.3) — `validation_code` stocke un hash (jamais
 * le code en clair, vérifié côté check-in) ; `travel_payments` — un
 * paiement référence une seule réservation du tenant, `callback_payload_redacted`
 * ne contient jamais de secret/token (webhooks provider, pattern Accounting/
 * Billing HMAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_tickets')) {
            // Issue #7452 — la table est créée par 2026_08_29_000609_6023_create_travel_tickets_and_payments_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_tickets', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_tickets', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'ticket_number')) {
                    $table->string('ticket_number', 40)->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'booking_id')) {
                    $table->unsignedBigInteger('booking_id')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'passenger_id')) {
                    $table->unsignedBigInteger('passenger_id')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'validation_code')) {
                    $table->string('validation_code', 64)->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'pdf_asset_id')) {
                    $table->unsignedBigInteger('pdf_asset_id')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'issued_at')) {
                    $table->timestamp('issued_at')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'valid_from')) {
                    $table->timestamp('valid_from')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'valid_until')) {
                    $table->timestamp('valid_until')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'status')) {
                    $table->string('status', 20)->default('issued');
                }
                if (! schemaHasColumn('travel_tickets', 'checked_in_at')) {
                    $table->timestamp('checked_in_at')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'checked_in_by_user_id')) {
                    $table->unsignedBigInteger('checked_in_by_user_id')->nullable();
                }
                if (! schemaHasColumn('travel_tickets', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_payments')) {
            // Issue #7452 — la table est créée par 2026_08_29_000609_6023_create_travel_tickets_and_payments_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_payments', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_payments', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'reference')) {
                    $table->string('reference', 40)->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'booking_id')) {
                    $table->unsignedBigInteger('booking_id')->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'provider_code')) {
                    $table->string('provider_code', 20)->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'amount_minor')) {
                    $table->unsignedInteger('amount_minor')->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'currency')) {
                    $table->char('currency', 3)->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'status')) {
                    $table->string('status', 20)->default('pending');
                }
                if (! schemaHasColumn('travel_payments', 'provider_reference')) {
                    $table->string('provider_reference', 120)->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'callback_payload_redacted')) {
                    $table->jsonb('callback_payload_redacted')->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'idempotency_key')) {
                    $table->string('idempotency_key', 255)->nullable();
                }
                if (! schemaHasColumn('travel_payments', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_payments', 'company_id')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_payments', 'reference')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('reference');
            });
        }
        if (schemaHasColumn('travel_payments', 'booking_id')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('booking_id');
            });
        }
        if (schemaHasColumn('travel_payments', 'provider_code')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('provider_code');
            });
        }
        if (schemaHasColumn('travel_payments', 'amount_minor')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('amount_minor');
            });
        }
        if (schemaHasColumn('travel_payments', 'currency')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
        if (schemaHasColumn('travel_payments', 'status')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_payments', 'provider_reference')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('provider_reference');
            });
        }
        if (schemaHasColumn('travel_payments', 'callback_payload_redacted')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('callback_payload_redacted');
            });
        }
        if (schemaHasColumn('travel_payments', 'idempotency_key')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('idempotency_key');
            });
        }
        if (schemaHasColumn('travel_payments', 'created_at')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_tickets', 'company_id')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_tickets', 'ticket_number')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('ticket_number');
            });
        }
        if (schemaHasColumn('travel_tickets', 'booking_id')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('booking_id');
            });
        }
        if (schemaHasColumn('travel_tickets', 'passenger_id')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('passenger_id');
            });
        }
        if (schemaHasColumn('travel_tickets', 'validation_code')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('validation_code');
            });
        }
        if (schemaHasColumn('travel_tickets', 'pdf_asset_id')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('pdf_asset_id');
            });
        }
        if (schemaHasColumn('travel_tickets', 'issued_at')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('issued_at');
            });
        }
        if (schemaHasColumn('travel_tickets', 'valid_from')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('valid_from');
            });
        }
        if (schemaHasColumn('travel_tickets', 'valid_until')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('valid_until');
            });
        }
        if (schemaHasColumn('travel_tickets', 'status')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_tickets', 'checked_in_at')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('checked_in_at');
            });
        }
        if (schemaHasColumn('travel_tickets', 'checked_in_by_user_id')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('checked_in_by_user_id');
            });
        }
        if (schemaHasColumn('travel_tickets', 'created_at')) {
            Schema::table('travel_tickets', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
