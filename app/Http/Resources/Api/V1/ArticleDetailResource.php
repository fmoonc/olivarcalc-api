<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full guide, including the Markdown body and FAQ. Spec §10.3 and §11 need the
 * body for SEO and the FAQ for the FAQPage JSON-LD.
 *
 * @mixin Article
 */
class ArticleDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge((new ArticleResource($this->resource))->toArray($request), [
            'body_markdown' => $this->body_markdown,
            'faq' => $this->faq ?? [],
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ]);
    }
}
