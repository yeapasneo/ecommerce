<?php

namespace App\Ai;

use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Drafts replies to product questions, reviews and contact messages for staff
 * to check and send. Customer names, emails and phone numbers are not sent to the AI.
 */
class ReplyWriter
{
    public function __construct(protected AiClient $ai) {}

    public function question(Question $question): string
    {
        $product = $question->product()->with(['brand:id,name', 'specs'])->first();
        $earlier = Question::where('product_id', $question->product_id)->whereKeyNot($question->id)->whereNotNull('answer')
            ->latest('answered_at')->take(3)->get()->map(fn ($q) => "Q: {$q->question}\nA: {$q->answer}")->implode("\n\n");

        return $this->ask('question', <<<TXT
        A shopper asked a question on a product page. Write the shop's public answer.
        Answer only from the product facts below. If they don't answer the question, say we will check and reply soon, or that the shopper can call {$this->phone()}.
        Keep it short (1-3 sentences), friendly and plain text. Reply in the language of the question (Bangla if it is written in Bangla or Banglish).

        {$this->productFacts($product)}
        {$this->labelled('Earlier answers on this product (for tone)', $earlier)}
        Question: {$question->question}
        TXT, 1500);
    }

    public function review(Review $review): string
    {
        $product = $review->product()->first(['id', 'name']);

        return $this->ask('review', <<<TXT
        Write the shop's short public reply to this product review (1-3 sentences, plain text, no greeting by name).
        Thank good reviews warmly. For a complaint, apologise, don't argue, don't promise refunds or replacements,
        and invite the customer to call {$this->phone()} so we can help. Reply in the review's language.

        Product: {$product?->name}
        Rating: {$review->rating} out of 5
        Review: {$review->comment}
        TXT, 1500);
    }

    /** Email reply. Starts with "Hi {name}," which is filled in here, not by the AI. */
    public function message(ContactMessage $message): string
    {
        $s = fn ($k) => Setting::get($k);
        $text = $this->ask('message', <<<TXT
        Write the shop's email reply to a customer's message from the contact form.
        Start with "Hi {name}," on its own line and end with "Thanks,\n{$s('site_name')} Team".
        Be helpful and brief (under 150 words), plain text. Use only the shop facts below; if you need details you don't have
        (an order number, a photo, a product link), ask for them. Never promise refunds, discounts or dates.
        Reply in the language of the message.

        Shop facts:
        - Hotline: {$this->phone()}. Email: {$s('email')}. Address: {$s('address')}.
        - Delivery: Tk {$s('delivery_charge_inside')} inside Dhaka, Tk {$s('delivery_charge_outside')} outside Dhaka.
        - Order tracking page: {$this->url('/track-order')}

        Subject: {$message->subject}
        Message: {$message->message}
        TXT, 2000);

        $first = Str::of((string) $message->name)->trim()->before(' ')->value() ?: 'there';

        return str_replace('{name}', $first, $text);
    }

    protected function ask(string $feature, string $prompt, int $maxTokens): string
    {
        $system = 'You write customer replies for '.Setting::get('site_name').', a computer and gadget shop in Bangladesh. '
            .'Be accurate: never invent specifications, prices, stock, policies or promises. Reply with the text only.';
        $text = $this->ai->text([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]], "reply_{$feature}", $maxTokens, 0.4);
        $text = trim(preg_replace('/^```\w*\s*|\s*```$/', '', $text), " \n\"");
        if ($text === '') {
            throw new AiException('The AI returned an empty reply. Try again.', 'format');
        }

        return Str::limit($text, 2000, '');
    }

    protected function productFacts(?Product $product): string
    {
        if (! $product) {
            return 'Product: (deleted)';
        }
        $specs = $product->specs->take(40)->map(fn ($s) => "- {$s->name}: {$s->value}")->implode("\n");
        $features = implode('; ', $product->key_features ?? []);

        return "Product: {$product->name}\nBrand: {$product->brand?->name}\nPrice: Tk ".number_format($product->basePrice())
            ."\nStock: {$product->stock_label}\nWarranty: ".($product->warranty_months ? "{$product->warranty_months} months" : 'not listed')
            .($features ? "\nKey features: {$features}" : '')."\nSpecifications:\n{$specs}";
    }

    protected function labelled(string $label, string $text): string
    {
        return $text === '' ? '' : "{$label}:\n{$text}\n";
    }

    protected function phone(): string
    {
        return (string) Setting::get('phone');
    }

    protected function url(string $path): string
    {
        return url($path);
    }
}
