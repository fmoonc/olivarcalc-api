<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary shape, used in list responses. The full body is only in
 * ArticleDetailResource: a list of 20 articles should not ship 20 long bodies
 * to a static build.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_image_path' => $this->cover_image_path,
            'category' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ]),
            // Lets the guide and the calculator cross-link (spec §11).
            'related_tool' => $this->related_tool,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
