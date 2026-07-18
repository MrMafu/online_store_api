<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "product_id" => Product::factory(),
            "quantity"   => fake()->numberBetween(5, 50),
        ];
    }
}
