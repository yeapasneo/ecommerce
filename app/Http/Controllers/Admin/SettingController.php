<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('Admin/Settings', ['values' => Setting::all_settings()]);
    }

    public function update(Request $request)
    {
        $rules = collect(Setting::DEFAULTS)->map(fn () => ['nullable', 'string', 'max:1000'])->all();
        foreach (['delivery_charge_inside', 'delivery_charge_outside', 'express_delivery_charge', 'free_delivery_over'] as $k) {
            $rules[$k] = ['required', 'numeric', 'min:0'];
        }
        $rules['site_name'] = ['required', 'string', 'max:100'];
        Setting::put($request->validate($rules));

        return back()->with('success', 'Settings saved.');
    }

    public function messages()
    {
        return Inertia::render('Admin/Messages', [
            'messages' => ContactMessage::latest()->paginate(20),
            'subscribers' => NewsletterSubscriber::latest()->take(200)->get(),
        ]);
    }

    public function readMessage(ContactMessage $message)
    {
        $message->update(['is_read' => true]);

        return back();
    }

    public function destroyMessage(ContactMessage $message)
    {
        $message->delete();

        return back()->with('success', 'Message deleted.');
    }
}
