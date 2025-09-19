<?php
/**
 * Удаление продукта по id + удаление ВСЕХ его изображений с диска.
 * Методы: POST (JSON/form) или DELETE (JSON) — поле id
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// CORS (по желанию)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
$pdo = db();

/** ===== Настройки поведения =====
 * Если true — не позволяем удалить продукт, если он встречается в order_items.
 * Вернём 409 Conflict.
 */
const BLOCK_IF_IN_ORDERS = true;

/** Универсальное чтение id из POST/JSON */
function read_id(): ?int {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'POST') {
    if (isset($_POST['id'])) return (int)$_POST['id'];
    $raw = file_get_contents('php://input');
    if ($raw) { $j = json_decode($raw, true); if (is_array($j) && isset($j['id'])) return (int)$j['id']; }
  } elseif ($m === 'DELETE') {
    $raw = file_get_contents('php://input');
    if ($raw) { $j = json_decode($raw, true); if (is_array($j) && isset($j['id'])) return (int)$j['id']; }
  }
  return null;
}

if (!in_array($method, ['POST','DELETE'], true)) {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST или DELETE.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = read_id();
if (!$id || $id < 1) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'Некорректный id продукта.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ===== Проверим наличие продукта и соберём пути изображений ===== */
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

  // если включена блокировка — проверим использование в order_items
  if (BLOCK_IF_IN_ORDERS) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :pid");
    $chk->execute([':pid'=>$id]);
    $count = (int)$chk->fetchColumn();
    if ($count > 0) {
      http_response_code(409);
      echo json_encode([
        'ok'=>false,
        'message'=>'Нельзя удалить продукт: есть связанные строки в заказах.',
        'orders_items_count'=>$count
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }

  // все изображения из product_images (галерея)
  $imgsStmt = $pdo->prepare("SELECT url FROM product_images WHERE product_id=:pid");
  $imgsStmt->execute([':pid'=>$id]);
  $galleryUrls = array_map(fn($r) => (string)$r['url'], $imgsStmt->fetchAll(PDO::FETCH_ASSOC));

  // добавим products.image (legacy, может дублироваться)
  $legacy = isset($prod['image']) ? (string)$prod['image'] : '';
  if ($legacy !== '') $galleryUrls[] = $legacy;

  // нормализуем и уберём дубли
  $uniq = [];
  foreach ($galleryUrls as $u) {
    $u = trim((string)$u);
    if ($u !== '') $uniq[$u] = true;
  }
  $filesToDelete = array_keys($uniq);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при подготовке к удалению.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ===== Удаление из БД =====
 * Если у тебя FK (product_images.product_id) ON DELETE CASCADE — теоретически
 * достаточно удалить из products. Мы чистим явно — это безопасно и прозрачно.
 */
try {
  $pdo->beginTransaction();

  // 1) Галерея (подстраховка)
  $delImgs = $pdo->prepare("DELETE FROM product_images WHERE product_id=:pid");
  $delImgs->execute([':pid'=>$id]);

  // 2) Сам продукт
  $delProd = $pdo->prepare("DELETE FROM products WHERE id=:id LIMIT 1");
  $delProd->execute([':id'=>$id]);
  if ($delProd->rowCount() < 1) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Не удалось удалить продукт.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo->commit();

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при удалении продукта.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ===== Физическое удаление файлов (после успешного удаления из БД) ===== */
$projectRoot = dirname(__DIR__, 2);
$uploadsDir  = $projectRoot . '/uploads/product';
$uploadsReal = is_dir($uploadsDir) ? realpath($uploadsDir) : false;

$attempted = 0;
$deletedCount = 0;
$deletedList = [];

if (!empty($filesToDelete) && $uploadsReal) {
  foreach ($filesToDelete as $relUrl) {
    $attempted++;

    // нормализуем относительный путь
    $rel = ltrim($relUrl, '/\\'); // убираем ведущий слэш
    $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;

    // безопасность: удаляем только из /uploads/product
    $absReal = file_exists($abs) ? realpath($abs) : null;
    if ($absReal && str_starts_with($absReal, $uploadsReal)) {
      if (@unlink($absReal)) {
        $deletedCount++;
        $deletedList[] = $relUrl;
      }
    }
  }
}

echo json_encode([
  'ok' => true,
  'deleted' => 1,
  'id' => $id,
  'files_attempted' => $attempted,
  'files_deleted' => $deletedCount,
  'files_deleted_list' => $deletedList
], JSON_UNESCAPED_UNICODE);
