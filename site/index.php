<?php
header('Content-Type: text/html; charset=utf-8');
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
    </style>
</head>
<body>
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
        
        <?php
        // Testar conexao com o banco
        try {
            $host = getenv('DB_HOST') ?: 'database';
            $dbname = getenv('DB_NAME') ?: 'wifiscan';
            $user = getenv('DB_USER') ?: 'meuusuario';
            $password = getenv('DB_PASS') ?: 'minhasenha';
            
            $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<p class='success'>✅ Conectado ao banco de dados WiFi!</p>";
            
            // Estatisticas rapidas
            $total_registros = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
            $total_locais = $conn->query("SELECT COUNT(DISTINCT local) FROM wifi_scan")->fetchColumn();
            $total_usuarios = $conn->query("SELECT COUNT(DISTINCT usuario) FROM wifi_scan")->fetchColumn();
            $ultimo_registro = $conn->query("SELECT MAX(data_registro) FROM wifi_scan")->fetchColumn();
            
        } catch(PDOException $e) {
            echo "<p class='error'>❌ Erro na conexao: " . $e->getMessage() . "</p>";
        }
        ?>
        
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