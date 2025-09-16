<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST/PUT/PATCH.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : null;

if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Неверный JSON. Ожидается {"order":[ids...]}.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Поддерживаем два формата:
 * 1) {"order":[5,3,1,2]}  — позиции с 1
 * 2) {"items":[{"id":5,"sort_order":1}, ...]}
 */
$updates = [];

if (isset($payload['order']) && is_array($payload['order'])) {
  $pos = 1;
  foreach ($payload['order'] as $id) {
    $id = (int)$id;
    if ($id > 0) $updates[] = [$id, $pos++];
  }
} elseif (isset($payload['items']) && is_array($payload['items'])) {
  foreach ($payload['items'] as $row) {
    if (!isset($row['id'], $row['sort_order'])) continue;
    $id = (int)$row['id'];
    $ord = (int)$row['sort_order'];
    if ($id > 0) $updates[] = [$id, $ord];
  }
}

if (!$updates) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Пустой список для обновления.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo->beginTransaction();
  $stmt = $pdo->prepare('UPDATE faqs SET sort_order = :ord WHERE id = :id');
  foreach ($updates as [$id, $ord]) {
    $stmt->execute([':ord'=>$ord, ':id'=>$id]);
  }
  $pdo->commit();

  echo json_encode(['ok'=>true, 'message'=>'Порядок сохранён.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка сохранения порядка: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
