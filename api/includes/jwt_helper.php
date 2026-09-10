<?php
/**
 * Minimal, dependency-free JWT (HS256) encode/decode.
 *
 * This project has no composer/vendor setup, so rather than pull in
 * firebase/php-jwt we implement the same three-segment
 * base64url(header).base64url(payload).base64url(signature) format
 * ourselves using PHP's built-in hash_hmac/hash_equals.
 */

function jwt_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Build a signed JWT. Adds iat/exp claims on top of whatever is passed in $payload.
 */
function jwt_encode(array $payload, string $secret, int $expirySeconds): string {
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + $expirySeconds;

    $segments = [
        jwt_base64url_encode(json_encode($header)),
        jwt_base64url_encode(json_encode($payload)),
    ];
    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $secret, true);
    $segments[] = jwt_base64url_encode($signature);

    return implode('.', $segments);
}

/**
 * Verify signature + expiry and return the decoded payload, or null if the
 * token is malformed, tampered with, or expired.
 */
function jwt_decode(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$headerB64, $payloadB64, $sigB64] = $parts;

    $expectedSig = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true);
    $actualSig = jwt_base64url_decode($sigB64);
    if (!hash_equals($expectedSig, $actualSig)) {
        return null;
    }

    $payload = json_decode(jwt_base64url_decode($payloadB64), true);
    if (!is_array($payload)) {
        return null;
    }
    if (!isset($payload['exp']) || time() >= (int)$payload['exp']) {
        return null;
    }

    return $payload;
}
