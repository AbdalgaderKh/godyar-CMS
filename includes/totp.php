<?php
declare(strict_types=1);

/**
 * Minimal TOTP (RFC 6238) implementation.
 * - base32 secret (Google Authenticator compatible)
 * - verify with +/- window steps
 */

function totp_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
    $bits = '';
    foreach (str_split($b32) as $ch) {
        $v = strpos($alphabet, $ch);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $out .= chr(bindec($byte));
    }
    return $out;
}

function totp_generate_secret(int $length = 16): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
}

function totp_code(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
{
    $timestamp = $timestamp ?? time();
    $counter = intdiv($timestamp, $period);

    $key = totp_base32_decode($secret);
    $binCounter = pack('N*', 0) . pack('N*', $counter);

    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part = substr($hash, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;

    $mod = 10 ** $digits;
    return str_pad((string)($value % $mod), $digits, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1, int $period = 30, int $digits = 6): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if ($code === '') return false;

    $now = time();
    for ($w = -$window; $w <= $window; $w++) {
        $t = $now + ($w * $period);
        if (hash_equals(totp_code($secret, $t, $period, $digits), $code)) return true;
    }
    return false;
}

function totp_otpauth_url(string $issuer, string $account, string $secret): string
{
    $label = rawurlencode($issuer . ':' . $account);
    $issuerEnc = rawurlencode($issuer);
    $secretEnc = rawurlencode($secret);
    return "otpauth://totp/{$label}?secret={$secretEnc}&issuer={$issuerEnc}&algorithm=SHA1&digits=6&period=30";
}
