<?php

namespace App\Services;

use App\Models\Example;

class ExampleService extends BaseService
{
    protected array $searchableColumns = [
        'uuid',
        'ulid',
        'string_column',
        'string_with_length',
        'char_column',
        'text_column',
        'medium_text_column',
        'long_text_column',
        'enum_column',
        'set_column',
        'ip_address_column',
        'mac_address_column',
        'geometry_column',
        'point_column',
        'linestring_column',
        'polygon_column',
    ];

    public function __construct(Example $example)
    {
        parent::__construct($example);
    }
}
