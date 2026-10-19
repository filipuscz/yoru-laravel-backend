<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

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
        $secret = self::secret();
        $expiryHours = self::expireHours();

        try {
            $decoded = base64_decode($header, true);
            if (! is_string($decoded)) {
                return false;
            }

            $signedParts = explode('.', $decoded, 2);
            if (count($signedParts) !== 2 || $signedParts[0] === '' || $signedParts[1] === '') {
                return false;
            }

            [$encrypted, $signature] = $signedParts;
            $expectedSignature = hash_hmac('sha256', $encrypted, $secret);
            if (! hash_equals($expectedSignature, $signature)) {
                return false;
            }

            $payloadParts = explode('|', Crypt::decryptString($encrypted), 2);
            if (count($payloadParts) !== 2
                || ! preg_match('/^\d{14}$/', $payloadParts[0])
                || ! preg_match('/^[a-f0-9]{16}$/', $payloadParts[1])) {
                return false;
            }

            $tokenDate = Carbon::createFromFormat('YmdHis', $payloadParts[0]);

            return ! now()->greaterThan($tokenDate->clone()->addHours($expiryHours));
        } catch (Throwable) {
            return false;
        }
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
