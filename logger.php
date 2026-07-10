<?php
/**
 * logger.php -- Sistema de Logs com Discord Webhook
 *
 * Uso: logEvent('evento', 'mensagem', ['chave' => 'valor'], 'NIVEL');
 * Niveis: SUCCESS | INFO | WARNING | ERROR
 */

function logEvent(string $event, string $message, array $extra = [], string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

    $colors = ['SUCCESS' => 3066993, 'INFO' => 3447003, 'WARNING' => 15105570, 'ERROR' => 15158332];
    $emojis = ['SUCCESS' => '✅', 'INFO' => 'ℹ️', 'WARNING' => '⚠️', 'ERROR' => '❌'];

    // 1. Grava no activity.log
    $entry = ['timestamp' => $timestamp, 'level' => $level, 'event' => $event, 'message' => $message, 'ip' => $ip];
    if (!empty($extra)) $entry['extra'] = $extra;
    file_put_contents(__DIR__ . '/activity.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

    // 2. Le DISCORD_WEBHOOK_URL diretamente do .env
    $discordUrl = '';
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2 && trim($parts[0]) === 'DISCORD_WEBHOOK_URL') {
                $discordUrl = trim($parts[1]);
                break;
            }
        }
    }
    if (empty($discordUrl)) return;

    // 3. Monta embed Discord
    $fields = [
        ['name' => 'PC IP',      'value' => $ip,        'inline' => true],
        ['name' => 'Horario',    'value' => $timestamp, 'inline' => true],
    ];
    foreach ($extra as $k => $v) {
        $fields[] = ['name' => (string)$k, 'value' => (string)$v, 'inline' => true];
    }

    $payload = json_encode([
        'embeds' => [[
            'title'       => ($emojis[$level] ?? 'i') . ' ' . $event,
            'description' => $message,
            'color'       => $colors[$level] ?? 3447003,
            'fields'      => $fields,
            'footer'      => ['text' => 'Gerenciador de Backups'],
            'timestamp'   => date('c'),
        ]]
    ], JSON_UNESCAPED_UNICODE);

    // 4. Envia via cURL ou stream_context
    if (function_exists('curl_init')) {
        $ch = curl_init($discordUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 5,
            'ignore_errors' => true,
        ]]);
        @file_get_contents($discordUrl, false, $ctx);
    }
}
