<?php

namespace App\Services\Dte;

class DteSignerSimulatorService
{
    /**
     * Simulates JWS envelope generation (header.payload.signature) using an ephemeral
     * sandbox key. Never uses real Ministerio de Hacienda certificates (.crt).
     *
     * @param  array<string, mixed>  $dtePayload
     */
    public function sign(array $dtePayload, string $signingKey = 'sandbox-jws-hmac-key'): string
    {
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode((string) json_encode($dtePayload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $signingKey, true));

        return "{$header}.{$payload}.{$signature}";
    }

    public function verify(string $jws, string $signingKey = 'sandbox-jws-hmac-key'): bool
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $sig] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $signingKey, true));

        return hash_equals($expected, $sig);
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
