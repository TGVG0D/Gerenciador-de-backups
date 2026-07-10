<?php
// =============================================================================
// CRIPTOGRAFIA: Salt + Pepper + PBKDF2 + AES-256-CBC
// =============================================================================
// Pepper: segredo fixo hardcoded no código-fonte.
// NUNCA é salvo em arquivo ou banco de dados.
define('ENCRYPTION_PEPPER', 'GBkup$Pep#7xQ!mZ2vN8rLwA3sYdT5jF');

function readEnv($path) {
    if(!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $env;
}

function writeEnv($path, $data) {
    $content = '';
    foreach ($data as $key => $value) {
        $content .= $key . '=' . $value . "\n";
    }
    return file_put_contents($path, $content) !== false;
}

function deriveKey(string $masterKey, string $salt): string {
    $combined = $masterKey . ENCRYPTION_PEPPER;
    return hash_pbkdf2('sha256', $combined, $salt, 100000, 32, true);
}

function encryptData(array $data): string {
    $env = readEnv(__DIR__ . '/.env');
    $masterKey = $env['DATA_ENCRYPTION_KEY'] ?? '';

    if (empty($masterKey)) {
        $masterKey = bin2hex(random_bytes(32));
        $env['DATA_ENCRYPTION_KEY'] = $masterKey;
        writeEnv(__DIR__ . '/.env', $env);
    }

    $plaintext = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $salt      = random_bytes(16);
    $iv        = random_bytes(16);
    $key       = deriveKey($masterKey, $salt);

    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return base64_encode($salt . $iv . $ciphertext);
}

function decryptData(string $raw): array {
    if (empty($raw) || trim($raw) === '' || trim($raw) === '[]') {
        return [];
    }

    $decoded = base64_decode($raw, true);
    if ($decoded !== false && strlen($decoded) > 32) {
        $env = readEnv(__DIR__ . '/.env');
        $masterKey = $env['DATA_ENCRYPTION_KEY'] ?? '';

        if (!empty($masterKey)) {
            $salt       = substr($decoded, 0,  16);
            $iv         = substr($decoded, 16, 16);
            $ciphertext = substr($decoded, 32);
            $key        = deriveKey($masterKey, $salt);

            $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plaintext !== false) {
                $result = json_decode($plaintext, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                    return $result;
                }
            }
        }
    }

    $result = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
        return $result;
    }

    return [];
}
?>
