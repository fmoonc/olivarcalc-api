<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly variation of the four POOLred indices.
 *
 * Not in `oil_prices` because it is not a price. POOLred's public ticker
 * publishes how much each index moved over the window, with no €/tonne behind
 * it, and mixing the two would put a percentage into a DECIMAL money column.
 *
 * `poolred_id` is POOLred's own slug rather than our `quality` enum, because the
 * four names do not map one-to-one onto Extra/Virgin/Lampante: they are Extra,
 * Virgen, España and Lampante. Translation belongs in the presentation layer,
 * and this table stays a faithful record of what the source published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oil_index_quotes', function (Blueprint $table) {
            // Spec §6: declared per table so the schema does not depend on the
            // server default, which changed in MySQL 8.0.
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('poolred_id');
            $table->string('name');
            /** Percentage, signed. DECIMAL per spec §6, never a float. */
            $table->decimal('variation_pct', 6, 3);
            $table->date('quoted_on');
            /** Their label for the window, kept verbatim for provenance. */
            $table->string('period')->nullable();
            /** Their "actualizado" stamp, so a stored figure can be traced back. */
            $table->string('source_stamp')->nullable();
            $table->boolean('is_finalized')->default(false);
            $table->string('source_url')->nullable();
            $table->timestamps();

            // One row per index per window; a re-import updates rather than
            // accumulates duplicates.
            $table->unique(['poolred_id', 'quoted_on']);
            $table->index(['quoted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oil_index_quotes');
    }
};
