<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SettingResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $key = (string) ($data['key'] ?? '');
        $meta = config("settings.allowed.{$key}");
        $data['secret'] = is_array($meta) && ($meta['secret'] ?? false) === true;

        if ($data['secret']) {
            unset($data['value']);
        }

        return $data;
    }
}
