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
        unset($env['APP_USER']);
        unset($env['APP_PASS']);
        
        if (writeEnv($envFile, $env)) {
            $message = "Credenciais atualizadas com sucesso! (criptografadas)";
            $messageType = "success";
            $currentUsername = '(criptografado)';
            logEvent('profile_updated', 'Credenciais de login alteradas.', [], 'SUCCESS');
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
                $message = "2FA ativado com sucesso!";
                $messageType = "success";
                $totpSecret = $secret;
                $totpEnabled = true;
                logEvent('2fa_enabled', 'Autenticação de dois fatores ativada.', [], 'SUCCESS');
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
        logEvent('2fa_disabled', 'Autenticação de dois fatores desativada.', [], 'WARNING');
    }
}

// --- Ação: Atualizar Dica de Senha ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_hint') {
    $hint = trim($_POST['pass_hint'] ?? '');
    if (empty($hint)) {
        unset($env['APP_PASS_HINT']);
        $message = "Dica de senha removida.";
    } else {
        $env['APP_PASS_HINT'] = $hint;
        $message = "Dica de senha salva.";
    }
    if (writeEnv($envFile, $env)) {
        $messageType = "success";
        logEvent('profile_updated', 'Dica de senha alterada.', [], 'INFO');
    }
}

// --- Ação: Atualizar Discord Webhook ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_discord') {
    $discordUrl = trim($_POST['discord_url'] ?? '');
    if (empty($discordUrl)) {
        unset($env['DISCORD_WEBHOOK_URL']);
        $message = "Discord Webhook removido.";
    } else {
        $env['DISCORD_WEBHOOK_URL'] = $discordUrl;
        $message = "Discord Webhook configurado.";
    }
    if (writeEnv($envFile, $env)) {
        $messageType = "success";
        logEvent('profile_updated', 'Discord Webhook alterado.', [], 'INFO');
    }
}

