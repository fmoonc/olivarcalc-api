<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly origin olive-oil prices, spec §6. `unique(week_start, quality)` keeps
 * one row per quality per week; provisional rows are still shown, marked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oil_prices', function (Blueprint $table) {
            // Spec §6: declared per table so the schema does not depend on the
            // server default, which changed in MySQL 8.0.
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->enum('quality', ['extra', 'virgin', 'lampante']);
            // DECIMAL per spec §6, never a float.
            $table->decimal('avg_price_eur_t', 10, 2);
            $table->decimal('volume_t', 10, 2)->nullable();
            $table->boolean('provisional')->default(false);
            // 'poolred' only once the owner confirms the terms of use; until
            // then everything is 'manual' (spec §8).
            $table->enum('source', ['poolred', 'manual'])->default('manual');
            $table->string('source_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['week_start', 'quality']);
            $table->index(['quality', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oil_prices');
    }
};
