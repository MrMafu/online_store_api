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

        $this->serverProcess = new Process([
            "php", "artisan", "serve", "--port=8181",
        ], base_path());

        $this->serverProcess->start();

        usleep(1_500_000);
    }

    protected function tearDown(): void
    {
        $this->serverProcess->stop();
        parent::tearDown();
    }

    public function test_flash_sale_purchase_prevents_negative_inventory_with_multi_unit_requests(): void
    {
        DB::table("order_items")->delete();
        DB::table("orders")->delete();
        DB::table("flash_sales")->delete();
        DB::table("inventories")->delete();
        DB::table("products")->delete();

        $stock = 5;
        $qtyPerRequest = 3;
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

        $expectedSuccessCount = intdiv($stock, $qtyPerRequest);
        $expectedRemainingStock = $stock % $qtyPerRequest;
        $expectedConflictCount = $concurrentRequests - $expectedSuccessCount;

        $client = new Client(["base_uri" => $this->baseUri]);

        $requests = function () use ($flashSale, $concurrentRequests, $qtyPerRequest) {
            for ($i = 0; $i < $concurrentRequests; $i++) {
                yield new Request(
                    "POST",
                    "/api/flash-sales/{$flashSale->id}/purchase",
                    ["Content-Type" => "application/json", "Accept" => "application/json"],
                    json_encode(["quantity" => $qtyPerRequest])
                );
            }
        };

        $successCount = 0;
        $conflictCount = 0;

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
        fwrite(STDERR, "Requested qty per request: {$qtyPerRequest}\n");
        fwrite(STDERR, "Successful purchases:      {$successCount} (expected {$expectedSuccessCount})\n");
        fwrite(STDERR, "Rejected purchases:        {$conflictCount} (expected {$expectedConflictCount})\n");
        fwrite(STDERR, "Final inventory quantity:  {$finalQuantity} (expected {$expectedRemainingStock})\n");
        fwrite(STDERR, "Order item rows created:   {$orderItemCount} (expected {$expectedSuccessCount})\n");
        fwrite(STDERR, "------------------------------------\n");
        
        $this->assertEquals(
            $expectedSuccessCount,
            $successCount,
            "Expected {$expectedSuccessCount} successful purchases (stock {$stock} ÷ qty {$qtyPerRequest}), got {$successCount}."
        );

        $this->assertEquals(
            $expectedConflictCount,
            $conflictCount,
            "Expected {$expectedConflictCount} rejected purchases, got {$conflictCount}."
        );

        $this->assertEquals(
            $expectedRemainingStock,
            $finalQuantity,
            "Expected {$expectedRemainingStock} units left over (unfulfillable remainder), got {$finalQuantity}."
        );

        $this->assertGreaterThanOrEqual(
            0,
            $finalQuantity,
            "Inventory must never go negative."
        );

        $this->assertEquals(
            $expectedSuccessCount,
            $orderItemCount,
            "Expected {$expectedSuccessCount} order_item rows, found {$orderItemCount}."
        );
    }
}
