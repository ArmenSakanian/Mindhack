<?php
/**
 * Список продуктов (с пагинацией и фильтром по категории).
 * Метод: GET
 * Параметры:
 *  - page (int, >=1, по умолчанию 1)
 *  - limit (int, 1..100, по умолчанию 20)
 *  - category_id (int, optional) — чтобы отдать продукты конкретной категории
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте GET.'], JSON_UNESCAPED_UNICODE); exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

$page  = isset($_GET['page'])  ? max(1,(int)$_GET['page'])  : 1;
$limit = isset($_GET['limit']) ? min(100,max(1,(int)$_GET['limit'])) : 20;
$offset = ($page-1)*$limit;

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// базовый URL для image_url
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? ($scheme.'://'.$host) : '';

try {
  if ($category_id > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=:cid");
    $stmt->execute([':cid'=>$category_id]);
    $total = (int)$stmt->fetchColumn();

    $sql = "SELECT id, category_id, category_title, eyebrow, title, tagline, features, price, image, created_at, updated_at
            FROM products WHERE category_id=:cid
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
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при получении продуктов.'], JSON_UNESCAPED_UNICODE); exit;
}

$items = array_map(function($r) use ($baseUrl){
  $features = [];
  if (!empty($r['features'])) {
    $d = json_decode($r['features'], true);
    if (is_array($d)) $features = $d;
  }
  $image = $r['image'] ?? '';
  $image_url = $image;
  if ($image && $baseUrl && strpos($image,'http')!==0) {
    if ($image[0] !== '/') $image = '/'.$image;
    $image_url = $baseUrl . $image;
  }
  return [
    'id'=>(int)$r['id'],
    'category_id'=>(int)$r['category_id'],
    'category_title'=>$r['category_title'],
    'eyebrow'=>$r['eyebrow'],
    'title'=>$r['title'],
    'tagline'=>$r['tagline'],
    'features'=>$features,
    'price'=>is_numeric($r['price'])?(float)$r['price']:$r['price'],
    'image'=>$image,
    'image_url'=>$image_url,
    'created_at'=>$r['created_at'],
    'updated_at'=>$r['updated_at'],
  ];
}, $rows);

$pages = $limit>0 ? (int)ceil(($total)/$limit) : 1;

echo json_encode([
  'ok'=>true,
  'items'=>$items,
  'page'=>$page,
  'limit'=>$limit,
  'total'=>$total,
  'pages'=>$pages
], JSON_UNESCAPED_UNICODE);
