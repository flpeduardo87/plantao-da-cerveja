-- Migration 006: tabela de clientes com email + senha
-- Executar no phpMyAdmin

CREATE TABLE IF NOT EXISTS customer_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(180) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20)  NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Salvar endereço padrão do cliente
CREATE TABLE IF NOT EXISTS customer_addresses (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NOT NULL,
  cep            VARCHAR(10)  NULL,
  street         VARCHAR(200) NULL,
  number         VARCHAR(20)  NULL,
  complement     VARCHAR(100) NULL,
  neighborhood   VARCHAR(100) NULL,
  reference      VARCHAR(200) NULL,
  is_default     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vincular pedidos ao cliente (sem quebrar pedidos antigos)
ALTER TABLE orders ADD COLUMN customer_user_id INT UNSIGNED NULL AFTER id;
