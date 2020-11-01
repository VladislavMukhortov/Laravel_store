<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
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
        foreach($products as $product) {
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

    }

    public function testPercentOffPromo()
    {

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
}
