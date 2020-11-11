@extends('layouts.app')
@section('title', 'products')

@section('content')
    @foreach($products as $product)
        <a href="{{ route('product', $product->id) }}">{{ $product->name }}</a><br>
    @endforeach
@endsection