// --- Ação: Atualizar Deploy Automático ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_auto_deploy') {
    $repo = trim($_POST['auto_update_repo'] ?? '');
    $protected = trim($_POST['protected_files'] ?? '');
    
    if (empty($repo)) $repo = 'https://github.com/TGVG0D/Gerenciador-de-backups.git';
    $env['AUTO_UPDATE_REPO'] = $repo;
    $env['PROTECTED_FILES'] = empty($protected) ? '.env, dados.json, categorias.json, activity.log, update.log, last_update.txt' : $protected;
    
    if (writeEnv($envFile, $env)) {
        $message = "Configurações de Atualização Automática salvas.";
        $messageType = "success";
        logEvent('profile_updated', 'Configurações de Atualização Automática alteradas.', [], 'INFO');
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
<!DOCTYPE html><html lang="pt-BR" class="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=block" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"><script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script><script id="tailwind-config">try{
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "inverse-on-surface": "#2e3038",
                        "on-primary-fixed-variant": "#004395",
                        "secondary-fixed": "#d8e3fb",
                        "tertiary-fixed": "#ffdcc6",
                        "glass-border": "rgba(255, 255, 255, 0.1)",
                        "on-tertiary-fixed": "#311400",
                        "primary-fixed": "#d8e2ff",
                        "outline": "#8c909f",
                        "surface-variant": "#32353c",
                        "on-secondary-fixed-variant": "#3c475a",
                        "primary-fixed-dim": "#adc6ff",
                        "glass-bg": "rgba(30, 41, 59, 0.7)",
                        "tertiary": "#ffb786",
                        "surface-container": "#1d2027",
                        "on-secondary-fixed": "#111c2d",
                        "tertiary-fixed-dim": "#ffb786",
                        "warning": "#e67e22",
                        "surface-dim": "#10131a",
                        "secondary": "#bcc7de",
                        "on-tertiary": "#502400",
                        "primary": "#adc6ff",
                        "background": "#10131a",
                        "surface": "#10131a",
                        "on-tertiary-fixed-variant": "#723600",
                        "on-error": "#690005",
                        "inverse-primary": "#005ac2",
                        "bg-base": "#0f172a",
                        "surface-container-low": "#191b23",
                        "on-primary-fixed": "#001a42",
                        "surface-container-lowest": "#0b0e15",
                        "error": "#ffb4ab",
                        "secondary-container": "#3e495d",
                        "surface-container-highest": "#32353c",
                        "on-error-container": "#ffdad6",
                        "on-primary-container": "#00285d",
                        "on-background": "#e1e2ec",
                        "on-tertiary-container": "#461f00",
                        "success": "#2ecc71",
                        "hover-primary": "#2563eb",
                        "primary-container": "#4d8eff",
                        "on-secondary-container": "#aeb9d0",
                        "text-primary": "#f8fafc",
                        "outline-variant": "#424754",
                        "on-surface": "#e1e2ec",
                        "error-container": "#93000a",
                        "surface-container-high": "#272a31",
                        "on-primary": "#002e6a",
                        "on-secondary": "#263143",
                        "inverse-surface": "#e1e2ec",
                        "surface-bright": "#363941",
                        "danger": "#ef4444",
                        "tertiary-container": "#df7412",
                        "on-surface-variant": "#c2c6d6",
                        "secondary-fixed-dim": "#bcc7de",
                        "surface-tint": "#adc6ff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "gap-lg": "2rem",
                        "gap-sm": "1rem",
                        "gutter-grid": "16px",
                        "margin-page": "24px",
                        "gap-md": "1.5rem",
                        "gap-xs": "0.5rem"
                    },
                    "fontFamily": {
                        "body-base": ["Inter"],
                        "label-sm": ["Inter"],
                        "headline-md": ["Inter"],
                        "mono-code": ["monospace"],
                        "display-lg": ["Inter"]
                    },
                }
            }
        }
    }catch(_e){}</script><meta charset="utf-8">
    <style>
        .bg-mesh {
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
            animation: bgShift 25s ease-in-out infinite alternate;
            z-index: -1;
        }
        @keyframes bgShift {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-2%, 2%) rotate(3deg); }
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.55);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        /* Future Feature Modal */
        .future-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
        }
        .future-modal.active { display: flex; }
        .future-modal-content {
            background: #1d2027;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 2rem;
            max-width: 360px;
            text-align: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head><body class="text-on-background min-h-screen bg-background">
<div class="bg-mesh"></div>
<!-- Top Navigation -->
<header class="flex justify-between items-center w-full px-margin-page py-4 backdrop-blur-xl fixed top-0 z-50 bg-glass-bg border-b border-glass-border">
<div class="flex items-center gap-gap-sm">
<span class="font-display-lg text-display-lg font-bold text-primary">Gerenciador de Backups</span>
</div>
<nav class="hidden md:flex gap-gap-md">
<a href="index" class="text-on-surface-variant font-medium hover:bg-white/10 transition-all duration-300 px-4 py-2 rounded-lg">Dashboard</a>
<a href="index" class="text-on-surface-variant font-medium hover:bg-white/10 transition-all duration-300 px-4 py-2 rounded-lg">Backups</a>
<a href="#" onclick="showFutureModal('Segurança', 'Configurações adicionais de segurança avançadas estarão disponíveis nas próximas versões.'); return false;" class="text-on-surface-variant font-medium hover:bg-white/10 transition-all duration-300 px-4 py-2 rounded-lg">Segurança</a>
<a href="settings" class="text-primary font-bold border-b-2 border-primary px-4 py-2">Configurações</a>
</nav>
<div class="flex gap-gap-sm">
<a href="settings" class="material-symbols-outlined p-2 rounded-full hover:bg-white/10 transition-all text-on-surface-variant">account_circle</a>
<a href="auth.php?action=logout" class="material-symbols-outlined p-2 rounded-full hover:bg-white/10 transition-all text-error">logout</a>
</div>
</header>
<!-- Side Navigation (Web) -->
<aside class="fixed left-0 top-0 h-screen w-64 flex flex-col p-gap-sm z-40 bg-glass-bg border-r border-glass-border pt-24 hidden lg:flex">
<div class="mb-gap-lg px-4">
<h2 class="font-headline-md text-headline-md font-extrabold text-primary">Gerenciador</h2>
<p class="text-label-sm font-label-sm text-on-surface-variant">Backup Seguro</p>
</div>
<div class="flex flex-col gap-gap-xs flex-1">
<a href="index" class="flex items-center gap-gap-sm px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-colors duration-300 rounded-lg">
<span class="material-symbols-outlined">dashboard</span>
<span class="font-label-sm text-label-sm">Dashboard</span>
</a>
<a href="index" class="flex items-center gap-gap-sm px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-colors duration-300 rounded-lg">
<span class="material-symbols-outlined">backup</span>
<span class="font-label-sm text-label-sm">Backups</span>
</a>
<a href="#" onclick="showFutureModal('Segurança Avançada', 'Aba dedicada para políticas de IP e controle de tentativas de ataque em desenvolvimento.'); return false;" class="flex items-center gap-gap-sm px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-colors duration-300 rounded-lg">
<span class="material-symbols-outlined">shield</span>
<span class="font-label-sm text-label-sm">Segurança</span>
</a>
<a href="settings" class="flex items-center gap-gap-sm px-4 py-3 bg-primary-container text-on-primary-container rounded-lg font-bold">
<span class="material-symbols-outlined">settings</span>
<span class="font-label-sm text-label-sm">Configurações</span>
</a>
</div>
<a href="index" class="mt-auto bg-primary text-on-primary font-bold py-3 px-4 rounded-xl flex items-center justify-center gap-2 active:scale-95 transition-transform text-center no-underline">
<span class="material-symbols-outlined">add</span>
            Novo Backup
</a>
</aside>
<!-- Main Content -->
<main class="lg:ml-64 pt-24 pb-12 px-margin-page flex flex-col items-center">
<div class="max-w-2xl w-full space-y-gap-md">

    <?php if(!empty($message)): ?>
        <div class="p-4 rounded-lg text-center font-medium text-sm <?php echo $messageType === 'success' ? 'bg-success/15 text-success border border-success/25' : 'bg-error/15 text-error border border-error/25'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

<!-- Alterar Login e Senha -->
<section class="glass-card p-6 rounded-xl space-y-4">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-primary">lock</span>
<h3 class="font-headline-md text-headline-md font-bold">Alterar Login e Senha</h3>
</div>
<form method="POST" action="settings" class="space-y-4">
<input type="hidden" name="action" value="update_credentials">
<div>
<label class="block text-label-sm font-label-sm text-on-surface-variant mb-2" for="new_username">Nome Usuário</label>
<input type="text" id="new_username" name="new_username" placeholder="Digite o novo usuário" required autocomplete="username" class="w-full bg-surface-container border border-glass-border rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
</div>
<div>
<label class="block text-label-sm font-label-sm text-on-surface-variant mb-2" for="new_password">Nova Senha</label>
<div class="relative">
<input type="password" id="new_password" name="new_password" placeholder="Digite a nova senha" required autocomplete="new-password" class="w-full bg-surface-container border border-glass-border rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
<button class="absolute right-3 top-2 text-on-surface-variant" type="button" onclick="togglePasswordVisibility('new_password', this)">👁️</button>
</div>
</div>
<button type="submit" class="w-full bg-primary-container text-on-primary-container font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.98] transition-all">
    Salvar Credenciais (Criptografado)
</button>
</form>
</section>

<!-- Dica de Senha -->
<section class="glass-card p-6 rounded-xl space-y-4">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-primary">help</span>
<h3 class="font-headline-md text-headline-md font-bold">Dica de Senha (Opcional)</h3>
</div>
<p class="text-label-sm font-label-sm text-on-surface-variant">Crie uma frase que lembre a sua senha. Ela ficará visível na tela de login para te ajudar, caso você esqueça.</p>
<form method="POST" action="settings" class="space-y-4">
<input type="hidden" name="action" value="update_hint">
<input type="text" id="pass_hint" name="pass_hint" value="<?php echo htmlspecialchars($env['APP_PASS_HINT'] ?? ''); ?>" placeholder="Ex: Nome do meu primeiro cachorro e ano de nascimento" class="w-full bg-surface-container border border-glass-border rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
<button type="submit" class="w-full bg-primary-container text-on-primary-container font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.98] transition-all">
    Salvar Dica
</button>
</form>
</section>

<!-- Notificações no Discord (Logs) -->
<section class="glass-card p-6 rounded-xl space-y-4">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-primary">notifications</span>
<h3 class="font-headline-md text-headline-md font-bold">Notificações no Discord (Logs)</h3>
</div>
<p class="text-label-sm font-label-sm text-on-surface-variant">Cole a URL de webhook de seu servidor do Discord para receber alertas de login, backups e alterações.</p>
<form method="POST" action="settings" class="space-y-4">
<input type="hidden" name="action" value="update_discord">
<input type="url" id="discord_url" name="discord_url" value="<?php echo htmlspecialchars($env['DISCORD_WEBHOOK_URL'] ?? ''); ?>" placeholder="https://discord.com/api/webhooks/..." class="w-full bg-surface-container border border-glass-border rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<button type="submit" class="bg-primary-container text-on-primary-container font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.98] transition-all">
    Salvar Webhook
