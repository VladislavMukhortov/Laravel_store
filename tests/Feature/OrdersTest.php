<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Promo;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PromoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateOrder()
    {
        $user = User::factory()->create();
        $products = Product::factory(3)->create();

        $orderService = OrderService::create();

        $order = $orderService->createOrder($user);

        $this->assertCount(1, Order::all());

        $orderTotalAmount = 0;
        foreach ($products as $product) {
            $quantity = rand(1, 999);
            $orderProduct = $orderService->addOrderProduct($order, $product, $quantity);
            $orderProductAmount = $product->price * $quantity;
            $orderTotalAmount += $orderProductAmount;

            $this->assertDatabaseHas((new OrderProduct())->getTable(), [
                'id' => $orderProduct->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'product_amount' => $orderProductAmount,
            ]);
        }
        $this->assertDatabaseHas((new Order())->getTable(), [
            'id' => $order->id,
            'user_id' => $user->id,
            'total_amount' => $orderTotalAmount,
        ]);
    }

    public function testAmountOffPromo()
    {
        $promo = Promo::factory()->create(['type' => 'amount_off', 'value' => 1]);
        $product = Product::factory()->create();

        $orderService = OrderService::create();

        $quantity = rand(1, 999);

        $order = $orderService->createOrder(null, $promo);

        $orderService->addOrderProduct($order, $product, $quantity);

        $subtotalAmount = $product->price * $quantity;
        $totalAmount = $subtotalAmount - $promo->value;

        $this->assertCount(1, Order::all());

        $this->assertDatabaseHas($order->getTable(), [
            'subtotal_amount' => $subtotalAmount,
            'total_amount' => $totalAmount,
            'promo_id' => $promo->id,
        ]);
    }

    public function testPercentOffPromo()
    {
        $promo = Promo::factory()->create(['type' => 'percent_off']);
        $product = Product::factory()->create();

        $orderService = OrderService::create();

        $quantity = rand(1, 999);

        $order = $orderService->createOrder(null, $promo);

        $orderService->addOrderProduct($order, $product, $quantity);

        $subtotalAmount = $product->price * $quantity;

        $totalAmount = $subtotalAmount - intval(floor($subtotalAmount * $promo->value / 100));

        $order->total_amount = ($totalAmount > 0) ? $totalAmount : 0;

        $this->assertCount(1, Order::all());

        $this->assertDatabaseHas($order->getTable(), [
            'subtotal_amount' => $subtotalAmount,
            'total_amount' => $totalAmount,
            'promo_id' => $promo->id,
        ]);
    }

    public function testExactValuesPercentOffPromo()
    {
        $promo = Promo::factory()->create(['type' => 'percent_off', 'value' => 9]);
        $product = Product::factory()->create(['price' => 100]);

        $orderService = OrderService::create();

        $quantity = 1;
        $user = null;

        $order = $orderService->createOrder($user, $promo);

        $orderService->addOrderProduct($order, $product, $quantity);

        $subtotalAmount = 100;

        $totalAmount = 91;

        $this->assertDatabaseHas($order->getTable(), [
            'subtotal_amount' => $subtotalAmount,
            'total_amount' => $totalAmount,
            'promo_id' => $promo->id,
        ]);
    }

    public function testDuplicateProductInOrder()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $orderService = OrderService::create();

        $order = $orderService->createOrder($user);

        $orderProduct = $orderService->addOrderProduct($order, $product, 1);
        $orderService->addOrderProduct($order, $product, 2);

        $this->assertCount(1, OrderProduct::all());

        $this->assertDatabaseHas((new OrderProduct())->getTable(), [
            'id' => $orderProduct->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'product_amount' => $product->price * 3,
        ]);

    }

    public function testAddOrderPromo()
    {
        $promo = Promo::factory()->create();
        $products = Product::factory(3)->create();
        $orderService = OrderService::create();

        $order = $orderService->createOrder();
        foreach ($products as $product) {
            $quantity = rand(1, 999);
            $orderService->addOrderProduct($order, $product, $quantity);
        }

        $orderPromo = $orderService->addOrderPromo($order, $promo);

        $this->assertCount(1, Order::all());

        $this->assertDatabaseHas((new Order())->getTable(), [
            'promo_id' => $orderPromo->promo_id,
        ]);

        $this->assertLessThan($order->subtotal_amount, $order->total_amount);
    }

    public function testNonNegativeTotal()
    {
        $promo = Promo::factory()->create(['value' => 10, 'type' => 'amount_off']);
        $product = Product::factory()->create(['price' => 2]);

        $orderService = OrderService::create();

        $order = $orderService->createOrder(null, $promo);

        $quantity = 1;

        $orderService->addOrderProduct($order, $product, $quantity);

        $this->assertCount(1, Order::all());

        $this->assertDatabaseHas((new Order())->getTable(), [
            'total_amount' => 0,
        ]);
    }

    public function testReduceItemsLeft()
    {
        $orderService = OrderService::create();

        $product = Product::factory()->create(['items_left' => 1000]);
        $order = $orderService->createOrder();

        $quantity = rand(1, 999);

        $itemsLeft = $product->items_left - $quantity;

        $orderService->addOrderProduct($order, $product, $quantity);

        $this->assertDatabaseHas((new Product())->getTable(), [
            'id' => $product->id,
            'items_left' => $itemsLeft,
        ]);
    }

    public function testUnfulfillableQuantity()
    {
        $orderService = OrderService::create();

        $product = Product::factory()->create(['items_left' => 0]);
        $order = $orderService->createOrder();

        $quantity = rand(1, 999);

        $errorCode = null;
        try {
            $orderService->addOrderProduct($order, $product, $quantity);
        }catch (\Exception $e) {
            $errorCode = $e->getCode();
        }

        $this->assertEquals($orderService::ERROR_UNFULFILLABLE_QUANTITY, $errorCode);
    }

    public function testItemsProcessing()
    {
        $product = Product::factory()->create(['items_left' => 1000]);
        $orderService = OrderService::create();
        $order = $orderService->createOrder();

        $quantity = 100;

        $orderService->addOrderProduct($order, $product, $quantity);
        $orderService->addItemsProcessing($product, $quantity);
        $this->assertLessThan(1000, $product->items_left);

        $this->assertDatabaseHas((new Product())->getTable(), [
            'id' => $product->id,
            'items_processing' => $quantity,
        ]);

        $order = null;

        if ($order === null) {
            $orderService->removeItemsProcessing($product);

            $this->assertDatabaseHas((new Product())->getTable(), [
                'id' => $product->id,
                'items_processing' => null,
            ]);

            $this->assertEquals($product->items_left, 1000);
        }
    }
}
