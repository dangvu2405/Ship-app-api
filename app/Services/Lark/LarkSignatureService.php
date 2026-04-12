<?php

declare(strict_types=1);

namespace App\Services\Lark;

use Illuminate\Http\Request;

class LarkSignatureService
{
    public function isTimestampFresh(int $timestamp): bool
    {
        $maxAge = (int) config('lark.security.max_request_age_seconds', 300);

        return abs(time() - $timestamp) <= $maxAge;
    }

    public function verifyRequest(Request $request): bool
    {
        $secret = (string) config('lark.signing_secret');
        if ($secret === '') {
            return false;
        }

        $timestamp = (string) $request->header('X-Lark-Request-Timestamp', '');
        $nonce = (string) $request->header('X-Lark-Request-Nonce', '');
        $signature = (string) $request->header('X-Lark-Signature', '');

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return false;
        }

        if (! $this->isTimestampFresh((int) $timestamp)) {
            return false;
        }

        $rawBody = (string) $request->getContent();
        $toSign = $timestamp.$nonce.$rawBody;
        $computed = base64_encode(hash_hmac('sha256', $toSign, $secret, true));

        return hash_equals($computed, $signature);
    }

    public function verifyChallengeToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals((string) config('lark.verification_token'), $token);
    }
}
