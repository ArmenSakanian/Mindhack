<?php
/**
 * /php/categories/update.php
 * Обновление категории по id.
 *
 * Методы:
 *  - POST (multipart/form-data) — для обновлений с возможной заменой изображения
 *  - PUT/PATCH (application/json) — для обновлений без изображения
 *
 * Поля:
 *  - id (int, required)
 *  - title (string, required)
 *  - subtitle (string, required)
 *  - description (string, required)
 *  - price (number > 0, required)
 *  - keywords (JSON string) ИЛИ keywords[] (array), 1..4
 *  - image (file, optional) — PNG/JPG/WEBP до 5 МБ; если передан — заменить
 *
 * Ответы:
 *  - { ok: true, updated: 1, id, image_replaced: bool, category: {...} }
 *  - { ok: false, message: '...' }
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
$acceptedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

/* ---------- 0) Хелперы пути проекта/загрузок ---------- */
$phpDir      = __DIR__;                  // /php/categories
$projectRoot = dirname($phpDir, 2);      // корень сайта
$uploadsDir  = $projectRoot . '/uploads/categories';

if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}

/* ---------- 1) Чтение запроса ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$payload = [];
$files = [];

if ($method === 'POST') {
    $payload = $_POST;
    $files   = $_FILES;
} elseif ($method === 'PUT' || $method === 'PATCH') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) $payload = $json;
    }
} else {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте POST или PUT/PATCH.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 2) Извлечение и нормализация полей ---------- */
$id           = isset($payload['id']) ? (int)$payload['id'] : 0;
$title        = trim((string)($payload['title'] ?? ''));
$subtitle     = trim((string)($payload['subtitle'] ?? ''));
$description  = trim((string)($payload['description'] ?? ''));
$priceRaw     = $payload['price'] ?? null;

$keywords = [];
if (array_key_exists('keywords', $payload)) {
    $kw = $payload['keywords'];
    if (is_string($kw)) {
        $decoded = json_decode($kw, true);
        if (is_array($decoded)) $keywords = $decoded;
    } elseif (is_array($kw)) {
        $keywords = $kw;
    }
} else {
    // альтернативный формат keywords[]
    // если пришёл через multipart, PHP сам соберёт в $payload['keywords'] при name="keywords[]"
}

$keywords = array_values(array_filter(array_map(function ($v) {
    $v = trim((string)$v);
    $v = preg_replace('/\s{2,}/u', ' ', $v);
    return $v;
}, $keywords), fn($v) => $v !== ''));

// Убираем дубликаты без учёта регистра
$tmp = []; $dedup = [];
foreach ($keywords as $kw) {
    $low = mb_strtolower($kw, 'UTF-8');
    if (isset($tmp[$low])) continue;
    $tmp[$low] = true; $dedup[] = $kw;
}
$keywords = $dedup;

/* ---------- 3) Валидация ---------- */
$errors = [];
if ($id < 1)               $errors[] = 'Некорректный id.';
if ($title === '')         $errors[] = 'Заполните заголовок.';
if ($subtitle === '')      $errors[] = 'Заполните подзаголовок.';
if ($description === '')   $errors[] = 'Заполните описание.';

$price = null;
if ($priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw)) {
    $errors[] = 'Введите корректную цену.';
} else {
    $price = (float)$priceRaw;
    if ($price <= 0) $errors[] = 'Цена должна быть больше 0.';
}

if (count($keywords) < 1 || count($keywords) > 4) {
    $errors[] = 'Нужно от 1 до 4 ключевых слов.';
}

$hasNewImage = false;
if ($method === 'POST' && isset($files['image']) && is_uploaded_file($files['image']['tmp_name'])) {
    $file = $files['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Ошибка загрузки изображения.';
    } else {
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($acceptedMime[$mime])) {
            $errors[] = 'Недопустимый формат изображения. Разрешены PNG/JPG/WEBP.';
        }
        if ($file['size'] > IMAGE_MAX_BYTES) {
            $errors[] = 'Файл слишком большой (до 5 МБ).';
        }
        $hasNewImage = empty($errors);
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 4) Получить текущую запись ---------- */
try {
    $stmt = $pdo->prepare("SELECT id, title, subtitle, description, price, keywords, image FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Категория не найдена.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при чтении категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 5) Сравнение (есть ли изменения) ---------- */
$currentKeywords = [];
if (!empty($current['keywords'])) {
    $decoded = json_decode($current['keywords'], true);
    if (is_array($decoded)) $currentKeywords = $decoded;
}

$noDataChange =
    $title       === (string)$current['title'] &&
    $subtitle    === (string)$current['subtitle'] &&
    $description === (string)$current['description'] &&
    (float)$price === (float)$current['price'] &&
    json_encode(array_values($keywords), JSON_UNESCAPED_UNICODE) === json_encode(array_values($currentKeywords), JSON_UNESCAPED_UNICODE);

if ($noDataChange && !$hasNewImage) {
    // Ничего не меняли
    echo json_encode(['ok' => false, 'message' => 'Вы ничего не меняли. Сохранять нечего.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 6) Если есть новая картинка — сохранить, старую удалить после успешного UPDATE ---------- */
$newImageRelPath = null;
$oldImageRelPath = (string)($current['image'] ?? '');

if ($hasNewImage) {
    $file = $files['image'];
    $mime = mime_content_type($file['tmp_name']);
    $ext  = $acceptedMime[$mime] ?? 'bin';
    $base = bin2hex(random_bytes(6)) . '-' . time();
    $name = $base . '.' . $ext;

    $dest = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось сохранить новое изображение.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $newImageRelPath = '/uploads/categories/' . $name;
}

/* ---------- 7) Обновление записи ---------- */
try {
    $sql = "UPDATE categories
            SET title = :title,
                subtitle = :subtitle,
                description = :description,
                price = :price,
                keywords = :keywords"
            . ($hasNewImage ? ", image = :image" : "") .
           " WHERE id = :id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $params = [
        ':title'       => $title,
        ':subtitle'    => $subtitle,
        ':description' => $description,
        ':price'       => $price,
        ':keywords'    => json_encode($keywords, JSON_UNESCAPED_UNICODE),
        ':id'          => $id,
    ];
    if ($hasNewImage) {
        $params[':image'] = $newImageRelPath;
    }

    $stmt->execute($params);
    $updated = $stmt->rowCount(); // может быть 0, если изменились только поля, равные прежним (мы уже проверили выше)

    // Если было новое изображение — удалим старое
    $imageReplaced = false;
    if ($hasNewImage) {
        $imageReplaced = true;
        if ($oldImageRelPath) {
            $rel = $oldImageRelPath[0] === '/' ? substr($oldImageRelPath, 1) : $oldImageRelPath;
            $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;

            $uploadsReal = realpath($uploadsDir);
            $absReal = (file_exists($abs)) ? realpath($abs) : null;

            if ($uploadsReal && $absReal && str_starts_with($absReal, $uploadsReal)) {
                @unlink($absReal);
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'updated' => 1,
        'id' => $id,
        'image_replaced' => $imageReplaced,
        'category' => [
            'id'          => $id,
            'title'       => $title,
            'subtitle'    => $subtitle,
            'description' => $description,
            'price'       => $price,
            'keywords'    => $keywords,
            'image'       => $hasNewImage ? $newImageRelPath : $oldImageRelPath,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Откат нового файла, если он был сохранён
    if ($hasNewImage && isset($dest) && file_exists($dest)) {
        @unlink($dest);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при обновлении категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}
