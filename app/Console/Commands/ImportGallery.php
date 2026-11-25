<?php

namespace App\Console\Commands;

use App\Models\Product;
use Database\Seeders\ImportedCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Attach the extra photos from the catalog file to products already in the database. */
class ImportGallery extends Command
{
    protected $signature = 'catalog:import-gallery';

    protected $description = 'Add extra product photos (gallery) from database/data/catalog.json.gz';

    public function handle(): int
    {
        $file = database_path(ImportedCatalogSeeder::FILE);
        if (! is_file($file)) {
            $this->error('No catalog file at '.$file);

            return self::FAILURE;
        }
        $data = json_decode(gzdecode(file_get_contents($file)), true);
        $added = 0;
        $bySlug = Product::pluck('id', 'slug');
        $bySku = Product::pluck('id', 'sku');
        foreach ($data['products'] ?? [] as $p) {
            $paths = ImportedCatalogSeeder::galleryPaths($p);
            $id = $bySlug[$p['slug'] ?? ''] ?? $bySku[$p['code'] ?? ''] ?? null;
            if (! $paths || ! $id) {
                continue;
            }
            $existing = DB::table('product_images')->where('product_id', $id)->pluck('path')->all();
            $sort = count($existing);
            foreach (array_diff($paths, $existing) as $path) {
                DB::table('product_images')->insert(['product_id' => $id, 'path' => $path, 'sort_order' => ++$sort, 'created_at' => now(), 'updated_at' => now()]);
                $added++;
            }
        }
        $this->info("Added {$added} gallery photo(s).");

        return self::SUCCESS;
    }
}
