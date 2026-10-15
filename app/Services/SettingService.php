<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SettingService extends BaseService
{
    public function __construct(Setting $setting)
    {
        parent::__construct($setting);
    }

    /**
     * Get all settings from cache or database as a key-value array.
     */
    public function allSettings(): array
    {
        return Cache::rememberForever('global_settings', function () {
            return $this->model->pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get a specific setting by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->allSettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Update or create a setting.
     */
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $this->assertAllowed($key);

        $this->model->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
        Cache::forget('global_settings');
    }

    /**
     * Bulk update multiple settings.
     */
    public function setMany(array $settings): void
    {
        foreach (array_keys($settings) as $key) {
            $this->assertAllowed((string) $key);
        }

        foreach ($settings as $key => $value) {
            $this->model->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        Cache::forget('global_settings');
    }

    private function assertAllowed(string $key): void
    {
        if (! array_key_exists($key, config('settings.allowed', []))) {
            throw ValidationException::withMessages([
                'settings.'.$key => __('validation.in', ['attribute' => 'settings.'.$key]),
            ]);
        }
    }
}
