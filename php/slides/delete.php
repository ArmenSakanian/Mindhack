<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : [];
$id = isset($payload['id']) ? (int)$payload['id'] : 0;

if ($id < 1) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'id?'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT path FROM slides WHERE id = ?");
  $stmt->execute([$id]);
  $path = $stmt->fetchColumn();
  if (!$path) {
    echo json_encode(['ok'=>false,'message'=>'Слайд не найден'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo->prepare("DELETE FROM slides WHERE id = ?")->execute([$id]);

  $projectRoot = dirname(__DIR__, 2);
  $abs = $projectRoot . $path;
  if (is_file($abs)) @unlink($abs);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'DB error: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}

