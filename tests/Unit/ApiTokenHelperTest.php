<?php

namespace Tests\Unit;

use App\Helpers\ApiTokenHelper;
use Carbon\Carbon;
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

    public function test_malformed_and_tampered_tokens_are_invalid_without_throwing(): void
    {
        config(['api.secret_key' => 'configured-secret-value']);

        $this->assertFalse(ApiTokenHelper::validation('not-base64!'));
        $this->assertFalse(ApiTokenHelper::validation(base64_encode('missing-separator')));
        $this->assertFalse(ApiTokenHelper::validation(base64_encode('invalid.invalid-signature')));
    }

    public function test_expired_token_is_invalid(): void
    {
        config([
            'api.secret_key' => 'configured-secret-value',
            'api.token_expire_hours' => 1,
        ]);

        Carbon::setTestNow('2026-01-01 00:00:00');
        $token = ApiTokenHelper::generate();
        Carbon::setTestNow('2026-01-01 02:00:00');

        $this->assertFalse(ApiTokenHelper::validation($token));

        Carbon::setTestNow();
    }
}
