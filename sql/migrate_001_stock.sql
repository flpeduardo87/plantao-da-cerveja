-- Migration 001: add stock column to products
-- Run only if upgrading from an existing installation
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS stock INT NULL COMMENT 'NULL = ilimitado'
  AFTER discount_pct;
