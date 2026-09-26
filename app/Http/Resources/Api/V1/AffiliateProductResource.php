<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AffiliateProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AffiliateProduct
 */
class AffiliateProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'url' => $this->url,
            'image_path' => $this->image_path,
            'price_text' => $this->price_text,
            'short_description' => $this->short_description,
        ];
    }
}
