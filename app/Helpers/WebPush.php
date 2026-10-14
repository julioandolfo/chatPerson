<?php
/**
 * Helper WebPush
 *
 * Implementação do Web Push sem dependências externas (só ext-openssl):
 * - Criptografia do conteúdo: RFC 8291 (Message Encryption for Web Push) com
 *   "Content-Encoding: aes128gcm" (RFC 8188).
 * - Autenticação do servidor: VAPID (RFC 8292), JWT assinado com ES256 (P-256).
 *
 * Formato das chaves: base64url, como o navegador entrega (PushSubscription) e como as
 * bibliotecas de web-push usam (chave pública = ponto não comprimido de 65 bytes,
 * chave privada = escalar "d" de 32 bytes).
 */

namespace App\Helpers;

class WebPush
{
    /** Prefixo DER de uma SubjectPublicKeyInfo P-256 (o ponto de 65 bytes vem logo depois) */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Tamanho do registro (rs) do aes128gcm; o payload criptografado cabe num registro só */
    private const RECORD_SIZE = 4096;

    /** Limite seguro de payload (o padrão garante pelo menos 4096 bytes no total) */
    public const MAX_PAYLOAD = 3000;

    // =====================================================================
    // CHAVES VAPID
    // =====================================================================

    /**
     * Gerar par de chaves VAPID: ['public' => base64url(65 bytes), 'private' => base64url(32 bytes)]
     */
    public static function generateVapidKeys(): array
    {
        [$private, $publicRaw] = self::newP256KeyPair();
        $details = openssl_pkey_get_details($private);

        return [
            'public' => self::b64uEncode($publicRaw),
            'private' => self::b64uEncode(str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    // =====================================================================
    // ENVIO
    // =====================================================================

    /**
     * Montar a requisição HTTP de um push. Não envia (o envio em lote fica no PushService).
     *
     * @param array $subscription ['endpoint' => ..., 'p256dh' => base64url, 'auth' => base64url]
     * @param array $vapid ['public' => ..., 'private' => ..., 'subject' => 'mailto:...|https://...']
     * @return array ['url' => ..., 'headers' => [...], 'body' => binário]
     */
    public static function buildRequest(array $subscription, string $payload, array $vapid, int $ttl = 86400, string $urgency = 'high', ?string $topic = null): array
    {
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new \InvalidArgumentException('Payload de push grande demais (' . strlen($payload) . ' bytes)');
        }

        $body = self::encrypt($payload, $subscription['p256dh'], $subscription['auth']);

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Urgency: ' . $urgency,
            'Authorization: ' . self::vapidAuthorization($subscription['endpoint'], $vapid),
        ];

        // Topic: o serviço de push substitui uma mensagem ainda não entregue com o mesmo tópico
        if ($topic !== null && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $topic)) {
            $headers[] = 'Topic: ' . $topic;
        }

        return ['url' => $subscription['endpoint'], 'headers' => $headers, 'body' => $body];
    }

    /**
     * Criptografar o conteúdo para um assinante (RFC 8291 / aes128gcm)
     */
    public static function encrypt(string $payload, string $userPublicKeyB64, string $authSecretB64, ?string $salt = null, $serverPrivateKey = null): string
    {
        $userPublic = self::b64uDecode($userPublicKeyB64);
        $authSecret = self::b64uDecode($authSecretB64);

        if (strlen($userPublic) !== 65 || $userPublic[0] !== "\x04") {
            throw new \InvalidArgumentException('Chave p256dh inválida');
        }
        if (strlen($authSecret) !== 16) {
            throw new \InvalidArgumentException('Segredo auth inválido');
        }

        // Par de chaves efêmero do servidor (um por mensagem)
        if ($serverPrivateKey === null) {
            [$serverPrivateKey, $serverPublic] = self::newP256KeyPair();
        } else {
            $serverPublic = self::publicPointFromKey($serverPrivateKey);
        }

        $sharedSecret = openssl_pkey_derive(self::publicKeyFromPoint($userPublic), $serverPrivateKey, 32);
        if ($sharedSecret === false || strlen($sharedSecret) !== 32) {
            throw new \RuntimeException('Falha no ECDH: ' . openssl_error_string());
        }

        // IKM = HKDF(salt=auth_secret, ikm=ecdh_secret, info="WebPush: info\0" || ua_public || as_public)
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\0" . $userPublic . $serverPublic, $authSecret);

        $salt = $salt ?? random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // Registro único: conteúdo + delimitador 0x02 ("último registro")
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Falha ao criptografar push: ' . openssl_error_string());
        }

