<?php
require_once 'auth.php';
checkLogin();

$message = '';
$messageType = '';

$envFile = __DIR__ . '/.env';
$env = readEnv($envFile);

// Detecta se está usando hash ou texto puro
$currentUsername = '';
if (isset($env['APP_USER_HASH'])) {
    $currentUsername = '(criptografado)';
} else {
    $currentUsername = $env['APP_USER'] ?? 'admin';
}

$totpSecret = $env['TOTP_SECRET'] ?? '';
$totpEnabled = !empty($totpSecret);

// --- Ação: Alterar Credenciais ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_credentials') {
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if (!empty($newUsername) && !empty($newPassword)) {
        $env['APP_USER_HASH'] = password_hash($newUsername, PASSWORD_DEFAULT);
        $env['APP_PASS_HASH'] = password_hash($newPassword, PASSWORD_DEFAULT);
        // Remove versões em texto puro se existirem
        unset($env['APP_USER']);
        unset($env['APP_PASS']);
        
        if (writeEnv($envFile, $env)) {
            $message = "Credenciais atualizadas com sucesso! (criptografadas)";
            $messageType = "success";
            $currentUsername = '(criptografado)';
        } else {
            $message = "Erro ao salvar. Verifique as permissões do .env.";
            $messageType = "error";
        }
    } else {
        $message = "Os campos não podem estar vazios.";
        $messageType = "error";
    }
}

// --- Ação: Ativar 2FA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enable_2fa') {
    $secret = $_POST['totp_secret'] ?? '';
    $code = trim($_POST['verify_code'] ?? '');

    if (!empty($secret) && !empty($code)) {
        if (SimpleTOTP::verify($secret, $code)) {
            $env['TOTP_SECRET'] = $secret;
            if (writeEnv($envFile, $env)) {
                $message = "2FA ativado com sucesso! Use o Google Authenticator para os próximos logins.";
                $messageType = "success";
                $totpSecret = $secret;
                $totpEnabled = true;
            } else {
                $message = "Erro ao salvar configurações.";
                $messageType = "error";
            }
        } else {
            $message = "Código de verificação incorreto. Tente novamente.";
            $messageType = "error";
        }
    }
}

// --- Ação: Desativar 2FA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable_2fa') {
    unset($env['TOTP_SECRET']);
    if (writeEnv($envFile, $env)) {
        $message = "2FA desativado com sucesso.";
        $messageType = "success";
        $totpSecret = '';
        $totpEnabled = false;
    }
}

// --- Gerar novo segredo para configuração ---
$newSecret = '';
$qrUrl = '';
if (!$totpEnabled) {
    $newSecret = SimpleTOTP::generateSecret();
    $appName = 'Gerenciador de Backups';
    $qrUrl = 'otpauth://totp/' . urlencode($appName) . '?secret=' . $newSecret . '&issuer=' . urlencode($appName);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Gerenciador de Backups</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?=time()?>">
    <style>
        .msg {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        .msg.success {
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.2);
        }
        .msg.error {
            background: rgba(255, 71, 87, 0.1);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.2);
        }
        .profile-container {
            max-width: 550px;
            margin: 0 auto;
        }
        .section-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .section-card h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-active {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
        }
        .badge-inactive {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
        }
        .qr-container {
            text-align: center;
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: #fff;
            border-radius: 12px;
        }
        .qr-container img {
            max-width: 200px;
        }
        .secret-key {
            text-align: center;
            margin: 1rem 0;
            padding: 0.75rem;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            letter-spacing: 0.15rem;
            color: var(--primary-color);
            word-break: break-all;
        }
        .totp-verify-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: 600;
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background: #ef4444;
            color: #fff;
        }
        .setup-steps {
            margin: 1rem 0;
            padding-left: 0;
            list-style: none;
            counter-reset: step-counter;
        }
        .setup-steps li {
            counter-increment: step-counter;
            padding: 0.5rem 0 0.5rem 2.5rem;
            position: relative;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        .setup-steps li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.2);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><span class="highlight">P</span>erfil do <span class="highlight">U</span>suário</h1>
            <p>Gerencie suas credenciais e segurança.</p>
            <div style="display: flex; gap: 1rem; align-items: center; margin-top: 1rem; justify-content: center;">
                <a href="index.php" class="btn-logout" style="background: rgba(255,255,255,0.1); color: #fff; text-decoration: none;">Voltar</a>
                <a href="auth.php?action=logout" class="btn-logout" style="text-decoration: none;">Sair</a>
            </div>
        </header>

        <main>
            <div class="profile-container">
                <?php if($message): ?>
                    <div class="msg <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Seção: Alterar Credenciais -->
                <div class="section-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Alterar Login e Senha
                    </h3>
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="update_credentials">
                        <div class="form-group">
                            <label for="new_username">Novo Usuário</label>
                            <input type="text" id="new_username" name="new_username" placeholder="Digite o novo usuário" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nova Senha</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Digite a nova senha" required>
                        </div>
                        <button type="submit" class="btn-submit" style="width: 100%;">Salvar Credenciais (Criptografado)</button>
                    </form>
                </div>

                <!-- Seção: 2FA -->
                <div class="section-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                        Autenticação de Dois Fatores (2FA)
                        <?php if($totpEnabled): ?>
                            <span class="badge badge-active">Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inativo</span>
                        <?php endif; ?>
                    </h3>

                    <?php if($totpEnabled): ?>
                        <!-- 2FA Ativado -->
                        <p style="color: #94a3b8; margin-bottom: 1rem; font-size: 0.9rem;">
                            O 2FA está ativo. Cada vez que você fizer login, será necessário informar o código do Google Authenticator.
                        </p>
                        <form method="POST" action="profile.php" onsubmit="return confirm('Tem certeza que deseja desativar o 2FA? Isso reduzirá a segurança da sua conta.');">
                            <input type="hidden" name="action" value="disable_2fa">
                            <button type="submit" class="btn-danger">Desativar 2FA</button>
                        </form>

                    <?php else: ?>
                        <!-- 2FA Desativado - Configurar -->
                        <p style="color: #94a3b8; margin-bottom: 1rem; font-size: 0.9rem;">
                            Proteja sua conta com uma camada extra de segurança usando o Google Authenticator.
                        </p>

                        <ol class="setup-steps">
                            <li>Baixe o <strong>Google Authenticator</strong> no seu celular</li>
                            <li>Escaneie o QR Code abaixo ou digite a chave manualmente</li>
                            <li>Digite o código de 6 dígitos gerado pelo app para confirmar</li>
                        </ol>

                        <!-- QR Code via Google Charts API -->
                        <div class="qr-container">
                            <img src="https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=<?php echo urlencode($qrUrl); ?>" alt="QR Code 2FA">
                        </div>

                        <p style="color: #94a3b8; font-size: 0.8rem; text-align: center;">Ou digite manualmente a chave secreta:</p>
                        <div class="secret-key"><?php echo htmlspecialchars($newSecret); ?></div>

                        <form method="POST" action="profile.php" style="margin-top: 1.5rem;">
                            <input type="hidden" name="action" value="enable_2fa">
                            <input type="hidden" name="totp_secret" value="<?php echo htmlspecialchars($newSecret); ?>">
                            <div class="form-group">
                                <label for="verify_code">Código de Verificação</label>
                                <input type="text" id="verify_code" name="verify_code" class="totp-verify-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" required>
                            </div>
                            <button type="submit" class="btn-submit" style="width: 100%;">Ativar 2FA</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
