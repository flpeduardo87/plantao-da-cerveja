<?php
// GET /products              — list active products (optionally ?category_id=X or ?category=slug)
// GET /products/{id}         — single product (inclui category_ids)
// POST /products             — create  [admin]
// PUT /products/{id}         — update  [admin]
// PATCH /products/{id}       — partial update (e.g. toggle active/featured)
// DELETE /products/{id}      — soft-delete [admin]

$pdo = db();

match ($method) {
    'GET' => (function () use ($pdo, $id) {
        if ($id) {
            $s = $pdo->prepare(
                'SELECT p.*, c.slug AS category_slug, c.name AS category_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = ?'
            );
            $s->execute([$id]);
            $row = $s->fetch();
            if (!$row) { json_err('Not found', 404); return; }
            // Carrega todas as categorias do produto (multi-categoria)
            $cs = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
            $cs->execute([$id]);
            $row['category_ids'] = array_column($cs->fetchAll(), 'category_id');
            json_ok($row);
        } else {
            $where = empty($_GET['all']) ? 'p.active = 1' : '1=1';
            $vals  = [];
            if (!empty($_GET['category_id'])) {
                $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id = ?)';
                $vals[] = (int)$_GET['category_id'];
            } elseif (!empty($_GET['category'])) {
                $where .= ' AND p.id IN (
                    SELECT pc.product_id FROM product_categories pc
                    JOIN categories cc ON cc.id = pc.category_id
                    WHERE cc.slug = ? OR cc.parent_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1)
                )';
                $vals[] = $_GET['category'];
                $vals[] = $_GET['category'];
            }
            $sql = "SELECT p.*, c.slug AS category_slug, c.name AS category_name,
                           COALESCE(GROUP_CONCAT(DISTINCT pc.category_id ORDER BY pc.category_id), '') AS _cat_ids_str
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    LEFT JOIN product_categories pc ON pc.product_id = p.id
                    WHERE $where
                    GROUP BY p.id
                    ORDER BY p.sort_order, p.id";
            $s = $pdo->prepare($sql);
            $s->execute($vals);
            $rows = $s->fetchAll();
            foreach ($rows as &$r) {
                $str = $r['_cat_ids_str'] ?? '';
                $r['category_ids'] = $str !== '' ? array_map('intval', explode(',', $str)) : [];
                unset($r['_cat_ids_str']);
            }
            json_ok($rows);
        }
    })(),

    'POST' => (function () use ($pdo) {
        $d = body();
        if (empty($d['name']) || !isset($d['price']) || empty($d['category_id'])) {
            json_err('name, price and category_id required');
        }
        $s = $pdo->prepare(
            'INSERT INTO products (category_id, name, description, price, old_price, discount_pct, image_url, active, featured, sort_order, brand, unit_type, size, has_deposit, deposit_value)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $s->execute([
            (int)$d['category_id'],
            $d['name'],
            $d['description'] ?? null,
            (float)$d['price'],
            isset($d['old_price']) ? (float)$d['old_price'] : null,
            (int)($d['discount_pct'] ?? 0),
            $d['image_url'] ?? null,
            (int)($d['active'] ?? 1),
            (int)($d['featured'] ?? 0),
            (int)($d['sort_order'] ?? 0),
            $d['brand'] ?? null,
            $d['unit_type'] ?? null,
            $d['size'] ?? null,
            (int)($d['has_deposit'] ?? 0),
            (float)($d['deposit_value'] ?? 0),
        ]);
        $newId = (int)$pdo->lastInsertId();
        // Sincroniza product_categories
        $catIds = isset($d['category_ids'])
            ? array_filter(array_unique(array_map('intval', (array)$d['category_ids'])))
            : [(int)$d['category_id']];
        $ins = $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?,?)');
        foreach ($catIds as $cid) { if ($cid) $ins->execute([$newId, $cid]); }
        json_ok(['id' => $newId], 201);
    })(),

    'PUT', 'PATCH' => (function () use ($pdo, $id, $method) {
        if (!$id) json_err('ID required');
        $d = body();
        $allowed = ['category_id','name','description','price','old_price','discount_pct','image_url','active','featured','sort_order','stock','brand','unit_type','size','has_deposit','deposit_value'];
        $fields = [];
        $vals   = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $d)) { $fields[] = "$f = ?"; $vals[] = $d[$f]; }
        }
        if ($fields) {
            $vals[] = $id;
            $pdo->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        }
        // Sincroniza product_categories quando category_ids enviado
        if (isset($d['category_ids'])) {
            $catIds = array_filter(array_unique(array_map('intval', (array)$d['category_ids'])));
            $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?,?)');
            foreach ($catIds as $cid) { $ins->execute([$id, $cid]); }
        } elseif (isset($d['category_id']) && $d['category_id']) {
            $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?,?)')->execute([$id, (int)$d['category_id']]);
        }
        json_ok(['updated' => true]);
    })(),

    'DELETE' => (function () use ($pdo, $id) {
        if (!$id) json_err('ID required');
        $pdo->prepare('UPDATE products SET active = 0 WHERE id = ?')->execute([$id]);
        json_ok(['deleted' => true]);
    })(),

    default => json_err('Method not allowed', 405),
};
