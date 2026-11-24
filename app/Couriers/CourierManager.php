<?php

namespace App\Couriers;

use App\Models\Order;
use InvalidArgumentException;

class CourierManager
{
    public const COURIERS = [
        'steadfast' => Steadfast::class,
        'pathao' => Pathao::class,
    ];

    public function driver(string $key): Courier
    {
        if (! isset(self::COURIERS[$key])) {
            throw new InvalidArgumentException("Unknown courier [{$key}].");
        }

        return app(self::COURIERS[$key]);
    }

    /** Couriers switched on with their credentials filled in. */
    public function enabled(): array
    {
        return collect(self::COURIERS)->keys()
            ->filter(fn ($k) => $this->driver($k)->isEnabled())
            ->mapWithKeys(fn ($k) => [$k => $this->driver($k)->label()])
            ->all();
    }

    public function trackingUrl(Order $order): ?string
    {
        return $order->courier && isset(self::COURIERS[$order->courier]) ? $this->driver($order->courier)->trackingUrl($order) : null;
    }
}
