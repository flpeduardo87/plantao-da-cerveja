-- Migration 008: Stone payment gateway support
-- Adds stone_payment_url column to orders table

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS stone_payment_url VARCHAR(512) NULL DEFAULT NULL
    AFTER payment_method;
