<?php

namespace Tests\Feature;

use App\Enums\QueryAcceptedComparatorEnum;
use App\Services\ExampleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Tests\TestCase;

class BaseServicePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_uses_only_the_services_searchable_columns(): void
    {
        $service = app(ExampleService::class);
        $service->create($this->exampleAttributes('ordinary text', 987654321));
        $expected = $service->create($this->exampleAttributes('find 987654321 here', 1));

        $results = $service->findByIndexes(
            ['search' => '987654321'],
            false,
            10,
            [],
            QueryAcceptedComparatorEnum::ILIKE,
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertSame(1, $results->total());
        $this->assertSame($expected->getKey(), $results->first()->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleAttributes(string $stringColumn, int $integerColumn): array
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
            'integer_column' => $integerColumn,
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
