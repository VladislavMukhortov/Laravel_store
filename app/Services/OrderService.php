<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;

class OrderService
{
    public static function create(): self
    {
        return resolve(self::class);
    }

    public function createOrder( User $user = null ): Order
    {
        $order = new Order();
        $order->total_amount = 0;

        if($user) {
            $order->user_id = $user->id;
        }

        $order->save();

        return $order;
    }

    public function addOrderProduct(Order $order, Product $product, int $quantity): OrderProduct
    {
        $orderProduct = new OrderProduct();
        $orderProduct->order_id = $order->id;
        $orderProduct->product_id = $product->id;
        $orderProduct->quantity = $quantity;
        $orderProduct->product_amount = $quantity * $product->price;
        $orderProduct->save();

        $this->updateOrderTotal($order);

        return $orderProduct;
    }

    public function updateOrderTotal(Order $order): Order
    {
        $orderProducts = $order->orderProducts()->get();
        $orderTotalAmount = 0;
        foreach ($orderProducts as $orderProduct) {
            $orderTotalAmount += $orderProduct->product_amount;
        }

        $order->total_amount = $orderTotalAmount;
        $order->save();

        return $order;
    }
}
