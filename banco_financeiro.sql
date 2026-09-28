CREATE DATABASE IF NOT EXISTS fincontrol;
USE fincontrol;

-- Tabela de Usuários (o usuário Dev é criado automaticamente pelo PHP)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nome_completo VARCHAR(100),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Contas Parceladas
CREATE TABLE IF NOT EXISTS contas_parceladas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    num_parcelas INT NOT NULL,
    parcelas_pagas INT DEFAULT 0,
    valor_parcela DECIMAL(10,2) NOT NULL,
    proximo_vencimento DATE,
    status VARCHAR(50) DEFAULT 'Ativo',
    usuario_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Backups
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_arquivo VARCHAR(255) NOT NULL,
    tamanho VARCHAR(50),
    tipo VARCHAR(50) DEFAULT 'Manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dados de exemplo (opcional)
INSERT INTO contas_parceladas (descricao, valor_total, num_parcelas, parcelas_pagas, valor_parcela, proximo_vencimento, status) 
VALUES ('Notebook Novo', 5000.00, 24, 1, 208.33, DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Ativo'),
       ('Geladeira', 3000.00, 12, 11, 250.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Ativo');