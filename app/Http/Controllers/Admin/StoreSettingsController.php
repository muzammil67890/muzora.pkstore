<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\UpdateShippingSettingsRequest;
use App\Models\StoreSetting;
use App\Services\Settings\StoreSettingsService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreSettingsController extends Controller
{
    public function __construct(private readonly StoreSettingsService $settings)
    {
    }

    public function general(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'manage', StoreSetting::class);

        return view('admin.settings.general', ['settings' => $this->settings->all()]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'manage', StoreSetting::class);
        $this->settings->updateGroup('general', $request->safe()->only([
            'store_name', 'store_email', 'store_phone', 'store_address', 'currency', 'footer_text',
        ]));

        return back()->with('status', 'Store settings saved.');
    }

    public function shipping(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'manage', StoreSetting::class);
        $settings = $this->settings->all();
        $settings['shipping_standard_fee'] = Money::fromMinor($settings['shipping_standard_fee_minor']);
        $settings['shipping_free_threshold'] = Money::fromMinor($settings['shipping_free_threshold_minor']);
        $settings['minimum_order_amount'] = Money::fromMinor($settings['minimum_order_amount_minor']);

        return view('admin.settings.shipping', ['settings' => $settings]);
    }

    public function updateShipping(UpdateShippingSettingsRequest $request): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'manage', StoreSetting::class);
        $data = $request->validated();
        $this->settings->updateGroup('shipping', [
            'shipping_standard_fee_minor' => Money::toMinor((string) $data['shipping_standard_fee']),
            'shipping_free_threshold_minor' => Money::toMinor((string) $data['shipping_free_threshold']),
            'minimum_order_amount_minor' => Money::toMinor((string) $data['minimum_order_amount']),
        ]);

        return back()->with('status', 'Shipping settings saved.');
    }
}
