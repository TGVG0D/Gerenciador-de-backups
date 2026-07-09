<?php
require_once 'auth.php';

// Bloqueia requisições de origens externas
$host = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Bloqueia acesso direto pelo navegador (só permite via Fetch/AJAX)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    http_response_code(404);
    include '404.php';
    exit;
}

if (!empty($origin) && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(404);
    include '404.php';
    exit;
}
if (!empty($referer) && parse_url($referer, PHP_URL_HOST) !== $host) {
    http_response_code(404);
    include '404.php';
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(404);
    include '404.php';
    exit;
}

header('Content-Type: application/json');

$file = 'dados.json';

// Ensure the file exists
if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return all backups (descriptografa antes de enviar)
    $data = decryptData(file_get_contents($file));
    echo json_encode($data);
    exit;
}

if ($method === 'POST') {
    // Add a new backup
    $input = file_get_contents('php://input');
    $newBackup = json_decode($input, true);

    if (isset($newBackup['nome'], $newBackup['link'], $newBackup['data'], $newBackup['tamanho'], $newBackup['informacao'])) {
        // Sanitização de Backend para prevenir XSS permanente no servidor
        $newBackup['nome'] = htmlspecialchars(strip_tags(trim($newBackup['nome'])));
        $newBackup['link'] = filter_var(trim($newBackup['link']), FILTER_SANITIZE_URL);
        $newBackup['data'] = htmlspecialchars(strip_tags(trim($newBackup['data'])));
        $newBackup['tamanho'] = htmlspecialchars(strip_tags(trim($newBackup['tamanho'])));
        $newBackup['informacao'] = htmlspecialchars(strip_tags(trim($newBackup['informacao'])));
        $newBackup['categoriaId'] = isset($newBackup['categoriaId']) && !empty($newBackup['categoriaId']) ? htmlspecialchars(strip_tags(trim($newBackup['categoriaId']))) : null;
        
        $newBackup['id'] = uniqid(); // Add a unique ID
        $newBackup['timestamp'] = time(); // Add a timestamp for sorting
        
        $currentData = decryptData(file_get_contents($file));
        
        // Add to beginning of array
        array_unshift($currentData, $newBackup);
        
        file_put_contents($file, encryptData($currentData));
        
        echo json_encode(['success' => true, 'message' => 'Backup salvo com sucesso.']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    }
    exit;
}

if ($method === 'PUT') {
    $input = file_get_contents('php://input');
    $updateReq = json_decode($input, true);

    if (isset($updateReq['id'], $updateReq['nome'], $updateReq['link'], $updateReq['data'], $updateReq['tamanho'], $updateReq['informacao'])) {
        $currentData = decryptData(file_get_contents($file));
        
        foreach ($currentData as &$item) {
            if ($item['id'] === $updateReq['id']) {
                $item['nome'] = htmlspecialchars(strip_tags(trim($updateReq['nome'])));
                $item['link'] = filter_var(trim($updateReq['link']), FILTER_SANITIZE_URL);
                $item['data'] = htmlspecialchars(strip_tags(trim($updateReq['data'])));
                $item['tamanho'] = htmlspecialchars(strip_tags(trim($updateReq['tamanho'])));
                $item['informacao'] = htmlspecialchars(strip_tags(trim($updateReq['informacao'])));
                $item['categoriaId'] = isset($updateReq['categoriaId']) && !empty($updateReq['categoriaId']) ? htmlspecialchars(strip_tags(trim($updateReq['categoriaId']))) : null;
                break;
            }
        }
        
        file_put_contents($file, encryptData($currentData));
        echo json_encode(['success' => true, 'message' => 'Backup atualizado com sucesso.']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados incompletos para atualização.']);
    }
    exit;
}

if ($method === 'DELETE') {
    // Delete a backup
    $input = file_get_contents('php://input');
    $deleteReq = json_decode($input, true);
    
    if(isset($deleteReq['id'])) {
        $currentData = decryptData(file_get_contents($file));
        $currentData = array_filter($currentData, function($item) use ($deleteReq) {
            return $item['id'] !== $deleteReq['id'];
        });
        
        file_put_contents($file, encryptData(array_values($currentData)));
        echo json_encode(['success' => true, 'message' => 'Backup removido.']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
?>
