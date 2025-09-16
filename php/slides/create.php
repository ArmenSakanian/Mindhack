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

/** ====== Настройки загрузки изображений ====== */
const SLIDE_MAX_BYTES = 10 * 1024 * 1024; // 10MB
$ACCEPT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];

/** Папка загрузки (как в твоём продукте, через projectRoot) */
$projectRoot = dirname(__DIR__, 2);
$publicPrefix = '/uploads/slides';
$uploadDir = $projectRoot . $publicPrefix;

if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>"Нет доступа к $uploadDir"], JSON_UNESCAPED_UNICODE);
  exit;
}

/** Читаем файлы из input name="files[]" */
if (empty($_FILES['files'])) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'Нет файлов'], JSON_UNESCAPED_UNICODE);
  exit;
}

$filesPayload = [];
$f = $_FILES['files'];
if (is_array($f['name'])) {
  $count = count($f['name']);
  for ($i=0; $i<$count; $i++) {
    $filesPayload[] = [
      'name' => $f['name'][$i],
      'type' => $f['type'][$i],
      'tmp_name' => $f['tmp_name'][$i],
      'error' => $f['error'][$i],
      'size' => $f['size'][$i],
    ];
  }
} else {
  $filesPayload[] = $f;
}

/** Предвалидация */
foreach ($filesPayload as $idx => $file) {
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>"Файл #".($idx+1)." не загружен"], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>"Ошибка загрузки файла #".($idx+1)], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($file['size'] > SLIDE_MAX_BYTES) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>"Файл #".($idx+1)." слишком большой"], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
  if (!isset($ACCEPT[$mime])) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>"Недопустимый формат файла #".($idx+1)], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

try {
  $getMax = (int)$pdo->query("SELECT COALESCE(MAX(position), -1) FROM slides")->fetchColumn();
  $created = [];

  $insert = $pdo->prepare("INSERT INTO slides (path, position, is_active) VALUES (?, ?, 1)");

  foreach ($filesPayload as $i => $file) {
    $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
    $ext  = $ACCEPT[$mime] ?? 'bin';
    $stamp = (int) round(microtime(true) * 1_000_000); // число мкс
$name  = 'slide_' . bin2hex(random_bytes(6)) . '_' . $stamp . '.' . $ext;

    $destAbs = $uploadDir . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
      http_response_code(500);
      echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить файл'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $getMax++;
    $publicPath = $publicPrefix . '/' . $name; // для БД/фронта
    $insert->execute([$publicPath, $getMax]);
    $created[] = ['id'=>$pdo->lastInsertId(), 'path'=>$publicPath, 'position'=>$getMax, 'is_active'=>1];
  }

  echo json_encode(['ok'=>true, 'items'=>$created], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'DB error: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
