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
 *      { id, title, subtitle, description, price, keywords:[], image, image_url, created_at, updated_at }
 *    ],
 *    page, limit, total, pages
 *  }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте GET.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../db.php'; // функция db(): PDO
$pdo = db();

// Параметры пагинации
$page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

// Соберём базовый URL для абсолютного пути к картинке
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? ($scheme . '://' . $host) : '';

// Получим total
try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при подсчёте записей.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Получим страницу данных
try {
    $sql = "SELECT id, title, subtitle, description, price, keywords, image, created_at, updated_at
            FROM categories
            ORDER BY created_at DESC
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

// Преобразуем ключевые слова из JSON и добавим абсолютный URL для картинки
$items = array_map(function ($r) use ($baseUrl) {
    $keywords = [];
    if (isset($r['keywords'])) {
        $decoded = json_decode($r['keywords'], true);
        if (is_array($decoded)) $keywords = $decoded;
    }
    $image = $r['image'] ?? '';
    $image_url = $image;
    // Если путь относительный — сделаем абсолютный
    if ($image && $baseUrl && strpos($image, 'http') !== 0) {
        // гарантируем ведущий слэш
        if ($image[0] !== '/') $image = '/' . $image;
        $image_url = $baseUrl . $image;
    }

    return [
        'id'          => (int)$r['id'],
        'title'       => $r['title'],
        'subtitle'    => $r['subtitle'],
        'description' => $r['description'],
        'price'       => is_numeric($r['price']) ? (float)$r['price'] : $r['price'],
        'keywords'    => $keywords,
        'image'       => $image,       // относительный путь (как в БД)
        'image_url'   => $image_url,   // абсолютный путь (удобно для фронта)
        'created_at'  => $r['created_at'],
        'updated_at'  => $r['updated_at'],
    ];
}, $rows);

// Подсчёт количества страниц
$pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

echo json_encode([
    'ok'    => true,
    'items' => $items,
    'page'  => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => $pages
], JSON_UNESCAPED_UNICODE);
