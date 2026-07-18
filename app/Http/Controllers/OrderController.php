<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     //
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            "items"              => ["required", "array", "min:1"],
            "items.*.product_id" => ["required", "exists:products,id"],
            "items.*.quantity"   => ["required", "integer", "min:1"],
        ])->validate();

        // Create the order and process stock deductions for all items as a single atomic operation
        $result = DB::transaction(function () use ($validated) {
            $order = Order::create(["status" => "pending"]);

            foreach ($validated["items"] as $item) {
                // Decrement stock only if enough quantity exists to prevent overselling
                $updated = DB::table("inventories")
                    ->where("product_id", $item["product_id"])
                    ->where("quantity", ">=", $item["quantity"])
                    ->decrement("quantity", $item["quantity"]);

                // If zero rows were updated, it means stock was insufficient
                if ($updated === 0) {
                    return null;
                }

                $product = Product::find($item["product_id"]);

                $order->items()->create([
                    "product_id" => $item["product_id"],
                    "quantity"   => $item["quantity"],
                    "unit_price" => $product->price,
                ]);
            }

            $order->update(["status" => "completed"]);

            return $order;
        });

        // Handle the aborted transaction failure
        if ($result === null) {
            return response()->json([
                "message" => "One or more items are out of stock.",
            ], 409);
        }

        return (new OrderResource($result->load("items")))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return new OrderResource($order->load("items"));
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
