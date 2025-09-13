<?php
/**
 * Удаление продукта по id + удаление его изображения.
 * Методы: POST (JSON/form) или DELETE (JSON) — поле id
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = null;

if ($method === 'POST') {
  if (isset($_POST['id'])) $id = (int)$_POST['id'];
  if (!$id) { $raw=file_get_contents('php://input'); $j=$raw?json_decode($raw,true):null; if(is_array($j)&&isset($j['id'])) $id=(int)$j['id']; }
} elseif ($method === 'DELETE') {
  $raw=file_get_contents('php://input'); $j=$raw?json_decode($raw,true):null; if(is_array($j)&&isset($j['id'])) $id=(int)$j['id'];
} else {
  http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST или DELETE.'], JSON_UNESCAPED_UNICODE); exit;
}

if (!$id || $id<1) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Некорректный id продукта.'], JSON_UNESCAPED_UNICODE); exit; }

// найдём и удалим запись
try {
  $stmt = $pdo->prepare("SELECT image FROM products WHERE id=:id LIMIT 1");
  $stmt->execute([':id'=>$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Продукт не найден.'], JSON_UNESCAPED_UNICODE); exit; }
  $imageRel = (string)($row['image'] ?? '');
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при поиске продукта.'], JSON_UNESCAPED_UNICODE); exit;
}

try {
  $del = $pdo->prepare("DELETE FROM products WHERE id=:id LIMIT 1");
  $del->execute([':id'=>$id]);
  if ($del->rowCount()<1) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Не удалось удалить продукт.'], JSON_UNESCAPED_UNICODE); exit; }
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при удалении продукта.'], JSON_UNESCAPED_UNICODE); exit;
}

// удалить файл
$projectRoot = dirname(__DIR__, 2);
$uploadsDir  = $projectRoot . '/uploads/product/';
$imageRemoved = false;
if ($imageRel) {
  $rel = $imageRel[0]==='/' ? substr($imageRel,1) : $imageRel;
  $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;
  $uploadsReal = realpath($uploadsDir);
  $absReal = file_exists($abs) ? realpath($abs) : null;
  if ($uploadsReal && $absReal && str_starts_with($absReal, $uploadsReal)) {
    $imageRemoved = @unlink($absReal);
  }
}

echo json_encode(['ok'=>true,'deleted'=>1,'id'=>$id,'image_removed'=>$imageRemoved], JSON_UNESCAPED_UNICODE);
