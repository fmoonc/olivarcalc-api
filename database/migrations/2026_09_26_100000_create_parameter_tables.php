<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec §5.1: parameters live in versioned sets. Only one set is `published`
 * per campaign; publishing a new one archives the previous. Sets are never
 * deleted, because saved calculations reference them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_sets', function (Blueprint $table) {
            // Spec §6: declared per table so the schema does not depend on the
            // server default, which changed in MySQL 8.0.
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('version')->unique();
            $table->string('campaign');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign', 'status']);
        });

        Schema::create('parameters', function (Blueprint $table) {
            // Spec §6: declared per table so the schema does not depend on the
            // server default, which changed in MySQL 8.0.
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('parameter_set_id')->constrained('parameter_sets')->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->enum('value_type', ['decimal', 'bool', 'string', 'json'])->default('decimal');
            $table->string('unit')->nullable();
            // false means the owner has not confirmed this against the
            // official source yet. Spec §0.2, §5.2.
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('source_label')->nullable();
            $table->string('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['parameter_set_id', 'key']);
            // Lets the publish rule ask "is anything unverified?" cheaply.
            $table->index(['parameter_set_id', 'verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameters');
        Schema::dropIfExists('parameter_sets');
    }
};
