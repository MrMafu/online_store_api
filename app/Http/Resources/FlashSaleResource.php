<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlashSaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"                  => $this->id,
            "product_id"          => $this->product_id,
            "product_name"        => $this->whenLoaded("product", fn () => $this->product->name),
            "discounted_price"    => $this->discounted_price,
            "quantity_available"  => $this->quantity_available,
            "starts_at"           => $this->starts_at,
            "ends_at"             => $this->ends_at,
            "is_active"           => $this->isActive(),
        ];
    }
}
