<?php

namespace Database\Factories;

use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSale>
 */
class FlashSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "product_id"          => Product::factory(),
            "discounted_price"    => fake()->numberBetween(500, 15000),
            "starts_at"           => now()->subMinute(),
            "ends_at"             => now()->addHour(),
        ];
    }
}
