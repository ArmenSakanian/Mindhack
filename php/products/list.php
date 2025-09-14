<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте GET.'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

/** Параметры пагинации и фильтров */
$page   = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;
$limit  = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

/** Базовый URL для абсолютных ссылок */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? ($scheme . '://' . $host) : '';

/** Получаем продукты */
try {
  if ($category_id > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=:cid");
    $stmt->execute([':cid' => $category_id]);
    $total = (int)$stmt->fetchColumn();

    $sql = "SELECT id, category_id, category_title, eyebrow, title, tagline, features, price, image, created_at, updated_at
            FROM products
            WHERE category_id=:cid
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':cid', $category_id, PDO::PARAM_INT);
  } else {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $sql = "SELECT id, category_id, category_title, eyebrow, title, tagline, features, price, image, created_at, updated_at
            FROM products
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
  }

  $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при получении продуктов.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** Подготовим список product_id для выборки изображений */
$productIds = array_map(fn($r) => (int)$r['id'], $rows);
$imagesByProduct = [];

/** Если есть продукты — достанем их изображения одной пачкой */
if (!empty($productIds)) {
  try {
    $in = implode(',', array_fill(0, count($productIds), '?'));
    $sqlImg = "SELECT id, product_id, url, alt, sort, is_primary
               FROM product_images
               WHERE product_id IN ($in)
               ORDER BY is_primary DESC, sort ASC, id ASC";
    $stmtImg = $pdo->prepare($sqlImg);
    // bind list
    foreach ($productIds as $i => $pid) {
      $stmtImg->bindValue($i + 1, $pid, PDO::PARAM_INT);
    }
    $stmtImg->execute();
    $allImgs = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allImgs as $img) {
      $pid = (int)$img['product_id'];
      if (!isset($imagesByProduct[$pid])) $imagesByProduct[$pid] = [];
      // абсолютный URL
      $url = (string)$img['url'];
      $url_full = $url;
      if ($url && $baseUrl && strpos($url, 'http') !== 0) {
        if ($url[0] !== '/') $url = '/' . $url;
        $url_full = $baseUrl . $url;
      }
      $imagesByProduct[$pid][] = [
        'id'         => (int)$img['id'],
        'url'        => (string)$img['url'],    // относительный путь как хранится в БД
        'url_full'   => $url_full,              // абсолютная ссылка (удобно фронту)
        'alt'        => $img['alt'] ?? null,
        'sort'       => (int)$img['sort'],
        'is_primary' => (int)$img['is_primary'],
      ];
    }
  } catch (Throwable $e) {
    // Не падаем — просто не вернём images, но логика продуктов продолжится
    $imagesByProduct = [];
  }
}

/** Сформируем items */
$items = array_map(function($r) use ($baseUrl, $imagesByProduct) {
  $features = [];
  if (!empty($r['features'])) {
    $d = json_decode($r['features'], true);
    if (is_array($d)) $features = $d;
  }

  // Абсолютный URL для products.image (обратная совместимость)
  $image = $r['image'] ?? '';
  $image_url = $image;
  if ($image && $baseUrl && strpos($image, 'http') !== 0) {
    if ($image[0] !== '/') $image = '/' . $image;
    $image_url = $baseUrl . $image;
  }

  $pid = (int)$r['id'];
  $images = $imagesByProduct[$pid] ?? [];

  return [
    'id'             => $pid,
    'category_id'    => (int)$r['category_id'],
    'category_title' => $r['category_title'],
    'eyebrow'        => $r['eyebrow'],
    'title'          => $r['title'],
    'tagline'        => $r['tagline'],
    'features'       => $features,
    'price'          => is_numeric($r['price']) ? (float)$r['price'] : $r['price'],
    'image'          => $r['image'],     // как было (относительная)
    'image_url'      => $image_url,      // как было (абсолютная)
    'images'         => $images,         // НОВОЕ: галерея
    'created_at'     => $r['created_at'],
    'updated_at'     => $r['updated_at'],
  ];
}, $rows);

$pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

echo json_encode([
  'ok'    => true,
  'items' => $items,
  'page'  => $page,
  'limit' => $limit,
  'total' => $total,
  'pages' => $pages
], JSON_UNESCAPED_UNICODE);