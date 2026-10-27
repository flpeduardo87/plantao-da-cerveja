<?php
// POST /upload  — upload product image [admin]
// Returns: { "url": "https://..." }

if ($method !== 'POST') json_err('Method not allowed', 405);

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) json_err('No valid file uploaded');

$mime    = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) json_err('Only JPG, PNG, WEBP or GIF allowed');

if ($file['size'] > 3 * 1024 * 1024) json_err('File too large (max 3 MB)');

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
$filename = uniqid('prod_', true) . '.' . $ext;
$dest     = rtrim(UPLOADS_PATH, '/') . '/' . $filename;

if (!is_dir(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $dest)) json_err('Failed to save file', 500);

json_ok(['url' => rtrim(UPLOADS_URL, '/') . '/' . $filename]);
