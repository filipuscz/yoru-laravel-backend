<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'me_user_details';

    protected $fillable = [
        'user_id',
        'transaction_pin',
        'phone_number',
        'identity_number',
        'address',
        'last_transaction_ip',
        'last_transaction_at',
        'device_fingerprint',
        'security_log',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'transaction_pin',
    ];

    protected function casts(): array
    {
        return [
            'transaction_pin' => 'hashed',
            'last_transaction_at' => 'datetime',
            'security_log' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
