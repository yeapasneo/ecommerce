<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Setting;
use InvalidArgumentException;

class PaymentManager
{
    public const GATEWAYS = [
        'bkash' => Bkash::class,
        'nagad' => Nagad::class,
        'sslcommerz' => SslCommerz::class,
        'eps' => Eps::class,
    ];

    public function driver(string $key): Gateway
    {
        if (! isset(self::GATEWAYS[$key])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return app(self::GATEWAYS[$key]);
    }

    public function isGateway(string $method): bool
    {
        return isset(self::GATEWAYS[$method]);
    }

    /** Online gateways switched on and fully configured. */
    public function enabledGateways(): array
    {
        return collect(self::GATEWAYS)->keys()
            ->filter(fn ($key) => $this->driver($key)->isEnabled())
            ->mapWithKeys(fn ($key) => [$key => Order::PAYMENT_METHODS[$key]])
            ->all();
    }

    /** Methods offered at checkout. Cash on delivery stays available if nothing else is. */
    public function checkoutMethods(): array
    {
        $methods = Setting::enabled('cod_enabled') ? ['cod' => Order::PAYMENT_METHODS['cod']] : [];
        $methods += $this->enabledGateways();

        return $methods ?: ['cod' => Order::PAYMENT_METHODS['cod']];
    }
}
