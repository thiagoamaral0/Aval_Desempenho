<?php
header('Content-Type: application/json; charset=utf-8');

$response = [
    'arquivo_modificado' => false,
    'arquivo' => null,
    'ultima_modificacao' => null,
    'mensagem' => ''
];

try {
    // Verificar se a pasta existe
    $csv_dir = __DIR__ . '/importedCSvs/';
    
    if (!file_exists($csv_dir) || !is_dir($csv_dir)) {
        throw new Exception('Pasta importedCSvs não encontrada');
    }
    
    // Procurar arquivo CSV
    $csv_file = null;
    $files = scandir($csv_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
            $csv_file = $file;
            break;
        }
    }
    
    if (!$csv_file) {
        $response['mensagem'] = 'Nenhum arquivo CSV encontrado';
        echo json_encode($response);
        exit;
    }
    
    $file_path = $csv_dir . $csv_file;
    
    // Verificar se o arquivo existe
    if (!file_exists($file_path)) {
        throw new Exception('Arquivo CSV não encontrado: ' . $csv_file);
    }
    
    // Obter última modificação do arquivo
    $ultima_modificacao = filemtime($file_path);
    
    // Arquivo para armazenar a última modificação verificada
    $status_file = __DIR__ . '/importedCSvs/ultima_verificacao.txt';
    
    // Ler última verificação
    $ultima_verificacao = 0;
    if (file_exists($status_file)) {
        $ultima_verificacao = (int)file_get_contents($status_file);
    }
    
    // Verificar se houve modificação
    if ($ultima_modificacao > $ultima_verificacao) {
        $response['arquivo_modificado'] = true;
        $response['arquivo'] = $csv_file;
        $response['ultima_modificacao'] = date('d/m/Y H:i:s', $ultima_modificacao);
        $response['mensagem'] = 'Arquivo modificado detectado';
        
        // Atualizar arquivo de verificação
        file_put_contents($status_file, $ultima_modificacao);
    } else {
        $response['mensagem'] = 'Nenhuma modificação detectada';
        $response['arquivo'] = $csv_file;
        $response['ultima_modificacao'] = date('d/m/Y H:i:s', $ultima_modificacao);
    }
    
} catch (Exception $e) {
    $response['mensagem'] = "Erro ao verificar atualizações: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>