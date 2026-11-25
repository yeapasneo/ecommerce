<?php

namespace App\Http\Controllers;

use App\Ai\AiException;
use App\Ai\ShopAssistant;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** The storefront chat assistant. */
class AssistantController extends Controller
{
    public function reply(Request $request, ShopAssistant $assistant)
    {
        abort_unless(ShopAssistant::enabled(), 404);
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);
        if (end($data['messages'])['role'] !== 'user') {
            abort(422);
        }

        try {
            return response()->json($assistant->reply($data['messages']));
        } catch (AiException $e) {
            Log::warning('Assistant failed: '.$e->getMessage());
            $phone = Setting::get('phone');

            return response()->json([
                'reply' => $e->reason === 'limit'
                    ? "I'm resting for today. Please search the shop or call us at {$phone}."
                    : "Sorry, I can't answer right now. Please try again in a moment or call us at {$phone}.",
                'products' => [],
                'error' => true,
            ]);
        }
    }
}
