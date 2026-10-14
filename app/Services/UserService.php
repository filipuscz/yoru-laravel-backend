<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserService extends BaseService
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = parent::create($data);

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            $details = $this->prepareDetails($data['details'] ?? []);
            $user->detail()->create($details);

            return $user->load(['roles', 'detail']);
        });
    }

    public function update(array $data, mixed $idOrSlug): ?Model
    {
        return DB::transaction(function () use ($data, $idOrSlug) {
            if (empty($data['password'])) {
                unset($data['password']);
            }

            /** @var User|null $user */
            $user = parent::update($data, $idOrSlug);

            if ($user) {
                if (isset($data['roles'])) {
                    $user->syncRoles($data['roles']);
                }

                if (isset($data['details'])) {
                    $user->detail()->updateOrCreate(
                        ['user_id' => $user->id],
                        $this->prepareDetails($data['details'])
                    );
                }

                $user->load(['roles', 'detail']);

                // Clear the cache for this user since we just updated them
                Cache::forget("user_profile_{$user->id}");
            }

            return $user;
        });
    }

    public function findById($idOrSlug): ?Model
    {
        // Remember the user in the database cache for 60 minutes
        return Cache::remember("user_profile_{$idOrSlug}", 3600, function () use ($idOrSlug) {
            $user = parent::findById($idOrSlug);
            if ($user) {
                $user->load(['roles', 'detail']);
            }

            return $user;
        });
    }

    public function delete($idOrSlug): void
    {
        parent::delete($idOrSlug);
        Cache::forget("user_profile_{$idOrSlug}");
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function prepareDetails(array $details): array
    {
        if (array_key_exists('transaction_pin', $details) && ($details['transaction_pin'] === null || $details['transaction_pin'] === '')) {
            $details['transaction_pin'] = null;
        }

        return $details;
    }
}
