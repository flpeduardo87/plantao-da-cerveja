-- Migration 011: Produtos placeholder
-- Usa INSERT IGNORE — não afeta produtos já cadastrados com mesmo nome + category_id

-- ─── CERVEJAS (10) ───────────────────────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Brahma Lata', 'Cerveja Pilsen, sabor suave e refrescante', 4.99, NULL, 'Brahma', 'un', '350ml', 1, 0, 1
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Skol Lata', 'A cerveja que desce redondo', 4.49, NULL, 'Skol', 'un', '350ml', 1, 0, 2
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Heineken Lata', 'Cerveja Premium Holandesa', 7.99, NULL, 'Heineken', 'un', '350ml', 1, 1, 3
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Amstel Lata', 'Cerveja Premium com sabor encorpado', 6.99, NULL, 'Amstel', 'un', '350ml', 1, 0, 4
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Corona Extra Long Neck', 'Cerveja mexicana premium com sabor leve', 12.99, NULL, 'Corona', 'un', '330ml', 1, 1, 5
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Budweiser Lata', 'The King of Beers', 6.49, NULL, 'Budweiser', 'un', '350ml', 1, 0, 6
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Itaipava Lata', 'Cerveja Pilsen refrescante', 3.99, NULL, 'Itaipava', 'un', '350ml', 1, 0, 7
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Stella Artois Lata', 'Cerveja belga de sabor suave e refinado', 7.49, NULL, 'Stella Artois', 'un', '350ml', 1, 0, 8
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Bohemia Lata', 'A primeira cerveja do Brasil, sabor marcante', 5.99, NULL, 'Bohemia', 'un', '350ml', 1, 0, 9
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Original Garrafa', 'Cerveja premium com fermentação natural', 11.99, NULL, 'Brahma', 'un', '600ml', 1, 0, 10
FROM categories c WHERE c.slug = 'cervejas' LIMIT 1;

-- ─── DESTILADOS (5) ──────────────────────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Vodka Absolut', 'Vodka sueca premium, destilada de trigo invernal', 69.90, NULL, 'Absolut', 'un', '750ml', 1, 1, 1
FROM categories c WHERE c.slug = 'destilados' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Gin Tanqueray', 'Gin londrino clássico, aroma de zimbro', 89.90, NULL, 'Tanqueray', 'un', '750ml', 1, 1, 2
FROM categories c WHERE c.slug = 'destilados' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Whisky Johnnie Walker Red Label', 'Blended Scotch Whisky com toque defumado', 99.90, NULL, 'Johnnie Walker', 'un', '1L', 1, 0, 3
FROM categories c WHERE c.slug = 'destilados' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Rum Bacardí Branco', 'Rum cubano leve e versátil para drinks', 54.90, NULL, 'Bacardí', 'un', '980ml', 1, 0, 4
FROM categories c WHERE c.slug = 'destilados' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Cachaça 51', 'A cachaça mais tradicional do Brasil', 22.90, NULL, '51', 'un', '965ml', 1, 0, 5
FROM categories c WHERE c.slug = 'destilados' LIMIT 1;

-- ─── VINHOS (5) ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Vinho Tinto Suave Bordô', 'Vinho nacional de sabor adocicado e frutado', 29.90, NULL, 'Salton', 'un', '750ml', 1, 0, 1
FROM categories c WHERE c.slug = 'vinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Vinho Branco Seco Miolo', 'Branco seco com notas de frutas cítricas', 34.90, NULL, 'Miolo', 'un', '750ml', 1, 0, 2
FROM categories c WHERE c.slug = 'vinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Vinho Rosé Quinta do Morgado', 'Rosé fresco com notas de morango e framboesa', 39.90, NULL, 'Quinta do Morgado', 'un', '750ml', 1, 1, 3
FROM categories c WHERE c.slug = 'vinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Vinho Tinto Seco Casillero del Diablo', 'Cabernet Sauvignon chileno encorpado', 54.90, NULL, 'Concha y Toro', 'un', '750ml', 1, 1, 4
FROM categories c WHERE c.slug = 'vinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Espumante Freixenet Ice Rosé', 'Espumante rosé semi-seco, ideal para celebrar', 49.90, NULL, 'Freixenet', 'un', '750ml', 1, 0, 5
FROM categories c WHERE c.slug = 'vinhos' LIMIT 1;

-- ─── ÁGUAS/GELOS (4) ─────────────────────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Água Crystal sem Gás', 'Água mineral natural sem gás', 3.49, NULL, 'Crystal', 'un', '500ml', 1, 0, 1
FROM categories c WHERE c.slug = 'aguas-gelos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Água Tônica Antarctica', 'Água tônica com toque de quinina', 4.99, NULL, 'Antarctica', 'un', '350ml', 1, 0, 2
FROM categories c WHERE c.slug = 'aguas-gelos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Gelo em Cubo 2kg', 'Gelo de água purificada em cubos', 9.90, NULL, NULL, 'pacote', '2kg', 1, 0, 3
FROM categories c WHERE c.slug = 'aguas-gelos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Água com Gás Lindoya', 'Água mineral com gás, levemente gaseificada', 4.49, NULL, 'Lindoya', 'un', '500ml', 1, 0, 4
FROM categories c WHERE c.slug = 'aguas-gelos' LIMIT 1;

-- ─── NÃO ALCOÓLICOS (4) ──────────────────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Refrigerante Coca-Cola', 'A original, gelada e refrescante', 6.99, NULL, 'Coca-Cola', 'un', '350ml', 1, 0, 1
FROM categories c WHERE c.slug = 'nao-alcoolicos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Suco de Laranja Del Valle', 'Suco natural de laranja sem conservantes', 7.49, NULL, 'Del Valle', 'un', '1L', 1, 0, 2
FROM categories c WHERE c.slug = 'nao-alcoolicos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Cerveja sem Álcool Heineken 0.0', 'Todo o sabor Heineken, zero álcool', 6.99, NULL, 'Heineken', 'un', '330ml', 1, 1, 3
FROM categories c WHERE c.slug = 'nao-alcoolicos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Red Bull Energy Drink', 'Bebida energética que dá asas', 10.99, NULL, 'Red Bull', 'un', '250ml', 1, 0, 4
FROM categories c WHERE c.slug = 'nao-alcoolicos' LIMIT 1;

-- ─── SALGADINHOS (4) ─────────────────────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Batata Ruffles Original', 'Batata ondulada crocante, sabor original', 8.99, NULL, 'Ruffles', 'un', '96g', 1, 0, 1
FROM categories c WHERE c.slug = 'salgadinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Amendoim Crocante Elma Chips', 'Amendoim crocante temperado', 7.49, NULL, 'Elma Chips', 'un', '150g', 1, 0, 2
FROM categories c WHERE c.slug = 'salgadinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Torrada Triunfo Cream Cracker', 'Biscoito cream cracker levinho e crocante', 5.49, NULL, 'Triunfo', 'un', '200g', 1, 0, 3
FROM categories c WHERE c.slug = 'salgadinhos' LIMIT 1;

INSERT IGNORE INTO products (category_id, name, description, price, old_price, brand, unit_type, size, active, featured, sort_order)
SELECT c.id, 'Mix de Castanhas Premium', 'Mix de castanhas tostadas: caju, amendoim e nozes', 19.90, NULL, NULL, 'un', '200g', 1, 1, 4
FROM categories c WHERE c.slug = 'salgadinhos' LIMIT 1;
