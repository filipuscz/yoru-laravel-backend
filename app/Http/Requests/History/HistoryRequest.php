<?php

namespace App\Http\Requests\History;

use Illuminate\Foundation\Http\FormRequest;

abstract class HistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $source = (string) $this->route('historySource');
        $role = config("history.sources.{$source}.role");
        if (is_string($role) && $role !== '') {
            return $user->hasRole($role, 'api');
        }

        $permission = config("history.sources.{$source}.permission");

        return is_string($permission) && $permission !== '' && $user->can($permission);
    }
}
