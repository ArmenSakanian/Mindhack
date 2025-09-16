<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

try {
  $sql = "SELECT id, path, position, is_active FROM slides
          ORDER BY position ASC, id ASC";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'DB error: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}

