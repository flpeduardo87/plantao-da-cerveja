-- ============================================================
-- Plantão da Cerveja 24h — Database Schema
-- MySQL 5.7+ / MariaDB 10+
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ------------------------------------------------------------
-- Categories
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  emoji       VARCHAR(10)  NOT NULL DEFAULT '',
  slug        VARCHAR(100) NOT NULL UNIQUE,
  sort_order  INT          NOT NULL DEFAULT 0,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, emoji, slug, sort_order) VALUES
  ('Ofertas',       '🔥', 'ofertas',     1),
  ('Cervejas',      '🍺', 'cervejas',    2),
  ('Vinhos',        '🍷', 'vinhos',      3),
  ('Destilados',    '🥃', 'destilados',  4),
  ('Chopp',         '🧊', 'chopp',       5),
  ('Águas e Gelo',  '💧', 'aguas',       6),
  ('Refrigerantes', '🥤', 'refri',       7),
  ('Energéticos',   '⚡', 'energeticos', 8),
  ('Churrasco',     '🍖', 'churrasco',   9);

-- ------------------------------------------------------------
-- Products
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  category_id  INT            NOT NULL,
  name         VARCHAR(200)   NOT NULL,
  description  TEXT,
  price        DECIMAL(10,2)  NOT NULL,
  old_price    DECIMAL(10,2),
  discount_pct TINYINT        NOT NULL DEFAULT 0,
  image_url    VARCHAR(500),
  stock        INT            NULL COMMENT 'NULL = ilimitado',
  active       TINYINT(1)     NOT NULL DEFAULT 1,
  featured     TINYINT(1)     NOT NULL DEFAULT 0,
  sort_order   INT            NOT NULL DEFAULT 0,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Orders
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  order_number        VARCHAR(20)    NOT NULL UNIQUE,
  -- Customer
  customer_name       VARCHAR(200)   NOT NULL,
  customer_phone      VARCHAR(20)    NOT NULL,
  customer_email      VARCHAR(200),
  -- Delivery address
  address_cep         VARCHAR(10),
  address_street      VARCHAR(200),
  address_number      VARCHAR(20),
  address_complement  VARCHAR(100),
  address_neighborhood VARCHAR(100),
  address_reference   VARCHAR(200),
  -- Totals
  subtotal            DECIMAL(10,2)  NOT NULL DEFAULT 0,
  delivery_fee        DECIMAL(10,2)  NOT NULL DEFAULT 0,
  total               DECIMAL(10,2)  NOT NULL DEFAULT 0,
  -- Payment
  payment_method      ENUM('pix','credit_card','debit_card','cash','machine') NOT NULL DEFAULT 'pix',
  payment_status      ENUM('pending','paid','failed','refunded')              NOT NULL DEFAULT 'pending',
  stone_transaction_id VARCHAR(100),
  -- Delivery
  delivery_type       ENUM('immediate','scheduled') NOT NULL DEFAULT 'immediate',
  scheduled_at        DATETIME,
  -- Status
  status              ENUM('new','accepted','preparing','delivering','delivered','cancelled') NOT NULL DEFAULT 'new',
  notes               TEXT,
  whatsapp_sent       TINYINT(1)     NOT NULL DEFAULT 0,
  created_at          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Order Items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT            NOT NULL,
  product_id   INT,
  product_name VARCHAR(200)   NOT NULL,
  quantity     INT            NOT NULL DEFAULT 1,
  unit_price   DECIMAL(10,2)  NOT NULL,
  total        DECIMAL(10,2)  NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Store Settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS store_settings (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO store_settings (setting_key, setting_value) VALUES
  ('store_name',         'Plantão da Cerveja 24h'),
  ('store_phone',        ''),
  ('store_whatsapp',     ''),
  ('min_order',          '20.00'),
  ('delivery_fee',       '0.00'),
  ('delivery_time_min',  '15'),
  ('delivery_time_max',  '20'),
  ('store_open',         '1'),
  ('fonnte_token',       ''),
  ('stone_client_id',    ''),
  ('stone_client_secret',''),
  ('stone_sandbox',      '1');

-- ------------------------------------------------------------
-- Admin Users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200) NOT NULL,
  email         VARCHAR(200) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    TIMESTAMP    NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: email=admin@plantaodacerveja.com.br
-- IMPORTANTE: altere a senha imediatamente após o primeiro acesso
INSERT INTO admin_users (name, email, password_hash) VALUES
  ('Administrador', 'admin@plantaodacerveja.com.br',
   '$2y$12$NXqhfW7p5yfO2nPEwWjVZuxZ4bza/6lQCGPGhZkU1TL1Au.ILVnxW');
