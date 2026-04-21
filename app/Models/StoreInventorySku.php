<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StoreInventorySku extends Model
{
    protected $fillable = [
        'prod_cd',
        'is_active',
        'sort_order',
        'memo',
        'image_url',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImageStoragePathAttribute(): string
    {
        return self::normalizeImagePath((string) ($this->attributes['image_url'] ?? ''));
    }

    public function getImagePublicUrlAttribute(): string
    {
        return self::toPublicImageUrl((string) ($this->attributes['image_url'] ?? ''));
    }

    public static function normalizeImagePath(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (! str_contains($raw, '://')) {
            if (str_starts_with($raw, '/storage/')) {
                return ltrim(substr($raw, 9), '/');
            }

            if (str_starts_with($raw, 'storage/')) {
                return ltrim(substr($raw, 8), '/');
            }

            return ltrim($raw, '/');
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '';
        }

        $offset = strpos($path, '/storage/');
        if ($offset === false) {
            return '';
        }

        return ltrim(substr($path, $offset + 9), '/');
    }

    public static function toPublicImageUrl(?string $value): string
    {
        $path = self::normalizeImagePath($value);
        if ($path === '') {
            return '';
        }

        return Storage::disk('public')->url($path);
    }
}
