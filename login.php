<?php
require_once 'auth.php';

// Se já logado, redireciona para o index
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Verifica se deve mostrar o formulário 2FA
$show2faForm = isset($show2fa) && $show2fa === true;

// Verifica se há dica de senha
$envParams = readEnv(__DIR__ . '/.env');
$passHint = $envParams['APP_PASS_HINT'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gerenciador de Backups</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-base: #10131a;
            --surface-low: #191b23;
            --surface-container: #1d2027;
            --surface-high: #272a31;
            --surface-highest: #32353c;
            --primary: #adc6ff;
            --primary-dim: #005ac2;
            --primary-container: #4d8eff;
            --on-primary: #002e6a;
            --on-surface: #e1e2ec;
            --on-surface-variant: #c2c6d6;
            --outline: #8c909f;
            --glass-border: rgba(255, 255, 255, 0.1);
            --warning: #e67e22;
            --error: #ffb4ab;
            --error-container: #93000a;
            --danger: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: var(--on-surface);
            background: var(--bg-base);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        /* Atmospheric Background */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
            z-index: 0;
        }

        @keyframes bgShift {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-2%, 2%) rotate(3deg); }
        }

        main {
            width: 100%;
            max-w: 28rem;
            max-width: 28rem;
            padding: 0 1.5rem;
            position: relative;
            z-index: 1;
        }

        /* Glass Panel */
        .glass-panel {
            background: rgba(30, 41, 59, 0.55);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-icon {
            display: flex;
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .brand-icon .material-symbols-outlined {
            color: var(--primary);
            font-size: 48px;
            font-variation-settings: 'FILL' 1;
        }

        .brand-title {
            font-size: 32px;
            line-height: 1.2;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        .brand-subtitle {
            font-size: 14px;
            font-weight: 500;
            color: var(--on-surface-variant);
            margin-top: 0.5rem;
        }

        /* Form */
        .form-space { display: flex; flex-direction: column; gap: 1rem; }

        .field-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--on-surface-variant);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > .material-symbols-outlined {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--outline);
            font-size: 20px;
        }

        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            background: var(--surface-low);
            border: 1px solid var(--glass-border);
            border-radius: 0.5rem;
            color: var(--on-surface);
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-field::placeholder {
            color: rgba(140, 144, 159, 0.5);
        }

        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(173, 198, 255, 0.15);
        }

        .toggle-pass-btn {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--outline);
            cursor: pointer;
            transition: color 0.2s;
            padding: 0;
            display: flex;
        }

        .toggle-pass-btn:hover { color: var(--primary); }
        .toggle-pass-btn .material-symbols-outlined { font-size: 20px; }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: var(--on-primary);
            font-family: 'Inter', sans-serif;
            font-size: 20px;
            font-weight: 600;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }

        .submit-btn:hover { background: var(--primary-container); }
        .submit-btn:active { transform: scale(0.95); }

        .submit-btn.warning-mode {
            background: var(--warning);
            color: #311400;
        }

        .submit-btn.warning-mode:hover {
            background: #d4700a;
        }

        /* Support Links */
        .support-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
        }

        .support-link {
            font-size: 12px;
            font-weight: 300;
            color: var(--on-surface-variant);
            text-decoration: none;
            transition: color 0.2s;
        }

        .support-link:hover { color: var(--primary); }

        /* Error Message */
        .error-msg {
            background: rgba(147, 0, 10, 0.3);
            border: 1px solid rgba(255, 180, 171, 0.3);
            color: var(--error);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 14px;
            text-align: center;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* 2FA Section */
        .totp-hidden { display: none; }

        .totp-visible {
            display: block;
            animation: fadeUp 0.4s ease forwards;
        }

        .totp-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .totp-header .material-symbols-outlined {
            color: var(--warning);
        }

        .totp-header label {
            font-size: 14px;
            font-weight: 500;
            color: var(--warning);
        }

        .totp-description {
            font-size: 12px;
            color: var(--on-surface-variant);
            margin-bottom: 1rem;
        }

        .totp-input {
            width: 100%;
            padding: 1rem;
            background: var(--surface-highest);
            border: 2px solid rgba(230, 126, 34, 0.3);
            border-radius: 0.5rem;
            color: var(--on-surface);
            text-align: center;
            font-size: 32px;
            font-weight: 600;
            letter-spacing: 0.5em;
            font-family: monospace;
            outline: none;
            transition: all 0.3s ease;
        }

        .totp-input:focus {
            border-color: var(--warning);
        }

        .totp-input::placeholder {
            color: rgba(140, 144, 159, 0.3);
        }

        /* Credentials dimmed when 2FA is shown */
        .credentials-dimmed {
            opacity: 0.3;
            pointer-events: none;
            transform: scale(0.95);
            transition: all 0.5s ease;
        }

        /* Hint Box */
        .hint-toggle {
            color: var(--primary);
            font-size: 0.85rem;
            text-decoration: none;
            cursor: pointer;
        }

        .hint-toggle:hover { text-decoration: underline; }

        .hint-box {
            display: none;
            background: rgba(255,255,255,0.05);
            border: 1px dashed rgba(255,255,255,0.2);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #cbd5e1;
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
            background: var(--surface-container);
            border: 1px solid var(--glass-border);
            border-radius: 0.75rem;
            padding: 2rem;
            max-width: 360px;
            text-align: center;
            animation: fadeUp 0.3s ease forwards;
        }

        .future-modal-content .material-symbols-outlined {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 1rem;
            font-variation-settings: 'FILL' 1;
        }

        .future-modal-content h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--on-surface);
            margin-bottom: 0.5rem;
        }

        .future-modal-content p {
            font-size: 14px;
            color: var(--on-surface-variant);
            margin-bottom: 1.5rem;
        }

        .future-modal-close {
            padding: 0.5rem 1.5rem;
            background: var(--surface-high);
            border: 1px solid var(--glass-border);
            border-radius: 0.5rem;
            color: var(--on-surface);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .future-modal-close:hover { background: var(--surface-highest); }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Footer */
        .login-footer {
            margin-top: 2rem;
            text-align: center;
            opacity: 0.6;
        }

        .login-footer p {
            font-size: 12px;
            font-weight: 300;
            color: var(--on-surface-variant);
        }

        /* Spinner */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body>
    <main>
        <!-- Form Container -->
        <div class="glass-panel">
            <!-- Brand Header -->
            <div class="brand-header">
                <div class="brand-icon">
                    <span class="material-symbols-outlined">security</span>
                </div>
                <h1 class="brand-title">Gerenciador de Backups</h1>
                <p class="brand-subtitle">Acesse seu cofre de dados seguro</p>
            </div>

            <?php if(isset($error)): ?>
                <div class="error-msg" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login" method="POST" class="form-space" id="loginForm">

                <!-- Step 1: Credentials -->
                <div id="credentialsFields" class="form-space <?php echo $show2faForm ? 'credentials-dimmed' : ''; ?>">
                    <div>
                        <label class="field-label" for="username">Usuário</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined">person</span>
                            <input class="input-field" id="username" name="username" placeholder="ex: admin_backup" required type="text" autocomplete="username" <?php echo $show2faForm ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="password">Senha</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined">lock</span>
                            <input class="input-field" id="password" name="password" placeholder="••••••••" required type="password" autocomplete="current-password" style="padding-right: 3rem;" <?php echo $show2faForm ? 'disabled' : ''; ?>>
                            <button class="toggle-pass-btn" type="button" onclick="togglePassword()">
                                <span class="material-symbols-outlined" id="passIcon">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: 2FA (shown by PHP when credentials are valid and 2FA is enabled) -->
                <div id="totpSection" class="<?php echo $show2faForm ? 'totp-visible' : 'totp-hidden'; ?>">
                    <div class="totp-header">
                        <span class="material-symbols-outlined">verified_user</span>
                        <label for="totp_code">Verificação em Duas Etapas (2FA)</label>
                    </div>
                    <p class="totp-description">Insira o código de 6 dígitos gerado pelo seu aplicativo autenticador.</p>
                    <?php if ($show2faForm): ?>
                        <input class="totp-input" id="totp_code" name="totp_code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" required autocomplete="one-time-code">
                    <?php endif; ?>

                    <?php if(isset($error2fa)): ?>
                        <div class="error-msg" style="margin-top: 1rem;"><?php echo htmlspecialchars($error2fa); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <button class="submit-btn <?php echo $show2faForm ? 'warning-mode' : ''; ?>" id="submitBtn" type="submit">
                    <?php if($show2faForm): ?>
                        <span>Confirmar Acesso</span>
                        <span class="material-symbols-outlined">shield_check</span>
                    <?php else: ?>
                        <span>Entrar</span>
                        <span class="material-symbols-outlined">login</span>
                    <?php endif; ?>
                </button>

                <!-- Hint + Support Links -->
                <div style="text-align: center; margin-top: 0.5rem;">
                    <?php if($passHint && !$show2faForm): ?>
                        <a href="#" class="hint-toggle" onclick="document.getElementById('hint-box').style.display='block'; this.style.display='none'; return false;">Esqueci minha senha &#128161;</a>
                        <div id="hint-box" class="hint-box">
                            <strong>Dica:</strong> <?php echo htmlspecialchars($passHint); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if(!$show2faForm): ?>
                <div class="support-links">
                    <a class="support-link" href="#" onclick="showFutureModal('Redefinir Senha', 'A funcionalidade de redefinição de senha será adicionada em uma atualização futura.'); return false;">Esqueceu a senha?</a>
                    <a class="support-link" href="#" onclick="showFutureModal('Solicitar Acesso', 'A funcionalidade de solicitação de acesso será adicionada em uma atualização futura.'); return false;">Solicitar acesso</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Footer -->
        <footer class="login-footer">
            <p>© <?php echo date('Y'); ?> Gerenciador de Backups</p>
        </footer>
    </main>

    <!-- Future Feature Modal -->
    <div class="future-modal" id="futureModal">
        <div class="future-modal-content">
            <span class="material-symbols-outlined">construction</span>
            <h3 id="futureModalTitle">Em Breve</h3>
            <p id="futureModalText">Esta funcionalidade será adicionada em uma atualização futura.</p>
            <button class="future-modal-close" onclick="closeFutureModal()">Entendi</button>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passInput = document.getElementById('password');
            const passIcon = document.getElementById('passIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                passIcon.innerText = 'visibility_off';
            } else {
                passInput.type = 'password';
                passIcon.innerText = 'visibility';
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

        // Auto-focus on TOTP input when 2FA step is visible
        const totpInput = document.getElementById('totp_code');
        const totpSection = document.getElementById('totpSection');
        if (totpInput && totpSection.classList.contains('totp-visible')) {
            totpInput.focus();
        }

        // OTP auto-formatting: only allow digits
        if (totpInput) {
            totpInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 6);
            });
        }
    </script>
</body>
</html>
