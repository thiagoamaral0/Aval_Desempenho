<?php
header('Content-Type: text/html; charset=utf-8');

$host = getenv('DB_HOST') ?: 'database';
$dbname = getenv('DB_NAME') ?: 'wifiscan';
$user = getenv('DB_USER') ?: 'meuusuario';
$password = getenv('DB_PASS') ?: 'minhasenha';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Estatisticas basicas
    $total_registros = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
    $total_locais = $conn->query("SELECT COUNT(DISTINCT local) FROM wifi_scan")->fetchColumn();
    $total_usuarios = $conn->query("SELECT COUNT(DISTINCT usuario) FROM wifi_scan")->fetchColumn();
    $total_macs = $conn->query("SELECT COUNT(DISTINCT endereco_mac) FROM wifi_scan")->fetchColumn();
    
    // Top usuarios
    $top_usuarios = $conn->query("
        SELECT usuario, COUNT(*) as total 
        FROM wifi_scan 
        GROUP BY usuario 
        ORDER BY total DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribuicao de sinais
    $dist_sinais = $conn->query("
        SELECT 
            CASE 
                WHEN CAST(REPLACE(REPLACE(intensidade_sinal, ' dBm', ''), '-', '') AS SIGNED) <= 50 THEN 'Forte (≥ -50 dBm)'
                WHEN CAST(REPLACE(REPLACE(intensidade_sinal, ' dBm', ''), '-', '') AS SIGNED) <= 70 THEN 'Médio (-51 a -70 dBm)'
                ELSE 'Fraco (≤ -71 dBm)'
            END as qualidade,
            COUNT(*) as total
        FROM wifi_scan 
        GROUP BY qualidade
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Locais mais comuns
    $top_locais = $conn->query("
        SELECT local, COUNT(*) as total 
        FROM wifi_scan 
        GROUP BY local 
        ORDER BY total DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Estatisticas WiFi</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
            background-color: #f8f9fa; 
        }
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
        .nav a:hover { 
            background: #495057; 
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin: 30px 0; 
        }
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            text-align: center; 
            border-left: 5px solid #007bff;
        }
        .stat-number { 
            font-size: 2.5em; 
            font-weight: bold; 
            color: #007bff; 
            margin: 10px 0; 
        }
        .stat-label { 
            color: #6c757d; 
            font-size: 1.1em; 
        }
        .chart-container { 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin: 20px 0; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #343a40; 
            color: white; 
        }
        tr:hover { 
            background-color: #f5f5f5; 
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            margin: 5px 0;
        }
        .progress-fill {
            background: #007bff;
            height: 100%;
            border-radius: 10px;
            text-align: center;
            color: white;
            font-size: 0.8em;
            line-height: 20px;
        }
    </style>
</head>
<body>
    <div class="nav">
        <a href="/">📡 Dashboard</a>
        <a href="/importar.php">📤 Importar CSV</a>
        <a href="/scan.php">🔍 Ver Dados</a>
        <a href="/estatisticas.php">📊 Estatisticas</a>
        <a href="http://localhost:8080" target="_blank">🗃️ phpMyAdmin</a>
    </div>

    <div class="container">
        <h1>📊 Estatisticas do Monitoramento WiFi</h1>
        
        <?php if(isset($error)): ?>
            <div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">
                <strong>Erro:</strong> <?= $error ?>
            </div>
        <?php else: ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_registros ?></div>
                    <div class="stat-label">Total de Scans</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_locais ?></div>
                    <div class="stat-label">Locais Monitorados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_usuarios ?></div>
                    <div class="stat-label">SSIDs Únicos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_macs ?></div>
                    <div class="stat-label">Endereços MAC</div>
                </div>
            </div>

            <?php if ($total_registros > 0): ?>

                <div class="chart-container">
                    <h2>📶 Distribuição da Qualidade do Sinal</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Qualidade do Sinal</th>
                                <th>Total de Registros</th>
                                <th>Percentual</th>
                                <th>Visualização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dist_sinais as $dist): ?>
                            <?php $percentual = round(($dist['total'] / $total_registros) * 100, 1); ?>
                            <tr>
                                <td><?= $dist['qualidade'] ?></td>
                                <td><?= $dist['total'] ?></td>
                                <td><?= $percentual ?>%</td>
                                <td style="width: 200px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percentual ?>%">
                                            <?= $percentual >= 10 ? $percentual . '%' : '' ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chart-container">
                    <h2>👥 Top 10 SSIDs Mais Detectados</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>SSID/Usuário</th>
                                <th>Total de Ocorrências</th>
                                <th>Percentual</th>
                                <th>Visualização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_usuarios as $usuario): ?>
                            <?php $percentual = round(($usuario['total'] / $total_registros) * 100, 1); ?>
                            <tr>
                                <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                                <td><?= $usuario['total'] ?></td>
                                <td><?= $percentual ?>%</td>
                                <td style="width: 200px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percentual ?>%">
                                            <?= $percentual >= 10 ? $percentual . '%' : '' ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chart-container">
                    <h2>🏢 Locais Mais Monitorados</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Local</th>
                                <th>Total de Scans</th>
                                <th>Percentual</th>
                                <th>Visualização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_locais as $local): ?>
                            <?php $percentual = round(($local['total'] / $total_registros) * 100, 1); ?>
                            <tr>
                                <td><?= htmlspecialchars($local['local']) ?></td>
                                <td><?= $local['total'] ?></td>
                                <td><?= $percentual ?>%</td>
                                <td style="width: 200px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percentual ?>%">
                                            <?= $percentual >= 10 ? $percentual . '%' : '' ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <h3>📊 Nenhum dado para mostrar</h3>
                    <p>Importe alguns dados CSV para ver as estatísticas.</p>
                    <a href="/importar.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px;">
                        📤 Importar CSV
                    </a>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>