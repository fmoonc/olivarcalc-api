<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArticleIndexRequest;
use App\Http\Resources\Api\V1\AffiliateProductResource;
use App\Http\Resources\Api\V1\ArticleDetailResource;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ToolPageResource;
use App\Models\AffiliateProduct;
use App\Models\Article;
use App\Models\Category;
use App\Models\ToolPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public content endpoints, spec §7.2: categories, guides, tool pages and
 * affiliate products.
 *
 * All of this text is authored in Filament, never in the web repo, so the
 * owner can revise it without a deploy.
 */
class ContentController extends Controller
{
    /** GET /api/v1/categories */
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->withCount(['articles' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->cached(['data' => CategoryResource::collection($categories)]);
    }

    /**
     * GET /api/v1/articles
     *
     * Paginated in Laravel's standard shape (data / links / meta), spec §7.1.
     */
    public function articles(ArticleIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $articles = Article::query()
            ->published()
            ->with('category')
            ->when($validated['category'] ?? null, fn ($q, $slug) => $q->whereHas(
                'category',
                fn ($cq) => $cq->where('slug', $slug),
            ))
            ->when($validated['tool'] ?? null, fn ($q, $tool) => $q->where('related_tool', $tool))
            ->orderByDesc('published_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return $this->cached(ArticleResource::collection($articles));
    }

    /** GET /api/v1/articles/{slug} */
    public function article(string $slug): JsonResponse
    {
        $article = Article::query()
            ->published()
            ->with(['category', 'author'])
            ->where('slug', $slug)
            ->first();

        if ($article === null) {
            return response()->json([
                'message' => "No existe la guía «{$slug}».",
            ], 404);
        }

        return $this->cached(ArticleDetailResource::make($article));
    }

    /** GET /api/v1/tool-pages/{tool_key} */
    public function toolPage(Request $request, string $toolKey): JsonResponse
    {
        $page = ToolPage::where('tool_key', $toolKey)->first();

        if ($page === null) {
            return response()->json([
                'message' => "No hay página de herramienta para «{$toolKey}».",
            ], 404);
        }

        return $this->cached(ToolPageResource::make($page));
    }

    /** GET /api/v1/affiliate-products */
    public function affiliateProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string', 'max:100'],
        ]);

        $products = AffiliateProduct::query()
            ->active()
            ->when($validated['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->cached(['data' => AffiliateProductResource::collection($products)]);
    }

    private function cached(mixed $payload): JsonResponse
    {
        $response = $payload instanceof AnonymousResourceCollection
            || $payload instanceof JsonResource
            ? $payload->response()
            : response()->json($payload);

        return $response
            ->header('Cache-Control', 'public, max-age=300')
            ->setEtag(md5((string) $response->getContent()));
    }
}
