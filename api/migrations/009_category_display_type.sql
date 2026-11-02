ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS display_type ENUM('icon','image') NOT NULL DEFAULT 'icon';
