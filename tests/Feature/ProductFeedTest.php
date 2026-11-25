<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ProductFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(ProductFeed::path()); // start without a feed file
    }

    protected function tearDown(): void
    {
        @unlink(ProductFeed::path());
        parent::tearDown();
    }

    public function test_feed_lists_products_with_prices_and_variants(): void
    {
        $parent = Category::create(['name' => 'Component', 'slug' => 'component']);
        $cat = Category::create(['name' => 'Graphics Card', 'slug' => 'gpu', 'parent_id' => $parent->id]);
        $brand = Brand::create(['name' => 'Asus', 'slug' => 'asus']);
        $gpu = Product::create(['category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Asus RTX 5060', 'slug' => 'asus-rtx-5060', 'sku' => 'GPU1', 'model' => 'DUAL-RTX5060',
            'price' => 45000, 'regular_price' => 48000, 'stock' => 3, 'stock_status' => 'in_stock', 'image' => '/images/gpu.webp', 'description' => '<p>Fast &amp; quiet.</p>']);
        $gpu->images()->create(['path' => '/images/gpu-2.webp', 'sort_order' => 1]);
        $phone = Product::create(['category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Asus Phone', 'slug' => 'asus-phone', 'sku' => 'PH', 'price' => 20000, 'stock' => 1, 'stock_status' => 'in_stock', 'image' => '/images/ph.webp', 'has_variants' => true]);
        $phone->variants()->create(['name' => 'Black', 'options' => ['Colour' => 'Black'], 'sku' => 'PH-BK', 'price' => 20000, 'stock' => 1, 'is_active' => true]);
        Product::create(['category_id' => $cat->id, 'name' => 'No photo', 'slug' => 'no-photo', 'sku' => 'NP', 'price' => 100, 'stock' => 1, 'stock_status' => 'in_stock']);
        Product::create(['category_id' => $cat->id, 'name' => 'No price', 'slug' => 'no-price', 'sku' => 'NO', 'price' => 0, 'stock' => 1, 'stock_status' => 'in_stock', 'image' => '/x.webp']);

        $this->get('/feeds/google.xml')->assertNotFound(); // off by default
        Setting::put(['feeds_enabled' => '1']);
        $xml = $this->get('/feeds/google.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getFile()->getContent();

        $doc = simplexml_load_string($xml);
        $items = $doc->channel->item;
        $this->assertCount(2, $items);
        $g = fn ($item) => $item->children('http://base.google.com/ns/1.0');
        $first = $g($items[0]);
        $this->assertSame(['GPU1', '48000.00 BDT', '45000.00 BDT', 'in_stock', 'Asus', 'Component > Graphics Card', 'DUAL-RTX5060', 'Fast & quiet.'],
            [(string) $first->id, (string) $first->price, (string) $first->sale_price, (string) $first->availability, (string) $first->brand, (string) $first->product_type, (string) $first->mpn, (string) $first->description]);
        $this->assertStringEndsWith('/images/gpu-2.webp', (string) $first->additional_image_link);
        $variant = $g($items[1]);
        $this->assertSame(['PH-BK', 'PH', 'Asus Phone - Black', 'Black'], [(string) $variant->id, (string) $variant->item_group_id, (string) $variant->title, (string) $variant->color]);
        $this->assertSame('2', Setting::get('feed_count'));
        $this->assertSame($xml, $this->get('/feeds/facebook.xml')->getFile()->getContent());
    }
}
