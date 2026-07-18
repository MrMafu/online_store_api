<?php
use App\Http\Controllers\ProductController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Product routes
Route::apiResource("products", ProductController::class)->only("index", "store", "show");

// Update stock quantity for the product inventory
Route::post("products/{product}/inventory", [ProductController::class, "updateInventory"]);

// Flash sale routes
Route::apiResource("flash-sales", FlashSaleController::class)->only(["store", "show"]);

// Purchase route for flash sale
Route::post("flash-sales/{flashSale}/purchase", [FlashSaleController::class, "purchase"]);

// Order routes
Route::apiResource("orders", OrderController::class)->only(["store", "show"]);