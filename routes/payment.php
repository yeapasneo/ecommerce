<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
| Gateway callbacks. Loaded without the "web" middleware group: gateways POST back
| cross-site, so the session cookie isn't sent, and starting a new session here would
| replace the customer's (logging them out and emptying their cart). No CSRF either:
| every callback is verified server-to-server with the gateway before an order is paid.
*/
Route::match(['get', 'post'], '/payment/{gateway}/{result}/{order}', [PaymentController::class, 'callback'])
    ->whereIn('result', ['return', 'success', 'fail', 'cancel'])
    ->name('payment.callback');
Route::post('/payment/{gateway}/ipn', [PaymentController::class, 'ipn'])->name('payment.ipn');
