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

        $this->call([CatalogSeeder::class, ContentSeeder::class]);
    }
}
