<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Product $ram;

    protected Product $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->owner = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $cat = Category::create(['name' => 'RAM', 'slug' => 'ram']);
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->ram = Product::create(['category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Acme 8GB', 'slug' => 'acme-8gb', 'sku' => 'RAM-8', 'price' => 3000, 'stock' => 5, 'stock_status' => 'in_stock']);
        $this->phone = Product::create(['category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Acme Phone', 'slug' => 'acme-phone', 'sku' => 'PH', 'price' => 20000, 'stock' => 0, 'stock_status' => 'in_stock', 'has_variants' => true]);
        $this->phone->variants()->create(['name' => '128GB', 'options' => ['Storage' => '128GB'], 'sku' => 'PH-128', 'price' => 20000, 'stock' => 2, 'is_active' => true]);
        $this->phone->variants()->create(['name' => '256GB', 'options' => ['Storage' => '256GB'], 'sku' => 'PH-256', 'price' => 24000, 'stock' => 1, 'is_active' => true]);
    }

    protected function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('products.csv', $body);
    }

    public function test_export_lists_products_and_variants(): void
    {
        $csv = $this->actingAs($this->owner)->get('/admin/products/export')->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertStringContainsString('product_id,variant_id,sku,name', $lines[0]);
        $this->assertCount(4, $lines); // header + RAM + 2 phone variants
        $this->assertStringContainsString('PH-256', $csv);
    }

    public function test_preview_then_apply(): void
    {
        $v256 = $this->phone->variants()->where('sku', 'PH-256')->first();
        $file = $this->csv("sku,variant_id,price,regular_price,cost_price,stock,active\n"
            ."ram-8,,\"3,200\",-,2500,0,\n"         // matched by SKU (any case); stock 0 → out of stock
            .",{$v256->id},23500,,,4,\n"           // variant row
            ."NOPE,,100,,,,\n"                      // unknown SKU
            ."PH,,1,,,,\n"                          // product with variants
            ."RAM-8,,abc,,,,\n");                   // bad number

        $res = $this->actingAs($this->owner)->post('/admin/products/import', ['file' => $file]);
        $res->assertInertia(fn (Assert $p) => $p->component('Admin/Products/Import')
            ->where('preview.count', 2)
            ->where('preview.errorCount', 3)
            ->where('preview.changes.0.fields.price', [3000, 3200])
            ->where('preview.changes.0.fields.stock', [5, 0]));
        $this->assertEquals(3000, $this->ram->fresh()->price); // nothing saved yet

        $token = $res->viewData('page')['props']['preview']['token'];
        $this->actingAs($this->owner)->post('/admin/products/import/apply', ['token' => $token])->assertSessionHas('success');

        $ram = $this->ram->fresh();
        $this->assertSame([3200.0, null, 2500.0, 0, 'out_of_stock'], [$ram->price, $ram->regular_price, $ram->cost_price, $ram->stock, $ram->stock_status]);
        $phone = $this->phone->fresh();
        $this->assertSame([20000.0, 6], [$phone->price, $phone->stock]); // cheapest variant, stock = 2 + 4
        $this->assertSame(23500.0, $v256->fresh()->price);
        $this->assertSame(2, StockMovement::where('reason', 'import')->count());
        $this->assertSame(1, ActivityLog::where('description', 'like', 'Bulk import%')->count());

        // A token works once.
        $this->actingAs($this->owner)->post('/admin/products/import/apply', ['token' => $token])->assertSessionHas('error');
    }

    public function test_excel_upload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>sku</t></si><si><t>stock</t></si><si><t>RAM-8</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>42</v></c></row></sheetData></worksheet>');
        $zip->close();
        $file = new UploadedFile($path, 'products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->owner)->post('/admin/products/import', ['file' => $file])
            ->assertInertia(fn (Assert $p) => $p->where('preview.count', 1)->where('preview.changes.0.fields.stock', [5, 42]));
    }
}
