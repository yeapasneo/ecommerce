<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Ai\DraftQueue;
use App\Ai\ProductWriter;
use App\Ai\ReplyWriter;
use App\Http\Controllers\Controller;
use App\Models\AiDraft;
use App\Models\AiLog;
use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** AI Writer: product texts written by AI, reviewed and approved by staff. */
class AiController extends Controller
{
    public function index(Request $request, AiClient $ai, DraftQueue $queue)
    {
        $status = in_array($request->status, ['pending', 'approved', 'rejected', 'failed'], true) ? $request->status : 'pending';
        $drafts = AiDraft::with(['product:id,name,slug,image,description,key_features,meta_title,meta_description,description_bn,key_features_bn', 'reviewer:id,name'])
            ->where('status', $status)->latest('updated_at')->paginate(10)->withQueryString();
        $drafts->getCollection()->each(fn ($d) => $d->product?->append('image_url'));

        return Inertia::render('Admin/AiWriter', [
            'drafts' => $drafts,
            'status' => $status,
            'counts' => AiDraft::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'needing' => $queue->candidates()->count(),
            'ai' => [
                'configured' => $ai->isConfigured(),
                'provider' => $ai->isConfigured() ? $ai->label($ai->chain()[0]) : null,
                'usedToday' => $ai->usedToday(),
                'limit' => (int) Setting::get('ai_daily_limit', 500),
                'auto' => Setting::enabled('ai_writer_auto'),
                'perHour' => (int) Setting::get('ai_writer_per_hour', 10),
                'errorsToday' => AiLog::where('created_at', '>=', today())->where('status', 'error')->count(),
                'lastError' => AiLog::where('status', 'error')->where('created_at', '>=', now()->subDay())->latest('id')->value('error'),
            ],
        ]);
    }

    /** "Write with AI" on the product form: returns texts to fill in (nothing is saved). */
    public function write(Product $product, ProductWriter $writer)
    {
        try {
            return response()->json(['fields' => $writer->write($product)]);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->reason === 'not_configured' ? 409 : 422);
        }
    }

    /** Draft a reply to a question, review or contact message (staff edit and send it). */
    public function reply(string $type, int $id, ReplyWriter $writer)
    {
        $model = match ($type) {
            'question' => Question::findOrFail($id),
            'review' => Review::findOrFail($id),
            'message' => ContactMessage::findOrFail($id),
            default => abort(404),
        };
        try {
            return response()->json(['reply' => $writer->{$type}($model)]);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->reason === 'not_configured' ? 409 : 422);
        }
    }

    /** Queue drafts for the products that need them most. */
    public function queue(Request $request, AiClient $ai, DraftQueue $queue)
    {
        $data = $request->validate(['count' => ['required', 'integer', 'between:1,100']]);
        if (! $ai->isConfigured()) {
            return back()->with('error', 'Add an AI key in Settings → AI first.');
        }
        $n = $queue->queue($data['count']);

        return back()->with('success', $n ? "Writing {$n} product text(s) in the background. Refresh in a minute to review them." : 'Every product already has a text or a draft.');
    }

    public function approve(Request $request, AiDraft $draft, ProductWriter $writer)
    {
        abort_unless($draft->status === 'pending', 404);
        $fields = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.key_features' => ['nullable', 'array', 'max:8'],
            'fields.key_features.*' => ['nullable', 'string', 'max:255'],
            'fields.description' => ['nullable', 'string', 'max:20000'],
            'fields.meta_title' => ['nullable', 'string', 'max:255'],
            'fields.meta_description' => ['nullable', 'string', 'max:500'],
            'fields.key_features_bn' => ['nullable', 'array', 'max:8'],
            'fields.key_features_bn.*' => ['nullable', 'string', 'max:255'],
            'fields.description_bn' => ['nullable', 'string', 'max:20000'],
        ])['fields'] ?? null;
        $writer->apply($draft, $request->user()->id, $fields);

        return back()->with('success', "Published the new text for {$draft->product->name}.");
    }

    public function approveAll(Request $request, ProductWriter $writer)
    {
        $drafts = AiDraft::with('product')->where('status', 'pending')->whereNotNull('fields')->get();
        $drafts->each(fn ($d) => $writer->apply($d, $request->user()->id));

        return back()->with('success', "Published {$drafts->count()} product text(s).");
    }

    public function reject(Request $request, AiDraft $draft)
    {
        $draft->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id]);

        return back()->with('success', 'Draft rejected.');
    }

    public function retry(AiDraft $draft, ProductWriter $writer)
    {
        abort_unless(in_array($draft->status, ['failed', 'rejected'], true), 404);
        $draft->update(['status' => 'pending']);
        $draft = $writer->draft($draft->product);

        return back()->with($draft->status === 'failed' ? 'error' : 'success', $draft->status === 'failed' ? $draft->error : 'New draft written.');
    }
}
