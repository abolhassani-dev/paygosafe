<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_login();
$f = (string)($_GET['f'] ?? '');
if (!preg_match('~^\d+_[a-f0-9]{12}\.(jpg|png|webp|gif)$~', $f)) { http_response_code(404); exit; }
$path = UPLOAD_DIR . '/' . $f;
if (!is_file($path)) { http_response_code(404); exit; }
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][pathinfo($f, PATHINFO_EXTENSION)];
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($path);
