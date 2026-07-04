<?php

namespace App\Support\Automation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RunnerSignature
{
    public function verify(Request $request): bool
    {
        $secret = (string) config('automation.runner.shared_secret');
        $timestamp = $request->header('X-Testify-Timestamp');
        $signature = $request->header('X-Testify-Signature');
        $requestId = $request->header('X-Testify-Request-Id');

        if ($secret === '' || ! is_string($timestamp) || ! ctype_digit($timestamp)
            || ! is_string($signature) || ! is_string($requestId) || trim($requestId) === '') {
            return false;
        }

        $tolerance = max(30, (int) config('automation.runner.signature_tolerance_seconds', 300));

        if (abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $this->canonical($request, $timestamp), $secret);

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        return Cache::add('automation-runner-request:'.hash('sha256', $requestId), true, $tolerance);
    }

    public function canonical(Request $request, string $timestamp): string
    {
        return implode("\n", [
            $timestamp,
            strtoupper($request->method()),
            '/'.$request->path(),
            hash('sha256', $request->getContent()),
        ]);
    }
}
