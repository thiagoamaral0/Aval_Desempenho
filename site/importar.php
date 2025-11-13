<?php
header('Content-Type: text/html; charset=utf-8');

// Verificar extensões instaladas (para debug)
// echo "Extensões PHP carregadas: " . implode(", ", get_loaded_extensions());

$host = getenv('DB_HOST') ?: 'database';
$dbname = getenv('DB_NAME') ?: 'wifiscan';
$user = getenv('DB_USER') ?: 'meuusuario';
$password = getenv('DB_PASS') ?: 'minhasenha';

$mensagem = '';
$total_importados = 0;
$erros = [];

// Função para conectar com o banco
function connectDB($host, $dbname, $user, $password) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $conn = new PDO($dsn, $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch (PDOException $e) {
        throw new Exception("Erro de conexão: " . $e->getMessage());
    }
}

// Verificar se existe arquivo CSV na pasta
$csv_dir = __DIR__ . '/importedCSvs/';
$csv_file = null;
$file_info = null;

if (file_exists($csv_dir) && is_dir($csv_dir)) {
    $files = scandir($csv_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
            $csv_file = $file;
            $file_path = $csv_dir . $file;
            $file_info = [
                'nome' => $file,
                'tamanho' => filesize($file_path),
                'modificacao' => filemtime($file_path),
                'caminho' => $file_path
            ];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        // Testar conexão primeiro
        $conn = connectDB($host, $dbname, $user, $password);
        
        $file = $_FILES['csv_file'];
        
        // Validar arquivo
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do php.ini)',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
                UPLOAD_ERR_PARTIAL => 'Upload parcial do arquivo',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever no disco',
                UPLOAD_ERR_EXTENSION => 'Extensão PHP interrompeu o upload'
            ];
            $error_msg = $upload_errors[$file['error']] ?? "Erro desconhecido (código: {$file['error']})";
            throw new Exception("Erro no upload do arquivo: " . $error_msg);
        }
        
        // Validar tipo de arquivo
        $file_type = mime_content_type($file['tmp_name']);
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_type !== 'text/plain' && $file_type !== 'text/csv' && $file_extension !== 'csv') {
            throw new Exception("Por favor, envie um arquivo CSV válido. Tipo recebido: " . $file_type);
        }
        
        // Processar o arquivo CSV
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            $linha_numero = 0;
            $importados = 0;
            $ignorados = 0;
            
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
                    $erros[] = "Linha $linha_numero: Número insuficiente de colunas (" . count($data) . ")";
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
                
                // Validar formato do MAC address (mais flexível)
                $mac_clean = str_replace([':', '-'], '', $endereco_mac);
                if (!preg_match('/^[0-9A-Fa-f]{12}$/', $mac_clean)) {
                    $erros[] = "Linha $linha_numero: Formato de MAC address inválido: $endereco_mac";
                    $ignorados++;
                    continue;
                }
                
                // Formatar MAC address consistentemente
                $endereco_mac_formatado = implode(':', str_split($mac_clean, 2));
                
                try {
                    // Inserir no banco
                    $stmt->execute([$local, $usuario, $intensidade_sinal, $endereco_mac_formatado]);
                    $importados++;
                } catch (PDOException $e) {
                    // Verificar se é erro de duplicação (pode ser normal)
                    if ($e->getCode() == '23000') {
                        $erros[] = "Linha $linha_numero: Registro duplicado (MAC: $endereco_mac_formatado)";
                    } else {
                        $erros[] = "Linha $linha_numero: Erro no banco - " . $e->getMessage();
                    }
                    $ignorados++;
                }
            }
            fclose($handle);
            
            $total_importados = $importados;
            
            if ($importados > 0) {
                $mensagem = "✅ Importação concluída! $importados registros importados com sucesso.";
                if ($ignorados > 0) {
                    $mensagem .= " $ignorados registros ignorados devido a erros.";
                }
            } else {
                $mensagem = "⚠️ Nenhum registro foi importado. Verifique o formato do arquivo.";
                if ($linha_numero === 0) {
                    $mensagem .= " O arquivo parece estar vazio.";
                }
            }
            
        } else {
            throw new Exception("Não foi possível abrir o arquivo CSV para leitura");
        }
        
    } catch (Exception $e) {
        $mensagem = "❌ Erro: " . $e->getMessage();
        
        // Adicionar informações de debug para conexão
        if (strpos($e->getMessage(), 'driver') !== false) {
            $mensagem .= "<br><small>Driver PDO MySQL não encontrado. Verifique se a extensão pdo_mysql está instalada.</small>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Importar CSV - WiFi Scan</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f8f9fa; }
        .nav { background: #343a40; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .nav a { color: white; text-decoration: none; margin: 0 15px; padding: 8px 15px; border-radius: 3px; }
        .nav a:hover { background: #495057; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .upload-area { border: 2px dashed #007bff; border-radius: 10px; padding: 40px; text-align: center; margin: 20px 0; }
        .upload-area-pasta { border: 2px dashed #28a745; border-radius: 10px; padding: 40px; text-align: center; margin: 20px 0; background: #f8fff9; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        button:hover { background: #0056b3; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
        .btn-pasta { background: #28a745; }
        .btn-pasta:hover { background: #1e7e34; }
        .mensagem { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .sucesso { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .erro { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .erros-lista { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; max-height: 200px; overflow-y: auto; }
        .erro-item { color: #dc3545; margin: 5px 0; font-family: monospace; font-size: 0.9em; }
        .pasta-status { margin-top: 15px; min-height: 50px; }
        .file-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="/">📡 Dashboard</a>
        <a href="/importar.php">📤 Importar CSV</a>
        <a href="/scan.php">🔍 Ver Dados</a>
        <a href="/estatisticas.php">📊 Estatisticas</a>
    </div>

    <div class="container">
        <h1>📤 Importar Arquivo CSV</h1>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?= strpos($mensagem, '✅') !== false ? 'sucesso' : (strpos($mensagem, '⚠️') !== false ? 'info' : 'erro') ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="erros-lista">
                <h3>Erros encontrados (<?= count($erros) ?>):</h3>
                <?php foreach(array_slice($erros, 0, 20) as $erro): ?>
                    <div class="erro-item"><?= $erro ?></div>
                <?php endforeach; ?>
                <?php if (count($erros) > 20): ?>
                    <div class="erro-item">... e mais <?= count($erros) - 20 ?> erros</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Informações do Arquivo na Pasta -->
        <?php if ($file_info): ?>
        <div class="file-info">
            <h3>📁 Arquivo CSV na Pasta:</h3>
            <p><strong>Nome:</strong> <?= $file_info['nome'] ?></p>
            <p><strong>Tamanho:</strong> <?= number_format($file_info['tamanho'] / 1024, 2) ?> KB</p>
            <p><strong>Última modificação:</strong> <?= date('d/m/Y H:i:s', $file_info['modificacao']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Importar da Pasta -->
        <?php if ($file_info): ?>
        <div class="upload-area-pasta">
            <h2>🔄 Importar da Pasta</h2>
            <p>Importe automaticamente o arquivo CSV da pasta <code>importedCSvs</code></p>
            
            <div class="form-group">
                <button type="button" onclick="importarDaPasta()" id="btnImportarPasta" class="btn-pasta">
                    📁 Importar da Pasta
                </button>
                <button type="button" onclick="verificarAtualizacao()" class="btn btn-info">
                    🔍 Verificar Atualização
                </button>
            </div>
            
            <div id="pastaStatus" class="pasta-status"></div>
        </div>
        <?php else: ?>
        <div class="info mensagem">
            <h3>📭 Nenhum arquivo CSV na pasta</h3>
            <p>Coloque um arquivo CSV na pasta <code>importedCSvs</code> para importação automática.</p>
        </div>
        <?php endif; ?>

        <!-- Upload Manual -->
        <div class="upload-area">
            <h2>📁 Upload Manual</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group">
                    <label for="csv_file">Selecione o arquivo CSV:</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </div>
                
                <div class="form-group">
                    <button type="submit">📤 Importar CSV</button>
                </div>
            </form>
        </div>

        <div class="info mensagem">
            <h3>📋 Formato do CSV (SEM CABECALHO):</h3>
            <p>O arquivo CSV deve conter APENAS os dados, SEM linha de cabecalho:</p>
            <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: left; background: #f8f9fa;"><strong>Coluna 1</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: left; background: #f8f9fa;"><strong>Coluna 2</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: left; background: #f8f9fa;"><strong>Coluna 3</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: left; background: #f8f9fa;"><strong>Coluna 4</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">Sala de reunioes</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">_Simba-5G</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">-67 dBm</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">38:6b:1c:bc:16:4f</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">Sala de reunioes</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">LLM_H0A9_059530</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">-85 dBm</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">c8:47:8c:4b:b4:fc</td>
                </tr>
            </table>
            
            <h4>💡 Importante:</h4>
            <ul>
                <li><strong>NAO incluir linha de cabecalho</strong></li>
                <li>Ordem das colunas: Local, Usuario, Intensidade Sinal, MAC Address</li>
                <li>O arquivo deve usar codificacao UTF-8</li>
                <li>Separador de colunas: virgula (,)</li>
                <li>Formato MAC: 00:1A:2B:3C:4D:5E ou 00-1A-2B-3C-4D-5E</li>
            </ul>
        </div>

        <?php if ($total_importados > 0): ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="/scan.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                    🔍 Ver Dados Importados
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Preview do arquivo selecionado
        document.getElementById('csv_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                console.log('Arquivo selecionado:', file.name);
                const fileName = file.name;
                const fileSize = (file.size / 1024).toFixed(2);
                alert(`Arquivo selecionado: ${fileName}\nTamanho: ${fileSize} KB`);
            }
        });

        // Validacao antes do envio
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('csv_file');
            if (!fileInput.value) {
                alert('Por favor, selecione um arquivo CSV.');
                e.preventDefault();
                return;
            }
            
            const fileName = fileInput.files[0].name;
            if (!fileName.toLowerCase().endsWith('.csv')) {
                alert('Por favor, selecione um arquivo com extensão .csv');
                e.preventDefault();
                return;
            }
        });

        // Função para importar da pasta
        async function importarDaPasta() {
            const btn = document.getElementById('btnImportarPasta');
            const statusDiv = document.getElementById('pastaStatus');
            
            btn.disabled = true;
            btn.innerHTML = '🔄 Importando...';
            statusDiv.innerHTML = '<div class="info mensagem">🔍 Importando arquivo da pasta...</div>';
            
            try {
                const response = await fetch('/importar_csv_pasta.php');
                const result = await response.json();
                
                if (result.success) {
                    statusDiv.innerHTML = `<div class="sucesso mensagem">✅ ${result.message}</div>`;
                    
                    // Mostrar estatísticas se disponíveis
                    if (result.estatisticas) {
                        const stats = result.estatisticas;
                        statusDiv.innerHTML += `
                            <div class="info mensagem">
                                <h4>📊 Estatísticas da Importação:</h4>
                                <p>📈 Registros importados: ${stats.importados}</p>
                                <p>⚠️ Registros ignorados: ${stats.ignorados}</p>
                                <p>❌ Erros encontrados: ${stats.erros}</p>
                                <p>📄 Arquivo: ${stats.arquivo}</p>
                            </div>
                        `;
                    }
                    
                    // Adicionar link para ver dados
                    statusDiv.innerHTML += `
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="/scan.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                                🔍 Ver Dados Importados
                            </a>
                        </div>
                    `;
                    
                    // Recarregar a página após 3 segundos
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                    
                } else {
                    statusDiv.innerHTML = `<div class="erro mensagem">❌ ${result.message}</div>`;
                }
                
            } catch (error) {
                statusDiv.innerHTML = `<div class="erro mensagem">❌ Erro na conexão: ${error.message}</div>`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '📁 Importar da Pasta';
            }
        }

        // Função para verificar atualizações
        async function verificarAtualizacao() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '🔍 Verificando...';
            
            try {
                const response = await fetch('/verificar_atualizacao.php');
                const result = await response.json();
                
                if (result.arquivo_modificado) {
                    if (confirm(`📁 Arquivo modificado detectado!\n\nArquivo: ${result.arquivo}\nÚltima modificação: ${result.ultima_modificacao}\n\nDeseja importar agora?`)) {
                        importarDaPasta();
                    }
                } else {
                    alert('✅ Nenhuma modificação detectada no arquivo CSV.');
                }
            } catch (error) {
                alert('❌ Erro ao verificar atualizações: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>