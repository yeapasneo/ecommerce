<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Product search that tolerates typos ("smasung", "logitec mouse") using PostgreSQL
 * trigram matching. Exact word matches rank first, then the closest spellings.
 */
class ProductSearch
{
    public const THRESHOLD = 0.5;

    public static function normalize(string $term): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $term)));
    }

    public static function apply(Builder $query, string $term): Builder
    {
        $term = self::normalize($term);
        if ($term === '') {
            return $query;
        }
        $words = array_slice(explode(' ', $term), 0, 8);
        $fuzzy = DB::getDriverName() === 'pgsql';
        if ($fuzzy) {
            // Lets "<%" use the trigram index (set for this connection only).
            DB::statement('SET pg_trgm.word_similarity_threshold = '.self::THRESHOLD);
        }

        return $query->where(function ($q) use ($words, $term, $fuzzy) {
            $q->where(function ($all) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $all->where(fn ($w) => $w->whereRaw('lower(products.name) like ?', [$like])
                        ->orWhereRaw('lower(products.sku) like ?', [$like])
                        ->orWhereRaw("lower(coalesce(products.model, '')) like ?", [$like]));
                }
            });
            if ($fuzzy) {
                $q->orWhereRaw('? <% lower(products.name)', [$term]);
            }
        });
    }

    /** Best matches first. */
    public static function rank(Builder $query, string $term): Builder
    {
        $term = self::normalize($term);
        if ($term === '') {
            return $query;
        }
        $query->orderByRaw('(lower(products.name) like ?) desc', ['%'.addcslashes($term, '%_\\').'%']);
        if (DB::getDriverName() === 'pgsql') {
            $query->orderByRaw('word_similarity(?, lower(products.name)) desc', [$term]);
        }

        return $query;
    }
}
