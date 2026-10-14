<?php

namespace Tests\Unit;

use App\Helpers\ApiTokenHelper;
use Tests\TestCase;

class ApiTokenHelperTest extends TestCase
{
    public function test_token_signature_uses_the_configured_secret(): void
    {
        config([
            'api.secret_key' => 'configured-secret-value',
            'api.token_expire_hours' => 1,
        ]);

        $token = ApiTokenHelper::generate();
        $decoded = base64_decode($token, true);
        $this->assertIsString($decoded);

        [$encrypted, $signature] = explode('.', $decoded, 2);
        $expected = hash_hmac('sha256', $encrypted, 'configured-secret-value');

        $this->assertSame($expected, $signature);
        $this->assertTrue(ApiTokenHelper::validation($token));
    }
}
