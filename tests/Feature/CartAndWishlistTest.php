<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartAndWishlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_cart_uses_database_prices_even_if_the_client_submits_a_price(): void
    {
        $product = $this->makeProduct(price: 2499, stock: 5);

        $response = $this->postJson('/cart/add', [
            'product' => $product->slug,
            'quantity' => 2,
            'price' => 1,
            'subtotal' => 2,
        ]);

        $response->assertCreated()->assertJsonPath('cart_count', 2)->assertJsonPath('subtotal', '4998.00');
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 5]);
        $guestCart = Cart::query()->whereNull('user_id')->firstOrFail();
        $this->assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/', $guestCart->session_id);
        $this->assertFalse(Schema::hasColumn('cart_items', 'price'));

        $this->get('/cart')->assertOk()->assertSee($product->name)->assertSee('4,998.00')->assertSee('Cart subtotal');
    }

    public function test_cart_totals_use_exact_minor_units_from_the_current_product_price(): void
    {
        $product = $this->makeProduct(price: '19.95', stock: 3, slug: 'fractional-price-product');

        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('subtotal', '59.85');

        $this->get('/cart')->assertOk()->assertSee('19.95')->assertSee('59.85');
    }

    public function test_guest_can_update_quantity_remove_an_item_and_clear_cart(): void
    {
        $product = $this->makeProduct(stock: 8);
        $itemId = $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated()->json('item_id');

        $this->patchJson('/cart/'.$itemId, ['quantity' => 3])->assertOk()->assertJsonPath('cart_count', 3);
        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 3]);

        $this->deleteJson('/cart/'.$itemId)->assertOk()->assertJsonPath('cart_count', 0);
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);

        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->postJson('/cart/clear')->assertOk()->assertJsonPath('cart_count', 0);
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_duplicate_adds_increment_one_cart_item_and_enforce_stock_total(): void
    {
        $product = $this->makeProduct(stock: 3);

        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated()->assertJsonPath('cart_count', 3);

        $this->assertSame(1, CartItem::query()->where('product_id', $product->id)->count());
        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_inactive_and_out_of_stock_products_cannot_be_added(): void
    {
        $inactive = $this->makeProduct(slug: 'inactive-product', active: false);
        $outOfStock = $this->makeProduct(slug: 'out-of-stock-product', stock: 0);

        $this->postJson('/cart/add', ['product' => $inactive->slug, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->postJson('/cart/add', ['product' => $outOfStock->slug, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_authenticated_customer_cart_is_persistent_and_uses_the_account_cart(): void
    {
        $product = $this->makeProduct(stock: 6);
        $customer = $this->makeCustomer('persistent@example.com');

        $this->actingAs($customer, 'web')
            ->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('cart_count', 2);

        $cart = Cart::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertNull($cart->session_id);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->post('/login', ['email' => $customer->email, 'password' => 'CustomerPass123'])->assertRedirect(route('account.index'));
        $this->get('/cart')->assertOk()->assertSee($product->name)->assertSee('2 items');
    }

    public function test_customer_cannot_update_or_remove_another_customers_cart_item(): void
    {
        $product = $this->makeProduct();
        $owner = $this->makeCustomer('owner@example.com');
        $other = $this->makeCustomer('other@example.com');
        $cart = Cart::create(['user_id' => $owner->id]);
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($other, 'web')->patchJson('/cart/'.$item->id, ['quantity' => 2])->assertNotFound();
        $this->deleteJson('/cart/'.$item->id)->assertNotFound();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'cart_id' => $cart->id]);
    }

    public function test_guest_cart_merges_after_login_and_combines_duplicate_products(): void
    {
        $product = $this->makeProduct(stock: 6);
        $customer = $this->makeCustomer('merge@example.com');
        $customerCart = Cart::create(['user_id' => $customer->id]);
        $customerCart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->post('/login', ['email' => $customer->email, 'password' => 'CustomerPass123'])->assertRedirect(route('account.index'));

        $this->assertDatabaseHas('cart_items', ['cart_id' => $customerCart->id, 'product_id' => $product->id, 'quantity' => 4]);
        $this->assertSame(1, CartItem::query()->where('cart_id', $customerCart->id)->where('product_id', $product->id)->count());
        $this->assertSame(0, Cart::query()->whereNull('user_id')->count());
    }

    public function test_guest_cart_merge_clamps_quantities_to_stock(): void
    {
        $product = $this->makeProduct(stock: 3, slug: 'merge-limited-stock');
        $customer = $this->makeCustomer('merge-stock@example.com');
        $customerCart = Cart::create(['user_id' => $customer->id]);
        $customerCart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->post('/login', ['email' => $customer->email, 'password' => 'CustomerPass123'])
            ->assertRedirect(route('account.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cart_items', ['cart_id' => $customerCart->id, 'product_id' => $product->id, 'quantity' => 3]);
        $this->assertSame(0, Cart::query()->whereNull('user_id')->count());
    }

    public function test_guest_cart_merges_on_registration_and_skips_products_that_became_unavailable(): void
    {
        $product = $this->makeProduct(slug: 'unavailable-during-merge');
        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $product->update(['is_active' => false]);

        $this->post('/register', [
            'name' => 'New Cart Customer',
            'email' => 'new-cart@example.com',
            'phone' => '03001234567',
            'password' => 'NewCustomer123',
            'password_confirmation' => 'NewCustomer123',
        ])->assertRedirect(route('account.index'))->assertSessionHas('status');

        $customer = User::query()->where('email', 'new-cart@example.com')->firstOrFail();
        $this->assertSame(0, CartItem::query()->where('product_id', $product->id)->count());
        $this->assertSame(0, Cart::query()->whereNull('user_id')->count());
        $customerCart = Cart::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertSame(0, $customerCart->items()->count());
    }

    public function test_guest_cannot_access_wishlist_and_receives_login_message(): void
    {
        $product = $this->makeProduct(slug: 'guest-wishlist-product');
        $this->get('/wishlist')->assertRedirect(route('login'))->assertSessionHas('status', 'Please log in to use your wishlist.');
        $this->post('/wishlist/'.$product->slug)->assertRedirect(route('login'));
    }

    public function test_authenticated_customer_can_add_and_remove_wishlist_products_without_duplicates(): void
    {
        $product = $this->makeProduct(slug: 'wishlist-item');
        $customer = $this->makeCustomer('wishlist@example.com');

        $this->actingAs($customer, 'web')->post('/wishlist/'.$product->slug)->assertRedirect();
        $this->post('/wishlist/'.$product->slug)->assertRedirect();
        $this->assertSame(1, WishlistItem::query()->count());

        $this->post('/logout')->assertRedirect(route('home'));
        $this->post('/login', ['email' => $customer->email, 'password' => 'CustomerPass123'])->assertRedirect(route('account.index'));
        $this->get('/wishlist')->assertOk()->assertSee($product->name);
        $this->delete('/wishlist/'.$product->slug)->assertRedirect();
        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_wishlist_toggle_adds_and_removes_the_customers_own_item(): void
    {
        $product = $this->makeProduct(slug: 'toggle-wishlist-item');
        $customer = $this->makeCustomer('toggle-wishlist@example.com');

        $this->actingAs($customer, 'web')->post('/wishlist/'.$product->slug.'/toggle')->assertRedirect();
        $this->assertSame(1, WishlistItem::query()->count());
        $this->post('/wishlist/'.$product->slug.'/toggle')->assertRedirect();
        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_customer_cannot_remove_another_customers_wishlist_product(): void
    {
        $product = $this->makeProduct(slug: 'private-wishlist-item');
        $owner = $this->makeCustomer('wishlist-owner@example.com');
        $other = $this->makeCustomer('wishlist-other@example.com');
        $wishlist = Wishlist::create(['user_id' => $owner->id]);
        $item = $wishlist->items()->create(['product_id' => $product->id]);

        $this->actingAs($other, 'web')->delete('/wishlist/'.$product->slug)->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', ['id' => $item->id, 'wishlist_id' => $wishlist->id]);

        $this->post('/wishlist/'.$product->slug.'/move-to-cart')->assertNotFound();
        $this->assertDatabaseHas('wishlist_items', ['id' => $item->id]);
    }

    public function test_wishlist_product_can_be_moved_to_the_authenticated_cart(): void
    {
        $product = $this->makeProduct(price: 3999, stock: 4, slug: 'move-to-cart-item');
        $customer = $this->makeCustomer('move@example.com');
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        $wishlist->items()->create(['product_id' => $product->id]);

        $this->actingAs($customer, 'web')->post('/wishlist/'.$product->slug.'/move-to-cart')->assertRedirect();

        $this->assertDatabaseMissing('wishlist_items', ['wishlist_id' => $wishlist->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => Cart::where('user_id', $customer->id)->value('id'),
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_unavailable_wishlist_product_stays_saved_when_move_to_cart_fails(): void
    {
        $product = $this->makeProduct(stock: 0, slug: 'unavailable-move-item');
        $customer = $this->makeCustomer('unavailable-move@example.com');
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        $item = $wishlist->items()->create(['product_id' => $product->id]);

        $this->actingAs($customer, 'web')
            ->post('/wishlist/'.$product->slug.'/move-to-cart')
            ->assertRedirect()
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('wishlist_items', ['id' => $item->id]);
        $this->assertSame(0, CartItem::query()->count());
    }

    private function makeProduct(
        string|int $price = '2499.00',
        int $stock = 10,
        string $slug = 'cart-product',
        bool $active = true
    ): Product {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'accessories'],
            ['name' => 'Accessories', 'is_active' => true]
        );
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'muzora-brand'],
            ['name' => 'MUZORA Brand', 'is_active' => true]
        );

        return Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test product '.str_replace('-', ' ', $slug),
            'slug' => $slug,
            'sku' => strtoupper(substr(str_replace('-', '', $slug), 0, 20)),
            'price' => $price,
            'stock_quantity' => $stock,
            'is_active' => $active,
            'icon' => 'devices_other',
        ]);
    }

    private function makeCustomer(string $email): User
    {
        return User::create([
            'name' => 'Cart Customer',
            'email' => $email,
            'phone' => '03001234567',
            'password' => 'CustomerPass123',
        ]);
    }
}
