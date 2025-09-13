<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();
echo json_encode(['ok' => !empty($_SESSION['is_admin'])], JSON_UNESCAPED_UNICODE);
