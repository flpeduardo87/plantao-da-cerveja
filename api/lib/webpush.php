<?php
declare(strict_types=1);

// Minimal Web Push / VAPID implementation (PHP 8.1+, ext-openssl, ext-curl)

function wp_b64u(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function wp_b64d(string $s): string {
    $pad = (4 - strlen($s) % 4) % 4;
    return base64_decode(strtr($s . str_repeat('=', $pad), '-_', '+/'));
}

function wp_hkdf(string $salt, string $ikm, string $info, int $len): string {
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t = ''; $okm = '';
    for ($i = 1; strlen($okm) < $len; $i++)
        $okm .= ($t = hash_hmac('sha256', $t . $info . chr($i), $prk, true));
    return substr($okm, 0, $len);
}

function wp_der_to_rs(string $der): string {
    $i = 2;
    if (ord($der[1]) & 0x80) $i += ord($der[1]) & 0x7f;
    $i++; $rlen = ord($der[$i++]);
    if (ord($der[$i]) === 0x00) { $i++; $rlen--; }
    $r = substr($der, $i, $rlen); $i += $rlen;
    $i++; $slen = ord($der[$i++]);
    if (ord($der[$i]) === 0x00) { $i++; $slen--; }
    $s = substr($der, $i, $slen);
    return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
}

function wp_generate_keys(): array {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($key, $priv_pem);
    $d = openssl_pkey_get_details($key);
    return ['public_b64u' => wp_b64u("\x04" . $d['ec']['x'] . $d['ec']['y']), 'private_pem' => $priv_pem];
}

function wp_vapid_jwt(string $endpoint, string $subject, string $priv_pem): string {
    $p = parse_url($endpoint);
    $aud = $p['scheme'] . '://' . $p['host'];
    $h = wp_b64u('{"typ":"JWT","alg":"ES256"}');
    $c = wp_b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $subject]));
    openssl_sign("$h.$c", $sig, $priv_pem, OPENSSL_ALGO_SHA256);
    return "$h.$c." . wp_b64u(wp_der_to_rs($sig));
}

// P-256 SubjectPublicKeyInfo DER prefix (26 bytes)
const WP_DER_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
                    . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

function wp_send(array $sub, string $payload, string $vapid_pub, string $vapid_priv_pem, string $vapid_subject): bool {
    if (!function_exists('openssl_pkey_derive')) return false;

    $endpoint = $sub['endpoint'];
    $auth     = wp_b64d($sub['keys']['auth']);
    $p256dh   = wp_b64d($sub['keys']['p256dh']); // 65 bytes: 04||x||y

    // Ephemeral sender key
    $sender = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($sender, $sender_pem);
    $sd = openssl_pkey_get_details($sender);
    $sender_pub = "\x04" . $sd['ec']['x'] . $sd['ec']['y']; // 65 bytes

    // Load subscriber public key
    $pem = "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode(WP_DER_PREFIX . $p256dh), 64)
         . "-----END PUBLIC KEY-----\n";
    $sub_key = openssl_pkey_get_public($pem);
    if (!$sub_key) return false;

    // ECDH shared secret
    $ecdh = openssl_pkey_derive($sub_key, $sender);
    if (!$ecdh) return false;
    $ecdh = str_pad($ecdh, 32, "\0", STR_PAD_LEFT);

    // Key derivation (RFC 8291)
    $ikm   = wp_hkdf($auth, $ecdh, "WebPush: info\x00" . $p256dh . $sender_pub, 32);
    $salt  = random_bytes(16);
    $cek   = wp_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = wp_hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

    // AES-128-GCM encrypt
    $tag = '';
    $ct  = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

    // aes128gcm record: salt(16) + rs(4) + keyid_len(1) + sender_pub(65) + ciphertext + tag(16)
    $body = $salt . pack('N', 4096) . chr(strlen($sender_pub)) . $sender_pub . $ct . $tag;

    // VAPID
    $jwt  = wp_vapid_jwt($endpoint, $vapid_subject, $vapid_priv_pem);
    $auth_hdr = 'vapid t=' . $jwt . ', k=' . $vapid_pub;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: ' . $auth_hdr,
            'TTL: 86400',
            'Urgency: high',
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
