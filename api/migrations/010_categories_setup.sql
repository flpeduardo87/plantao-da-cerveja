-- Migration 010: Setup completo das categorias (ícones e imagens)
-- Categorias que existem nos dois tipos ficam com slug original (ícone) e slug + '-img' (imagem)

-- ────────────────────────────────────────────
-- CATEGORIAS DE ÍCONE (carrossel de pills)
-- ────────────────────────────────────────────
INSERT INTO categories (name, slug, emoji, display_type, active, sort_order)
VALUES
  ('Ofertas',        'ofertas',       '🏷',  'icon', 1,  1),
  ('Cervejas',       'cervejas',      '🍺',  'icon', 1,  2),
  ('Churrasco',      'churrasco',     '🥩',  'icon', 1,  3),
  ('Águas/Gelos',    'aguas-gelos',   '💧',  'icon', 1,  4),
  ('Drinks',         'drinks',        '🍹',  'icon', 1,  5),
  ('Não Alcoólicos', 'nao-alcoolicos','🥤',  'icon', 1,  6),
  ('Destilados',     'destilados',    '🥃',  'icon', 1,  7),
  ('Vinhos',         'vinhos',        '🍷',  'icon', 1,  8),
  ('Congelados',     'congelados',    '🧊',  'icon', 1,  9),
  ('Salgadinhos',    'salgadinhos',   '🍿',  'icon', 1, 10),
  ('Doces',          'doces',         '🍬',  'icon', 1, 11),
  ('Sorvetes',       'sorvetes',      '🍦',  'icon', 1, 12),
  ('Mercearia',      'mercearia',     '🛒',  'icon', 1, 13),
  ('Tabacos',        'tabacos',       '🚬',  'icon', 1, 14)
ON DUPLICATE KEY UPDATE
  display_type = VALUES(display_type),
  active       = VALUES(active),
  sort_order   = VALUES(sort_order);

-- ────────────────────────────────────────────
-- CATEGORIAS DE IMAGEM (cards visuais)
-- Duplicatas (mesma categoria nos dois tipos) recebem slug + '-img'
-- ────────────────────────────────────────────
INSERT INTO categories (name, slug, emoji, display_type, active, sort_order)
VALUES
  ('Ofertas',          'ofertas-img',       '🏷',  'image', 1,  1),
  ('Novidades',        'novidades',         '✨',  'image', 1,  2),
  ('Cervejas Lata',    'cervejas-lata',     '🍺',  'image', 1,  3),
  ('Packs Cervejas',   'packs-cervejas',    '📦',  'image', 1,  4),
  ('Long Necks',       'long-necks',        '🍾',  'image', 1,  5),
  ('Retornáveis',      'retornaveis',       '♻️',  'image', 1,  6),
  ('Zero',             'zero',              '0️⃣',  'image', 1,  7),
  ('Baixas Calorias',  'baixas-calorias',   '💪',  'image', 1,  8),
  ('Churrasco',        'churrasco-img',     '🥩',  'image', 1,  9),
  ('Águas/Gelos',      'aguas-gelos-img',   '💧',  'image', 1, 10),
  ('Drinks Prontos',   'drinks-prontos',    '🍹',  'image', 1, 11),
  ('Não Alcoólicos',   'nao-alcoolicos-img','🥤',  'image', 1, 12),
  ('Energéticos',      'energeticos',       '⚡',  'image', 1, 13),
  ('Destilados',       'destilados-img',    '🥃',  'image', 1, 14),
  ('Vinhos',           'vinhos-img',        '🍷',  'image', 1, 15),
  ('Congelados',       'congelados-img',    '🧊',  'image', 1, 16),
  ('Salgadinhos',      'salgadinhos-img',   '🍿',  'image', 1, 17),
  ('Doces',            'doces-img',         '🍬',  'image', 1, 18),
  ('Mercearia',        'mercearia-img',     '🛒',  'image', 1, 19)
ON DUPLICATE KEY UPDATE
  display_type = VALUES(display_type),
  active       = VALUES(active),
  sort_order   = VALUES(sort_order);
