-- Configurar charset do banco
ALTER DATABASE wifiscan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Criar tabela de scan WiFi
CREATE TABLE IF NOT EXISTS wifi_scan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local VARCHAR(100) NOT NULL,
    usuario VARCHAR(100) NOT NULL,
    intensidade_sinal VARCHAR(20) NOT NULL,
    endereco_mac VARCHAR(17) NOT NULL,
    data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Criar índices para melhor performance
CREATE INDEX idx_local ON wifi_scan(local);
CREATE INDEX idx_usuario ON wifi_scan(usuario);
CREATE INDEX idx_mac ON wifi_scan(endereco_mac);