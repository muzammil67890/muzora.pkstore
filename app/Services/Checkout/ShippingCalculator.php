<?php

namespace App\Services\Checkout;

use App\Services\Settings\StoreSettingsService;

class ShippingCalculator
{
    public function __construct(private readonly StoreSettingsService $settings)
    {
    }

    public function calculate(int $subtotalMinor): int
    {
        $freeThreshold = $this->settings->freeShippingThresholdMinor();
        $standardFee = $this->settings->shippingFeeMinor();

        if ($subtotalMinor >= $freeThreshold) {
            return 0;
        }

        return $standardFee;
    }
}
