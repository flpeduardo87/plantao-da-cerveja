-- Migration 012: Hierarquia de categorias e multi-categoria por produto

-- 1. Coluna parent_id em categories (subcategorias apontam para a categoria-pai)
ALTER TABLE categories ADD COLUMN parent_id INT NULL AFTER sort_order;
CREATE INDEX idx_cat_parent ON categories (parent_id);

-- 2. Tabela N:N produto ↔ categorias
CREATE TABLE IF NOT EXISTS product_categories (
  product_id  INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (product_id, category_id),
  KEY idx_pc_product  (product_id),
  KEY idx_pc_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Migrar category_id existente para product_categories
INSERT IGNORE INTO product_categories (product_id, category_id)
SELECT id, category_id FROM products WHERE category_id IS NOT NULL;

-- 4. Definir parent_id: subcategorias (image) → categoria-pai (icon)
-- Cervejas
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='cervejas' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('cervejas-lata','packs-cervejas','long-necks','retornaveis','zero','baixas-calorias') AND display_type='image';

-- Churrasco
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='churrasco' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('churrasco-img') AND display_type='image';

-- Drinks
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='drinks' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('drinks-prontos') AND display_type='image';

-- Não Alcoólicos
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='nao-alcoolicos' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('nao-alcoolicos-img','energeticos') AND display_type='image';

-- Destilados
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='destilados' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('destilados-img') AND display_type='image';

-- Vinhos
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='vinhos' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('vinhos-img') AND display_type='image';

-- Águas/Gelos
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='aguas-gelos' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('aguas-gelos-img') AND display_type='image';

-- Congelados
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='congelados' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('congelados-img') AND display_type='image';

-- Salgadinhos
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='salgadinhos' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('salgadinhos-img') AND display_type='image';

-- Doces
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='doces' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('doces-img') AND display_type='image';

-- Mercearia
UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='mercearia' AND display_type='icon' LIMIT 1) t)
WHERE slug IN ('mercearia-img') AND display_type='image';
