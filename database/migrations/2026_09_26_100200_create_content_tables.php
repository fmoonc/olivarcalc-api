<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial content, spec §6. All of it is managed from Filament, never from
 * the web repo: the API owns the text (spec §10 architecture note).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt');
            $table->longText('body_markdown');
            $table->string('cover_image_path')->nullable();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            // Which calculator the guide supports, for internal linking (spec §11).
            $table->string('related_tool')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('faq')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // E-E-A-T: the owner reviews guides on a cadence (spec §9).
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('tool_pages', function (Blueprint $table) {
            $table->id();
            // settlement | yield_converter | cost_per_hectare | harvest_crew ...
            $table->string('tool_key')->unique();
            $table->string('title');
            $table->longText('intro_markdown')->nullable();
            $table->longText('body_markdown')->nullable();
            $table->json('faq')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('url');
            $table->string('image_path')->nullable();
            $table->string('price_text')->nullable();
            $table->text('short_description');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_products');
        Schema::dropIfExists('tool_pages');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('categories');
    }
};
