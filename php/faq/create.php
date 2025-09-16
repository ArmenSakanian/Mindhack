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

$question = trim($_POST['question'] ?? '');
$answer   = trim($_POST['answer'] ?? '');

if ($question === '' || $answer === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Заполните вопрос и ответ.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare('INSERT INTO faqs (question, answer) VALUES (:q, :a)');
  $stmt->execute([':q' => $question, ':a' => $answer]);

  $id = (int)$pdo->lastInsertId();
  $rowStmt = $pdo->prepare('SELECT id, question, answer, created_at, updated_at FROM faqs WHERE id = :id');
  $rowStmt->execute([':id' => $id]);
  $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'faq'=>$row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Не удалось добавить: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
