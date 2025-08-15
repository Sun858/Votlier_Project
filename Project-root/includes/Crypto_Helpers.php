<?php
declare(strict_types=1);

const VOTIFY_CIPHER  = 'aes-256-gcm';
const VOTIFY_IV_LEN  = 12;
const VOTIFY_TAG_LEN = 16;

function votify_load_env_once(): void {
    static $done = false;
    if ($done) return;
    if (!empty($_ENV['APP_AES_KEY']) || getenv('APP_AES_KEY') || !empty($_ENV['MASTER_EMAIL_ENCRYPTION_KEY']) || getenv('MASTER_EMAIL_ENCRYPTION_KEY')) { $done = true; return; }
    $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
    $env  = $root . '/.env';
    if (is_file($env) && is_readable($env)) {
        $pairs = parse_ini_file($env, false, INI_SCANNER_RAW) ?: [];
        foreach ($pairs as $k => $v) {
            if (is_string($v) && strlen($v) >= 2) {
                $q = $v[0]; $r = substr($v, -1);
                if (($q === '"' && $r === '"') || ($q === "'" && $r === "'")) $v = substr($v, 1, -1);
            }
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
    $done = true;
}

function getAppKey(): string {
    votify_load_env_once();
    $raw = trim((string)(
        $_ENV['APP_AES_KEY']
        ?? $_ENV['MASTER_EMAIL_ENCRYPTION_KEY']
        ?? getenv('APP_AES_KEY')
        ?? getenv('MASTER_EMAIL_ENCRYPTION_KEY')
        ?? ''
    ));

    $b64 = base64_decode($raw, true);
    if ($b64 !== false && strlen($b64) === 32) return $b64;

    if (preg_match('/^[0-9a-fA-F]{64}$/', $raw)) {
        $bin = hex2bin($raw);
        if ($bin !== false && strlen($bin) === 32) return $bin;
    }

    if (strlen($raw) === 32) return $raw;

    throw new RuntimeException('AES key missing or not 32 bytes (base64/hex/raw).');
}

function encryptField(string $plaintext): string {
    $key = getAppKey();
    $iv  = random_bytes(VOTIFY_IV_LEN);
    $tag = '';
    $ct  = openssl_encrypt($plaintext, VOTIFY_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', VOTIFY_TAG_LEN);
    if ($ct === false) throw new RuntimeException('Encryption failed');
    return base64_encode($iv . $tag . $ct);
}

function decryptField(?string $stored): ?string {
    if ($stored === null) return null;
    $blob = base64_decode($stored, true);
    if ($blob === false) $blob = $stored;
    if (strlen($blob) < (VOTIFY_IV_LEN + VOTIFY_TAG_LEN)) return null;

    $iv  = substr($blob, 0, VOTIFY_IV_LEN);
    $tag = substr($blob, VOTIFY_IV_LEN, VOTIFY_TAG_LEN);
    $ct  = substr($blob, VOTIFY_IV_LEN + VOTIFY_TAG_LEN);

    $key = getAppKey();
    $pt  = openssl_decrypt($ct, VOTIFY_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
    return ($pt === false) ? null : $pt;
}
