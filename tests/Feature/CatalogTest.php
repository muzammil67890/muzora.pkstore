<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_homepage_renders_active_catalog_data_from_the_database(): void
    {
        [$category, $product] = $this->createCatalogProduct();

        $this->get('/')
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee($product->name)
            ->assertDontSee('Anker Soundcore Space One ANC Headphones');
    }

    public function test_shop_search_and_price_sort_use_database_products(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio', 'icon' => 'headphones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand', 'is_active' => true]);
        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Alpha Headphones',
            'slug' => 'alpha-headphones',
            'sku' => 'ALPHA-1',
            'price' => 25000,
            'stock_quantity' => 4,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Beta Headphones',
            'slug' => 'beta-headphones',
            'sku' => 'BETA-1',
            'price' => 10000,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $this->get('/shop?q=headphones&sort=price-asc')
            ->assertOk()
            ->assertSeeInOrder(['Beta Headphones', 'Alpha Headphones']);

        $this->get('/shop?category=audio&max_price=15000')
            ->assertOk()
            ->assertSee('Beta Headphones')
            ->assertDontSee('Alpha Headphones');
    }

    public function test_product_detail_uses_slug_and_inactive_products_are_not_public(): void
    {
        [$category, $product] = $this->createCatalogProduct();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Rs. 12,999');

        $product->update(['is_active' => false]);
        $this->get('/product/'.$product->slug)->assertNotFound();
    }

    /** @return array{Category, Product} */
    private function createCatalogProduct(): array
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio', 'icon' => 'headphones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Example Audio', 'slug' => 'example-audio', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Example Headphones',
            'slug' => 'example-headphones',
            'sku' => 'EX-100',
            'short_description' => 'Demo item for tests.',
            'price' => 12999,
            'compare_at_price' => 14999,
            'stock_quantity' => 5,
            'icon' => 'headphones',
            'is_active' => true,
            'is_featured' => true,
        ]);

        return [$category, $product];
    }
}
