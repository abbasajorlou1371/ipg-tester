<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PaymentController::class, 'create'])->name('payments.create');
Route::post('/payments', [PaymentController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('payments.store');
Route::match(['get', 'post'], '/payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');
Route::get('/payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
