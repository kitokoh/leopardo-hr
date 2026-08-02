<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partners')) {
            Schema::table('partners', function (Blueprint $table) {
                if (! Schema::hasColumn('partners', 'application_status')) {
                    $table->string('application_status')->default('pending')->after('status');
                }
                if (! Schema::hasColumn('partners', 'payment_details')) {
                    $table->text('payment_details')->nullable()->after('application_status');
                }
                if (! Schema::hasColumn('partners', 'tax_rate')) {
                    $table->integer('tax_rate')->default(0)->after('default_commission_rate');
                }
                if (! Schema::hasColumn('partners', 'payout_threshold')) {
                    $table->integer('payout_threshold')->default(5000)->after('tax_rate');
                }
                if (! Schema::hasColumn('partners', 'payout_cycle')) {
                    $table->string('payout_cycle')->default('monthly')->after('payout_threshold');
                }
            });
        }

        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                if (! Schema::hasColumn('commissions', 'net_amount')) {
                    $table->integer('net_amount')->nullable()->after('amount');
                }
                if (! Schema::hasColumn('commissions', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 15, 8)->default(1.0)->after('currency');
                }
                if (! Schema::hasColumn('commissions', 'original_amount')) {
                    $table->integer('original_amount')->nullable()->after('exchange_rate');
                }
                if (! Schema::hasColumn('commissions', 'original_currency')) {
                    $table->string('original_currency', 3)->nullable()->after('original_amount');
                }
            });
        }

        if (Schema::hasTable('partner_clicks')) {
            Schema::table('partner_clicks', function (Blueprint $table) {
                $table->index(['partner_link_id', 'clicked_at']);
            });
        }

        if (! Schema::hasTable('partner_payout_requests')) {
            Schema::create('partner_payout_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->integer('amount'); // in cents
                $table->string('currency', 3);
                $table->string('status')->default('pending'); // pending, approved, paid, rejected
                $table->text('admin_notes')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payout_requests');

        Schema::table('partner_clicks', function (Blueprint $table) {
            $table->dropIndex(['partner_link_id', 'clicked_at']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['net_amount', 'exchange_rate', 'original_amount', 'original_currency']);
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['application_status', 'payment_details', 'tax_rate', 'payout_threshold', 'payout_cycle']);
        });
    }
};
