<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront')->name('home');
Route::view('/shop', 'storefront')->name('shop');
Route::view('/product/{product}', 'storefront')->name('product.show');
Route::view('/cart', 'storefront')->name('cart');