</button>
<a href="logs" class="bg-surface-container-high text-on-surface font-bold py-3 rounded-lg border border-glass-border hover:bg-white/5 active:scale-[0.98] transition-all flex items-center justify-center gap-2 text-center no-underline">
    <span class="material-symbols-outlined">visibility</span>
    Visualizar Logs no Navegador
</a>
</div>
</form>
</section>

<!-- Atualização Automática -->
<section class="glass-card p-6 rounded-xl space-y-4">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-primary">update</span>
<h3 class="font-headline-md text-headline-md font-bold">Atualização Automática (A cada 12h)</h3>
</div>
<p class="text-label-sm font-label-sm text-on-surface-variant">O servidor buscará atualizações silenciosamente a cada 12 horas baseando-se no repositório abaixo.</p>
<form method="POST" action="settings" class="space-y-4">
<input type="hidden" name="action" value="update_auto_deploy">
<div>
<label class="block text-label-sm font-label-sm text-on-surface-variant mb-2 font-bold" for="auto_update_repo">URL do Repositório Git (Padrão Oficial)</label>
<input type="text" id="auto_update_repo" name="auto_update_repo" value="<?php echo htmlspecialchars($env['AUTO_UPDATE_REPO'] ?? 'https://github.com/TGVG0D/Gerenciador-de-backups.git'); ?>" class="w-full bg-surface-container border border-glass-border rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
<p class="text-caption font-caption text-on-surface-variant mt-1">Modifique apenas se você fez um fork do repositório para o seu próprio servidor.</p>
</div>
<div>
<label class="block text-label-sm font-label-sm text-on-surface-variant mb-2 font-bold" for="protected_files">Arquivos Protegidos (Separados por vírgula)</label>
<textarea rows="3" id="protected_files" name="protected_files" class="w-full bg-surface-container border border-glass-border rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all font-mono-code text-mono-code"><?php echo htmlspecialchars($env['PROTECTED_FILES'] ?? ".env, dados.json, categorias.json, activity.log, update.log, last_update.txt"); ?></textarea>
<p class="text-caption font-caption text-on-surface-variant mt-1">Estes arquivos e seus dados NUNCA serão sobrescritos pelas atualizações.</p>
</div>
<button type="submit" class="w-full bg-surface-container-high text-on-surface font-bold py-3 rounded-lg border border-glass-border hover:bg-white/5 active:scale-[0.98] transition-all">
    Salvar Configurações de Atualização
