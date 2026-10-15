<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = config('settings.allowed', []);
            foreach (array_keys($this->input('settings', [])) as $key) {
                if (! is_string($key) || ! is_array($allowed) || ! array_key_exists($key, $allowed)) {
                    $validator->errors()->add(
                        'settings.'.$key,
                        __('validation.in', ['attribute' => 'settings.'.$key]),
                    );
                }
            }
        });
    }
}
