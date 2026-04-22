<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (Schema::hasTable('seed_locks')) {
            return;
        }

        try {
            Schema::create('seed_locks', function (Blueprint $table) {
                $table->increments('id');
                $table->string('lock_key', 120)->unique();
                $table->timestampTz('ran_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '42P07') {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        Schema::dropIfExists('seed_locks');
    }
};
