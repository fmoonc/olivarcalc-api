<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\OilPrice;
use App\Models\ParameterSet;
use App\Models\ToolPage;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/build-manifest
 *
 * Everything the Astro build needs to know about the site in one request:
 * which slugs exist and when each last changed. Spec §7.2 and §11 (the sitemap
 * needs real lastmod values, not "now").
 *
 * Spec §10.1: if this request fails the build fails. That is deliberate — an
 * empty guide index is worse than a failed deploy.
 */
class BuildManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $articles = Article::query()
            ->published()
            ->orderBy('slug')
            ->get(['slug', 'published_at', 'updated_at', 'related_tool', 'category_id']);

        $categories = Category::query()->orderBy('slug')->get(['slug', 'name']);

        $tools = ToolPage::query()->orderBy('tool_key')->get(['tool_key', 'updated_at']);

        // max() on a date column hands back a string, not a Carbon instance.
        $latestPriceWeek = OilPrice::query()->published()->max('week_start');

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'campaign' => ParameterSet::publishedFor()?->campaign,
            'latest_price_week' => $latestPriceWeek !== null ? (string) $latestPriceWeek : null,
            'articles' => $articles->map(fn (Article $a) => [
                'slug' => $a->slug,
                // A guide's lastmod is the later of publication and last edit.
                'lastmod' => ($a->updated_at ?? $a->published_at)?->toIso8601String(),
                'related_tool' => $a->related_tool,
            ])->all(),
            'categories' => $categories->map(fn (Category $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
            ])->all(),
            'tool_pages' => $tools->map(fn (ToolPage $t) => [
                'tool_key' => $t->tool_key,
                'lastmod' => $t->updated_at?->toIso8601String(),
            ])->all(),
        ];

        // No caching, and no ETag: this is read once per build and a stale
        // manifest means stale slugs. Symfony will render the header as
        // `no-store, private`, which is exactly the intent.
        return response()->json($payload)
            ->header('Cache-Control', 'no-store');
    }
}