</button>
</form>
</section>

<!-- Autenticação de Dois Fatores (2FA) -->
<section class="glass-card p-6 rounded-xl space-y-6">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary">verified_user</span>
<h3 class="font-headline-md text-headline-md font-bold">Autenticação de Dois Fatores (2FA)</h3>
</div>
<?php if($totpEnabled): ?>
    <span class="bg-success/20 text-success text-[10px] uppercase font-bold px-2 py-0.5 rounded">Ativo</span>
<?php else: ?>
    <span class="bg-error/20 text-error text-[10px] uppercase font-bold px-2 py-0.5 rounded">Inativo</span>
<?php endif; ?>
</div>

<?php if($totpEnabled): ?>
    <p class="text-label-sm font-label-sm text-on-surface-variant">O 2FA está ativo. Cada vez que você fizer login, será necessário informar o código gerado pelo aplicativo autenticador.</p>
    <form method="POST" action="settings" onsubmit="return confirm('Tem certeza que deseja desativar o 2FA? Isso reduzirá a segurança da sua conta.');">
        <input type="hidden" name="action" value="disable_2fa">
        <button type="submit" class="w-full bg-error text-on-primary font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.98] transition-all">
            Desativar 2FA
        </button>
    </form>
<?php else: ?>
    <p class="text-label-sm font-label-sm text-on-surface-variant">Proteja sua conta com uma camada extra de segurança usando o Google Authenticator.</p>
    <div class="space-y-3">
    <div class="flex items-start gap-3">
    <span class="bg-primary text-on-primary w-5 h-5 flex items-center justify-center rounded-full text-[12px] font-bold mt-0.5">1</span>
    <p class="text-label-sm font-label-sm">Baixe o <b>Google Authenticator</b> no seu celular</p>
    </div>
    <div class="flex items-start gap-3">
    <span class="bg-primary text-on-primary w-5 h-5 flex items-center justify-center rounded-full text-[12px] font-bold mt-0.5">2</span>
    <p class="text-label-sm font-label-sm">Escaneie o <b>QR Code</b> abaixo ou digite a chave manualmente</p>
    </div>
    <div class="flex items-start gap-3">
    <span class="bg-primary text-on-primary w-5 h-5 flex items-center justify-center rounded-full text-[12px] font-bold mt-0.5">3</span>
    <p class="text-label-sm font-label-sm">Digite o código de 6 dígitos gerado pelo app para confirmar</p>
    </div>
    </div>
    <div class="bg-white p-4 rounded-xl max-w-[200px] mx-auto">
        <img class="w-full aspect-square object-contain" alt="QR Code 2FA" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=M&data=<?php echo urlencode($qrUrl); ?>">
    </div>
    <div class="text-center space-y-2">
    <p class="text-caption font-caption text-on-surface-variant">Ou digite manualmente a chave secreta:</p>
    <code class="bg-surface-container px-4 py-2 rounded font-mono-code text-primary tracking-widest text-body-base border border-glass-border"><?php echo htmlspecialchars($newSecret); ?></code>
    </div>
    <form method="POST" action="settings" class="space-y-4">
        <input type="hidden" name="action" value="enable_2fa">
        <input type="hidden" name="totp_secret" value="<?php echo htmlspecialchars($newSecret); ?>">
        <div>
        <label class="block text-center text-label-sm font-label-sm text-on-surface-variant mb-2">Código de Verificação</label>
        <div class="flex justify-center gap-2">
            <input class="w-full max-w-[240px] bg-surface-container border border-glass-border rounded-lg px-4 py-3 text-center text-xl tracking-[1em] focus:border-primary outline-none transition-all" maxlength="6" id="verify_code" name="verify_code" placeholder="000000" type="text" required inputmode="numeric">
        </div>
        </div>
        <button type="submit" class="w-full bg-primary text-on-primary font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.98] transition-all">
            Ativar 2FA
        </button>
    </form>
