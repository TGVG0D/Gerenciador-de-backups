<?php
/**
 * webhook.php — Atualização Automática via GitHub Push
 *
 * Configure no GitHub:
 *   Settings → Webhooks → Add webhook
 *   Payload URL : https://seu-servidor.com/webhook.php
 *   Content type: application/json
 *   Secret      : o valor de WEBHOOK_SECRET no .env
 *   Events      : Just the push event
 */

$envFile = __DIR__ . '/.env';

function readWebhookEnv($path) {
    if (!file_exists($path)) return [];
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

$env = readWebhookEnv($envFile);

// Gera automaticamente o WEBHOOK_SECRET se ainda não existir
if (empty($env['WEBHOOK_SECRET'])) {
    $newSecret = bin2hex(random_bytes(20));
    $env['WEBHOOK_SECRET'] = $newSecret;

    $content = '';
    foreach ($env as $key => $value) {
        $content .= $key . '=' . $value . "\n";
    }
    file_put_contents($envFile, $content);
}

$WebhookSecret = $env['WEBHOOK_SECRET'];

// Garante que a requisição é um POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'M\u00e9todo n\u00e3o permitido. Use POST.']);
    exit;
}

$payload = file_get_contents('php://input');

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (empty($signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Assinatura ausente. Configure o WEBHOOK_SECRET no GitHub.']);
    exit;
}

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $WebhookSecret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Assinatura inv\u00e1lida. Requisi\u00e7\u00e3o n\u00e3o autorizada.']);
    exit;
}

$data = json_decode($payload, true);

$branch = $env['WEBHOOK_BRANCH'] ?? 'main';
$pushedBranch = $data['ref'] ?? '';

if ($pushedBranch !== 'refs/heads/' . $branch) {
    http_response_code(200);
    echo json_encode([
        'status'  => 'ignored',
        'message' => "Push ignorado. Branch recebida: {$pushedBranch}. Branch configurada: {$branch}."
    ]);
    exit;
}

$projectDir = escapeshellarg(__DIR__);
$command = "cd {$projectDir} && git pull origin " . escapeshellarg($branch) . " 2>&1";
$output = shell_exec($command);
$timestamp = date('Y-m-d H:i:s');

$logFile = __DIR__ . '/webhook.log';
$logEntry = "[{$timestamp}] Branch: {$branch} | Commit: " . substr($data['after'] ?? 'N/A', 0, 8) . "\n";
$logEntry .= "Autor: " . ($data['pusher']['name'] ?? 'Desconhecido') . "\n";
$logEntry .= "Mensagem: " . ($data['head_commit']['message'] ?? 'N/A') . "\n";
$logEntry .= "Git output:\n{$output}\n";
$logEntry .= str_repeat('-', 60) . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status'    => 'success',
    'message'   => 'git pull executado com sucesso.',
    'branch'    => $branch,
    'commit'    => substr($data['after'] ?? '', 0, 8),
    'timestamp' => $timestamp,
    'output'    => trim($output)
]);
