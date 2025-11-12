<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Teste de Conexão com Banco</title>
</head>
<body>
    <h1>Teste de Conexão com Banco de Dados</h1>
    
    <?php
    echo "<h2>Extensões PHP Carregadas:</h2>";
    echo "<pre>" . implode("\n", get_loaded_extensions()) . "</pre>";
    
    echo "<h2>Teste de Conexão MySQL:</h2>";
    
    try {
        $host = getenv('DB_HOST') ?: 'database';
        $dbname = getenv('DB_NAME') ?: 'wifiscan';
        $user = getenv('DB_USER') ?: 'meuusuario';
        $password = getenv('DB_PASS') ?: 'minhasenha';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $conn = new PDO($dsn, $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<p style='color: green;'>✅ Conexão com MySQL bem-sucedida!</p>";
        
        // Testar consulta
        $stmt = $conn->query("SELECT COUNT(*) as total FROM wifi_scan");
        $result = $stmt->fetch();
        echo "<p>Total de registros na tabela: " . $result['total'] . "</p>";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    }
    ?>
    
    <br>
    <a href="/">Voltar para o Dashboard</a>
</body>
</html>