<?php endif; ?>
</section>

</div>
</main>

<!-- Floating Action Button - Mobile Contextual -->
<a href="index" class="md:hidden fixed bottom-6 right-6 bg-primary text-on-primary w-14 h-14 rounded-full shadow-lg flex items-center justify-center z-50 text-center no-underline">
    <span class="material-symbols-outlined">arrow_back</span>
</a>

<!-- Future Feature Modal -->
<div class="future-modal" id="futureModal">
    <div class="future-modal-content">
        <span class="material-symbols-outlined text-[48px] text-primary block mb-4">construction</span>
        <h3 id="futureModalTitle" class="text-lg font-bold text-on-surface mb-2">Em Breve</h3>
        <p id="futureModalText" class="text-sm text-on-surface-variant mb-6">Esta funcionalidade será adicionada em uma atualização futura.</p>
        <button class="future-modal-close" onclick="closeFutureModal()">Entendi</button>
    </div>
</div>

<script>
    // Micro-interaction for input focus effects
    const inputs = document.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('scale-[1.01]');
            input.parentElement.classList.add('transition-transform');
        });
        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('scale-[1.01]');
        });
    });

    // Toggle password visibility
    function togglePasswordVisibility(fieldId, btn) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
            btn.textContent = '🙈';
        } else {
            field.type = 'password';
            btn.textContent = '👁️';
        }
    }

    // Future feature modal
    function showFutureModal(title, text) {
        document.getElementById('futureModalTitle').textContent = title;
        document.getElementById('futureModalText').textContent = text;
        document.getElementById('futureModal').classList.add('active');
    }

    function closeFutureModal() {
        document.getElementById('futureModal').classList.remove('active');
    }

    document.getElementById('futureModal').addEventListener('click', function(e) {
        if (e.target === this) closeFutureModal();
    });

    // Format TOTP code input
    const totpInput = document.getElementById('verify_code');
    if (totpInput) {
        totpInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 6);
        });
    }
</script>
</body></html>
