<?php
require_once 'auth.php';

// Bloqueia requisições de origens externas
$host = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

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

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(404);
    include '404.php';
    exit;
}

header('Content-Type: application/json');

$file = 'categorias.json';

// Ensure the file exists
if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $data = decryptData(file_get_contents($file));
    echo json_encode($data);
    exit;
}

if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $newCat = json_decode($input, true);

    if (isset($newCat['nome'])) {
        $newCat['nome'] = htmlspecialchars(strip_tags(trim($newCat['nome'])));
        $newCat['parentId'] = isset($newCat['parentId']) && !empty($newCat['parentId']) ? htmlspecialchars(strip_tags(trim($newCat['parentId']))) : null;
        $newCat['id'] = uniqid('cat_');
        
        $currentData = decryptData(file_get_contents($file));
        $currentData[] = $newCat;
        
        file_put_contents($file, encryptData($currentData));
        echo json_encode(['success' => true, 'message' => 'Categoria salva.', 'id' => $newCat['id']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome da categoria é obrigatório.']);
    }
    exit;
}

if ($method === 'PUT') {
    $input = file_get_contents('php://input');
    $updateReq = json_decode($input, true);

    if (isset($updateReq['id'], $updateReq['nome'])) {
        $currentData = decryptData(file_get_contents($file));
        
        foreach ($currentData as &$item) {
            if ($item['id'] === $updateReq['id']) {
                $item['nome'] = htmlspecialchars(strip_tags(trim($updateReq['nome'])));
                $item['parentId'] = isset($updateReq['parentId']) && !empty($updateReq['parentId']) ? htmlspecialchars(strip_tags(trim($updateReq['parentId']))) : null;
                break;
            }
        }
        
        file_put_contents($file, encryptData($currentData));
        echo json_encode(['success' => true, 'message' => 'Categoria atualizada.']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    }
    exit;
}

if ($method === 'DELETE') {
    $input = file_get_contents('php://input');
    $deleteReq = json_decode($input, true);
    
    if(isset($deleteReq['id'])) {
        $idToDelete = $deleteReq['id'];
        $currentData = decryptData(file_get_contents($file));
        
        // Delete cascading: delete the category and its subcategories
        $idsToDelete = [$idToDelete];
        foreach ($currentData as $cat) {
            if ($cat['parentId'] === $idToDelete) {
                $idsToDelete[] = $cat['id'];
            }
        }

        $currentData = array_filter($currentData, function($item) use ($idsToDelete) {
            return !in_array($item['id'], $idsToDelete);
        });
        
        file_put_contents($file, encryptData(array_values($currentData)));
        echo json_encode(['success' => true, 'message' => 'Categoria removida.']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
?>
