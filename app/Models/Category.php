<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'slug', 'icon', 'description', 'filter_attributes', 'sort_order', 'is_active', 'is_featured'];

    protected $casts = [
        'filter_attributes' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** IDs of this category and all of its descendants. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** Ancestors from root to this category (inclusive). */
    public function breadcrumbs(): array
    {
        $trail = [];
        $node = $this;
        while ($node) {
            array_unshift($trail, ['name' => $node->name, 'slug' => $node->slug]);
            $node = $node->parent;
        }

        return $trail;
    }

    /** Filter attributes, inherited from the nearest ancestor that defines them. */
    public function effectiveFilterAttributes(): array
    {
        $node = $this;
        while ($node) {
            if (! empty($node->filter_attributes)) {
                return $node->filter_attributes;
            }
            $node = $node->parent;
        }

        return [];
    }

    public static function menuTree(): array
    {
        $all = static::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name', 'slug', 'icon']);
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ?? 0);
        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'children' => $build($c->id),
            ])->values()->all();
        };

        return $build(0);
    }
}
