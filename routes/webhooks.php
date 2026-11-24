<?php

use App\Couriers\CourierManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Courier status webhooks. Stateless like the payment callbacks (no session, no CSRF);
| each courier authenticates with the token / secret saved in Settings → Couriers.
*/
Route::post('/webhooks/courier/{courier}', function (Request $request, string $courier, CourierManager $couriers) {
    abort_unless(isset(CourierManager::COURIERS[$courier]), 404);

    return $couriers->driver($courier)->webhook($request);
})->whereIn('courier', array_keys(CourierManager::COURIERS))->middleware('throttle:120,1')->name('webhooks.courier');
