<?php

namespace App\Services\Settings;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;

class StoreSettingsService
{
    private const NUMERIC_KEYS = [
        'shipping_standard_fee_minor',
        'shipping_free_threshold_minor',
        'minimum_order_amount_minor',
    ];

    /** @return array<string, mixed> */
    public function all(): array
    {
        $values = $this->defaults();
        foreach (StoreSetting::query()->get(['setting_key', 'setting_value']) as $setting) {
            if (in_array($setting->setting_key, self::NUMERIC_KEYS, true)) {
                $values[$setting->setting_key] = max(0, (int) $setting->setting_value);
            } else {
                $values[$setting->setting_key] = $setting->setting_value ?? '';
            }
        }
        $values['currency_label'] = $values['currency'] === 'PKR' ? 'Rs.' : $values['currency'];

        return $values;
    }

    public function get(string $key): mixed
    {
        $values = $this->all();

        return $values[$key] ?? null;
    }

    public function shippingFeeMinor(): int
    {
        return (int) $this->get('shipping_standard_fee_minor');
    }

    public function freeShippingThresholdMinor(): int
    {
        return (int) $this->get('shipping_free_threshold_minor');
    }

    public function minimumOrderAmountMinor(): int
    {
        return (int) $this->get('minimum_order_amount_minor');
    }

    public function currency(): string
    {
        return (string) $this->get('currency');
    }

    /** @param array<string, string|int|null> $values */
    public function updateGroup(string $group, array $values): void
    {
        DB::transaction(function () use ($group, $values): void {
            foreach ($values as $key => $value) {
                StoreSetting::query()->updateOrCreate(
                    ['setting_key' => $key],
                    ['group' => $group, 'setting_value' => $value === null ? null : (string) $value]
                );
            }
        });
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'store_name' => config('app.name', 'MUZORA.PK'),
            'store_email' => config('mail.from.address', ''),
            'store_phone' => '',
            'store_address' => '',
            'currency' => config('checkout.currency', 'PKR'),
            'footer_text' => 'Your trusted destination for authentic gadgets, premium accessories and next-gen gaming gear. Genuine warranty, quick delivery and real support.',
            'shipping_standard_fee_minor' => max(0, (int) config('checkout.shipping.standard_fee_minor', 25000)),
            'shipping_free_threshold_minor' => max(0, (int) config('checkout.shipping.free_threshold_minor', 299900)),
            'minimum_order_amount_minor' => 0,
        ];
    }
}