        // Cabeçalho aes128gcm: salt(16) || rs(4) || idlen(1) || keyid(chave pública efêmera)
        return $salt . pack('N', self::RECORD_SIZE) . chr(strlen($serverPublic)) . $serverPublic . $cipher . $tag;
    }

    /**
     * Cabeçalho Authorization com JWT VAPID (ES256)
     */
    public static function vapidAuthorization(string $endpoint, array $vapid): string
    {
        $parts = parse_url($endpoint);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Endpoint de push inválido');
        }
        $audience = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $header = self::b64uEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64uEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $vapid['subject'],
        ], JSON_UNESCAPED_SLASHES));
        $signingInput = $header . '.' . $claims;

        $privateKey = self::privateKeyFromRaw(self::b64uDecode($vapid['private']), self::b64uDecode($vapid['public']));
        if (!openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Falha ao assinar JWT VAPID: ' . openssl_error_string());
        }

        $jwt = $signingInput . '.' . self::b64uEncode(self::derSignatureToRaw($derSignature));

        return 'vapid t=' . $jwt . ', k=' . $vapid['public'];
    }

    // =====================================================================
    // UTILITÁRIOS DE CHAVE
    // =====================================================================

    /** @return array [OpenSSLAsymmetricKey $private, string $publicPoint65] */
    private static function newP256KeyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new \RuntimeException('Falha ao gerar chave P-256: ' . openssl_error_string());
        }
        return [$key, self::publicPointFromKey($key)];
    }

    private static function publicPointFromKey($key): string
    {
        $details = openssl_pkey_get_details($key);
        return "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    private static function publicKeyFromPoint(string $point)
    {
        $der = hex2bin(self::P256_SPKI_PREFIX) . $point;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \InvalidArgumentException('Chave pública P-256 inválida');
        }
        return $key;
    }

    /**
     * Chave privada a partir do escalar "d" (32 bytes) e do ponto público (65 bytes),
     * montando o ECPrivateKey (RFC 5915) em DER
     */
    private static function privateKeyFromRaw(string $d, string $publicPoint)
    {
        if (strlen($d) !== 32 || strlen($publicPoint) !== 65) {
            throw new \InvalidArgumentException('Chaves VAPID inválidas');
        }
        $der = hex2bin('30770201010420') . $d
            . hex2bin('a00a06082a8648ce3d030107')
            . hex2bin('a144034200') . $publicPoint;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new \InvalidArgumentException('Chave privada VAPID inválida');
        }
        return $key;
    }

    /**
     * Assinatura ECDSA em DER (SEQUENCE { INTEGER r, INTEGER s }) → r||s com 32 bytes cada (JWS)
     */
    private static function derSignatureToRaw(string $der): string
    {
        $offset = 2;
        if (ord($der[1]) & 0x80) {
            $offset += ord($der[1]) & 0x7f;
        }

        $read = function () use ($der, &$offset): string {
            if (ord($der[$offset]) !== 0x02) {
                throw new \RuntimeException('Assinatura DER inválida');
            }
            $len = ord($der[$offset + 1]);
            $value = substr($der, $offset + 2, $len);
            $offset += 2 + $len;
            return str_pad(ltrim($value, "\0"), 32, "\0", STR_PAD_LEFT);
        };

        $r = $read();
        $s = $read();
        return $r . $s;
    }

    public static function b64uEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Base64url inválido');
        }
        return $decoded;
    }
}
