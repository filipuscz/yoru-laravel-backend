<?php

namespace Tests\Feature;

use App\Services\ExampleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleHistTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgres_trigger_writes_example_history(): void
    {
        $service = app(ExampleService::class);
        $example = $service->create($this->exampleAttributes());

        $this->assertDatabaseHas('tb_example_hist', [
            'example_id' => $example->getKey(),
            'action' => 'created',
        ]);

        $service->update(['string_column' => 'changed'], $example->getKey());

        $updated = DB::table('tb_example_hist')
            ->where('example_id', $example->getKey())
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($updated);
        $snapshot = json_decode($updated->snapshot, true);
        $changes = json_decode($updated->changes, true);
        $this->assertSame('changed', $snapshot['string_column']);
        $this->assertSame('changed', $changes['string_column']);

        $service->delete($example->getKey());

        $this->assertDatabaseHas('tb_example_hist', [
            'example_id' => $example->getKey(),
            'action' => 'deleted',
        ]);
        $this->assertSame(3, DB::table('tb_example_hist')->where('example_id', $example->getKey())->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleAttributes(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            'string_column' => 'original',
            'string_with_length' => 'length',
            'char_column' => 'ABCD',
            'text_column' => 'text',
            'medium_text_column' => 'medium',
            'long_text_column' => 'long',
            'integer_column' => 1,
            'big_integer_column' => 1,
            'medium_integer_column' => 1,
            'small_integer_column' => 1,
            'tiny_integer_column' => 1,
            'unsigned_integer_column' => 1,
            'decimal_column' => 10.50,
            'double_column' => 1.5,
            'float_column' => 1.5,
            'boolean_column' => false,
            'date_column' => '2026-01-01',
            'datetime_column' => '2026-01-01 00:00:00',
            'datetime_tz_column' => '2026-01-01 00:00:00',
            'time_column' => '00:00:00',
            'time_tz_column' => '00:00:00',
            'year_column' => 2026,
            'enum_column' => 'active',
            'set_column' => 'one',
        ];
    }
}
