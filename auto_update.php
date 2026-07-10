<?php
/**
 * auto_update.php
 * Verifica atualizações a cada 12 horas puxando do repositório público.
 */

// Permite execução longa se necessário
set_time_limit(300);

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/crypto.php';

$env = readEnv(__DIR__ . '/.env');

$lastUpdateFile = __DIR__ . '/last_update.txt';
$intervalo = 12 * 60 * 60; // 12 horas em segundos

// Verifica se já passaram 12 horas
if (file_exists($lastUpdateFile)) {
    $ultimo = (int)file_get_contents($lastUpdateFile);
    if (time() - $ultimo < $intervalo) {
        // Ainda não passou 12 horas
        exit;
    }
}

// Atualiza o timestamp para evitar execuções paralelas
file_put_contents($lastUpdateFile, time());

// Atualiza o arquivo exclude do git com a lista do painel
$protectedStr = $env['PROTECTED_FILES'] ?? '.env, dados.json, categorias.json, activity.log, update.log, last_update.txt';
$protectedList = array_filter(array_map('trim', explode(',', $protectedStr)));

$excludeDir = __DIR__ . '/.git/info';
if (is_dir($excludeDir)) {
    file_put_contents($excludeDir . '/exclude', implode("\n", $protectedList) . "\n");
}

$repoUrl = $env['AUTO_UPDATE_REPO'] ?? 'https://github.com/TGVG0D/Gerenciador-de-backups.git';
$branch = 'main';
$projectDir = escapeshellarg(__DIR__);

// Comandos do git
// 1. Fetch
// 2. Count commits behind
// 3. Reset se necessário
$command = "cd {$projectDir} && git fetch " . escapeshellarg($repoUrl) . " " . escapeshellarg($branch) . " 2>&1";
$outputFetch = shell_exec($command);

$commandStatus = "cd {$projectDir} && git rev-list HEAD...FETCH_HEAD --count 2>&1";
$outputStatus = shell_exec($commandStatus);
$commitsAtras = (int)trim($outputStatus);

if ($commitsAtras > 0) {
    $commandReset = "cd {$projectDir} && git reset --hard FETCH_HEAD 2>&1";
    $outputReset = shell_exec($commandReset);
    
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/update.log';
    
    $logEntry = "[{$timestamp}] Atualização executada! Commits novos: {$commitsAtras}\n";
    $logEntry .= "Git Fetch Output:\n{$outputFetch}\n";
    $logEntry .= "Git Reset Output:\n{$outputReset}\n";
    $logEntry .= str_repeat('-', 60) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    logEvent('system_update', 'O sistema foi atualizado com a versão mais recente do GitHub.', [
        'Commits'  => $commitsAtras,
        'Log'      => substr(trim($outputReset), 0, 100) . '...'
    ], 'SUCCESS');
} else {
    // Sem atualizações
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/update.log';
    
    $logEntry = "[{$timestamp}] Verificação de atualização: Nenhum commit novo encontrado.\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
