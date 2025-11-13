<?php
header('Content-Type: application/json; charset=utf-8');

// Configurações
$host = getenv('DB_HOST') ?: 'database';
$dbname = getenv('DB_NAME') ?: 'wifiscan';
$user = getenv('DB_USER') ?: 'meuusuario';
$password = getenv('DB_PASS') ?: 'minhasenha';

$response = [
    'success' => false,
    'message' => '',
    'estatisticas' => null
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
        throw new Exception('Nenhum arquivo CSV encontrado na pasta');
    }
    
    $file_path = $csv_dir . $csv_file;
    
    // Verificar se o arquivo existe e é legível
    if (!file_exists($file_path) || !is_readable($file_path)) {
        throw new Exception('Arquivo CSV não pode ser lido: ' . $csv_file);
    }
    
    // Conectar ao banco
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Processar o arquivo CSV
    $importados = 0;
    $ignorados = 0;
    $erros = [];
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $linha_numero = 0;
        
        // Preparar statement para inserção
        $stmt = $conn->prepare("INSERT INTO wifi_scan (local, usuario, intensidade_sinal, endereco_mac) VALUES (?, ?, ?, ?)");
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $linha_numero++;
            
            // Pular linhas vazias
            if (count($data) === 1 && empty(trim($data[0]))) {
                continue;
            }
            
            // Validar número de colunas
            if (count($data) < 4) {
                $erros[] = "Linha $linha_numero: Número insuficiente de colunas";
                $ignorados++;
                continue;
            }
            
            // Extrair e validar dados
            $local = trim($data[0]);
            $usuario = trim($data[1]);
            $intensidade_sinal = trim($data[2]);
            $endereco_mac = trim($data[3]);
            
            // Validar campos obrigatórios
            if (empty($local) || empty($usuario) || empty($intensidade_sinal) || empty($endereco_mac)) {
                $erros[] = "Linha $linha_numero: Campos obrigatórios em branco";
                $ignorados++;
                continue;
            }
            
            // Validar formato do MAC address
            $mac_clean = str_replace([':', '-'], '', $endereco_mac);
            if (!preg_match('/^[0-9A-Fa-f]{12}$/', $mac_clean)) {
                $erros[] = "Linha $linha_numero: Formato de MAC address inválido: $endereco_mac";
                $ignorados++;
                continue;
            }
            
            // Formatar MAC address
            $endereco_mac_formatado = implode(':', str_split($mac_clean, 2));
            
            try {
                // Inserir no banco
                $stmt->execute([$local, $usuario, $intensidade_sinal, $endereco_mac_formatado]);
                $importados++;
            } catch (PDOException $e) {
                // Verificar se é erro de duplicação
                if ($e->getCode() == '23000') {
                    $ignorados++;
                } else {
                    $erros[] = "Linha $linha_numero: Erro no banco - " . $e->getMessage();
                    $ignorados++;
                }
            }
        }
        fclose($handle);
    } else {
        throw new Exception('Não foi possível abrir o arquivo CSV para leitura');
    }
    
    // Preparar resposta
    $response['success'] = true;
    $response['message'] = "Importação automática concluída";
    $response['estatisticas'] = [
        'importados' => $importados,
        'ignorados' => $ignorados,
        'erros' => count($erros),
        'arquivo' => $csv_file
    ];
    
} catch (Exception $e) {
    $response['message'] = "Erro na importação automática: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>