<?php
// GET /products              — list active products (optionally ?category_id=X)
// GET /products/{id}         — single product
// POST /products             — create  [admin]
// PUT /products/{id}         — update  [admin]
// PATCH /products/{id}       — partial update (e.g. toggle active/featured)
// DELETE /products/{id}      — soft-delete [admin]

$pdo = db();

match ($method) {
    'GET' => (function () use ($pdo, $id) {
        if ($id) {
            $s = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $s->execute([$id]);
            $row = $s->fetch();
            $row ? json_ok($row) : json_err('Not found', 404);
        } else {
            $where = 'p.active = 1';
            $vals  = [];
            if (!empty($_GET['category_id'])) {
                $where .= ' AND p.category_id = ?';
                $vals[] = (int)$_GET['category_id'];
            }
            $sql = "SELECT p.*, c.slug AS category_slug, c.name AS category_name
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE $where
                    ORDER BY p.sort_order, p.id";
            $s = $pdo->prepare($sql);
            $s->execute($vals);
            json_ok($s->fetchAll());
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
        json_ok(['id' => (int)$pdo->lastInsertId()], 201);
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
        if (!$fields) json_err('Nothing to update');
        $vals[] = $id;
        $pdo->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        json_ok(['updated' => true]);
    })(),

    'DELETE' => (function () use ($pdo, $id) {
        if (!$id) json_err('ID required');
        $pdo->prepare('UPDATE products SET active = 0 WHERE id = ?')->execute([$id]);
        json_ok(['deleted' => true]);
    })(),

    default => json_err('Method not allowed', 405),
};
