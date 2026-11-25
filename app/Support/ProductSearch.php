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

    /** Name, SKU and model in one string; the products_search_trgm index covers exactly this expression. */
    public const TEXT = "lower(products.name || ' ' || products.sku || ' ' || coalesce(products.model, ''))";

    /**
     * Every word must appear in the name, SKU or model ("logitec mouse" matches
     * "Logitech G102 ... Mouse"). Only when nothing matches that way are typos
     * forgiven ("smasung galaxy"), so normal searches stay fast and precise.
     */
    public static function apply(Builder $query, string $term): Builder
    {
        $term = self::normalize($term);
        if ($term === '') {
            return $query;
        }
        $words = array_slice(explode(' ', $term), 0, 8);
        $exact = function ($q) use ($words) {
            foreach ($words as $word) {
                $q->whereRaw(self::TEXT.' like ?', ['%'.addcslashes($word, '%_\\').'%']);
            }
        };

        if (DB::getDriverName() !== 'pgsql' || (clone $query)->where($exact)->exists()) {
            return $query->where($exact);
        }

        // No exact match: forgive typos (uses the trigram index on the name).
        DB::statement('SET pg_trgm.word_similarity_threshold = '.self::THRESHOLD);

        return $query->whereRaw('? <% lower(products.name)', [$term]);
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
