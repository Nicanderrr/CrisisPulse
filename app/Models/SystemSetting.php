<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function valueFor(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function logoUrl(): ?string
    {
        $path = static::valueFor('brand_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function loginMediaUrl(): ?string
    {
        $path = static::valueFor('login_media_path');

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
