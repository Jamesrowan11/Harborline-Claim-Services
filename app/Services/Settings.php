<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * DB-backed, cached settings. Falls back to config() defaults so the
 * application works before any admin customization. Admin-editable values
 * (branding, case-number format, consent text, fee settings…) all live here.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        $all = Cache::remember('settings.all', 300, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'updated_by' => Auth::id()],
        );
        Cache::forget('settings.all');
    }

    /** Brand values: DB settings override config/branding.php which reads env. */
    public static function brand(string $key, mixed $default = null): mixed
    {
        return static::get("brand.$key", config("branding.$key", $default));
    }
}
