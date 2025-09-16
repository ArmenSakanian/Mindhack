<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$id = null;
if ($method === 'POST') {
  // Принимаем и form-data, и JSON
  if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
  } else {
    $raw = file_get_contents('php://input');
    if ($raw) {
      $j = json_decode($raw, true);
      if (is_array($j) && isset($j['id'])) $id = (int)$j['id'];
    }
  }
} elseif ($method === 'DELETE') {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $j = json_decode($raw, true);
    if (is_array($j) && isset($j['id'])) $id = (int)$j['id'];
  }
} else {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST или DELETE.'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!$id || $id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Некорректный id.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare('DELETE FROM faqs WHERE id = :id');
  $stmt->execute([':id'=>$id]);

  if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Запись не найдена или уже удалена.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['ok'=>true, 'message'=>'Удалено.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Не удалось удалить: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
