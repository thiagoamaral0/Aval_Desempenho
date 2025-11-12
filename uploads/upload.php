<?php
header("Content-Type: application/json");

// 1. Verifica se veio um arquivo na requisição
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado.']);
    exit;
}

// 2. Cria diretório de uploads, se não existir
$uploadDir = __DIR__ . '/../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 3. Define o nome e caminho do arquivo
$filename = basename($_FILES['file']['name']);
$filepath = $uploadDir . $filename;

// 4. Move o arquivo enviado para o diretório
if (!move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao salvar o arquivo.']);
    exit;
}

// 5. Caminho absoluto para o script importar.php
$importScript = __DIR__ . '/../importar.php';

// 6. Executa o script PHP internamente (sem HTTP)
ob_start();
$_GET['arquivo'] = $filename;
include($importScript);
$response = ob_get_clean();

// 7. Retorna resposta em JSON
echo json_encode([
    "message" => "Upload realizado e análise executada com sucesso.",
    "output" => $response
]);
