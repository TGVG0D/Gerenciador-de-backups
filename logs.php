<?php
require_once 'auth.php';
checkLogin();

$logFile = __DIR__ . '/activity.log';
$logs = [];
if (file_exists($logFile)) {
    $raw = file_get_contents($logFile);
    if (!empty($raw) && $raw[0] === '{') {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded) $logs[] = $decoded;
        }
    } else {
        $logs = decryptData($raw);
    }
    if (is_array($logs)) {
        $logs = array_reverse($logs); // Mais recentes primeiro
    } else {
        $logs = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .log-container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .log-table { width: 100%; border-collapse: collapse; background: var(--glass-bg); border-radius: 12px; overflow: hidden; border: 1px solid var(--glass-border); }
        .log-table th, .log-table td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); color: #e2e8f0; font-size: 0.9rem; }
        .log-table th { background: rgba(0,0,0,0.2); font-weight: 600; color: #fff; }
        .log-table tr:last-child td { border-bottom: none; }
        .log-table tr:hover { background: rgba(255,255,255,0.02); }
        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .bg-INFO { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .bg-SUCCESS { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
        .bg-WARNING { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .bg-ERROR { background: rgba(255, 71, 87, 0.15); color: #ff4757; }
        .extra-data { font-family: monospace; font-size: 0.8rem; color: #94a3b8; margin-top: 0.5rem; background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="log-container">
        <header style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1><span class="highlight">L</span>ogs do Sistema</h1>
                <p style="color: #94a3b8;">Histórico de atividades recentes no servidor.</p>
            </div>
            <a href="profile.php" class="btn-submit" style="text-decoration:none; display:inline-block;">Voltar para o Perfil</a>
        </header>

        <?php if(empty($logs)): ?>
            <div style="text-align:center; padding: 3rem; background: var(--glass-bg); border-radius: 12px; border: 1px solid var(--glass-border); color: #94a3b8;">
                Nenhum log encontrado. As atividades começarão a aparecer aqui.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Nível</th>
                            <th>Mensagem</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
                                <td><span class="badge bg-<?= htmlspecialchars($log['level'] ?? 'INFO') ?>"><?= htmlspecialchars($log['level'] ?? 'INFO') ?></span></td>
                                <td>
                                    <?= htmlspecialchars($log['message'] ?? '') ?>
                                    <?php if(!empty($log['extra'])): ?>
                                        <div class="extra-data">
                                            <?php foreach($log['extra'] as $k => $v): ?>
                                                <strong><?= htmlspecialchars($k) ?>:</strong> <?= htmlspecialchars($v) ?><br>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['ip'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
