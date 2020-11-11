<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function products()
    {
        $products = Product::get();

        return view('products', [
            'products' => $products,
        ]);
    }

    public function product(Request $request)
    {
        $product = Product::where('id', $request->id)->first();

        return view('product', [
            'product' => $product,
        ]);
    }
}
