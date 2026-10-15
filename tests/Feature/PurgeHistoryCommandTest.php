<?php

namespace Tests\Feature;

use App\Services\ExampleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The purge procedure commits, so this test cannot run inside a wrapping transaction.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    protected function tearDown(): void
    {
        DB::table('tb_example_hist')->delete();
        DB::table('tb_examples')->delete();

        parent::tearDown();
    }

    public function test_purge_command_deletes_history_older_than_the_cutoff(): void
    {
        $service = app(ExampleService::class);
        $old = $service->create($this->exampleAttributes('old'));
        $service->update(['string_column' => 'old-change'], $old->getKey());
        DB::table('tb_example_hist')->where('example_id', $old->getKey())->update([
            'created_at' => now()->subDays(400),
        ]);

        $recent = $service->create($this->exampleAttributes('recent'));

        $this->artisan('history:purge', ['--days' => 365, '--batch' => 1])
            ->assertSuccessful();

        $this->assertSame(0, DB::table('tb_example_hist')->where('example_id', $old->getKey())->count());
        $this->assertSame(1, DB::table('tb_example_hist')->where('example_id', $recent->getKey())->count());
    }

    public function test_purge_command_rejects_a_zero_day_cutoff(): void
    {
        $this->artisan('history:purge', ['--days' => 0])->assertFailed();
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleAttributes(string $stringColumn): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            'string_column' => $stringColumn,
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
