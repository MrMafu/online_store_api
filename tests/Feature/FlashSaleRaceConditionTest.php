<?php

use App\Models\FlashSale;
use App\Models\Inventory;
use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FlashSaleRaceConditionTest extends TestCase
{
    protected Process $serverProcess;
    protected string $baseUri = "http://127.0.0.1:8181";

    protected function setUp(): void
    {
        parent::setUp();

        /*
        Boot a real, separate server process so requests below are handled
        concurrently instead of sequentially on the test's own connection.
        */
        $this->serverProcess = new Process([
            "php", "artisan", "serve", "--port=8181",
        ], base_path());

        $this->serverProcess->start();

        // Give the background PHP server a moment to boot
        usleep(1_500_000);
    }

    protected function tearDown(): void
    {
        $this->serverProcess->stop();
        parent::tearDown();
    }

    public function test_flash_sale_purchase_prevents_negative_inventory_under_concurrent_load(): void
    {
        // Manually clear the tables to ensure a completely clean state
        DB::table("order_items")->delete();
        DB::table("orders")->delete();
        DB::table("flash_sales")->delete();
        DB::table("inventories")->delete();
        DB::table("products")->delete();

        $stock = 5;
        $concurrentRequests = 20;

        $product = Product::factory()->create(["price" => 20000]);

        Inventory::factory()->create([
            "product_id" => $product->id,
            "quantity"   => $stock,
        ]);

        $flashSale = FlashSale::factory()->create([
            "product_id"       => $product->id,
            "discounted_price" => 12000,
        ]);

        $client = new Client(["base_uri" => $this->baseUri]);

        // Queue up 20 purchase requests for 1 unit each, all fighting over 5 units of stock
        $requests = function () use ($flashSale, $concurrentRequests) {
            for ($i = 0; $i < $concurrentRequests; $i++) {
                yield new Request(
                    "POST",
                    "/api/flash-sales/{$flashSale->id}/purchase",
                    ["Content-Type" => "application/json", "Accept" => "application/json"],
                    json_encode(["quantity" => 1])
                );
            }
        };

        $successCount = 0;
        $conflictCount = 0;

        // Fire all requests essentially at once, tallying how many succeeded and how many rejected
        $pool = new Pool($client, $requests(), [
            "concurrency" => $concurrentRequests,
            "fulfilled" => function ($response) use (&$successCount, &$conflictCount) {
                if ($response->getStatusCode() === 201) {
                    $successCount++;
                } elseif ($response->getStatusCode() === 409) {
                    $conflictCount++;
                }
            },
            "rejected" => function () use (&$conflictCount) {
                $conflictCount++;
            },
        ]);

        $pool->promise()->wait();

        $finalQuantity = DB::table("inventories")
            ->where("product_id", $product->id)
            ->value("quantity");

        $orderItemCount = DB::table("order_items")
            ->where("flash_sale_id", $flashSale->id)
            ->count();

        fwrite(STDERR, "\n--- Race Condition Test Results ---\n");
        fwrite(STDERR, "Successful purchases:     {$successCount} (expected {$stock})\n");
        fwrite(STDERR, "Rejected purchases:       {$conflictCount} (expected " . ($concurrentRequests - $stock) . ")\n");
        fwrite(STDERR, "Final inventory quantity: {$finalQuantity} (expected 0)\n");
        fwrite(STDERR, "Order item rows created:  {$orderItemCount} (expected {$stock})\n");
        fwrite(STDERR, "------------------------------------\n");
        
        // Exactly 5 of the 20 requests should have won the race
        $this->assertEquals(
            $stock,
            $successCount,
            "Expected exactly {$stock} successful purchases, got {$successCount}."
        );

        $this->assertEquals(
            $concurrentRequests - $stock,
            $conflictCount,
            "Expected " . ($concurrentRequests - $stock) . " rejected purchases, got {$conflictCount}."
        );
        
        // Stock should be fully depleted, not left over and never negative
        $this->assertEquals(
            0,
            $finalQuantity,
            "Expected final inventory to be exactly 0, got {$finalQuantity}."
        );

        $this->assertGreaterThanOrEqual(
            0,
            $finalQuantity,
            "Inventory must never go negative."
        );

        // Each successful purchase should have created exactly one order item, no more, no less
        $this->assertEquals(
            $stock,
            $orderItemCount,
            "Expected {$stock} order_item rows, found {$orderItemCount}."
        );
    }
}
