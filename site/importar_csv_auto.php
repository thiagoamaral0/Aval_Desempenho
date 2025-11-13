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
    'importados' => 0,
    'total_registros' => 0,
    'arquivo_modificado' => false
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
        $response['message'] = 'Nenhum arquivo CSV encontrado na pasta';
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    
    $file_path = $csv_dir . $csv_file;
    
    // Verificar se o arquivo existe e é legível
    if (!file_exists($file_path) || !is_readable($file_path)) {
        throw new Exception('Arquivo CSV não pode ser lido: ' . $csv_file);
    }
    
    // Obter última modificação do arquivo
    $ultima_modificacao = filemtime($file_path);
    
    // Arquivo para armazenar a última importação
    $status_file = $csv_dir . 'ultima_importacao.txt';
    
    // Ler última importação
    $ultima_importacao = 0;
    if (file_exists($status_file)) {
        $ultima_importacao = (int)file_get_contents($status_file);
    }
    
    // Verificar se o arquivo foi modificado desde a última importação
    // (com margem de 2 segundos para evitar duplicações)
    if ($ultima_modificacao <= ($ultima_importacao + 2)) {
        $response['success'] = true;
        $response['message'] = 'Nenhuma modificação detectada no arquivo';
        $response['arquivo_modificado'] = false;
        
        // Conectar apenas para pegar o total de registros
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $response['total_registros'] = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
        
        echo json_encode($response);
        exit;
    }
    
    $response['arquivo_modificado'] = true;
    
    // Conectar ao banco
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obter total atual de registros antes da importação
    $total_inicial = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
    
    // Processar o arquivo CSV
    $importados = 0;
    $ignorados = 0;
    
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
                $ignorados++;
                continue;
            }
            
            // Validar formato do MAC address
            $mac_clean = str_replace([':', '-'], '', $endereco_mac);
            if (!preg_match('/^[0-9A-Fa-f]{12}$/', $mac_clean)) {
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
                // Ignorar erros de duplicação e outros
                $ignorados++;
            }
        }
        fclose($handle);
        
        // Atualizar arquivo de status da importação
        file_put_contents($status_file, $ultima_modificacao);
        
        // Obter total final de registros
        $total_final = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
        
        $response['success'] = true;
        $response['message'] = "Importação automática concluída: $importados novos registros, $ignorados ignorados";
        $response['importados'] = $importados;
        $response['ignorados'] = $ignorados;
        $response['total_registros'] = $total_final;
        
    } else {
        throw new Exception('Não foi possível abrir o arquivo CSV para leitura');
    }
    
} catch (Exception $e) {
    $response['message'] = "Erro na importação automática: " . $e->getMessage();
    error_log("Erro importar_csv_auto: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>