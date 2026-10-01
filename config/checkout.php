<?php

return [
    'currency' => 'PKR',
    'shipping' => [
        // PKR minor units (paisa). These can be changed per environment and moved into admin settings later.
        'standard_fee_minor' => (int) env('SHIPPING_STANDARD_FEE_MINOR', 25000),
        'free_threshold_minor' => (int) env('SHIPPING_FREE_THRESHOLD_MINOR', 299900),
    ],
];
