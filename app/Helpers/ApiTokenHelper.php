<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class ApiTokenHelper
{
    public static function generate(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = bin2hex(random_bytes(8));

        $payload = "$timestamp|$random";

        $encrypted = Crypt::encryptString($payload);

        $signature = hash_hmac('sha256', $encrypted, self::secret());

        return base64_encode($encrypted.'.'.$signature);
    }

    public static function validation(string $header): bool
    {
        $expiryHours = self::expireHours();
        $decoded = base64_decode($header);
        [$encrypted, $signature] = explode('.', $decoded);

        $expectedSignature = hash_hmac('sha256', $encrypted, self::secret());
        throw_if(! hash_equals($expectedSignature, $signature), new \Exception(__('exceptions.invalid_api_token_signature')));

        $decrypted = Crypt::decryptString($encrypted);
        [$timestamp, $random] = explode('|', $decrypted);
        $tokenDate = Carbon::createFromFormat('YmdHis', $timestamp);
        $expiryTime = $tokenDate->clone()->addHours($expiryHours);
        throw_if(now()->greaterThan($expiryTime), new \Exception(__('exceptions.api_token_expired')));

        return true;
    }

    private static function secret(): string
    {
        $secret = config('api.secret_key');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('API secret key is not configured.');
        }

        return $secret;
    }

    private static function expireHours(): int
    {
        return (int) config('api.token_expire_hours', 1);
    }
}
