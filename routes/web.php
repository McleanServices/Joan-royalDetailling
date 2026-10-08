<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/reservation', [HomeController::class, 'reservation'])->name('reservation');
Route::get('/accueil', [HomeController::class, 'index'])->name('accueil');
Route::get('/account', [AccountController::class, 'create'])->name('account');
Route::post('/account', [AccountController::class, 'store'])->name('account.store');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/order/add', [OrderController::class, 'add'])->name('order.add');
Route::delete('/order/lines/{line}', [OrderController::class, 'remove'])->name('order.remove');
Route::post('/order/create', [OrderController::class, 'store'])->name('order.create');
Route::get('/payments/checkout/{order}', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/payments/sumup/callback', [CheckoutController::class, 'notify'])->name('checkout.notify');
Route::get('/thank-you', [CheckoutController::class, 'thankYou'])->name('thank-you');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
