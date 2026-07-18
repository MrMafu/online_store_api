<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ProductResource::collection(Product::with("inventory")->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            "name"     => ["required", "string", "max:255"],
            "price"    => ["required", "integer", "min:0"],
            "quantity" => ["required", "integer", "min:0"],
        ])->validate();

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create([
                "name"  => $validated["name"],
                "price" => $validated["price"],
            ]);

            $product->inventory()->create([
                "quantity" => $validated["quantity"],
            ]);

            return $product;
        });

        return (new ProductResource($product->load("inventory")))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return new ProductResource($product->load("inventory"));
    }

    public function updateInventory(Request $request, Product $product)
    {
        $validated = Validator::make($request->all(), [
            "quantity" => ["required", "integer", "min:0"],
        ])->validate();

        $product->inventory()->update([
            "quantity" => $validated["quantity"],
        ]);

        return new ProductResource($product->load("inventory"));
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    /**
     * Remove the specified resource from storage.
     */
    // public function destroy(string $id)
    // {
    //     //
    // }
}
