<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Role::class, 'name')->ignore($id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => Rule::exists(Permission::class, 'id'),
        ];
    }
}
