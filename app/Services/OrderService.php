<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Promo;
use App\Models\User;

//TODO add tables -
// ProductVariant(name) - examples: size, color
// and
// ProductVariantOptions(name, price, items_left) XL, L, M, S, red, blue
// and
// ProductVa

class OrderService
{
    public const ERROR_UNFULFILLABLE_QUANTITY = 100;

    public static function create(): self
    {
        return resolve(self::class);
    }

    public function createOrder(User $user = null, Promo $promo = null): Order
    {
        $order = new Order();
        $order->total_amount = 0;
        $order->subtotal_amount = 0;
        if ($promo) {
            $order->promo_id = $promo->id;
        }

        if ($user) {
            $order->user_id = $user->id;
        }

        $order->save();

        return $order;
    }

    public function addOrderPromo(Order $order, Promo $promo): Order
    {
        $order->promo_id = $promo->id;
        $order->save();

        $this->updateOrderTotal($order);

        return $order;
    }

    public function addOrderProduct(Order $order, Product $product, int $quantity): OrderProduct
    {
        //TODO add transaction

        $orderProduct = OrderProduct::where('product_id', $product->id)
            ->where('order_id', $order->id)
            ->first();

        if ($product->items_left !== null) {
            $this->reduceItemsLeft($product, $quantity);
        }

        if ($orderProduct) {
            $orderProduct->quantity += $quantity;
            $orderProduct->product_amount = $orderProduct->quantity * $product->price;
            $orderProduct->save();
        } else {
            $orderProduct = new OrderProduct();
            $orderProduct->order_id = $order->id;
            $orderProduct->product_id = $product->id;
            $orderProduct->quantity = $quantity;
            $orderProduct->product_amount = $quantity * $product->price;
            $orderProduct->save();
        }

        $this->updateOrderTotal($order);

        return $orderProduct;
    }

    public function updateOrderTotal(Order $order): Order
    {
        $orderProducts = $order->orderProducts()->get();
        $orderSubtotalAmount = 0;
        foreach ($orderProducts as $orderProduct) {
            $orderSubtotalAmount += $orderProduct->product_amount;
        }

        $order->subtotal_amount = $orderSubtotalAmount;

        $orderTotalAmount = $orderSubtotalAmount;

        $promo = $order->promo()->first();

        if ($promo) {
            if ($promo->type === 'amount_off') {
                $orderTotalAmount = $orderSubtotalAmount - $promo->value;
            } elseif ($promo->type === 'percent_off') {
                $orderTotalAmount = $orderSubtotalAmount - intval(floor($orderSubtotalAmount * $promo->value / 100));
            }
        }

        $order->total_amount = ($orderTotalAmount > 0) ? $orderTotalAmount : 0;

        $order->save();

        return $order;
    }

    public function reduceItemsLeft(Product $product, int $quantity): Product
    {
        if ($product->items_left < $quantity) {
            throw new \Exception('Unfulfillable quantity', self::ERROR_UNFULFILLABLE_QUANTITY);
        }
        $product->items_left = $product->items_left - $quantity;
        $product->save();

        return $product;
    }

    public function addItemsProcessing(Product $product, int $quantity): Product
    {
        $product->items_processing = $quantity;
        $product->save();

        return $product;
    }

    public function removeItemsProcessing(Product $product): Product
    {
        $product->items_left = $product->items_left + $product->items_processing;
        $product->items_processing = null;
        $product->save();

        return $product;
    }
}
