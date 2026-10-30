-- Migration 005: campos de marca, tipo, tamanho e depósito nos produtos
-- Executar no phpMyAdmin antes de usar os novos campos no admin

ALTER TABLE products
  ADD COLUMN brand        VARCHAR(80)  NULL AFTER category_id,
  ADD COLUMN unit_type    VARCHAR(40)  NULL AFTER brand,
  ADD COLUMN size         VARCHAR(40)  NULL AFTER unit_type,
  ADD COLUMN has_deposit  TINYINT(1)   NOT NULL DEFAULT 0 AFTER size,
  ADD COLUMN deposit_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER has_deposit;

-- Índice para filtrar por marca rapidamente
CREATE INDEX idx_products_brand ON products (brand);
