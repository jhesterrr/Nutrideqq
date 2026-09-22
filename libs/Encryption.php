<?php

class Encryption
{
    private static ?string $key = null;

    public static function init(): void
    {
        $envKey = getenv('NUTRIDEQ_ENC_KEY');
        if (!$envKey) {
            throw new RuntimeException('NUTRIDEQ_ENC_KEY environment variable is not set');
        }
        self::$key = hash('sha256', $envKey);
    }

    public static function encrypt(string $data): string
    {
        if (self::$key === null) {
            self::init();
        }
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', self::$key, 0, $iv);
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encrypted): ?string
    {
        if (self::$key === null) {
            self::init();
        }
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < 16) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', self::$key, 0, $iv);
        if ($decrypted === false) {
            return null;
        }
        return $decrypted;
    }

    public static function encryptIfExists(string $data): string
    {
        if (self::isEncrypted($data)) {
            return $data;
        }
        return self::encrypt($data);
    }

    public static function isEncrypted(string $data): bool
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) < 48) {
            return false;
        }
        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        return openssl_decrypt($ciphertext, 'AES-256-CBC', hash('sha256', getenv('NUTRIDEQ_ENC_KEY') ?: ''), 0, $iv) !== false;
    }
}
