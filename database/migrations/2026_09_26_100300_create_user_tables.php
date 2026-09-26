<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auth and per-user data, spec §6 and §7.4. The mobile app uses the same tables
 * through Sanctum tokens, so nothing here is web-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['google', 'apple']);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
        });

        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->decimal('hectares', 8, 2)->nullable();
            $table->enum('system', ['traditional', 'intensive', 'superintensive'])->nullable();
            $table->enum('irrigation', ['dry', 'irrigated'])->nullable();
            $table->unsignedInteger('trees_count')->nullable();
            $table->string('variety')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * inputs/results are JSON snapshots. The API does NOT recalculate: the
         * formulas live in @olivar/calculations and this row records exactly
         * what the user saw, against the parameter set and calc version used
         * (spec §7.4). That is why parameter_set_id is a foreign key and never
         * cascades away the set.
         */
        Schema::create('saved_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'settlement',
                'yield_converter',
                'cost_per_hectare',
                'harvest_crew',
            ]);
            $table->string('title');
            $table->string('campaign');
            $table->json('inputs');
            $table->json('results');
            $table->foreignId('parameter_set_id')->constrained('parameter_sets')->restrictOnDelete();
            $table->string('calc_version', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'campaign']);
        });

        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('quality', ['extra', 'virgin', 'lampante']);
            $table->enum('direction', ['above', 'below']);
            $table->decimal('threshold_eur_kg', 6, 3);
            $table->json('channels');
            $table->boolean('active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['quality', 'active']);
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('expo_token')->unique();
            $table->enum('platform', ['ios', 'android']);
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        /**
         * Audit of Cloudflare Pages rebuild hooks (spec §8). The debounce lives
         * in the job; this table is the visible history the admin panel reads.
         */
        Schema::create('site_rebuild_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reason');
            $table->timestamp('requested_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->enum('status', ['pending', 'dispatched', 'failed'])->default('pending');
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_rebuild_requests');
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('price_alerts');
        Schema::dropIfExists('saved_calculations');
        Schema::dropIfExists('parcels');
        Schema::dropIfExists('social_accounts');
    }
};
