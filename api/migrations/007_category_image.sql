-- Migration 007: adicionar image_url nas categorias
ALTER TABLE categories ADD COLUMN image_url VARCHAR(500) NULL AFTER emoji;
