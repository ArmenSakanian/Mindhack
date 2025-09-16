<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id < 1 || empty($_FILES['file'])) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'id или файл?'], JSON_UNESCAPED_UNICODE);
  exit;
}

$err = (int)($_FILES['file']['error'] ?? UPLOAD_ERR_OK);
if ($err !== UPLOAD_ERR_OK) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>"Ошибка загрузки: code=$err"], JSON_UNESCAPED_UNICODE);
  exit;
}

$ACCEPT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];
$mime = @mime_content_type($_FILES['file']['tmp_name']) ?: ($_FILES['file']['type'] ?? '');
if (!isset($ACCEPT[$mime])) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'Недопустимый формат'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT path FROM slides WHERE id=?");
  $stmt->execute([$id]);
  $oldPath = $stmt->fetchColumn();
  if (!$oldPath) {
    echo json_encode(['ok'=>false,'message'=>'Слайд не найден'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $projectRoot = dirname(__DIR__, 2);
  $publicPrefix = '/uploads/slides';
  $uploadDir = $projectRoot . $publicPrefix;
  if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
  if (!is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>"Нет доступа к $uploadDir"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $ext  = $ACCEPT[$mime];
  $stamp = (int) round(microtime(true) * 1_000_000); // число мкс
$name  = 'slide_' . bin2hex(random_bytes(6)) . '_' . $stamp . '.' . $ext;

  $destAbs = $uploadDir . '/' . $name;
  if (!@move_uploaded_file($_FILES['file']['tmp_name'], $destAbs)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить файл'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $newPublic = $publicPrefix . '/' . $name;
  $pdo->prepare("UPDATE slides SET path=? WHERE id=?")->execute([$newPublic, $id]);

  // Удаляем старый файл
  $oldAbs = $projectRoot . $oldPath;
  if (is_file($oldAbs)) @unlink($oldAbs);

  echo json_encode(['ok'=>true, 'path'=>$newPublic], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'DB error: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
