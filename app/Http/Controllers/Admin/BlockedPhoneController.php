<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedPhone;
use App\Services\FraudCheck;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BlockedPhoneController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/BlockedPhones', [
            'phones' => BlockedPhone::with('creator:id,name')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        BlockedPhone::updateOrCreate(['phone' => $data['phone']], ['reason' => $data['reason'] ?? null, 'created_by' => $request->user()->id]);

        return back()->with('success', "{$data['phone']} is marked as fraud.");
    }

    public function update(Request $request, BlockedPhone $blockedPhone)
    {
        $blockedPhone->update($this->validated($request, $blockedPhone));

        return back()->with('success', 'Saved.');
    }

    public function destroy(BlockedPhone $blockedPhone)
    {
        $blockedPhone->delete();

        return back()->with('success', "{$blockedPhone->phone} is no longer blocked.");
    }

    /** Unblock by number (used from the order page). */
    public function unblock(Request $request)
    {
        BlockedPhone::where('phone', Phone::normalize((string) $request->input('phone')))->delete();

        return back()->with('success', 'Number unblocked.');
    }

    /** Customer validity report for the order page (JSON). */
    public function check(Request $request, FraudCheck $fraud)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30'], 'order_id' => ['nullable', 'integer'], 'external' => ['nullable', 'boolean']]);

        return response()->json($fraud->report($data['phone'], $data['order_id'] ?? null, $request->boolean('external')));
    }

    protected function validated(Request $request, ?BlockedPhone $existing = null): array
    {
        $request->merge(['phone' => Phone::normalize((string) $request->input('phone'))]);

        return $request->validate([
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', Rule::unique('blocked_phones')->ignore($existing?->id)],
            'reason' => ['nullable', 'string', 'max:500'],
        ], ['phone.regex' => 'Enter a valid mobile number, e.g. 01712345678.']);
    }
}
