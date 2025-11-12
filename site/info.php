<?php
echo "<h1>🐳 PHP Funcionando no Docker!</h1>";

// Testar conexão com MySQL
$host = 'database';
$user = 'meuusuario';
$password = 'minhasenha';
$dbname = 'meubanco';

echo "<h2>Teste de Conexão MySQL:</h2>";

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>❌ Erro MySQL: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Conectado ao MySQL com sucesso!</p>";
        
        // Mostrar informações do servidor
        echo "<h3>Informações do MySQL:</h3>";
        echo "<ul>";
        echo "<li>Versão: " . $conn->server_info . "</li>";
        echo "<li>Host: " . $conn->host_info . "</li>";
        echo "<li>Banco: $dbname</li>";
        echo "</ul>";
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Informações do PHP
echo "<h2>Informações do PHP:</h2>";
echo "<ul>";
echo "<li>Versão: " . phpversion() . "</li>";
echo "<li>Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "</ul>";

echo "<hr>";
echo "<a href='/index.html'>← Voltar</a>";
?>