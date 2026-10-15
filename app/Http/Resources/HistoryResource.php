<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class HistoryResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = (array) $this->resource;

        $payload = [
            'id' => $row['id'],
            'action' => $row['action'],
            'changes' => $this->jsonColumn($row['changes'] ?? null),
            'created_at' => $row['created_at'],
        ];

        if (array_key_exists('snapshot', $row)) {
            $payload['snapshot'] = $this->jsonColumn($row['snapshot']);
        }

        return $payload;
    }

    private function jsonColumn(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value);
    }
}
