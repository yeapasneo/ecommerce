<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PcComponent;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /** name => [icon, filter attributes, children] */
    protected array $tree = [
        'Desktop' => ['desktop', ['Processor', 'RAM', 'Storage'], ['Gaming PC', 'Brand PC', 'All-in-One PC', 'Mini PC']],
        'Laptop' => ['laptop', ['Processor', 'RAM', 'Storage', 'Display Size'], ['Gaming Laptop', 'Business Laptop', 'Ultrabook', 'Student Laptop']],
        'Component' => ['cpu', null, [
            'Processor' => ['cpu', ['Socket', 'Cores']],
            'Motherboard' => ['board', ['Socket', 'Form Factor', 'Memory Type']],
            'RAM' => ['memory', ['Capacity', 'Memory Type', 'Frequency']],
            'Graphics Card' => ['gpu', ['Chipset', 'Memory']],
            'SSD' => ['storage', ['Capacity', 'Interface']],
            'Hard Disk' => ['storage', ['Capacity', 'RPM']],
            'Power Supply' => ['power', ['Wattage', 'Certification']],
            'Casing' => ['case', ['Form Factor']],
            'CPU Cooler' => ['fan', ['Cooler Type']],
        ]],
        'Monitor' => ['monitor', ['Display Size', 'Resolution', 'Refresh Rate', 'Panel Type'], []],
        'Phone' => ['phone', ['RAM', 'Storage'], ['Smartphone', 'Feature Phone']],
        'Tablet' => ['tablet', ['Display Size', 'Storage'], []],
        'Accessories' => ['keyboard', null, [
            'Keyboard' => ['keyboard', ['Connectivity', 'Switch Type']],
            'Mouse' => ['mouse', ['Connectivity', 'DPI']],
            'Headphone' => ['headphone', ['Connectivity', 'Type']],
            'Speaker' => ['speaker', ['Connectivity']],
            'Webcam' => ['camera', ['Resolution']],
        ]],
        'Networking' => ['router', null, ['Router' => ['router', ['Speed', 'Band']], 'Network Switch' => ['router', ['Ports']]]],
        'Gaming' => ['gamepad', null, ['Gaming Console' => ['gamepad', []], 'Gamepad' => ['gamepad', ['Connectivity']]]],
        'Camera' => ['camera', ['Resolution'], ['DSLR Camera', 'Action Camera']],
        'TV' => ['tv', ['Display Size', 'Resolution'], []],
    ];

    protected array $brands = [
        'Intel', 'AMD', 'ASUS', 'MSI', 'Gigabyte', 'Corsair', 'Kingston', 'Samsung', 'Western Digital', 'Seagate',
        'Apple', 'Lenovo', 'HP', 'Dell', 'Acer', 'Logitech', 'Razer', 'TP-Link', 'NVIDIA', 'Cooler Master',
        'Xiaomi', 'Sony', 'Canon', 'LG', 'Thermaltake', 'Lexar', 'Havit', 'Fantech', 'DeepCool', 'Walton',
    ];

    public function run(): void
    {
        mt_srand(42);
        Storage::disk('public')->makeDirectory('products');

        foreach ($this->brands as $name) {
            Brand::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }

        $order = 0;
        foreach ($this->tree as $name => [$icon, $filters, $children]) {
            $parent = $this->category($name, $icon, $filters, null, $order++, true);
            $i = 0;
            foreach ($children as $childName => $def) {
                if (is_int($childName)) {
                    $this->category($def, $icon, null, $parent->id, $i++);
                } else {
                    $this->category($childName, $def[0], $def[1] ?: null, $parent->id, $i++, in_array($childName, ['Processor', 'Graphics Card', 'Keyboard', 'Router']));
                }
            }
        }

        foreach ($this->catalog() as $catSlug => $items) {
            $category = Category::where('slug', $catSlug)->firstOrFail();
            foreach ($items as $item) {
                $this->product($category, ...$item);
            }
        }

        $components = [
            ['CPU', 'processor', true, 'core'], ['CPU Cooler', 'cpu-cooler', false, 'core'], ['Motherboard', 'motherboard', true, 'core'],
            ['RAM', 'ram', true, 'core'], ['Storage', 'ssd', true, 'core'], ['Graphics Card', 'graphics-card', false, 'core'],
            ['Power Supply', 'power-supply', true, 'core'], ['Casing', 'casing', true, 'core'],
            ['Monitor', 'monitor', false, 'peripherals'], ['Keyboard', 'keyboard', false, 'peripherals'],
            ['Mouse', 'mouse', false, 'peripherals'], ['Headphone', 'headphone', false, 'peripherals'],
        ];
        foreach ($components as $i => [$name, $cat, $req, $section]) {
            PcComponent::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'category_id' => Category::where('slug', $cat)->value('id'),
                'is_required' => $req, 'section' => $section, 'sort_order' => $i,
            ]);
        }
    }

    protected function category(string $name, string $icon, ?array $filters, ?int $parentId, int $sort, bool $featured = false): Category
    {
        return Category::updateOrCreate(['slug' => Str::slug($name)], [
            'name' => $name, 'icon' => $icon, 'filter_attributes' => $filters, 'parent_id' => $parentId,
            'sort_order' => $sort, 'is_active' => true, 'is_featured' => $featured,
            'description' => "Shop the latest {$name} at the best price with official warranty.",
        ]);
    }

    /** [brand, name, price, specs(name=>value), key features?] grouped by category slug. */
    protected function catalog(): array
    {
        return [
            'processor' => [
                ['Intel', 'Intel Core i5-14400F Processor', 21500, ['Socket' => 'LGA1700', 'Cores' => '10', 'Threads' => '16', 'Base Clock' => '2.5 GHz', 'Max Turbo' => '4.7 GHz', 'Cache' => '20MB']],
                ['Intel', 'Intel Core i7-14700K Processor', 45500, ['Socket' => 'LGA1700', 'Cores' => '20', 'Threads' => '28', 'Base Clock' => '3.4 GHz', 'Max Turbo' => '5.6 GHz', 'Cache' => '33MB']],
                ['Intel', 'Intel Core i9-14900K Processor', 66000, ['Socket' => 'LGA1700', 'Cores' => '24', 'Threads' => '32', 'Base Clock' => '3.2 GHz', 'Max Turbo' => '6.0 GHz', 'Cache' => '36MB']],
                ['Intel', 'Intel Core i3-14100 Processor', 15500, ['Socket' => 'LGA1700', 'Cores' => '4', 'Threads' => '8', 'Base Clock' => '3.5 GHz', 'Max Turbo' => '4.7 GHz', 'Cache' => '12MB']],
                ['AMD', 'AMD Ryzen 5 7600 Processor', 23500, ['Socket' => 'AM5', 'Cores' => '6', 'Threads' => '12', 'Base Clock' => '3.8 GHz', 'Max Turbo' => '5.1 GHz', 'Cache' => '38MB']],
                ['AMD', 'AMD Ryzen 7 7800X3D Processor', 52000, ['Socket' => 'AM5', 'Cores' => '8', 'Threads' => '16', 'Base Clock' => '4.2 GHz', 'Max Turbo' => '5.0 GHz', 'Cache' => '104MB']],
                ['AMD', 'AMD Ryzen 9 7950X Processor', 68500, ['Socket' => 'AM5', 'Cores' => '16', 'Threads' => '32', 'Base Clock' => '4.5 GHz', 'Max Turbo' => '5.7 GHz', 'Cache' => '80MB']],
                ['AMD', 'AMD Ryzen 5 5600G Processor', 14500, ['Socket' => 'AM4', 'Cores' => '6', 'Threads' => '12', 'Base Clock' => '3.9 GHz', 'Max Turbo' => '4.4 GHz', 'Cache' => '19MB']],
            ],
            'motherboard' => [
                ['MSI', 'MSI PRO B760M-A WiFi DDR5 Motherboard', 19500, ['Socket' => 'LGA1700', 'Form Factor' => 'Micro-ATX', 'Memory Type' => 'DDR5', 'Chipset' => 'Intel B760', 'Memory Slots' => '4']],
                ['ASUS', 'ASUS ROG STRIX Z790-E Gaming WiFi Motherboard', 52000, ['Socket' => 'LGA1700', 'Form Factor' => 'ATX', 'Memory Type' => 'DDR5', 'Chipset' => 'Intel Z790', 'Memory Slots' => '4']],
                ['Gigabyte', 'Gigabyte H610M S2H DDR4 Motherboard', 10500, ['Socket' => 'LGA1700', 'Form Factor' => 'Micro-ATX', 'Memory Type' => 'DDR4', 'Chipset' => 'Intel H610', 'Memory Slots' => '2']],
                ['ASUS', 'ASUS TUF Gaming B650-Plus WiFi Motherboard', 27500, ['Socket' => 'AM5', 'Form Factor' => 'ATX', 'Memory Type' => 'DDR5', 'Chipset' => 'AMD B650', 'Memory Slots' => '4']],
                ['MSI', 'MSI MAG X670E Tomahawk WiFi Motherboard', 41000, ['Socket' => 'AM5', 'Form Factor' => 'ATX', 'Memory Type' => 'DDR5', 'Chipset' => 'AMD X670E', 'Memory Slots' => '4']],
                ['Gigabyte', 'Gigabyte B550M DS3H AC Motherboard', 13500, ['Socket' => 'AM4', 'Form Factor' => 'Micro-ATX', 'Memory Type' => 'DDR4', 'Chipset' => 'AMD B550', 'Memory Slots' => '4']],
            ],
            'ram' => [
                ['Corsair', 'Corsair Vengeance 16GB DDR5 5600MHz RAM', 7200, ['Capacity' => '16GB', 'Memory Type' => 'DDR5', 'Frequency' => '5600MHz', 'Latency' => 'CL36']],
                ['Corsair', 'Corsair Vengeance RGB 32GB (2x16GB) DDR5 6000MHz RAM', 15500, ['Capacity' => '32GB', 'Memory Type' => 'DDR5', 'Frequency' => '6000MHz', 'Latency' => 'CL30']],
                ['Kingston', 'Kingston Fury Beast 8GB DDR4 3200MHz RAM', 2600, ['Capacity' => '8GB', 'Memory Type' => 'DDR4', 'Frequency' => '3200MHz', 'Latency' => 'CL16']],
                ['Kingston', 'Kingston Fury Beast 16GB DDR4 3200MHz RAM', 4600, ['Capacity' => '16GB', 'Memory Type' => 'DDR4', 'Frequency' => '3200MHz', 'Latency' => 'CL16']],
                ['Lexar', 'Lexar Ares RGB 16GB DDR5 6400MHz RAM', 6800, ['Capacity' => '16GB', 'Memory Type' => 'DDR5', 'Frequency' => '6400MHz', 'Latency' => 'CL32']],
            ],
            'graphics-card' => [
                ['MSI', 'MSI GeForce RTX 4060 Ventus 2X 8GB Graphics Card', 39500, ['Chipset' => 'NVIDIA RTX 4060', 'Memory' => '8GB', 'Memory Type' => 'GDDR6', 'Boost Clock' => '2490 MHz']],
                ['ASUS', 'ASUS TUF Gaming RTX 4070 Super 12GB Graphics Card', 82000, ['Chipset' => 'NVIDIA RTX 4070 Super', 'Memory' => '12GB', 'Memory Type' => 'GDDR6X', 'Boost Clock' => '2610 MHz']],
                ['Gigabyte', 'Gigabyte RTX 4090 Gaming OC 24GB Graphics Card', 255000, ['Chipset' => 'NVIDIA RTX 4090', 'Memory' => '24GB', 'Memory Type' => 'GDDR6X', 'Boost Clock' => '2535 MHz']],
                ['ASUS', 'ASUS Dual Radeon RX 7600 8GB Graphics Card', 34500, ['Chipset' => 'AMD RX 7600', 'Memory' => '8GB', 'Memory Type' => 'GDDR6', 'Boost Clock' => '2715 MHz']],
                ['Gigabyte', 'Gigabyte Radeon RX 7800 XT Gaming OC 16GB Graphics Card', 68000, ['Chipset' => 'AMD RX 7800 XT', 'Memory' => '16GB', 'Memory Type' => 'GDDR6', 'Boost Clock' => '2565 MHz']],
            ],
            'ssd' => [
                ['Samsung', 'Samsung 990 Pro 1TB NVMe M.2 SSD', 14500, ['Capacity' => '1TB', 'Interface' => 'PCIe 4.0 NVMe', 'Read Speed' => '7450 MB/s', 'Write Speed' => '6900 MB/s']],
                ['Samsung', 'Samsung 870 EVO 500GB SATA SSD', 6200, ['Capacity' => '500GB', 'Interface' => 'SATA III', 'Read Speed' => '560 MB/s', 'Write Speed' => '530 MB/s']],
                ['Western Digital', 'WD Black SN850X 2TB NVMe M.2 SSD', 22500, ['Capacity' => '2TB', 'Interface' => 'PCIe 4.0 NVMe', 'Read Speed' => '7300 MB/s', 'Write Speed' => '6600 MB/s']],
                ['Kingston', 'Kingston NV2 512GB NVMe M.2 SSD', 4200, ['Capacity' => '512GB', 'Interface' => 'PCIe 4.0 NVMe', 'Read Speed' => '3500 MB/s', 'Write Speed' => '2100 MB/s']],
                ['Lexar', 'Lexar NM620 1TB NVMe M.2 SSD', 7900, ['Capacity' => '1TB', 'Interface' => 'PCIe 3.0 NVMe', 'Read Speed' => '3300 MB/s', 'Write Speed' => '3000 MB/s']],
            ],
            'hard-disk' => [
                ['Seagate', 'Seagate BarraCuda 1TB 7200RPM Desktop HDD', 4500, ['Capacity' => '1TB', 'RPM' => '7200', 'Interface' => 'SATA III', 'Cache' => '64MB']],
                ['Western Digital', 'WD Blue 2TB 5400RPM Desktop HDD', 6500, ['Capacity' => '2TB', 'RPM' => '5400', 'Interface' => 'SATA III', 'Cache' => '256MB']],
                ['Seagate', 'Seagate IronWolf 4TB NAS HDD', 12500, ['Capacity' => '4TB', 'RPM' => '5400', 'Interface' => 'SATA III', 'Cache' => '256MB']],
            ],
            'power-supply' => [
                ['Corsair', 'Corsair CV650 650W 80 Plus Bronze Power Supply', 6800, ['Wattage' => '650W', 'Certification' => '80 Plus Bronze', 'Modular' => 'Non-Modular']],
                ['Corsair', 'Corsair RM850e 850W 80 Plus Gold Power Supply', 13500, ['Wattage' => '850W', 'Certification' => '80 Plus Gold', 'Modular' => 'Fully Modular']],
                ['Cooler Master', 'Cooler Master MWE 550 Bronze V2 Power Supply', 5200, ['Wattage' => '550W', 'Certification' => '80 Plus Bronze', 'Modular' => 'Non-Modular']],
                ['Thermaltake', 'Thermaltake Toughpower GF3 1000W Gold Power Supply', 19500, ['Wattage' => '1000W', 'Certification' => '80 Plus Gold', 'Modular' => 'Fully Modular']],
            ],
            'casing' => [
                ['Corsair', 'Corsair 4000D Airflow Mid-Tower Casing', 10500, ['Form Factor' => 'ATX', 'Side Panel' => 'Tempered Glass', 'Fans Included' => '2']],
                ['Cooler Master', 'Cooler Master MasterBox Q300L Micro-ATX Casing', 4800, ['Form Factor' => 'Micro-ATX', 'Side Panel' => 'Acrylic', 'Fans Included' => '1']],
                ['Thermaltake', 'Thermaltake View 270 TG ARGB Casing', 9200, ['Form Factor' => 'ATX', 'Side Panel' => 'Tempered Glass', 'Fans Included' => '3']],
                ['DeepCool', 'DeepCool CH560 Digital ARGB Casing', 11500, ['Form Factor' => 'ATX', 'Side Panel' => 'Tempered Glass', 'Fans Included' => '4']],
            ],
            'cpu-cooler' => [
                ['DeepCool', 'DeepCool AK400 Air CPU Cooler', 3600, ['Cooler Type' => 'Air Cooler', 'Fan Size' => '120mm', 'TDP' => '220W']],
                ['Corsair', 'Corsair iCUE H150i Elite 360mm AIO Liquid Cooler', 21500, ['Cooler Type' => 'Liquid Cooler', 'Fan Size' => '3x 120mm', 'TDP' => '300W']],
                ['Cooler Master', 'Cooler Master Hyper 212 Black Edition Cooler', 3900, ['Cooler Type' => 'Air Cooler', 'Fan Size' => '120mm', 'TDP' => '180W']],
            ],
            'gaming-laptop' => [
                ['ASUS', 'ASUS ROG Strix G16 Core i7 RTX 4060 Gaming Laptop', 185000, ['Processor' => 'Intel Core i7', 'RAM' => '16GB', 'Storage' => '1TB SSD', 'Display Size' => '16 inch', 'Graphics' => 'RTX 4060 8GB']],
                ['MSI', 'MSI Katana 15 Core i5 RTX 4050 Gaming Laptop', 125000, ['Processor' => 'Intel Core i5', 'RAM' => '16GB', 'Storage' => '512GB SSD', 'Display Size' => '15.6 inch', 'Graphics' => 'RTX 4050 6GB']],
                ['Lenovo', 'Lenovo Legion 5 Ryzen 7 RTX 4070 Gaming Laptop', 210000, ['Processor' => 'AMD Ryzen 7', 'RAM' => '32GB', 'Storage' => '1TB SSD', 'Display Size' => '16 inch', 'Graphics' => 'RTX 4070 8GB']],
                ['Acer', 'Acer Nitro V 15 Core i5 RTX 3050 Gaming Laptop', 92000, ['Processor' => 'Intel Core i5', 'RAM' => '8GB', 'Storage' => '512GB SSD', 'Display Size' => '15.6 inch', 'Graphics' => 'RTX 3050 6GB']],
            ],
            'business-laptop' => [
                ['Dell', 'Dell Latitude 5440 Core i5 Business Laptop', 98000, ['Processor' => 'Intel Core i5', 'RAM' => '8GB', 'Storage' => '512GB SSD', 'Display Size' => '14 inch']],
                ['HP', 'HP ProBook 450 G10 Core i7 Laptop', 112000, ['Processor' => 'Intel Core i7', 'RAM' => '16GB', 'Storage' => '512GB SSD', 'Display Size' => '15.6 inch']],
                ['Lenovo', 'Lenovo ThinkPad E14 Gen 5 Ryzen 5 Laptop', 89000, ['Processor' => 'AMD Ryzen 5', 'RAM' => '16GB', 'Storage' => '512GB SSD', 'Display Size' => '14 inch']],
            ],
            'ultrabook' => [
                ['Apple', 'Apple MacBook Air 13 M3 8GB 256GB', 135000, ['Processor' => 'Apple M3', 'RAM' => '8GB', 'Storage' => '256GB SSD', 'Display Size' => '13.6 inch']],
                ['Apple', 'Apple MacBook Pro 14 M3 Pro 18GB 512GB', 255000, ['Processor' => 'Apple M3 Pro', 'RAM' => '18GB', 'Storage' => '512GB SSD', 'Display Size' => '14.2 inch']],
                ['ASUS', 'ASUS Zenbook 14 OLED Core Ultra 7 Laptop', 145000, ['Processor' => 'Intel Core Ultra 7', 'RAM' => '16GB', 'Storage' => '1TB SSD', 'Display Size' => '14 inch']],
            ],
            'student-laptop' => [
                ['HP', 'HP 15s Core i3 12th Gen Laptop', 52000, ['Processor' => 'Intel Core i3', 'RAM' => '8GB', 'Storage' => '512GB SSD', 'Display Size' => '15.6 inch']],
                ['Lenovo', 'Lenovo IdeaPad Slim 3 Ryzen 5 Laptop', 61000, ['Processor' => 'AMD Ryzen 5', 'RAM' => '8GB', 'Storage' => '512GB SSD', 'Display Size' => '15.6 inch']],
                ['Acer', 'Acer Aspire 3 Core i5 Laptop', 64000, ['Processor' => 'Intel Core i5', 'RAM' => '8GB', 'Storage' => '512GB SSD', 'Display Size' => '15.6 inch']],
            ],
            'gaming-pc' => [
                ['Intel', 'TechNest Gaming PC Core i5 RTX 4060', 125000, ['Processor' => 'Intel Core i5', 'RAM' => '16GB', 'Storage' => '1TB SSD', 'Graphics' => 'RTX 4060 8GB']],
                ['AMD', 'TechNest Gaming PC Ryzen 7 RTX 4070 Super', 215000, ['Processor' => 'AMD Ryzen 7', 'RAM' => '32GB', 'Storage' => '2TB SSD', 'Graphics' => 'RTX 4070 Super 12GB']],
            ],
            'brand-pc' => [
                ['Dell', 'Dell OptiPlex 7010 Core i5 Desktop PC', 78000, ['Processor' => 'Intel Core i5', 'RAM' => '8GB', 'Storage' => '512GB SSD']],
                ['HP', 'HP Pro Tower 280 G9 Core i3 Desktop PC', 55000, ['Processor' => 'Intel Core i3', 'RAM' => '8GB', 'Storage' => '256GB SSD']],
            ],
            'all-in-one-pc' => [
                ['Apple', 'Apple iMac 24 M3 8GB 256GB', 175000, ['Processor' => 'Apple M3', 'RAM' => '8GB', 'Storage' => '256GB SSD']],
                ['Lenovo', 'Lenovo IdeaCentre AIO 3 Core i5', 82000, ['Processor' => 'Intel Core i5', 'RAM' => '8GB', 'Storage' => '512GB SSD']],
            ],
            'mini-pc' => [
                ['ASUS', 'ASUS ExpertCenter PN64 Core i5 Mini PC', 58000, ['Processor' => 'Intel Core i5', 'RAM' => '8GB', 'Storage' => '512GB SSD']],
            ],
            'monitor' => [
                ['Samsung', 'Samsung Odyssey G5 27 inch QHD 165Hz Gaming Monitor', 32500, ['Display Size' => '27 inch', 'Resolution' => '2560x1440', 'Refresh Rate' => '165Hz', 'Panel Type' => 'VA']],
                ['LG', 'LG 24MP400 24 inch FHD IPS Monitor', 13500, ['Display Size' => '24 inch', 'Resolution' => '1920x1080', 'Refresh Rate' => '75Hz', 'Panel Type' => 'IPS']],
                ['ASUS', 'ASUS TUF VG249Q3A 24 inch 180Hz Gaming Monitor', 18500, ['Display Size' => '24 inch', 'Resolution' => '1920x1080', 'Refresh Rate' => '180Hz', 'Panel Type' => 'IPS']],
                ['Dell', 'Dell UltraSharp U2723QE 27 inch 4K Monitor', 72000, ['Display Size' => '27 inch', 'Resolution' => '3840x2160', 'Refresh Rate' => '60Hz', 'Panel Type' => 'IPS']],
                ['MSI', 'MSI MAG 32 inch Curved 4K Gaming Monitor', 65000, ['Display Size' => '32 inch', 'Resolution' => '3840x2160', 'Refresh Rate' => '160Hz', 'Panel Type' => 'VA']],
                ['Xiaomi', 'Xiaomi A27i 27 inch FHD 100Hz Monitor', 15500, ['Display Size' => '27 inch', 'Resolution' => '1920x1080', 'Refresh Rate' => '100Hz', 'Panel Type' => 'IPS']],
            ],
            'smartphone' => [
                ['Samsung', 'Samsung Galaxy S24 Ultra 12/256GB', 165000, ['RAM' => '12GB', 'Storage' => '256GB', 'Display' => '6.8 inch', 'Battery' => '5000mAh']],
                ['Apple', 'Apple iPhone 15 Pro 128GB', 155000, ['RAM' => '8GB', 'Storage' => '128GB', 'Display' => '6.1 inch', 'Battery' => '3274mAh']],
                ['Xiaomi', 'Xiaomi Redmi Note 13 Pro 8/256GB', 36000, ['RAM' => '8GB', 'Storage' => '256GB', 'Display' => '6.67 inch', 'Battery' => '5100mAh']],
                ['Samsung', 'Samsung Galaxy A55 5G 8/128GB', 52000, ['RAM' => '8GB', 'Storage' => '128GB', 'Display' => '6.6 inch', 'Battery' => '5000mAh']],
                ['Walton', 'Walton Orbit Y21 6/128GB', 16000, ['RAM' => '6GB', 'Storage' => '128GB', 'Display' => '6.5 inch', 'Battery' => '5000mAh']],
            ],
            'feature-phone' => [
                ['Walton', 'Walton Olvio L30 Feature Phone', 1450, ['RAM' => '32MB', 'Storage' => '32MB', 'Battery' => '1000mAh']],
            ],
            'tablet' => [
                ['Apple', 'Apple iPad 10th Gen 64GB WiFi', 58000, ['Display Size' => '10.9 inch', 'Storage' => '64GB']],
                ['Samsung', 'Samsung Galaxy Tab S9 FE 6/128GB', 55000, ['Display Size' => '10.9 inch', 'Storage' => '128GB']],
                ['Xiaomi', 'Xiaomi Pad 6 8/256GB', 42000, ['Display Size' => '11 inch', 'Storage' => '256GB']],
            ],
            'keyboard' => [
                ['Logitech', 'Logitech MX Keys S Wireless Keyboard', 13500, ['Connectivity' => 'Wireless', 'Switch Type' => 'Scissor']],
                ['Razer', 'Razer BlackWidow V4 Mechanical Gaming Keyboard', 19500, ['Connectivity' => 'Wired', 'Switch Type' => 'Mechanical']],
                ['Fantech', 'Fantech MAXFIT61 Mechanical Keyboard', 3900, ['Connectivity' => 'Wired', 'Switch Type' => 'Mechanical']],
                ['Havit', 'Havit KB275L Membrane Keyboard', 950, ['Connectivity' => 'Wired', 'Switch Type' => 'Membrane']],
            ],
            'mouse' => [
                ['Logitech', 'Logitech G502 X Plus Wireless Gaming Mouse', 15500, ['Connectivity' => 'Wireless', 'DPI' => '25600']],
                ['Logitech', 'Logitech M331 Silent Wireless Mouse', 1450, ['Connectivity' => 'Wireless', 'DPI' => '1000']],
                ['Razer', 'Razer DeathAdder V3 Gaming Mouse', 7500, ['Connectivity' => 'Wired', 'DPI' => '30000']],
                ['Fantech', 'Fantech X9 Thor Gaming Mouse', 850, ['Connectivity' => 'Wired', 'DPI' => '4800']],
            ],
            'headphone' => [
                ['Sony', 'Sony WH-1000XM5 Wireless Noise Cancelling Headphone', 42000, ['Connectivity' => 'Wireless', 'Type' => 'Over-Ear']],
                ['Razer', 'Razer BlackShark V2 X Gaming Headset', 6500, ['Connectivity' => 'Wired', 'Type' => 'Over-Ear']],
                ['Havit', 'Havit H2002d Gaming Headphone', 1650, ['Connectivity' => 'Wired', 'Type' => 'Over-Ear']],
                ['Xiaomi', 'Xiaomi Redmi Buds 5 Pro Earbuds', 7800, ['Connectivity' => 'Wireless', 'Type' => 'In-Ear']],
            ],
            'speaker' => [
                ['Logitech', 'Logitech Z407 Bluetooth Computer Speakers', 9800, ['Connectivity' => 'Bluetooth']],
                ['Havit', 'Havit SK202 USB Speaker', 650, ['Connectivity' => 'Wired']],
            ],
            'webcam' => [
                ['Logitech', 'Logitech C920 HD Pro Webcam', 8500, ['Resolution' => '1080p']],
                ['Logitech', 'Logitech Brio 4K Webcam', 21000, ['Resolution' => '4K']],
            ],
            'router' => [
                ['TP-Link', 'TP-Link Archer C6 AC1200 Dual Band Router', 3600, ['Speed' => '1200 Mbps', 'Band' => 'Dual Band']],
                ['TP-Link', 'TP-Link Archer AX55 AX3000 WiFi 6 Router', 8900, ['Speed' => '3000 Mbps', 'Band' => 'Dual Band']],
                ['ASUS', 'ASUS RT-AX88U Pro AX6000 Router', 32000, ['Speed' => '6000 Mbps', 'Band' => 'Dual Band']],
                ['Xiaomi', 'Xiaomi Mi Router 4C 300Mbps', 1750, ['Speed' => '300 Mbps', 'Band' => 'Single Band']],
            ],
            'network-switch' => [
                ['TP-Link', 'TP-Link TL-SG1008D 8-Port Gigabit Switch', 2100, ['Ports' => '8']],
                ['TP-Link', 'TP-Link TL-SG1016D 16-Port Gigabit Switch', 6500, ['Ports' => '16']],
            ],
            'gaming-console' => [
                ['Sony', 'Sony PlayStation 5 Slim Console', 68000, ['Storage' => '1TB']],
            ],
            'gamepad' => [
                ['Sony', 'Sony DualSense Wireless Controller', 8200, ['Connectivity' => 'Wireless']],
                ['Fantech', 'Fantech WGP13 Wireless Gamepad', 2300, ['Connectivity' => 'Wireless']],
            ],
            'dslr-camera' => [
                ['Canon', 'Canon EOS 200D II DSLR Camera with 18-55mm Lens', 78000, ['Resolution' => '24.1MP']],
                ['Sony', 'Sony Alpha A7 IV Mirrorless Camera Body', 245000, ['Resolution' => '33MP']],
            ],
            'action-camera' => [
                ['Xiaomi', 'Xiaomi 4K Action Camera', 12500, ['Resolution' => '4K']],
            ],
            'tv' => [
                ['Samsung', 'Samsung 55 inch Crystal UHD 4K Smart TV', 72000, ['Display Size' => '55 inch', 'Resolution' => '4K UHD']],
                ['Sony', 'Sony Bravia 65 inch 4K Google TV', 145000, ['Display Size' => '65 inch', 'Resolution' => '4K UHD']],
                ['Walton', 'Walton 43 inch FHD Android TV', 34000, ['Display Size' => '43 inch', 'Resolution' => 'Full HD']],
                ['Xiaomi', 'Xiaomi TV A 32 inch HD Smart TV', 21500, ['Display Size' => '32 inch', 'Resolution' => 'HD']],
            ],
        ];
    }

    protected function product(Category $category, string $brand, string $name, float $price, array $specs): void
    {
        $slug = Str::slug($name);
        $brandModel = Brand::where('name', $brand)->first();
        $roll = mt_rand(1, 100);
        $status = $roll <= 82 ? 'in_stock' : ($roll <= 90 ? 'out_of_stock' : ($roll <= 96 ? 'pre_order' : 'up_coming'));
        $hasDiscount = mt_rand(1, 100) <= 45;
        $regular = $hasDiscount ? round($price * (1 + mt_rand(4, 12) / 100), -2) : null;

        $features = [];
        foreach (array_slice($specs, 0, 4, true) as $k => $v) {
            $features[] = "{$k}: {$v}";
        }

        $imagePath = "products/{$slug}.svg";
        Storage::disk('public')->put($imagePath, $this->svg($name, $category->icon ?? 'box', $brand));

        $product = Product::updateOrCreate(['slug' => $slug], [
            'category_id' => $category->id,
            'brand_id' => $brandModel?->id,
            'name' => $name,
            'sku' => strtoupper(Str::substr(Str::slug($brand, ''), 0, 3)).'-'.str_pad((string) (crc32($slug) % 100000), 5, '0', STR_PAD_LEFT),
            'model' => trim(Str::after($name, $brand)) ?: $name,
            'price' => $price,
            'regular_price' => $regular,
            'stock' => $status === 'in_stock' ? mt_rand(3, 60) : 0,
            'stock_status' => $status,
            'key_features' => $features,
            'description' => "<p>The <strong>{$name}</strong> delivers reliable performance backed by official {$brand} warranty. "
                .'Designed for everyday users, gamers and professionals alike, it combines quality build with excellent value.</p>'
                ."<p>Buy {$name} at the best price in Bangladesh from TechNest. Order online for fast home delivery or pick it up from any of our showrooms.</p>",
            'image' => $imagePath,
            'warranty_months' => [12, 24, 36][mt_rand(0, 2)],
            'is_active' => true,
            'is_featured' => mt_rand(1, 100) <= 30,
            'views' => mt_rand(10, 5000),
        ]);

        $product->specs()->delete();
        $i = 0;
        $product->specs()->create(['group' => 'Basic Information', 'name' => 'Brand', 'value' => $brand, 'sort_order' => $i++]);
        foreach ($specs as $k => $v) {
            $product->specs()->create(['group' => 'Basic Information', 'name' => $k, 'value' => $v, 'sort_order' => $i++]);
        }
        $product->specs()->create(['group' => 'Warranty', 'name' => 'Warranty', 'value' => ($product->warranty_months / 12).' Year(s)', 'sort_order' => $i++]);
    }

    /** Simple branded illustration so the demo store has product images without external assets. */
    protected function svg(string $name, string $icon, string $brand): string
    {
        $palettes = [['#0f3d5c', '#1f6f9f'], ['#3b1f5c', '#6d3fa0'], ['#5c1f2e', '#a03f55'], ['#1f5c3d', '#3fa06d'], ['#5c4a1f', '#a0823f'], ['#1f2f5c', '#3f5aa0']];
        [$a, $b] = $palettes[crc32($name) % count($palettes)];
        $glyphs = [
            'cpu' => '<rect x="150" y="150" width="200" height="200" rx="16" fill="none" stroke="#fff" stroke-width="14"/><rect x="200" y="200" width="100" height="100" rx="8" fill="#fff" opacity=".85"/><path d="M190 130v-30M250 130v-30M310 130v-30M190 370v30M250 370v30M310 370v30M130 190h-30M130 250h-30M130 310h-30M370 190h30M370 250h30M370 310h30" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'board' => '<rect x="120" y="120" width="260" height="260" rx="14" fill="none" stroke="#fff" stroke-width="12"/><rect x="160" y="160" width="90" height="90" fill="#fff" opacity=".85"/><path d="M280 165h60M280 195h60M280 225h60M160 300h180M160 335h120" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'memory' => '<rect x="90" y="190" width="320" height="110" rx="10" fill="none" stroke="#fff" stroke-width="12"/><path d="M130 225h40v40h-40zM200 225h40v40h-40zM270 225h40v40h-40zM340 225h30v40h-30z" fill="#fff" opacity=".85"/><path d="M110 300v30M150 300v30M190 300v30M230 300v30M270 300v30M310 300v30M350 300v30M390 300v30" stroke="#fff" stroke-width="8"/>',
            'gpu' => '<rect x="80" y="170" width="340" height="160" rx="18" fill="none" stroke="#fff" stroke-width="12"/><circle cx="180" cy="250" r="50" fill="none" stroke="#fff" stroke-width="10"/><circle cx="320" cy="250" r="50" fill="none" stroke="#fff" stroke-width="10"/><circle cx="180" cy="250" r="12" fill="#fff"/><circle cx="320" cy="250" r="12" fill="#fff"/>',
            'storage' => '<rect x="140" y="110" width="220" height="280" rx="20" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="220" r="60" fill="none" stroke="#fff" stroke-width="10"/><circle cx="250" cy="220" r="10" fill="#fff"/><path d="M180 340h80" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'power' => '<rect x="110" y="150" width="280" height="200" rx="14" fill="none" stroke="#fff" stroke-width="12"/><circle cx="210" cy="250" r="60" fill="none" stroke="#fff" stroke-width="10"/><path d="M215 210l-20 45h30l-20 45" stroke="#fff" stroke-width="10" fill="none"/><path d="M310 200v100" stroke="#fff" stroke-width="12"/>',
            'case' => '<rect x="160" y="90" width="180" height="320" rx="16" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="170" r="35" fill="none" stroke="#fff" stroke-width="8"/><circle cx="250" cy="270" r="35" fill="none" stroke="#fff" stroke-width="8"/><path d="M200 370h100" stroke="#fff" stroke-width="10" stroke-linecap="round"/>',
            'fan' => '<circle cx="250" cy="250" r="130" fill="none" stroke="#fff" stroke-width="12"/><path d="M250 250c0-70 60-90 80-60s-40 60-80 60zm0 0c-60 35-110 0-95-35s75-5 95 35zm0 0c60 35 50 100 15 100s-35-65-15-100z" fill="#fff" opacity=".85"/>',
            'desktop' => '<rect x="90" y="110" width="320" height="210" rx="14" fill="none" stroke="#fff" stroke-width="12"/><path d="M210 320l-15 60h110l-15-60M170 380h160" stroke="#fff" stroke-width="12" fill="none" stroke-linecap="round"/>',
            'laptop' => '<rect x="130" y="130" width="240" height="160" rx="12" fill="none" stroke="#fff" stroke-width="12"/><path d="M90 320h320l-25 40H115z" fill="#fff" opacity=".85"/>',
            'monitor' => '<rect x="70" y="110" width="360" height="220" rx="14" fill="none" stroke="#fff" stroke-width="12"/><path d="M250 330v45M180 380h140" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'tv' => '<rect x="60" y="120" width="380" height="230" rx="10" fill="none" stroke="#fff" stroke-width="12"/><path d="M160 390h180M250 350v40" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'phone' => '<rect x="180" y="80" width="140" height="340" rx="24" fill="none" stroke="#fff" stroke-width="12"/><path d="M230 110h40" stroke="#fff" stroke-width="10" stroke-linecap="round"/><circle cx="250" cy="385" r="10" fill="#fff"/>',
            'tablet' => '<rect x="130" y="90" width="240" height="320" rx="22" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="385" r="9" fill="#fff"/>',
            'keyboard' => '<rect x="60" y="170" width="380" height="160" rx="16" fill="none" stroke="#fff" stroke-width="12"/><path d="M100 210h20M150 210h20M200 210h20M250 210h20M300 210h20M350 210h20M100 250h20M150 250h20M200 250h20M250 250h20M300 250h20M350 250h20M150 290h200" stroke="#fff" stroke-width="14" stroke-linecap="round"/>',
            'mouse' => '<rect x="175" y="110" width="150" height="270" rx="75" fill="none" stroke="#fff" stroke-width="12"/><path d="M250 115v90M175 205h150" stroke="#fff" stroke-width="10"/>',
            'headphone' => '<path d="M130 300v-50a120 120 0 0 1 240 0v50" fill="none" stroke="#fff" stroke-width="14"/><rect x="110" y="280" width="60" height="100" rx="20" fill="#fff" opacity=".85"/><rect x="330" y="280" width="60" height="100" rx="20" fill="#fff" opacity=".85"/>',
            'speaker' => '<rect x="160" y="90" width="180" height="320" rx="20" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="300" r="60" fill="none" stroke="#fff" stroke-width="10"/><circle cx="250" cy="170" r="30" fill="none" stroke="#fff" stroke-width="10"/>',
            'camera' => '<rect x="90" y="170" width="320" height="200" rx="20" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="270" r="65" fill="none" stroke="#fff" stroke-width="12"/><path d="M180 170l20-40h100l20 40" stroke="#fff" stroke-width="12" fill="none"/>',
            'router' => '<rect x="90" y="260" width="320" height="90" rx="14" fill="none" stroke="#fff" stroke-width="12"/><path d="M150 260l-30-130M350 260l30-130M250 260V120" stroke="#fff" stroke-width="12" stroke-linecap="round"/><circle cx="150" cy="305" r="10" fill="#fff"/><circle cx="190" cy="305" r="10" fill="#fff"/>',
            'gamepad' => '<path d="M150 180h200c50 0 80 60 80 130 0 50-50 60-80 20l-30-40H180l-30 40c-30 40-80 30-80-20 0-70 30-130 80-130z" fill="none" stroke="#fff" stroke-width="12"/><path d="M170 250h50M195 225v50" stroke="#fff" stroke-width="12" stroke-linecap="round"/><circle cx="310" cy="240" r="12" fill="#fff"/><circle cx="340" cy="270" r="12" fill="#fff"/>',
        ];
        $glyph = $glyphs[$icon] ?? $glyphs['desktop'];
        $label = e(Str::limit($name, 38));
        $brandLabel = e(strtoupper($brand));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500" width="500" height="500">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$a}"/><stop offset="1" stop-color="{$b}"/></linearGradient></defs>
<rect width="500" height="500" fill="url(#g)"/>
<circle cx="420" cy="80" r="140" fill="#fff" opacity=".06"/>
<g transform="translate(0 -20)">{$glyph}</g>
<text x="250" y="440" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" fill="#fff" opacity=".95">{$label}</text>
<text x="30" y="50" font-family="Arial, sans-serif" font-size="22" font-weight="700" fill="#fff" opacity=".9">{$brandLabel}</text>
</svg>
SVG;
    }
}
