<?php

namespace Database\Seeders;

use App\Models\Example;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExampleSeeder extends Seeder
{
    /**
     * Seed one sample row in tb_examples.
     */
    public function run(): void
    {
        Example::query()->firstOrCreate(
            ['uuid' => '00000000-0000-4000-8000-000000000001'],
            [
                'ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'string_column' => 'Sample example',
                'string_with_length' => 'sample',
                'char_column' => 'SAMP',
                'text_column' => 'One sample row for tb_examples.',
                'medium_text_column' => 'Sample medium text.',
                'long_text_column' => 'Sample long text.',
                'integer_column' => 1,
                'big_integer_column' => 1,
                'medium_integer_column' => 1,
                'small_integer_column' => 1,
                'tiny_integer_column' => 1,
                'unsigned_integer_column' => 1,
                'decimal_column' => 10.50,
                'double_column' => 1.5,
                'float_column' => 1.5,
                'boolean_column' => true,
                'date_column' => '2026-01-01',
                'datetime_column' => '2026-01-01 00:00:00',
                'datetime_tz_column' => '2026-01-01 00:00:00',
                'time_column' => '00:00:00',
                'time_tz_column' => '00:00:00',
                'year_column' => 2026,
                'json_column' => json_encode(['label' => 'sample']),
                'jsonb_column' => json_encode(['label' => 'sample']),
                'enum_column' => 'active',
                'set_column' => 'one',
                'ip_address_column' => '127.0.0.1',
                'mac_address_column' => '00:11:22:33:44:55',
                'user_id' => User::query()->where('email', 'admin@example.com')->value('id'),
            ],
        );
    }
}
