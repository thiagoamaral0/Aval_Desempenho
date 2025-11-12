<?php
header('Content-Type: text/html; charset=utf-8');

$host = getenv('DB_HOST') ?: 'database';
$dbname = getenv('DB_NAME') ?: 'wifiscan';
$user = getenv('DB_USER') ?: 'meuusuario';
$password = getenv('DB_PASS') ?: 'minhasenha';

$mensagem = '';
$total_registros = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Contar registros antes de excluir
        $total_registros = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
        
        if ($total_registros > 0) {
            // Excluir todos os registros
            $stmt = $conn->prepare("DELETE FROM wifi_scan");
            $stmt->execute();
            
            $mensagem = "✅ Banco de dados limpo com sucesso! $total_registros registros foram removidos.";
        } else {
            $mensagem = "ℹ️ O banco de dados já está vazio. Nenhum registro para excluir.";
        }
        
    } catch(PDOException $e) {
        $mensagem = "❌ Erro ao limpar o banco: " . $e->getMessage();
    }
} else {
    // Apenas contar registros para exibir na página
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $total_registros = $conn->query("SELECT COUNT(*) FROM wifi_scan")->fetchColumn();
    } catch(PDOException $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Limpar Banco de Dados - WiFi Scan</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
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
            text-align: center;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .mensagem {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
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
        .stats {
            background: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="nav">
        <a href="/">📡 Dashboard</a>
        <a href="/importar.php">📤 Importar CSV</a>
        <a href="/scan.php">🔍 Ver Dados</a>
        <a href="/estatisticas.php">📊 Estatisticas</a>
        <a href="/limpar.php">🗑️ Limpar Banco</a>
    </div>

    <div class="container">
        <h1>🗑️ Limpar Banco de Dados</h1>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?= strpos($mensagem, '✅') !== false ? 'sucesso' : (strpos($mensagem, 'ℹ️') !== false ? 'info' : 'erro') ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-number"><?= $total_registros ?></div>
            <div>Registros atuais no banco</div>
        </div>

        <?php if ($total_registros > 0): ?>
            <div class="warning-box">
                <h2>⚠️ Atenção!</h2>
                <p>Esta ação irá <strong>excluir permanentemente</strong> todos os <strong><?= $total_registros ?></strong> registros do banco de dados.</p>
                <p>Esta operação <strong>não pode ser desfeita</strong>.</p>
                
                <form method="POST" id="formLimpar" onsubmit="return confirmarLimpeza()">
                    <button type="submit" class="btn btn-danger">
                        🗑️ SIM, LIMPAR TODOS OS DADOS
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="info mensagem">
                <h3>✅ Banco de dados vazio</h3>
                <p>Não há registros para excluir no momento.</p>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <a href="/" class="btn btn-secondary">📊 Voltar ao Dashboard</a>
            <a href="/importar.php" class="btn btn-secondary">📤 Importar Novo CSV</a>
        </div>
    </div>

    <script>
        function confirmarLimpeza() {
            const registros = <?= $total_registros ?>;
            return confirm(`ATENÇÃO! Você está prestes a excluir permanentemente ${registros} registros.\n\nEsta ação não pode ser desfeita.\n\nClique em OK para confirmar a limpeza.`);
        }

        // Prevenir envio duplo do formulário
        document.getElementById('formLimpar')?.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '🗑️ Limpando...';
        });
    </script>
</body>
</html>