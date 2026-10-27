<?php
// GET /categories        — list active categories
// GET /categories/{id}   — single category
// POST /categories       — create  [admin]
// PUT /categories/{id}   — update  [admin]
// DELETE /categories/{id}— delete  [admin]

$pdo = db();

match ($method) {
    'GET' => (function () use ($pdo, $id) {
        if ($id) {
            $s = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $s->execute([$id]);
            $row = $s->fetch();
            $row ? json_ok($row) : json_err('Not found', 404);
        } else {
            $rows = $pdo->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
            json_ok($rows);
        }
    })(),

    'POST' => (function () use ($pdo) {
        $d = body();
        if (empty($d['name']) || empty($d['slug'])) json_err('name and slug required');
        $s = $pdo->prepare('INSERT INTO categories (name, emoji, slug, sort_order, active) VALUES (?,?,?,?,?)');
        $s->execute([$d['name'], $d['emoji'] ?? '', $d['slug'], $d['sort_order'] ?? 0, $d['active'] ?? 1]);
        json_ok(['id' => (int)$pdo->lastInsertId()], 201);
    })(),

    'PUT' => (function () use ($pdo, $id) {
        if (!$id) json_err('ID required');
        $d = body();
        $fields = [];
        $vals   = [];
        foreach (['name','emoji','slug','sort_order','active'] as $f) {
            if (array_key_exists($f, $d)) { $fields[] = "$f = ?"; $vals[] = $d[$f]; }
        }
        if (!$fields) json_err('Nothing to update');
        $vals[] = $id;
        $pdo->prepare('UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        json_ok(['updated' => true]);
    })(),

    'DELETE' => (function () use ($pdo, $id) {
        if (!$id) json_err('ID required');
        $pdo->prepare('UPDATE categories SET active = 0 WHERE id = ?')->execute([$id]);
        json_ok(['deleted' => true]);
    })(),

    default => json_err('Method not allowed', 405),
};
