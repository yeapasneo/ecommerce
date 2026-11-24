<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteController extends Controller
{
    public function offers()
    {
        seo()->title('Latest Offers & Deals')->description('Current discounts, bundle deals and promotions at '.Setting::get('site_name').'.');

        return Inertia::render('Offers', [
            'offers' => Offer::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', today()))
                ->latest()->get(),
        ]);
    }

    public function page(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->firstOrFail();
        seo()->title($page->meta_title ?: $page->title)->description($page->meta_description ?: $page->content)->keywords($page->meta_keywords);

        return Inertia::render('Page', ['page' => $page]);
    }

    public function contact()
    {
        seo()->title('Contact Us')->description('Call, email or visit '.Setting::get('site_name').': '.Setting::get('address').'.');

        return Inertia::render('Contact');
    }

    public function sendContact(Request $request)
    {
        ContactMessage::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]));

        return back()->with('success', 'Thanks for reaching out! We will get back to you soon.');
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        NewsletterSubscriber::firstOrCreate($data);

        return back()->with('success', 'Subscribed to our newsletter.');
    }

    public function track(Request $request)
    {
        $order = null;
        $searched = $request->filled('order_number');
        if ($searched) {
            $request->validate(['order_number' => ['required', 'string'], 'phone' => ['required', 'string']]);
            $order = Order::where('order_number', trim($request->order_number))
                ->where('phone', trim($request->phone))->with('histories', 'items')->first();
        }

        return Inertia::render('TrackOrder', [
            'order' => $order,
            'searched' => $searched,
            'query' => $request->only('order_number', 'phone'),
        ]);
    }
}
