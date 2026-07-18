<?php
use App\Http\Controllers\ProductController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::apiResource("products", ProductController::class)->only("index", "store", "show");
Route::post("products/{product}/inventory", [ProductController::class, "updateInventory"]);

Route::apiResource("flash-sales", FlashSaleController::class)->only(["store", "show"]);
Route::post("flash-sales/{flashSale}/purchase", [FlashSaleController::class, "purchase"]);

Route::apiResource("orders", OrderController::class)->only(["store", "show"]);