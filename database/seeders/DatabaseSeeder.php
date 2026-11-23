<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@technest.test'], [
            'name' => 'Store Admin',
            'phone' => '01700000000',
            'password' => 'password',
            'is_admin' => true,
        ]);
        User::updateOrCreate(['email' => 'customer@technest.test'], [
            'name' => 'Demo Customer',
            'phone' => '01800000000',
            'address' => 'House 5, Road 2, Banani',
            'city' => 'Dhaka',
            'password' => 'password',
        ]);

        // Use the crawled real-world catalog when present, otherwise the small demo catalog.
        $this->call(is_file(database_path(ImportedCatalogSeeder::FILE)) ? ImportedCatalogSeeder::class : CatalogSeeder::class);
        $this->call(ContentSeeder::class);
    }
}
