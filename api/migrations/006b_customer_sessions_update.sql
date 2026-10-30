-- Migration 006b: recriar customer_sessions para usar customer_id
-- Executar APÓS 006_customer_users.sql

DROP TABLE IF EXISTS customer_sessions;

CREATE TABLE customer_sessions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id   INT UNSIGNED NOT NULL,
  token         CHAR(64) NOT NULL,
  expires_at    DATETIME NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_token (token),
  INDEX idx_customer (customer_id),
  FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpar OTPs antigos (não usados mais)
DROP TABLE IF EXISTS customer_otps;
