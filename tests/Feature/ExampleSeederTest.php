<?php

namespace Tests\Feature;

use App\Models\Example;
use Database\Seeders\ExampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_example_seeder_creates_one_sample_row(): void
    {
        $this->seed(ExampleSeeder::class);
        $this->seed(ExampleSeeder::class);

        $this->assertSame(1, Example::query()->count());
        $this->assertDatabaseHas('tb_examples', [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'string_column' => 'Sample example',
            'enum_column' => 'active',
        ]);
        $this->assertDatabaseHas('tb_example_hist', [
            'action' => 'created',
        ]);
        $this->assertSame(1, DB::table('tb_example_hist')->where('example_id', Example::query()->value('id'))->count());
    }
}
