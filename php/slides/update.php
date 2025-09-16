<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : null;

if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'items?'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo->beginTransaction();
  $upd = $pdo->prepare("UPDATE slides SET position=?, is_active=? WHERE id=?");

  foreach ($payload['items'] as $it) {
    $id  = (int)($it['id'] ?? 0);
    $pos = (int)($it['position'] ?? 0);
    $act = (int)($it['is_active'] ?? 1);
    if ($id > 0) $upd->execute([$pos, $act, $id]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'DB error: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}

