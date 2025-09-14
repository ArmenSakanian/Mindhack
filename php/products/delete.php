<?php
/**
 * Удаление продукта по id + удаление ВСЕХ его изображений с диска.
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
  if (!$id) {
    $raw = file_get_contents('php://input');
    $j = $raw ? json_decode($raw, true) : null;
    if (is_array($j) && isset($j['id'])) $id = (int)$j['id'];
  }
} elseif ($method === 'DELETE') {
  $raw = file_get_contents('php://input');
  $j = $raw ? json_decode($raw, true) : null;
  if (is_array($j) && isset($j['id'])) $id = (int)$j['id'];
} else {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST или DELETE.'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!$id || $id < 1) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'Некорректный id продукта.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ===== Подтянем продукт и список всех связанных изображений ===== */
try {
  // сам продукт
  $stmt = $pdo->prepare("SELECT id, image FROM products WHERE id=:id LIMIT 1");
  $stmt->execute([':id'=>$id]);
  $prod = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$prod) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Продукт не найден.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // все изображения из product_images (галерея)
  $imgsStmt = $pdo->prepare("SELECT url FROM product_images WHERE product_id=:pid");
  $imgsStmt->execute([':pid'=>$id]);
  $galleryUrls = array_map(fn($r) => (string)$r['url'], $imgsStmt->fetchAll(PDO::FETCH_ASSOC));

  // добавим на всякий случай products.image (может дублироваться — уберём ниже)
  $legacy = isset($prod['image']) ? (string)$prod['image'] : '';
  if ($legacy !== '') $galleryUrls[] = $legacy;

  // нормализуем и уберём дубли
  $filesToDelete = [];
  foreach ($galleryUrls as $u) {
    $u = trim($u);
    if ($u === '') continue;
    // хранится как относительный /uploads/product/xxx.ext
    $filesToDelete[$u] = true;
  }
  $filesToDelete = array_keys($filesToDelete);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при подготовке к удалению.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ===== Удалим сам продукт (галерея удалится каскадом по FK) ===== */
try {
  $del = $pdo->prepare("DELETE FROM products WHERE id=:id LIMIT 1");
  $del->execute([':id'=>$id]);
  if ($del->rowCount() < 1) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Не удалось удалить продукт.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при удалении продукта.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ===== Физически удалим файлы изображений (после успешного удаления БД) ===== */
$projectRoot = dirname(__DIR__, 2);
$uploadsDir  = $projectRoot . '/uploads/product';
$uploadsReal = realpath($uploadsDir);
$deletedCount = 0;
$attempted = 0;

if (!empty($filesToDelete) && $uploadsReal) {
  foreach ($filesToDelete as $relUrl) {
    $attempted++;
    // нормализуем относительный путь
    $rel = $relUrl[0] === '/' ? substr($relUrl, 1) : $relUrl;
    $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;

    // безопасность: удаляем только из /uploads/product
    $absReal = file_exists($abs) ? realpath($abs) : null;
    if ($absReal && str_starts_with($absReal, $uploadsReal)) {
      if (@unlink($absReal)) $deletedCount++;
    }
  }
}

echo json_encode([
  'ok' => true,
  'deleted' => 1,
  'id' => $id,
  'files_attempted' => $attempted,
  'files_deleted' => $deletedCount
], JSON_UNESCAPED_UNICODE);