<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToolPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ToolPage
 */
class ToolPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'tool_key' => $this->tool_key,
            'title' => $this->title,
            'intro_markdown' => $this->intro_markdown,
            'body_markdown' => $this->body_markdown,
            'faq' => $this->faq ?? [],
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
