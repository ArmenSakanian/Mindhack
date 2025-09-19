<?php
/**
 * /php/categories/list.php
 * Возвращает список категорий для админки/фронта.
 * Метод: GET
 * Параметры:
 *  - page  (int, >=1, по умолчанию 1)
 *  - limit (int, 1..100, по умолчанию 20)
 * Ответ:
 *  {
 *    ok: true,
 *    items: [
 *      { id, coming_soon, title, subtitle, description, price, keywords:[], image, image_url, created_at, updated_at, sort_order }
 *    ],
 *    page, limit, total, pages
 *  }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте GET.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../db.php'; // функция db(): PDO
$pdo = db();

/** Параметры пагинации */
$page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

/** Базовый URL для абсолютного пути к картинке */
$proto = null;
// учитываем прокси/Cloudflare
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $proto = 'https';
} else {
    $proto = 'http';
}
$host    = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = ($host !== '') ? ($proto . '://' . $host) : '';

/** total */
try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при подсчёте записей.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** страница данных */
try {
    // ВАЖНО: сортируем по sort_order (позиция), затем по id
    $sql = "SELECT id, coming_soon, title, subtitle, description, price, keywords, image, created_at, updated_at, sort_order
            FROM categories
            ORDER BY sort_order ASC, id ASC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при получении категорий.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** трансформация полей */
$items = array_map(function (array $r) use ($baseUrl) {
    // keywords JSON -> array
    $keywords = [];
    if (array_key_exists('keywords', $r) && $r['keywords'] !== null && $r['keywords'] !== '') {
        $decoded = json_decode((string)$r['keywords'], true);
        if (is_array($decoded)) $keywords = $decoded;
    }

    // image относительный → абсолютный
    $image = (string)($r['image'] ?? '');
    $image_url = $image;
    if ($image !== '' && $baseUrl && !(str_starts_with($image, 'http://') || str_starts_with($image, 'https://'))) {
        if ($image[0] !== '/') $image = '/' . $image;
        $image_url = $baseUrl . $image;
    }

    // price в float, если число
    $priceVal = $r['price'];
    $price = is_numeric($priceVal) ? (float)$priceVal : null;

    return [
        'id'          => (int)$r['id'],
        'coming_soon' => (bool)($r['coming_soon'] ?? 0),
        'title'       => $r['title'],
        'subtitle'    => $r['subtitle'],
        'description' => $r['description'],
        'price'       => $price,
        'keywords'    => $keywords,
        'image'       => $image,       // относительный путь (как в БД)
        'image_url'   => $image_url,   // абсолютный путь (для фронта)
        'created_at'  => $r['created_at'],
        'updated_at'  => $r['updated_at'],
        'sort_order'  => isset($r['sort_order']) ? (int)$r['sort_order'] : null,
    ];
}, $rows);

/** страницы */
$pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

echo json_encode([
    'ok'    => true,
    'items' => $items,
    'page'  => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => $pages
], JSON_UNESCAPED_UNICODE);
