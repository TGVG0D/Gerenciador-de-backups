<?php
// Configurações seguras de Sessão (baseadas no README)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificação de Instalação: Se .env não existe, redirecionar para instalação
if (!file_exists(__DIR__ . '/.env')) {
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    if ($currentScript !== 'install.php') {
        header('Location: install.php');
        exit;
    }
}

// Inclusões Globais para todas as páginas que requerem auth.php
if (file_exists(__DIR__ . '/crypto.php')) {
    require_once __DIR__ . '/crypto.php';
}
if (file_exists(__DIR__ . '/logger.php')) {
    require_once __DIR__ . '/logger.php';
}

/**
 * Função para ler o arquivo .env
 */
if (!function_exists('readEnv')) {
    function readEnv($path) {
        $env = [];
        if (!file_exists($path)) {
            return $env;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Remover aspas se existirem
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                }
                $env[$name] = $value;
            }
        }
        return $env;
    }
}

/**
 * Função para proteger páginas que exigem login
 */
if (!function_exists('checkLogin')) {
    function checkLogin() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: login.php');
            exit;
        }
    }
}

// ---------------------------------------------------------
// Processamento de Logout (via GET)
// Ex: auth.php?action=logout ou login.php?action=logout
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---------------------------------------------------------
// Processamento de Login (via POST)
// ---------------------------------------------------------
$error = null;
$show2fa = false;
$error2fa = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $envParams = readEnv(__DIR__ . '/.env');
    
    // Processamento da Senha e Usuário (Passo 1)
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // Simular um atraso para evitar Timing Attacks (especificado no README)
        sleep(1);
        
        $validUser = isset($envParams['APP_USER_HASH']) && password_verify($username, $envParams['APP_USER_HASH']);
        $validPass = isset($envParams['APP_PASS_HASH']) && password_verify($password, $envParams['APP_PASS_HASH']);
        
        if ($validUser && $validPass) {
            // Verificar se o TOTP está ativado
            if (!empty($envParams['TOTP_SECRET'])) {
                // Tem 2FA
                $_SESSION['pending_2fa'] = true;
                $show2fa = true;
            } else {
                // Login direto (sem 2FA)
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Usuário ou senha inválidos.';
        }
    }
    
    // Processamento do TOTP (Passo 2)
    if (isset($_POST['totp_code']) && isset($_SESSION['pending_2fa'])) {
        $totpCode = preg_replace('/\D/', '', $_POST['totp_code']);
        
        // Verifica se há o arquivo totp.php
        if (file_exists(__DIR__ . '/totp.php')) {
            require_once __DIR__ . '/totp.php';
            // Chama a função de verificação do TOTP caso ela exista
            if (function_exists('verifyTotp')) {
                if (verifyTotp($envParams['TOTP_SECRET'], $totpCode)) {
                    session_regenerate_id(true);
                    $_SESSION['logged_in'] = true;
                    unset($_SESSION['pending_2fa']);
                    header('Location: index.php');
                    exit;
                } else {
                    $error2fa = 'Código 2FA inválido.';
                    $show2fa = true;
                }
            } else {
                $error2fa = 'Sistema 2FA não configurado corretamente.';
                $show2fa = true;
            }
        } else {
            $error2fa = 'Sistema 2FA (totp.php) está ausente.';
            $show2fa = true;
        }
    }
}
?>
