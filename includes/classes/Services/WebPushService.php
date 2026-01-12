<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * WebPushService (minimal, dependency-free)
 *
 * Implements:
 * - VAPID (ES256 JWT)
 * - aes128gcm payload encryption (RFC8291 / RFC8188)
 *
 * Notes:
 * - Works for modern browsers (Chrome/Edge/Firefox) that support aes128gcm.
 * - Designed for "manual broadcast" from admin panel (small-to-medium subscriber counts).
 */
final class WebPushService
{
    private PDO $pdo;
    private string $vapidPublic;  // base64url of uncompressed P-256 public key (65 bytes)
    private string $vapidPrivate; // base64url of private scalar d (32 bytes)
    private string $subject;      // mailto:... or https://...

    public function __construct(PDO $pdo, string $vapidPublic, string $vapidPrivate, string $subject)
    {
        $this->pdo = $pdo;
        $this->vapidPublic  = trim($vapidPublic);
        $this->vapidPrivate = trim($vapidPrivate);
        $this->subject      = trim($subject);
    }

    /**
     * Ensure required DB tables exist.
     *
     * Some deployments update files without applying DB migrations.
     * Creating tables here keeps the admin "Broadcast" feature from crashing.
     */
    private function ensureTables(): void
    {
        // Keep schema compatible with Api\NewsExtrasController::ensurePushTable() (sha1 hash length=40)
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                endpoint_hash CHAR(40) NOT NULL,
                user_id INT UNSIGNED NULL,
                endpoint TEXT NOT NULL,
                p256dh TEXT NOT NULL,
                auth TEXT NOT NULL,
                prefs_json JSON NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_endpoint (endpoint_hash),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function sendBroadcast(array $payload, int $ttlSeconds = 300, bool $testOnly = false): array
    {
        $result = [
            'ok' => false,
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'errors' => [],
        ];

        if ($this->vapidPublic === '' || $this->vapidPrivate === '') {
            $result['errors'][] = 'VAPID keys are missing.';
            return $result;
        }

        // Ensure table exists before querying.
        try { $this->ensureTables(); } catch (Throwable $e) { /* ignore */ }

        $st = $this->pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions ORDER BY updated_at DESC");
        $subs = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        $result['total'] = is_array($subs) ? count($subs) : 0;

        if ($testOnly && $result['total'] > 1) {
            $subs = [ $subs[0] ];
            $result['total'] = 1;
        }

        foreach ($subs as $row) {
            $endpoint = (string)($row['endpoint'] ?? '');
            $p256dh   = (string)($row['p256dh'] ?? '');
            $auth     = (string)($row['auth'] ?? '');

            if ($endpoint === '' || $p256dh === '' || $auth === '') {
                $result['failed']++;
                $result['errors'][] = 'Invalid subscription row (missing fields).';
                continue;
            }

            try {
                $r = $this->sendToSubscription($endpoint, $p256dh, $auth, $payload, $ttlSeconds);
                if (!empty($r['ok'])) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                    $msg = (string)($r['error'] ?? 'unknown error');
                    $result['errors'][] = $msg;
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['errors'][] = $e->getMessage();
            }
        }

        $result['ok'] = ($result['sent'] > 0 && $result['failed'] === 0) || ($result['sent'] > 0);
        return $result;
    }

    public function sendToSubscription(
        string $endpoint,
        string $p256dh_b64url,
        string $auth_b64url,
        array $payload,
        int $ttlSeconds = 300
    ): array {
        $endpoint = trim($endpoint);

        $aud = $this->endpointAudience($endpoint);
        if ($aud === '') {
            return ['ok'=>false, 'error'=>'Invalid endpoint URL'];
        }

        $subPublic = self::b64url_decode($p256dh_b64url);
        $subAuth   = self::b64url_decode($auth_b64url);

        if ($subPublic === '' || strlen($subPublic) < 65 || $subPublic[0] !== "\x04") {
            return ['ok'=>false, 'error'=>'Invalid p256dh key'];
        }
        if ($subAuth === '' || strlen($subAuth) < 16) {
            return ['ok'=>false, 'error'=>'Invalid auth secret'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) $json = '{}';

        // Encrypt payload (aes128gcm)
        $enc = $this->encrypt_aes128gcm($json, $subPublic, $subAuth);
        if (!$enc['ok']) {
            return ['ok'=>false, 'error'=>(string)$enc['error']];
        }

        // VAPID JWT + headers
        $jwt = $this->createVapidJwt($aud, $this->subject, time() + 12*60*60);
        if ($jwt === '') {
            return ['ok'=>false, 'error'=>'Failed to create VAPID JWT'];
        }

        $vapidPubRaw = self::b64url_decode($this->vapidPublic);

        // Auth style #1 (RFC8292): Authorization: vapid t=..., k=...
        $headers = [
            'TTL: ' . max(0, (int)$ttlSeconds),
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'Authorization: vapid t=' . $jwt . ', k=' . $this->vapidPublic,
            'Crypto-Key: p256ecdsa=' . $this->vapidPublic,
        ];

        $resp = $this->httpPost($endpoint, $enc['body'], $headers);

        // Some push services (or older implementations) prefer: Authorization: WebPush <JWT>
        // If we get a 400 with an empty body, retry once with the alternate header.
        $codeTry1 = (int)($resp['code'] ?? 0);
        $bodyTry1 = (string)($resp['response'] ?? '');
        if ($codeTry1 === 400 && trim($bodyTry1) === '') {
            $headers2 = $headers;
            foreach ($headers2 as $i => $h) {
                if (stripos($h, 'Authorization:') === 0) {
                    $headers2[$i] = 'Authorization: WebPush ' . $jwt;
                }
            }
            $resp = $this->httpPost($endpoint, $enc['body'], $headers2);
        }

        // 201/202 usually OK
        $code = (int)($resp['code'] ?? 0);
        if ($code >= 200 && $code < 300) {
            return ['ok'=>true, 'code'=>$code];
        }

        $respText = trim((string)($resp['response'] ?? ''));
        if ($respText === '') $respText = 'no response body';
        return ['ok'=>false, 'code'=>$code, 'error'=>'Push failed (HTTP ' . $code . '): ' . $respText];
    }

    /* -----------------------------
       HTTP
       ----------------------------- */

    private function httpPost(string $url, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            return ['code'=>0, 'response'=>'cURL not available'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $respBody = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'code' => $code,
            'response' => ($respBody !== false ? $respBody : $err),
        ];
    }

    private function endpointAudience(string $endpoint): string
    {
        $u = parse_url($endpoint);
        if (!is_array($u)) return '';
        $scheme = (string)($u['scheme'] ?? '');
        $host = (string)($u['host'] ?? '');
        if ($scheme === '' || $host === '') return '';
        $port = (int)($u['port'] ?? 0);
        $aud = $scheme . '://' . $host;
        if ($port && !in_array($port, [80, 443], true)) {
            $aud .= ':' . $port;
        }
        return $aud;
    }

    /* -----------------------------
       VAPID / JWT (ES256)
       ----------------------------- */

    private function createVapidJwt(string $aud, string $sub, int $exp): string
    {
        $header = ['typ'=>'JWT','alg'=>'ES256'];
        $claims = ['aud'=>$aud,'exp'=>$exp,'sub'=>$sub];

        $h = self::b64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $c = self::b64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $input = $h . '.' . $c;

        $privPem = $this->vapidPrivatePem();
        if ($privPem === '') return '';

        $pkey = openssl_pkey_get_private($privPem);
        if ($pkey === false) return '';

        $derSig = '';
        $ok = openssl_sign($input, $derSig, $pkey, OPENSSL_ALGO_SHA256);
        // Note: freeing OpenSSL keys is automatic in modern PHP; no explicit free needed.

        if (!$ok || $derSig === '') return '';

        $rawSig = self::ecdsa_der_to_jws($derSig);
        if ($rawSig === '') return '';

        return $input . '.' . self::b64url_encode($rawSig);
    }

    private function vapidPrivatePem(): string
    {
        $pubRaw = self::b64url_decode($this->vapidPublic);
        $d = self::b64url_decode($this->vapidPrivate);
        if ($pubRaw === '' || strlen($pubRaw) !== 65 || $d === '' || strlen($d) !== 32) return '';

        $ecPrivate = self::der_sequence(
            self::der_int("\x01") .
            self::der_octet($d) .
            self::der_tagged(0, self::der_oid(self::OID_PRIME256V1)) .
            self::der_tagged(1, self::der_bitstring("\x00" . $pubRaw))
        );

        $pkcs8 = self::der_sequence(
            self::der_int("\x00") .
            self::der_sequence(
                self::der_oid(self::OID_EC_PUBLIC_KEY) .
                self::der_oid(self::OID_PRIME256V1)
            ) .
            self::der_octet($ecPrivate)
        );

        $b64 = chunk_split(base64_encode($pkcs8), 64, "\n");
        return "-----BEGIN PRIVATE KEY-----\n" . $b64 . "-----END PRIVATE KEY-----\n";
    }

    /* -----------------------------
       Encryption: aes128gcm
       ----------------------------- */

    private function encrypt_aes128gcm(string $payload, string $receiverPub, string $authSecret): array
    {
        if (!function_exists('openssl_pkey_new')) {
            return ['ok'=>false,'error'=>'OpenSSL not available'];
        }

        // Server ephemeral ECDH key pair
        $serverKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        if ($serverKey === false) {
            return ['ok'=>false,'error'=>'Failed to generate ephemeral EC key'];
        }
        $serverDetails = openssl_pkey_get_details($serverKey);
        if (!is_array($serverDetails) || !isset($serverDetails['ec']['x'], $serverDetails['ec']['y'])) {
            return ['ok'=>false,'error'=>'Failed to read ephemeral key details'];
        }
        $sx = (string)$serverDetails['ec']['x'];
        $sy = (string)$serverDetails['ec']['y'];
        if (strlen($sx) !== 32 || strlen($sy) !== 32) {
            return ['ok'=>false,'error'=>'Unexpected ephemeral key size'];
        }
        $serverPubRaw = "\x04" . $sx . $sy;

        // Build receiver public key resource
        $receiverPem = self::spki_pem_from_uncompressed($receiverPub);
        $receiverKey = openssl_pkey_get_public($receiverPem);
        if ($receiverKey === false) {
            return ['ok'=>false,'error'=>'Invalid receiver public key'];
        }

        $sharedSecret = openssl_pkey_derive($receiverKey, $serverKey, 32);
        if ($sharedSecret === false || strlen($sharedSecret) !== 32) {
            return ['ok'=>false,'error'=>'ECDH derive failed'];
        }

        $salt = random_bytes(16);

        // HKDF (RFC8291 / RFC8188):
        // 1) PRK = HKDF-Extract(auth_secret, ECDH_shared_secret)
        // 2) IKM = HKDF-Expand(PRK, "WebPush: info\0" || ua_public || as_public, 32)
        // 3) PRK2 = HKDF-Extract(salt, IKM)
        // 4) CEK/nonce = HKDF-Expand(PRK2, ...)
        $prk = self::hkdf_extract($authSecret, $sharedSecret);

        $info = "WebPush: info\0" . $receiverPub . $serverPubRaw;
        $ikm = self::hkdf_expand($prk, $info, 32);

        $prk2 = self::hkdf_extract($salt, $ikm);

        $cek   = self::hkdf_expand($prk2, "Content-Encoding: aes128gcm\0", 16);
        $nonce = self::hkdf_expand($prk2, "Content-Encoding: nonce\0", 12);

        // plaintext = 2-byte padding length (0) + payload
        $plaintext = "\x00\x00" . $payload;

        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false || $tag === '') {
            return ['ok'=>false,'error'=>'AES-GCM encryption failed'];
        }

        $rs = 4096;
        $header = $salt . pack('N', $rs) . chr(strlen($serverPubRaw)) . $serverPubRaw;

        return [
            'ok' => true,
            'body' => $header . ($ciphertext . $tag),
            // kept for debugging
            'salt' => self::b64url_encode($salt),
            'dh'   => self::b64url_encode($serverPubRaw),
        ];
    }

    /* -----------------------------
       Helpers: base64url, HKDF, DER
       ----------------------------- */

    private const OID_EC_PUBLIC_KEY = '1.2.840.10045.2.1';
    private const OID_PRIME256V1    = '1.2.840.10045.3.1.7';

    public static function b64url_encode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64url_decode(string $b64url): string
    {
        $b64url = trim($b64url);
        if ($b64url === '') return '';
        $b64 = strtr($b64url, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $out = base64_decode($b64, true);
        return ($out === false) ? '' : $out;
    }

    private static function hkdf_extract(string $salt, string $ikm): string
    {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    private static function hkdf_expand(string $prk, string $info, int $len): string
    {
        $t = '';
        $okm = '';
        $i = 1;
        while (strlen($okm) < $len) {
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
            $i++;
        }
        return substr($okm, 0, $len);
    }

    private static function ecdsa_der_to_jws(string $der): string
    {
        // DER is SEQUENCE { INTEGER r, INTEGER s }
        $pos = 0;
        if (ord($der[$pos]) !== 0x30) return '';
        $pos++;
        $len = self::der_read_len($der, $pos);
        if ($len < 0) return '';

        if (ord($der[$pos]) !== 0x02) return '';
        $pos++;
        $rLen = self::der_read_len($der, $pos);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        if (ord($der[$pos]) !== 0x02) return '';
        $pos++;
        $sLen = self::der_read_len($der, $pos);
        $s = substr($der, $pos, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private static function der_read_len(string $der, int &$pos): int
    {
        $len = ord($der[$pos]);
        $pos++;
        if ($len < 0x80) return $len;
        $n = $len & 0x7F;
        if ($n === 0 || $n > 4) return -1;
        $len = 0;
        for ($i=0; $i<$n; $i++) {
            $len = ($len << 8) | ord($der[$pos]);
            $pos++;
        }
        return $len;
    }

    private static function spki_pem_from_uncompressed(string $pubRaw): string
    {
        $spki = self::der_sequence(
            self::der_sequence(
                self::der_oid(self::OID_EC_PUBLIC_KEY) .
                self::der_oid(self::OID_PRIME256V1)
            ) .
            self::der_bitstring("\x00" . $pubRaw)
        );

        $b64 = chunk_split(base64_encode($spki), 64, "\n");
        return "-----BEGIN PUBLIC KEY-----\n" . $b64 . "-----END PUBLIC KEY-----\n";
    }

    private static function der_len(int $len): string
    {
        if ($len < 0x80) return chr($len);
        $tmp = '';
        while ($len > 0) {
            $tmp = chr($len & 0xFF) . $tmp;
            $len >>= 8;
        }
        return chr(0x80 | strlen($tmp)) . $tmp;
    }

    private static function der_sequence(string $inner): string
    {
        return "\x30" . self::der_len(strlen($inner)) . $inner;
    }

    private static function der_int(string $bin): string
    {
        // Ensure positive integer (prepend 0 if MSB set)
        if ($bin !== '' && (ord($bin[0]) & 0x80)) {
            $bin = "\x00" . $bin;
        }
        return "\x02" . self::der_len(strlen($bin)) . $bin;
    }

    private static function der_octet(string $bin): string
    {
        return "\x04" . self::der_len(strlen($bin)) . $bin;
    }

    private static function der_bitstring(string $bin): string
    {
        return "\x03" . self::der_len(strlen($bin)) . $bin;
    }

    private static function der_tagged(int $tagNo, string $inner): string
    {
        // Context-specific, constructed: 0xA0 + tagNo
        $tag = chr(0xA0 + $tagNo);
        return $tag . self::der_len(strlen($inner)) . $inner;
    }

    private static function der_oid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        if (count($parts) < 2) return '';
        $first = (40 * $parts[0]) + $parts[1];
        $out = chr($first);
        for ($i=2; $i<count($parts); $i++) {
            $n = $parts[$i];
            $enc = '';
            do {
                $byte = $n & 0x7F;
                $n >>= 7;
                $enc = chr($byte | ($enc === '' ? 0 : 0x80)) . $enc;
            } while ($n > 0);
            $out .= $enc;
        }
        return "\x06" . self::der_len(strlen($out)) . $out;
    }
}
