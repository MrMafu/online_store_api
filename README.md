# Task 1: Online Store
## Endpoints
| Method | Endpoint | Description |
|---|---|---|
| GET | /api/products | View all products and stock |
| POST | /api/products | Create a product with initial inventory |
| GET | /api/products/{id} | View a product and its stock |
| POST | /api/products/{id}/inventory | Set/update inventory quantity |
| POST | /api/flash-sales | Create a flash sale for a product |
| GET | /api/flash-sales/{id} | View a flash sale |
| POST | /api/flash-sales/{id}/purchase | Purchase a product during a flash sale |
| POST | /api/orders | Create an order with one or more items |
| GET | /api/orders/{id} | View an order and its items |

## Setup
Run these commands:
```
composer install
cp .env.example .env
php artisan key:generate
```
Configure your database in `.env`, then:
```
php artisan migrate
```
Run the API:
```
php artisan serve
```

## Testing
The race condition tests spin up a separate artisan serve process and fire concurrent HTTP requests at it, so they need a real database (not in-memory SQLite) that both the test process and the spawned server can access. Configure this in `phpunit.xml`.
```
// Configure these in phpunit.xml
<env name="DB_CONNECTION" value=""/>
<env name="DB_HOST" value=""/>
<env name="DB_PORT" value=""/>
<env name="DB_DATABASE" value="online_store_api"/>
<env name="DB_USERNAME" value=""/>
<env name="DB_PASSWORD" value=""/>
```

There are 2 race condition tests:
1. FlashSaleRaceConditionTest
```
php artisan test tests/Feature/FlashSaleRaceConditionTest.php
```
seeds 5 units of stock, fires 20 concurrent purchase requests for 1 unit each, and asserts exactly 5 succeed, 15 are rejected, final inventory is 0, and exactly 5 order items were created.

2. FlashSaleRaceConditionUnevenQuantityTest
```
php artisan test tests/Feature/FlashSaleRaceConditionUnevenQuantityTest.php
```
same setup, but each request asks for 3 units against 5 in stock. Asserts only 1 request succeeds (since a second can't be fulfilled with 2 units left), and the leftover 2 units remain in inventory rather than being incorrectly depleted or driven negative. This test can work with any amounts of quantity requests by changing the value of `$qtyPerRequest`:
```
$stock = 5; // You can also adjust this too for further testing
$qtyPerRequest = 3; // Change this to your liking
$concurrentRequests = 20;
```