<?php
require_once 'auth.php';

// Se já logado, redireciona para o index
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Verifica se deve mostrar o formulário 2FA
$show2faForm = isset($show2fa) && $show2fa === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gerenciador de Backups</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-step { display: none; }
        .login-step.active { display: block; }
        .totp-input {
            text-align: center;
            font-size: 2rem;
            letter-spacing: 0.8rem;
            font-weight: 600;
            padding: 1rem;
        }
        .shield-icon {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .shield-icon svg {
            width: 64px;
            height: 64px;
            color: var(--primary-color);
            filter: drop-shadow(0 0 12px rgba(59, 130, 246, 0.4));
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        .step-dot.active {
            background: var(--primary-color);
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container glass">
        <!-- Step indicator -->
        <div class="step-indicator">
            <div class="step-dot <?php echo !$show2faForm ? 'active' : ''; ?>"></div>
            <div class="step-dot <?php echo $show2faForm ? 'active' : ''; ?>"></div>
        </div>

        <!-- Step 1: Login -->
        <div class="login-step <?php echo !$show2faForm ? 'active' : ''; ?>" id="step-login">
            <header style="margin-bottom: 2rem;">
                <h1><span class="highlight">G</span>erenciador</h1>
                <p>Faça login para continuar</p>
            </header>

            <?php if(isset($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login" id="login-form">
                <div class="form-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit">Entrar</button>
            </form>
        </div>

        <!-- Step 2: 2FA -->
        <div class="login-step <?php echo $show2faForm ? 'active' : ''; ?>" id="step-2fa">
            <div class="shield-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
            </div>
            <header style="margin-bottom: 2rem; text-align: center;">
                <h1 style="font-size: 1.5rem;">Verificação <span class="highlight">2FA</span></h1>
                <p style="color: #94a3b8; font-size: 0.9rem;">Digite o código de 6 dígitos do<br>Google Authenticator</p>
            </header>

            <?php if(isset($error2fa)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error2fa); ?></div>
            <?php endif; ?>

            <form method="POST" action="login" id="totp-form">
                <div class="form-group">
                    <input type="text" id="totp_code" name="totp_code" class="totp-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" required autocomplete="one-time-code">
                </div>
                <button type="submit" class="btn-submit">Verificar</button>
            </form>
        </div>
    </div>

    <script>
        // Auto-focus no campo de código 2FA quando visível
        const totpInput = document.getElementById('totp_code');
        if (totpInput && totpInput.closest('.login-step').classList.contains('active')) {
            totpInput.focus();
        }
    </script>
</body>
</html>
