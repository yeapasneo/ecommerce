<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Bulk product spreadsheet: export every product (and variant) to CSV, then import
 * the edited file to update prices, cost, stock, status and warranty in one go.
 * Rows are matched by variant_id, product_id or SKU. A blank cell means "no change".
 */
class ProductSheet
{
    public const COLUMNS = ['product_id', 'variant_id', 'sku', 'name', 'variant', 'category', 'brand', 'price', 'regular_price', 'cost_price', 'stock', 'stock_status', 'active', 'warranty_months'];

    /** Columns the import can change. */
    public const EDITABLE = ['name', 'price', 'regular_price', 'cost_price', 'stock', 'stock_status', 'active', 'warranty_months'];

    public function export($out, array $filters = []): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS);
        Product::query()->with(['category:id,name', 'brand:id,name', 'variants'])
            ->when($filters['category_id'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when($filters['brand_id'] ?? null, fn ($q, $b) => $q->where('brand_id', $b))
            ->when(($filters['stock'] ?? null) === 'low', fn ($q) => $q->where('stock', '<=', (int) Setting::get('low_stock_threshold', 3)))
            ->orderBy('id')->chunk(500, function ($products) use ($out) {
                foreach ($products as $p) {
                    $base = [$p->id, '', $p->sku, $p->name, '', $p->category?->name, $p->brand?->name];
                    if ($p->has_variants && $p->variants->isNotEmpty()) {
                        foreach ($p->variants as $v) {
                            fputcsv($out, [$p->id, $v->id, $v->sku ?: $p->sku, $p->name, $v->name, $p->category?->name, $p->brand?->name,
                                $v->price, $v->regular_price, $v->cost_price, $v->stock, '', $v->is_active ? 'yes' : 'no', '']);
                        }

                        continue;
                    }
                    fputcsv($out, [...$base, $p->getRawOriginal('price'), $p->getRawOriginal('regular_price'), $p->cost_price, $p->stock, $p->stock_status, $p->is_active ? 'yes' : 'no', $p->warranty_months]);
                }
            });
    }

    /** Read a CSV or Excel (.xlsx) file into rows keyed by column name. */
    public function read(UploadedFile $file): array
    {
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? $this->readXlsx($file->getRealPath()) : $this->readCsv($file->getRealPath());
        if (count($rows) < 2) {
            throw new RuntimeException('The file has no product rows.');
        }
        $header = array_map(fn ($h) => strtolower(trim(str_replace(["\xEF\xBB\xBF", ' '], ['', '_'], (string) $h))), array_shift($rows));
        if (! array_intersect($header, ['product_id', 'variant_id', 'sku'])) {
            throw new RuntimeException('The first row must have column names, including product_id or sku. Start from an exported file.');
        }

        $out = [];
        foreach ($rows as $i => $row) {
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $out[$i + 2] = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
        }

        return $out;
    }

    /**
     * Work out what would change, without saving.
     *
     * @return array{changes: array, errors: array, unchanged: int}
     */
    public function plan(array $rows): array
    {
        $changes = $errors = [];
        $unchanged = 0;
        $productIds = array_filter(array_map(fn ($r) => (int) ($r['product_id'] ?? 0), $rows));
        $variantIds = array_filter(array_map(fn ($r) => (int) ($r['variant_id'] ?? 0), $rows));
        $skus = array_filter(array_map(fn ($r) => strtolower(trim((string) ($r['sku'] ?? ''))), $rows));
        $products = Product::whereIn('id', $productIds)->orWhereIn(DB::raw('lower(sku)'), $skus ?: [''])->get()->keyBy('id');
        $productsBySku = $products->keyBy(fn ($p) => strtolower($p->sku));
        $variants = ProductVariant::whereIn('id', $variantIds)->orWhereIn(DB::raw('lower(sku)'), $skus ?: [''])->get();
        $variantsById = $variants->keyBy('id');
        $variantsBySku = $variants->filter(fn ($v) => $v->sku)->keyBy(fn ($v) => strtolower($v->sku));

        foreach ($rows as $line => $row) {
            $sku = strtolower(trim((string) ($row['sku'] ?? '')));
            $variant = ($vid = (int) ($row['variant_id'] ?? 0)) ? $variantsById[$vid] ?? null : null;
            $product = null;
            if (! $variant && ! $vid) {
                $product = ($pid = (int) ($row['product_id'] ?? 0)) ? $products[$pid] ?? null : null;
                $product ??= $sku !== '' ? $productsBySku[$sku] ?? null : null;
                $variant = ! $product && $sku !== '' ? $variantsBySku[$sku] ?? null : null;
            }
            $target = $variant ?? $product;
            if (! $target) {
                $errors[] = ['line' => $line, 'message' => 'No product found for '.($vid ? "variant_id {$vid}" : (($row['product_id'] ?? '') ?: 'SKU '.($row['sku'] ?? ''))).'.'];

                continue;
            }
            if ($product && $product->has_variants) {
                $errors[] = ['line' => $line, 'message' => "{$product->name} has variants: change each variant's row instead."];

                continue;
            }

            $fields = [];
            foreach (self::EDITABLE as $col) {
                if (! array_key_exists($col, $row) || trim((string) $row[$col]) === '') {
                    continue; // blank = keep
                }
                if ($variant && in_array($col, ['name', 'stock_status', 'warranty_months'], true)) {
                    continue;
                }
                [$new, $error] = $this->parse($col, trim((string) $row[$col]));
                if ($error) {
                    $errors[] = ['line' => $line, 'message' => "{$col}: {$error}"];

                    continue 2;
                }
                $attr = $col === 'active' ? 'is_active' : $col;
                $old = $attr === 'price' || $attr === 'regular_price' ? $target->getRawOriginal($attr) : $target->{$attr};
                $old = is_bool($old) ? $old : (is_numeric($old) ? (float) $old : $old);
                $same = is_numeric($old) && is_numeric($new) ? abs((float) $old - (float) $new) < 0.005 : $old === $new;
                if (! $same) {
                    $fields[$attr] = [$old, $new];
                }
            }
            if (! $fields) {
                $unchanged++;

                continue;
            }
            $changes[] = [
                'line' => $line,
                'type' => $variant ? 'variant' : 'product',
                'id' => $target->id,
                'product_id' => $variant ? $variant->product_id : $product->id,
                'label' => $variant ? ($variant->product?->name.' ('.$variant->name.')') : $product->name,
                'fields' => $fields,
            ];
        }

        return ['changes' => $changes, 'errors' => $errors, 'unchanged' => $unchanged];
    }

    /** Save a plan. Returns how many records changed. */
    public function apply(array $changes): int
    {
        return DB::transaction(fn () => Activity::quietly(fn () => Stock::as('import', null, function () use ($changes) {
            $touchedParents = [];
            foreach ($changes as $c) {
                $values = array_map(fn ($pair) => $pair[1], $c['fields']);
                if ($c['type'] === 'variant') {
                    ProductVariant::whereKey($c['id'])->first()?->update($values);
                    $touchedParents[$c['product_id']] = true;

                    continue;
                }
                $product = Product::find($c['id']);
                if (! $product) {
                    continue;
                }
                // Stock decides in/out of stock unless the sheet sets the status itself.
                if (isset($values['stock']) && ! isset($values['stock_status']) && in_array($product->stock_status, ['in_stock', 'out_of_stock'], true)) {
                    $values['stock_status'] = $values['stock'] > 0 ? 'in_stock' : 'out_of_stock';
                }
                $product->update($values);
            }
            foreach (array_keys($touchedParents) as $id) {
                Product::find($id)?->syncFromVariants();
            }

            return count($changes);
        }, 'Bulk import')));
    }

    /** @return array{0: mixed, 1: ?string} [value, error] */
    protected function parse(string $col, string $v): array
    {
        $number = fn () => is_numeric($n = str_replace([',', '৳', 'Tk', 'tk', ' '], '', $v)) ? (float) $n : null;

        return match ($col) {
            'name' => [mb_substr($v, 0, 255), null],
            'price', 'cost_price' => ($n = $number()) !== null && $n >= 0 ? [$n, null] : [null, "“{$v}” is not a valid amount"],
            'regular_price' => $v === '-' ? [null, null] : (($n = $number()) !== null && $n >= 0 ? [$n, null] : [null, "“{$v}” is not a valid amount"]),
            'stock', 'warranty_months' => ($n = $number()) !== null && $n >= 0 && floor($n) == $n ? [(int) $n, null] : [null, "“{$v}” must be a whole number"],
            'stock_status' => array_key_exists($s = strtolower(str_replace(' ', '_', $v)), Product::STOCK_STATUSES) ? [$s, null] : [null, 'use one of '.implode(', ', array_keys(Product::STOCK_STATUSES))],
            'active' => in_array($b = strtolower($v), ['yes', 'y', '1', 'true', 'active', 'on'], true) ? [true, null]
                : (in_array($b, ['no', 'n', '0', 'false', 'inactive', 'off', 'hidden'], true) ? [false, null] : [null, 'use yes or no']),
        };
    }

    protected function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $first = fgets($fh) ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($fh);
        $rows = [];
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($fh);

        return $rows;
    }

    /** Minimal .xlsx reader (first sheet, values only); no extra library needed. */
    protected function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Excel file.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach ((new SimpleXMLElement($xml))->si as $si) {
                $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('The Excel file has no worksheet.');
        }
        $rows = [];
        foreach ((new SimpleXMLElement($sheet))->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = 0;
                foreach (str_split(preg_replace('/\d/', '', (string) $c['r'])) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $strings[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => (string) $c->v,
                };
                $cells[$col - 1] = $value;
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $rows[] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
            }
        }

        return $rows;
    }
}
