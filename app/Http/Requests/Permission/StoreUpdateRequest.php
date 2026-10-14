<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class StoreUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Permission::class, 'name')->ignore($id),
            ],
        ];
    }
}
