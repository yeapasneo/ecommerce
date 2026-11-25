<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Ai\ProductWriter;
use App\Ai\ShopAssistant;
use App\Jobs\WriteProductDraft;
use App\Models\AiDraft;
use App\Models\AiLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    protected const GEMINI = 'generativelanguage.googleapis.com/*';

    protected const GROQ = 'api.groq.com/*';

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->withCredentials();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Laptop', 'slug' => 'laptop'])->id,
            'brand_id' => Brand::create(['name' => 'Asus', 'slug' => 'asus'])->id,
            'name' => 'Asus TUF Gaming F15 RTX 4060 Laptop', 'slug' => 'asus-tuf-f15', 'sku' => 'TUF15', 'price' => 125000, 'stock' => 5, 'stock_status' => 'in_stock',
        ]);
        $this->product->specs()->create(['group' => 'Graphics', 'name' => 'GPU', 'value' => 'RTX 4060 8GB']);
    }

    protected function keys(array $extra = []): void
    {
        Setting::put($extra + ['ai_provider' => 'gemini', 'ai_fallback' => 'groq', 'ai_gemini_key' => 'G-KEY', 'ai_groq_key' => 'Q-KEY']);
    }

    protected static function reply(?string $content, array $toolCalls = []): array
    {
        return ['choices' => [['message' => array_filter(['role' => 'assistant', 'content' => $content, 'tool_calls' => $toolCalls ?: null])]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
    }

    protected static function toolCall(string $name, array $args, string $id = 'call_1'): array
    {
        return ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]];
    }

    protected static function writerJson(): string
    {
        return "```json\n".json_encode([
            'key_features' => ['RTX 4060 8GB graphics', '15.6" 144Hz display', '- 16GB RAM'],
            'description' => '<p onclick="x()">A fast <strong>gaming</strong> laptop.</p><script>alert(1)</script><ul><li>RTX 4060</li></ul>',
            'meta_title' => 'Asus TUF F15 RTX 4060 Price in BD',
            'meta_description' => 'Buy the Asus TUF Gaming F15 with RTX 4060 graphics at the best price in Bangladesh.',
            'key_features_bn' => ['RTX 4060 8GB গ্রাফিক্স'],
            'description_bn' => '<p>একটি দ্রুত গেমিং ল্যাপটপ।</p>',
        ])."\n```";
    }

    // ── Client ───────────────────────────────────────────────────────

    public function test_nothing_runs_without_a_key(): void
    {
        $this->assertFalse(app(AiClient::class)->isConfigured());
        $this->expectException(AiException::class);
        app(AiClient::class)->text([['role' => 'user', 'content' => 'hi']], 'test');
    }

    public function test_falls_back_to_the_second_provider_and_logs_usage(): void
    {
        $this->keys();
        Http::fake([
            self::GEMINI => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
            self::GROQ => Http::response(self::reply('Hello from Groq')),
        ]);

        $this->assertSame('Hello from Groq', app(AiClient::class)->text([['role' => 'user', 'content' => 'hi']], 'test'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage') && $r->hasHeader('Authorization', 'Bearer G-KEY') && $r['model'] === 'gemini-3.5-flash-lite' && $r['reasoning_effort'] === 'low');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'groq') && $r->hasHeader('Authorization', 'Bearer Q-KEY'));
        $this->assertSame(['error', 'error', 'error', 'ok'], AiLog::orderBy('id')->pluck('status')->all()); // each Gemini model, then Groq
        $this->assertSame('Quota exceeded', AiLog::first()->error);
    }

    public function test_daily_limit_stops_calls(): void
    {
        $this->keys(['ai_daily_limit' => '2']);
        AiLog::insert(array_fill(0, 2, ['feature' => 'chat', 'provider' => 'gemini', 'status' => 'ok', 'created_at' => now()]));
        Http::fake();

        try {
            app(AiClient::class)->text([['role' => 'user', 'content' => 'hi']], 'test');
            $this->fail('Expected the limit');
        } catch (AiException $e) {
            $this->assertSame('limit', $e->reason);
        }
        Http::assertNothingSent();
    }

    public function test_settings_test_button(): void
    {
        $this->keys();
        Http::fake([self::GEMINI => Http::response(self::reply('OK'))]);
        $this->actingAs($this->admin)->postJson('/admin/settings/test-ai', ['provider' => 'gemini'])->assertOk()->assertJsonPath('ok', true);
        $this->actingAs($this->admin)->postJson('/admin/settings/test-ai', ['provider' => 'openrouter'])->assertStatus(422);

        // Keys are stored encrypted and never sent back to the browser.
        $this->assertNotSame('G-KEY', \DB::table('settings')->where('key', 'ai_gemini_key')->value('value'));
        $this->actingAs($this->admin)->get('/admin/settings')->assertInertia(fn (Assert $p) => $p->where('values.ai_gemini_key', '')->where('secretsSet.ai_gemini_key', true)->where('ai.ready', true));
    }

    // ── Writer ───────────────────────────────────────────────────────

    public function test_write_with_ai_fills_the_form_with_clean_text(): void
    {
        $this->keys();
        Http::fake([self::GEMINI => Http::response(self::reply(self::writerJson()))]);

        $fields = $this->actingAs($this->admin)->postJson("/admin/products/{$this->product->id}/ai-write")->assertOk()->json('fields');
        $this->assertSame('<p>A fast <strong>gaming</strong> laptop.</p>alert(1)<ul><li>RTX 4060</li></ul>', $fields['description']);
        $this->assertSame('16GB RAM', $fields['key_features'][2]);
        $this->assertSame(['RTX 4060 8GB গ্রাফিক্স'], $fields['key_features_bn']);
        Http::assertSent(fn (Request $r) => str_contains(json_encode($r['messages']), 'RTX 4060 8GB') && str_contains(json_encode($r['messages']), 'Asus'));
        $this->assertNull($this->product->fresh()->description); // nothing saved until staff save the form

        Setting::put(['ai_gemini_key' => '', 'ai_groq_key' => '']);
        $this->actingAs($this->admin)->postJson("/admin/products/{$this->product->id}/ai-write")->assertStatus(409);
    }

    public function test_drafts_are_queued_reviewed_and_published(): void
    {
        $this->keys();
        Http::fake([self::GEMINI => Http::response(self::reply(self::writerJson()))]);
        Queue::fake();

        $this->actingAs($this->admin)->post('/admin/ai-writer/queue', ['count' => 5])->assertSessionHas('success');
        Queue::assertPushed(WriteProductDraft::class, 1);

        // The job writes a pending draft; the product is unchanged until approved.
        (new WriteProductDraft($this->product))->handle(app(ProductWriter::class));
        $draft = AiDraft::firstOrFail();
        $this->assertSame('pending', $draft->status);
        $this->assertNull($this->product->fresh()->description);

        $this->actingAs($this->admin)->get('/admin/ai-writer')->assertInertia(fn (Assert $p) => $p->component('Admin/AiWriter')->has('drafts.data', 1)->where('needing', 0));

        // Staff can edit before publishing.
        $fields = $draft->fields;
        $fields['meta_title'] = 'Edited title';
        $this->actingAs($this->admin)->post("/admin/ai-writer/{$draft->id}/approve", ['fields' => $fields])->assertSessionHas('success');
        $p = $this->product->fresh();
        $this->assertSame('Edited title', $p->meta_title);
        $this->assertSame('<p>একটি দ্রুত গেমিং ল্যাপটপ।</p>', $p->description_bn);
        $this->assertSame('approved', $draft->fresh()->status);

        // The Bangla storefront shows the Bangla text.
        auth()->logout();
        $this->get('/locale/bn');
        $this->get('/product/asus-tuf-f15')->assertInertia(fn (Assert $page) => $page->where('product.description', '<p>একটি দ্রুত গেমিং ল্যাপটপ।</p>')->missing('product.description_bn'));
    }

    public function test_failed_draft_is_recorded(): void
    {
        $this->keys(['ai_fallback' => '']);
        Http::fake([self::GEMINI => Http::response(self::reply('Sorry, I cannot help with that.'))]);
        $draft = app(ProductWriter::class)->draft($this->product);
        $this->assertSame('failed', $draft->status);
        $this->assertStringContainsString('format', $draft->error);
    }

    public function test_scheduled_writer_only_runs_when_turned_on(): void
    {
        $this->keys();
        Queue::fake();
        $this->artisan('ai:write-drafts')->assertSuccessful();
        Queue::assertNothingPushed();

        Setting::put(['ai_writer_auto' => '1', 'ai_writer_per_hour' => '3']);
        $this->artisan('ai:write-drafts')->assertSuccessful();
        Queue::assertPushed(WriteProductDraft::class, 1);
    }

    public function test_catalog_permission_is_needed(): void
    {
        $orders = User::factory()->create(['is_admin' => true, 'role' => 'orders']);
        $this->actingAs($orders)->get('/admin/ai-writer')->assertForbidden();
        $this->actingAs($orders)->postJson("/admin/products/{$this->product->id}/ai-write")->assertForbidden();
    }

    // ── Shopping assistant ───────────────────────────────────────────

    public function test_assistant_is_hidden_until_turned_on(): void
    {
        $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'hi']]])->assertNotFound();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('assistant', null));

        $this->keys(['ai_chat_enabled' => '1']);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('assistant.name', Setting::get('site_name').' Assistant'));
    }

    public function test_assistant_searches_the_catalog_and_shows_real_products(): void
    {
        $this->keys(['ai_chat_enabled' => '1']);
        Http::fakeSequence(self::GEMINI)
            ->push(self::reply(null, [self::toolCall('search_products', ['query' => 'gaming laptop', 'max_price' => 150000])]))
            ->push(self::reply("The Asus TUF F15 has an RTX 4060 for ৳125,000.\nPRODUCTS: asus-tuf-f15, made-up-laptop"));

        $res = $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'gaming laptop under 1.5 lakh?']]])->assertOk();
        $res->assertJsonPath('reply', 'The Asus TUF F15 has an RTX 4060 for ৳125,000.')
            ->assertJsonCount(1, 'products')->assertJsonPath('products.0.slug', 'asus-tuf-f15')->assertJsonPath('products.0.price', 125000);

        // The second call carried our search results back to the AI.
        Http::assertSent(fn (Request $r) => collect($r['messages'])->contains(fn ($m) => $m['role'] === 'tool' && str_contains($m['content'], 'asus-tuf-f15') && str_contains($m['content'], '125000')));
        Http::assertSent(fn (Request $r) => collect($r['tools'] ?? [])->pluck('function.name')->all() === ['search_products', 'get_product', 'track_order']);
    }

    public function test_order_lookup_needs_the_matching_phone(): void
    {
        $order = Order::create(['order_number' => '26112500001', 'first_name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1, Road 2', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'shipped', 'subtotal' => 100, 'delivery_charge' => 0, 'discount' => 0, 'total' => 100]);
        $tools = app(ShopAssistant::class);

        $this->assertSame('shipped', $tools->runTool('track_order', ['order_number' => '26112500001', 'phone' => '+880 1712-345678'])['status']);
        $wrong = $tools->runTool('track_order', ['order_number' => '26112500001', 'phone' => '01800000000']);
        $this->assertArrayHasKey('error', $wrong);
        $this->assertSame($wrong, $tools->runTool('track_order', ['order_number' => 'nope', 'phone' => '01712345678']));
        // No address or name goes to the AI.
        $this->assertStringNotContainsString('Road 2', json_encode($tools->runTool('track_order', ['order_number' => $order->order_number, 'phone' => '01712345678'])));
    }

    public function test_assistant_answers_politely_when_the_ai_fails(): void
    {
        $this->keys(['ai_chat_enabled' => '1']);
        Http::fake([self::GEMINI => Http::response([], 500), self::GROQ => Http::response([], 503)]);
        $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'hi']]])->assertOk()->assertJsonPath('error', true);
        $this->postJson('/assistant', ['messages' => [['role' => 'assistant', 'content' => 'hi']]])->assertStatus(422);
    }

    // ── Reply drafts ─────────────────────────────────────────────────

    public function test_reply_drafts_for_questions_reviews_and_messages(): void
    {
        $this->keys();
        $customer = User::factory()->create();
        $question = Question::create(['product_id' => $this->product->id, 'user_id' => $customer->id, 'question' => 'Does it have RTX 4060?']);
        $review = Review::create(['product_id' => $this->product->id, 'user_id' => $customer->id, 'rating' => 2, 'comment' => 'Fan is loud', 'is_approved' => true]);
        $message = ContactMessage::create(['name' => 'Karim Ahmed', 'email' => 'karim@example.com', 'phone' => '01799999999', 'subject' => 'Delivery', 'message' => 'How long to Sylhet?']);
        Http::fakeSequence(self::GEMINI)
            ->push(self::reply('Yes, it has an RTX 4060 8GB.'))
            ->push(self::reply('"Sorry about the noise. Please call us."'))
            ->push(self::reply("Hi {name},\nDelivery to Sylhet takes 2-3 days.\nThanks,\nTechNest Team"));

        $this->actingAs($this->admin)->postJson("/admin/ai/reply/question/{$question->id}")->assertOk()->assertJsonPath('reply', 'Yes, it has an RTX 4060 8GB.');
        $this->actingAs($this->admin)->postJson("/admin/ai/reply/review/{$review->id}")->assertOk()->assertJsonPath('reply', 'Sorry about the noise. Please call us.');
        $this->actingAs($this->admin)->postJson("/admin/ai/reply/message/{$message->id}")->assertOk()->assertJsonPath('reply', "Hi Karim,\nDelivery to Sylhet takes 2-3 days.\nThanks,\nTechNest Team");

        // The question draft used the product's specs; no customer contact details went to the AI.
        Http::assertSent(fn (Request $r) => str_contains(json_encode($r['messages']), 'RTX 4060 8GB') && str_contains(json_encode($r['messages']), 'Does it have RTX 4060'));
        Http::assertNotSent(fn (Request $r) => str_contains(json_encode($r['messages']), 'karim@example.com') || str_contains(json_encode($r['messages']), '01799999999') || str_contains(json_encode($r['messages']), 'Karim'));
        $this->actingAs($this->admin)->postJson('/admin/ai/reply/order/1')->assertNotFound();
    }

    public function test_review_reply_shows_on_the_product_page(): void
    {
        $review = Review::create(['product_id' => $this->product->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'comment' => 'Great', 'is_approved' => true]);
        $this->actingAs($this->admin)->put("/admin/reviews/{$review->id}/reply", ['reply' => 'Thank you!'])->assertSessionHas('success');
        auth()->logout();
        $this->get('/product/asus-tuf-f15')->assertInertia(fn (Assert $p) => $p->where('reviews.0.reply', 'Thank you!'));

        $this->actingAs($this->admin)->put("/admin/reviews/{$review->id}/reply", ['reply' => ''])->assertSessionHas('success');
        $this->assertNull($review->fresh()->reply);
    }

    public function test_message_reply_is_emailed(): void
    {
        Mail::fake();
        $message = ContactMessage::create(['name' => 'Karim', 'email' => 'karim@example.com', 'subject' => 'Delivery', 'message' => 'How long?']);
        $this->actingAs($this->admin)->post("/admin/messages/{$message->id}/reply", ['reply' => 'Hi Karim, 2-3 days.'])->assertSessionHas('success');
        $message->refresh();
        $this->assertSame(['Hi Karim, 2-3 days.', true, $this->admin->id], [$message->reply, $message->is_read, $message->replied_by]);
    }

    public function test_engagement_permission_is_needed_for_reply_drafts(): void
    {
        $catalog = User::factory()->create(['is_admin' => true, 'role' => 'catalog']);
        $q = Question::create(['product_id' => $this->product->id, 'user_id' => $catalog->id, 'question' => 'x?']);
        $this->actingAs($catalog)->postJson("/admin/ai/reply/question/{$q->id}")->assertForbidden();
    }
}
