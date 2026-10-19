<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = $this->route('user') ?? $this->route('idOrSlug');
        $user = $this->user();
        $canManageUser = $userId
            ? $user?->can('update users')
            : $user?->can('create users');

        if (! $canManageUser || ! $this->has('roles')) {
            return (bool) $canManageUser;
        }

        $roles = $this->input('roles');
        if (! is_array($roles)) {
            return true;
        }

        $target = $userId ? User::withTrashed()->find($userId) : null;
        $roleNames = array_values(array_filter($roles, fn (mixed $role): bool => is_string($role)));

        return Gate::forUser($user)->allows('assignRoles', [User::class, $target, $roleNames]);
    }

    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('idOrSlug');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'username' => [
                'nullable', 'string', 'max:255',
                Rule::unique(User::class, 'username')->ignore($userId),
            ],
            'is_active' => 'sometimes|boolean',
            'roles' => 'nullable|array',
            'roles.*' => Rule::exists(Role::class, 'name')->where('guard_name', 'api'),

            // Nested UserDetail Rules
            'details' => 'nullable|array',
            'details.phone_number' => 'nullable|string|max:20',
            'details.address' => 'nullable|string',
            'details.identity_number' => 'nullable|string|max:50',
            'details.transaction_pin' => 'nullable|string|max:255',
        ];

        if (! $userId) {
            $rules['password'] = 'required|string|min:8';
        } else {
            $rules['password'] = 'nullable|string|min:8';
        }

        return $rules;
    }
}
