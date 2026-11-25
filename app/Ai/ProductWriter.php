<?php

namespace App\Ai;

use App\Models\AiDraft;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Writes product texts (description, key features, SEO title and meta description,
 * and Bangla versions) from the product's own specs. Facts come only from the specs
 * we send; staff review everything before it goes live.
 */
class ProductWriter
{
    public const FIELDS = ['key_features', 'description', 'meta_title', 'meta_description', 'key_features_bn', 'description_bn'];

    public function __construct(protected AiClient $ai) {}

    public function write(Product $product): array
    {
        $product->loadMissing(['brand:id,name', 'category:id,name', 'specs']);
        $specs = $product->specs->groupBy('group')
            ->map(fn ($rows, $group) => "{$group}:\n".$rows->map(fn ($s) => "- {$s->name}: {$s->value}")->implode("\n"))
            ->implode("\n");
        $facts = collect([
            'Name' => $product->name,
            'Brand' => $product->brand?->name,
            'Category' => $product->category?->name,
            'Model' => $product->model,
            'Product code' => $product->sku,
            'Price' => $product->basePrice() ? 'Tk '.number_format($product->basePrice()) : null,
            'Warranty' => $product->warranty_months ? "{$product->warranty_months} months" : null,
            'Current key features' => $product->key_features ? implode('; ', $product->key_features) : null,
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        $site = Setting::get('site_name');
        $system = <<<TXT
        You write product content for {$site}, a computer and gadget shop in Bangladesh.
        Use ONLY the facts given. Never invent specifications, prices, offers, warranty terms or awards.
        If a fact is missing, leave it out. Write clear, friendly, useful English for Bangladeshi shoppers, no hype.
        Reply with one JSON object only, no other text.
        TXT;
        $user = <<<TXT
        Product facts:
        {$facts}

        Specifications:
        {$specs}

        Return JSON with exactly these keys:
        "key_features": 4 to 6 short bullet points (max 80 characters each) with the most important specs,
        "description": an HTML description of 120-250 words using only <p>, <ul>, <li>, <strong> and <h3>: what it is, who it suits, the main features, and what is in the specs,
        "meta_title": an SEO page title, max 60 characters, with the product name,
        "meta_description": an SEO description, 120-155 characters,
        "key_features_bn": the key features in natural Bangla (keep model names, numbers and units in English),
        "description_bn": the description in natural Bangla, same HTML rules.
        TXT;

        $data = $this->ai->json([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], 'writer', 3000);

        return $this->clean($data);
    }

    /** Write a draft for staff to review. Returns the draft (with an error if it failed). */
    public function draft(Product $product): AiDraft
    {
        $draft = AiDraft::firstOrNew(['product_id' => $product->id, 'status' => 'pending']);
        try {
            $draft->fill(['fields' => $this->write($product), 'error' => null]);
        } catch (AiException $e) {
            $draft->fill(['status' => $draft->fields ? 'pending' : 'failed', 'error' => $e->getMessage()]);
        }
        $draft->save();

        return $draft;
    }

    /** Put an approved draft on the product. */
    public function apply(AiDraft $draft, int $userId, ?array $fields = null): void
    {
        $values = collect($fields ?? $draft->fields)->only(self::FIELDS)->all();
        $draft->product->update($this->clean($values));
        $draft->update(['status' => 'approved', 'reviewed_by' => $userId, 'fields' => $this->clean($values)]);
    }

    public function clean(array $data): array
    {
        $list = fn ($v) => collect(is_array($v) ? $v : preg_split('/\r?\n/', (string) $v))
            ->map(fn ($f) => Str::limit(trim(strip_tags((string) $f), " \t-•*"), 250, ''))->filter()->take(8)->values()->all();
        $html = fn ($v) => self::sanitizeHtml((string) $v);

        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = match ($k) {
                'key_features', 'key_features_bn' => $list($v),
                'description', 'description_bn' => $html($v),
                'meta_title' => Str::limit(trim(strip_tags((string) $v)), 70, ''),
                'meta_description' => Str::limit(trim(strip_tags((string) $v)), 300, ''),
                default => null,
            };
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    /** Keep only simple formatting tags, with no attributes. */
    public static function sanitizeHtml(string $html): string
    {
        $html = strip_tags($html, '<p><ul><ol><li><strong><b><em><h3><h4><br>');
        $html = preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $html);

        return trim($html);
    }
}
