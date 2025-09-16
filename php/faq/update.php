<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$question = trim($_POST['question'] ?? '');
$answer   = trim($_POST['answer'] ?? '');

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Некорректный id.'], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($question === '' || $answer === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Заполните вопрос и ответ.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Проверим, есть ли изменения
  $oldStmt = $pdo->prepare('SELECT question, answer FROM faqs WHERE id = :id');
  $oldStmt->execute([':id' => $id]);
  $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
  if (!$old) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Запись не найдена.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($old['question'] === $question && $old['answer'] === $answer) {
    echo json_encode(['ok'=>false,'message'=>'Вы ничего не меняли.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare('UPDATE faqs SET question = :q, answer = :a WHERE id = :id');
  $stmt->execute([':q' => $question, ':a' => $answer, ':id' => $id]);

  $rowStmt = $pdo->prepare('SELECT id, question, answer, created_at, updated_at FROM faqs WHERE id = :id');
  $rowStmt->execute([':id' => $id]);
  $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'faq'=>$row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Не удалось обновить: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
