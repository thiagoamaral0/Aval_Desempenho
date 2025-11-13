<?php
header('Content-Type: text/html; charset=utf-8');

// Configurações
$host = getenv('DB_HOST') ?: 'database';
$dbname = getenv('DB_NAME') ?: 'wifiscan';
$user = getenv('DB_USER') ?: 'meuusuario';
$password = getenv('DB_PASS') ?: 'minhasenha';

// Verificar se há atualizações automaticamente
$auto_import = false;
$mensagem_auto = '';

// Verificar se deve fazer importação automática
if (isset($_GET['auto_import']) && $_GET['auto_import'] === 'true') {
    $auto_import = true;
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Estatisticas rapidas
    $total_registros = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
    $total_locais = $conn->query("SELECT COUNT(DISTINCT local) FROM wifi_scan")->fetchColumn();
    $total_usuarios = $conn->query("SELECT COUNT(DISTINCT usuario) FROM wifi_scan")->fetchColumn();
    $ultimo_registro = $conn->query("SELECT MAX(data_registro) FROM wifi_scan")->fetchColumn();
    
    // Verificar arquivo CSV na pasta
    $csv_dir = __DIR__ . '/importedCSvs/';
    $csv_file = null;
    $file_info = null;
    $arquivo_modificado = false;
    
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
        
        // Verificar se o arquivo foi modificado desde a última importação
        if ($file_info && $ultimo_registro) {
            $ultima_importacao = strtotime($ultimo_registro);
            if ($file_info['modificacao'] > $ultima_importacao) {
                $arquivo_modificado = true;
                
                // Importação automática se solicitada
                if ($auto_import) {
                    $resultado = importarCSVAutomaticamente($file_info['caminho'], $conn);
                    if ($resultado['success']) {
                        $mensagem_auto = "✅ Importação automática realizada! " . $resultado['message'];
                        // Atualizar estatísticas após importação
                        $total_registros = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
                        $ultimo_registro = $conn->query("SELECT MAX(data_registro) FROM wifi_scan")->fetchColumn();
                    } else {
                        $mensagem_auto = "❌ Erro na importação automática: " . $resultado['message'];
                    }
                }
            }
        }
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

// Função para importação automática
function importarCSVAutomaticamente($file_path, $conn) {
    $resultado = [
        'success' => false,
        'message' => '',
        'importados' => 0,
        'ignorados' => 0
    ];
    
    try {
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
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
                    $ignorados++;
                }
            }
            fclose($handle);
            
            $resultado['success'] = true;
            $resultado['message'] = "$importados registros importados, $ignorados ignorados";
            $resultado['importados'] = $importados;
            $resultado['ignorados'] = $ignorados;
            
        } else {
            throw new Exception("Não foi possível abrir o arquivo CSV");
        }
        
    } catch (Exception $e) {
        $resultado['message'] = $e->getMessage();
    }
    
    return $resultado;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Importacao WiFi Scan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .nav { 
            background: #343a40; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px;
        }
        .nav a { 
            color: white; 
            text-decoration: none; 
            margin: 0 15px; 
            padding: 8px 15px;
            border-radius: 3px;
        }
        .nav a:hover { background: #495057; }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin: 20px 0;
        }
        .stat-card { 
            background: #e9ecef; 
            padding: 20px; 
            border-radius: 5px; 
            text-align: center;
        }
        .stat-number { 
            font-size: 2em; 
            font-weight: bold; 
            color: #007bff;
        }
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            margin: 20px 0;
            cursor: pointer;
        }
        .upload-area:hover {
            background: #e9ecef;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
        }
        .mensagem {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .file-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .auto-update-status {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
        #notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
    </style>
</head>
<body>
    <div id="notification"></div>

    <div class="nav">
        <a href="/">📡 Dashboard WiFi</a>
        <a href="/importar.php">📤 Importar CSV</a>
        <a href="/scan.php">🔍 Ver Dados</a>
        <a href="/estatisticas.php">📊 Estatisticas</a>
        <a href="/limpar.php">🗑️ Limpar Banco</a>
        <a href="http://localhost:8080" target="_blank">🗃️ phpMyAdmin</a>
    </div>

    <div class="container">
        <h1>📡 Sistema de Importacao WiFi Scan</h1>
        
        <?php if(isset($conn)): ?>
            <p class='success'>✅ Conectado ao banco de dados WiFi!</p>
        <?php else: ?>
            <p class='error'>❌ Erro na conexao: <?= $error ?? 'Desconhecido' ?></p>
        <?php endif; ?>

        <?php if ($mensagem_auto): ?>
            <div class="mensagem <?= strpos($mensagem_auto, '✅') !== false ? 'sucesso' : 'erro' ?>">
                <?= $mensagem_auto ?>
            </div>
        <?php endif; ?>

        <!-- Status de Atualização Automática -->
        <div class="auto-update-status">
            <strong>🔄 Atualização Automática Ativa</strong>
            <p>O sistema verifica automaticamente por atualizações a cada 30 segundos</p>
            <div id="lastCheck">Última verificação: <span id="lastCheckTime">Agora</span></div>
            <div id="updateStatus"></div>
        </div>
        
        <!-- Informações do Arquivo CSV -->
        <?php if ($file_info): ?>
        <div class="file-info">
            <h3>📁 Arquivo CSV Monitorado:</h3>
            <p><strong>Nome:</strong> <?= $file_info['nome'] ?></p>
            <p><strong>Tamanho:</strong> <?= number_format($file_info['tamanho'] / 1024, 2) ?> KB</p>
            <p><strong>Última modificação:</strong> <?= date('d/m/Y H:i:s', $file_info['modificacao']) ?></p>
            
            <?php if ($arquivo_modificado && !$auto_import): ?>
                <div class="warning mensagem">
                    <strong>🔄 Atualização Disponível!</strong>
                    <p>O arquivo CSV foi modificado. A importação automática será realizada em breve.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="info mensagem">
            <h3>📭 Nenhum arquivo CSV encontrado</h3>
            <p>Coloque um arquivo CSV na pasta <code>importedCSvs</code> para monitoramento automático.</p>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_registros ?? 0 ?></div>
                <div>Total de Registros</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_locais ?? 0 ?></div>
                <div>Locais Diferentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_usuarios ?? 0 ?></div>
                <div>SSIDs Unicos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">📶</div>
                <div>Sinais Monitorados</div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="action-buttons">
            <a href="/importar.php" class="btn btn-success">📤 Importar CSV</a>
            <a href="/scan.php" class="btn btn-primary">🔍 Ver Todos os Dados</a>
            <a href="/estatisticas.php" class="btn btn-warning">📊 Ver Estatisticas</a>
            <a href="/limpar.php" class="btn btn-danger">🗑️ Limpar Banco</a>
        </div>

        <div class="upload-area" onclick="location.href='/importar.php'">
            <h2>📤 Clique para Importar Arquivo CSV</h2>
            <p>Formato esperado: local, usuario, intensidade_sinal, endereco_mac</p>
            <p><small>Suporta arquivos .csv com codificacao UTF-8</small></p>
        </div>
        
        <?php if(isset($conn) && $total_registros > 0): ?>
            <h2>Ultimos Registros Importados:</h2>
            <?php
            $stmt = $conn->query("SELECT * FROM wifi_scan ORDER BY data_registro DESC LIMIT 5");
            $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #343a40; color: white;">
                        <th style="padding: 12px; text-align: left;">Local</th>
                        <th style="padding: 12px; text-align: left;">Usuario</th>
                        <th style="padding: 12px; text-align: left;">Sinal</th>
                        <th style="padding: 12px; text-align: left;">MAC</th>
                        <th style="padding: 12px; text-align: left;">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ultimos as $registro): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><?= htmlspecialchars($registro['local']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($registro['usuario']) ?></td>
                        <td style="padding: 10px;">
                            <?php 
                            $dbm = intval($registro['intensidade_sinal']);
                            if ($dbm >= -50) echo "<span style='color: #28a745;'>🔴 Forte</span>";
                            elseif ($dbm >= -70) echo "<span style='color: #ffc107;'>🟡 Medio</span>";
                            else echo "<span style='color: #dc3545;'>🔵 Fraco</span>";
                            ?>
                            <br><small><?= $registro['intensidade_sinal'] ?></small>
                        </td>
                        <td style="padding: 10px; font-family: monospace; font-size: 0.9em;"><?= $registro['endereco_mac'] ?></td>
                        <td style="padding: 10px; font-size: 0.9em;"><?= $registro['data_registro'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p><small>Ultima importacao: <?= $ultimo_registro ?? 'N/A' ?></small></p>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #6c757d;">
                <h3>📭 Nenhum dado importado ainda</h3>
                <p>Use o botao acima para importar seu primeiro arquivo CSV</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Função para mostrar notificação
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.style.display = 'block';
            notification.style.background = type === 'success' ? '#28a745' : '#dc3545';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }

        // Função para verificar atualizações automaticamente
        async function verificarAtualizacoes() {
            try {
                const response = await fetch('/verificar_atualizacao_auto.php');
                const result = await response.json();
                
                document.getElementById('lastCheckTime').textContent = new Date().toLocaleTimeString();
                
                if (result.arquivo_modificado) {
                    document.getElementById('updateStatus').innerHTML = 
                        '<span style="color: #dc3545;">🔄 Arquivo modificado detectado! Importando...</span>';
                    
                    // Fazer importação automática
                    const importResponse = await fetch('/importar_csv_auto.php');
                    const importResult = await importResponse.json();
                    
                    if (importResult.success) {
                        document.getElementById('updateStatus').innerHTML = 
                            '<span style="color: #28a745;">✅ Importação automática realizada: ' + 
                            importResult.estatisticas.importados + ' registros</span>';
                        
                        showNotification('✅ Dados atualizados automaticamente! ' + 
                                       importResult.estatisticas.importados + ' novos registros', 'success');
                        
                        // Recarregar a página após 2 segundos para mostrar novos dados
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        
                    } else {
                        document.getElementById('updateStatus').innerHTML = 
                            '<span style="color: #dc3545;">❌ Erro na importação: ' + importResult.message + '</span>';
                    }
                } else {
                    document.getElementById('updateStatus').innerHTML = 
                        '<span style="color: #6c757d;">✅ Nenhuma modificação detectada</span>';
                }
            } catch (error) {
                console.error('Erro na verificação automática:', error);
                document.getElementById('updateStatus').innerHTML = 
                    '<span style="color: #dc3545;">❌ Erro na verificação</span>';
            }
        }

        // Verificar a cada 30 segundos
        setInterval(verificarAtualizacoes, 30000);
        
        // Verificar imediatamente ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            verificarAtualizacoes();
        });

        // Adicionar confirmação para o botão de limpar banco
        document.addEventListener('DOMContentLoaded', function() {
            const btnLimpar = document.querySelector('a[href="/limpar.php"]');
            if (btnLimpar) {
                btnLimpar.addEventListener('click', function(e) {
                    if (!confirm('⚠️ Você será redirecionado para a página de limpeza do banco. Esta ação pode excluir todos os dados permanentemente. Continuar?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>