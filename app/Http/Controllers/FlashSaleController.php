<?php

namespace App\Http\Controllers;

use App\Http\Resources\FlashSaleResource;
use App\Http\Resources\OrderResource;
use App\Models\FlashSale;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FlashSaleController extends Controller
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
            "product_id"         => ["required", "exists:products,id"],
            "discounted_price"   => ["required", "integer", "min:0"],
            "starts_at"          => ["required", "date"],
            "ends_at"            => ["required", "date", "after:starts_at"],
        ])->validate();

        $flashSale = FlashSale::create($validated);

        return (new FlashSaleResource($flashSale->load("product")))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FlashSale $flashSale)
    {
        return new FlashSaleResource($flashSale->load("product"));
    }

    public function purchase(Request $request, FlashSale $flashSale)
    {
        $validated = Validator::make($request->all(), [
            "quantity" => ["sometimes", "integer", "min:1"],
        ])->validate();

        $qty = $validated["quantity"] ?? 1;

        if (!$flashSale->isActive()) {
            return response()->json([
                "message" => "This flash sale is not currently active.",
            ], 422);
        }

        $result = DB::transaction(function () use ($flashSale, $qty) {
            $updated = DB::table("inventories")
                ->where("product_id", $flashSale->product_id)
                ->where("quantity", ">=", $qty)
                ->decrement("quantity", $qty);

            if ($updated === 0) {
                return null;
            }

            $order = Order::create(["status" => "completed"]);

            $order->items()->create([
                "product_id"    => $flashSale->product_id,
                "flash_sale_id" => $flashSale->id,
                "quantity"      => $qty,
                "unit_price"    => $flashSale->discounted_price,
            ]);

            return $order;
        });

        if ($result === null) {
            return response()->json([
                "message" => "Product is out of stock.",
            ], 409);
        }

        return (new OrderResource($result->load("items")))
            ->response()
            ->setStatusCode(201);
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
