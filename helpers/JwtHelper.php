<?php
class JwtHelper {
    private string $secret;

    public function __construct(string $secret) {
        $this->secret = $secret;
    }

    public function encode(array $payload, int $ttlSeconds = 86400): string {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttlSeconds;
        $segments = [
            $this->b64(json_encode($header)),
            $this->b64(json_encode($payload))
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $this->secret, true);
        $segments[] = $this->b64($signature);
        return implode('.', $segments);
    }

    public function decode(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $signingInput = $h . '.' . $p;
        $signature = $this->unb64($s);
        $expected = hash_hmac('sha256', $signingInput, $this->secret, true);
        if (!hash_equals($expected, $signature)) return null;
        $payload = json_decode($this->unb64($p), true);
        if (!is_array($payload)) return null;
        if (($payload['exp'] ?? 0) < time()) return null;
        return $payload;
    }

    private function b64(string $data): string { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
    private function unb64(string $data): string { return base64_decode(strtr($data, '-_', '+/')); }
}

