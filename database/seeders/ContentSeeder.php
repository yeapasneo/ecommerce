<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('public')->makeDirectory('banners');
        $banners = [
            ['Build Your Dream PC', 'Pick every part with our PC Builder and get it assembled for free', '/pc-builder', 'slider', '#0f3d5c', '#f97316'],
            ['Gaming Laptops Mega Sale', 'Save up to 12% on RTX 40 series gaming laptops', '/category/gaming-laptop', 'slider', '#3b1f5c', '#22d3ee'],
            ['Upgrade to 4K', 'Crisp, colourful monitors for work and play', '/category/monitor', 'slider', '#1f5c3d', '#facc15'],
            ['Smartphone Deals', 'Latest flagships with official warranty', '/category/smartphone', 'side', '#5c1f2e', '#fde68a'],
            ['Use code WELCOME500', '৳500 off on your first order above ৳10,000', '/offers', 'side', '#1f2f5c', '#86efac'],
        ];
        foreach ($banners as $i => [$title, $subtitle, $link, $pos, $bg, $accent]) {
            $path = 'banners/banner-'.($i + 1).'.svg';
            Storage::disk('public')->put($path, $this->bannerSvg($title, $subtitle, $bg, $accent, $pos === 'side'));
            Banner::updateOrCreate(['title' => $title], [
                'subtitle' => $subtitle, 'link' => $link, 'position' => $pos, 'bg_color' => $bg, 'image' => $path, 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        Coupon::updateOrCreate(['code' => 'WELCOME500'], ['type' => 'fixed', 'value' => 500, 'min_order' => 10000, 'usage_limit' => 1000, 'is_active' => true]);
        Coupon::updateOrCreate(['code' => 'SAVE5'], ['type' => 'percent', 'value' => 5, 'min_order' => 5000, 'max_discount' => 3000, 'is_active' => true]);

        $offers = [
            ['Winter Gaming Fest', 'Up to 12% discount on gaming laptops, graphics cards and accessories. Limited stock available.', '/category/gaming'],
            ['Free Assembly on PC Builds', 'Build your PC with our PC Builder and we will assemble and test it for free.', '/pc-builder'],
            ['Student Laptop Week', 'Special prices on student laptops with free laptop bag.', '/category/student-laptop'],
        ];
        foreach ($offers as $i => [$title, $desc, $link]) {
            Offer::updateOrCreate(['slug' => Str::slug($title)], [
                'title' => $title, 'description' => $desc, 'link' => $link, 'image' => 'banners/banner-'.($i + 1).'.svg',
                'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(30 + $i * 10), 'is_active' => true,
            ]);
        }

        $pages = [
            'about-us' => ['About Us', '<p>TechNest is a computer and gadget retailer offering genuine products with official warranty, fast delivery and friendly after-sales support.</p><p>We stock desktops, laptops, components, monitors, phones, networking gear and accessories from leading global brands.</p>'],
            'terms-and-conditions' => ['Terms and Conditions', '<p>By placing an order you agree to these terms. Prices and availability are subject to change without notice. Products are covered by the manufacturer warranty stated on the product page.</p>'],
            'privacy-policy' => ['Privacy Policy', '<p>We collect only the information needed to process your orders and improve our service. We never sell your personal data.</p>'],
            'refund-policy' => ['Refund and Return Policy', '<p>Products can be returned within 7 days of delivery if they are unused and in original packaging. Faulty products are handled under warranty.</p>'],
            'delivery-policy' => ['Delivery Policy', '<p>Inside Dhaka delivery within 24-48 hours. Outside Dhaka within 3-5 working days. Express delivery is available inside Dhaka.</p>'],
        ];
        foreach ($pages as $slug => [$title, $content]) {
            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_active' => true]);
        }

        $customer = User::where('email', 'customer@technest.test')->first();
        if ($customer && ! Order::exists()) {
            mt_srand(7);
            $products = Product::where('stock_status', 'in_stock')->inRandomOrder()->take(30)->get();
            $statuses = ['pending', 'processing', 'shipped', 'delivered', 'delivered', 'delivered', 'cancelled'];
            for ($n = 0; $n < 24; $n++) {
                $lines = $products->random(mt_rand(1, 3));
                $subtotal = $lines->sum('price');
                $status = $statuses[array_rand($statuses)];
                $created = now()->subDays(mt_rand(0, 28))->subHours(mt_rand(0, 20));
                $order = Order::create([
                    'order_number' => Order::generateNumber(), 'user_id' => $n % 2 === 0 ? $customer->id : null,
                    'first_name' => $n % 2 === 0 ? 'Demo' : ['Rahim', 'Karim', 'Nusrat', 'Farhana', 'Tanvir'][mt_rand(0, 4)],
                    'last_name' => $n % 2 === 0 ? 'Customer' : 'Ahmed', 'email' => $n % 2 === 0 ? $customer->email : null,
                    'phone' => '01'.mt_rand(300000000, 999999999), 'address' => 'House '.mt_rand(1, 99).', Road '.mt_rand(1, 20),
                    'city' => ['Dhaka', 'Chattogram', 'Sylhet', 'Khulna'][mt_rand(0, 3)], 'delivery_method' => 'home_delivery',
                    'payment_method' => ['cod', 'bkash', 'online'][mt_rand(0, 2)],
                    'payment_status' => $status === 'delivered' ? 'paid' : 'unpaid', 'status' => $status,
                    'subtotal' => $subtotal, 'delivery_charge' => 60, 'discount' => 0, 'total' => $subtotal + 60,
                ]);
                $order->forceFill(['created_at' => $created, 'updated_at' => $created])->save();
                foreach ($lines as $p) {
                    $order->items()->create(['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => 1, 'total' => $p->price]);
                }
                $order->histories()->create(['status' => 'pending', 'comment' => 'Order placed.']);
                if ($status !== 'pending') {
                    $order->histories()->create(['status' => $status, 'comment' => null]);
                }
            }

            foreach (Product::inRandomOrder()->take(15)->get() as $i => $p) {
                Review::create(['product_id' => $p->id, 'user_id' => $customer->id, 'rating' => mt_rand(3, 5),
                    'comment' => ['Excellent product, works perfectly.', 'Good value for money.', 'Fast delivery and genuine product.', 'Very satisfied with the performance.'][mt_rand(0, 3)],
                    'is_approved' => $i < 12]);
            }
            foreach (Product::inRandomOrder()->take(6)->get() as $i => $p) {
                Question::create(['product_id' => $p->id, 'user_id' => $customer->id, 'question' => 'Does this come with official warranty?',
                    'answer' => $i < 4 ? 'Yes, it comes with official brand warranty.' : null, 'answered_at' => $i < 4 ? now() : null]);
            }
        }
    }

    protected function bannerSvg(string $title, string $subtitle, string $bg, string $accent, bool $side): string
    {
        [$w, $h] = $side ? [600, 300] : [1200, 450];
        $t = e($title);
        $s = e($subtitle);
        $fs = $side ? 36 : 60;
        $ss = $side ? 18 : 26;
        $x = $side ? 36 : 80;
        $ty = $side ? 110 : 170;
        $sy = $ty + ($side ? 36 : 56);
        $by = $side ? $h - 90 : $h - 140;
        $bty = $by + 29;
        $bcx = $x + 80;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}">
<rect width="{$w}" height="{$h}" fill="{$bg}"/>
<circle cx="{$w}" cy="0" r="{$h}" fill="{$accent}" opacity=".18"/>
<circle cx="{$w}" cy="{$h}" r="{$ty}" fill="#ffffff" opacity=".06"/>
<text x="{$x}" y="{$ty}" font-family="Arial, sans-serif" font-size="{$fs}" font-weight="700" fill="#fff">{$t}</text>
<text x="{$x}" y="{$sy}" font-family="Arial, sans-serif" font-size="{$ss}" fill="#fff" opacity=".85">{$s}</text>
<rect x="{$x}" y="{$by}" width="160" height="44" rx="22" fill="{$accent}"/>
<text x="{$bcx}" y="{$bty}" text-anchor="middle" font-family="Arial, sans-serif" font-size="18" font-weight="700" fill="{$bg}">Shop Now</text>
</svg>
SVG;
    }
}
