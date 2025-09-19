<?php
/**
 * /php/categories/create.php
 * Создание категории (админ).
 * Метод: POST (multipart/form-data)
 * Поля:
 *  - coming_soon (0|1) — НЕ влияет на обязательность полей
 *  - title (string) — ОБЯЗАТЕЛЬНО
 *  - subtitle (string, optional | пустая строка => NULL)
 *  - description (string, optional | пустая строка => NULL)
 *  - price (number > 0, optional | пусто => NULL)
 *  - keywords (JSON string) ИЛИ keywords[] (array, 0..4 шт.) | пусто => NULL
 *  - image (file, optional) — PNG/JPG/WEBP до 5 МБ
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
$acceptedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

/** Папка для файлов */
$projectRoot = dirname(__DIR__, 2);
$uploadDir   = $projectRoot . '/uploads/categories';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось создать папку для загрузок.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** Поля */
$comingSoon   = isset($_POST['coming_soon']) && (string)$_POST['coming_soon'] === '1' ? 1 : 0;
$title        = trim((string)($_POST['title'] ?? ''));
$subtitle     = trim((string)($_POST['subtitle'] ?? ''));
$description  = trim((string)($_POST['description'] ?? ''));
$priceRaw     = $_POST['price'] ?? null;

/** keywords: JSON или массив */
$keywords = [];
if (isset($_POST['keywords'])) {
    $kw = $_POST['keywords'];
    if (is_string($kw)) {
        $decoded = json_decode($kw, true);
        if (is_array($decoded)) $keywords = $decoded;
    } elseif (is_array($kw)) {
        $keywords = $kw;
    }
}
$keywords = array_values(array_filter(array_map(function ($v) {
    $v = trim((string)$v);
    $v = preg_replace('/\s{2,}/u', ' ', $v);
    return $v;
}, $keywords), fn($v) => $v !== ''));

/** дедуп (регистронезависимо) */
$tmp = []; $dedup = [];
foreach ($keywords as $kw) {
    $low = mb_strtolower($kw, 'UTF-8');
    if (isset($tmp[$low])) continue;
    $tmp[$low] = true; $dedup[] = $kw;
}
$keywords = $dedup;

/** файл пришёл? */
$imageProvided = isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name']);
$imagePublicPath = null;
$destPath = null;

/** Валидация: обязателен только title */
$errors = [];
if ($title === '') {
    $errors[] = 'Заполните заголовок.';
}

/** price — если передан, проверяем > 0 */
$price = null;
if ($priceRaw !== null && $priceRaw !== '') {
    if (!is_numeric($priceRaw)) {
        $errors[] = 'Цена: некорректное значение.';
    } else {
        $price = (float)$priceRaw;
        if ($price <= 0) $errors[] = 'Цена должна быть больше 0.';
    }
} else {
    $price = null;
}

/** keywords — допускаем 0..4 */
if (count($keywords) > 4) {
    $errors[] = 'Слишком много ключевых слов (максимум 4).';
}

/** image — опционально, если есть — валидируем */
if ($imageProvided) {
    $file = $_FILES['image'];
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
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Сохранение изображения (если есть) */
if ($imageProvided) {
    $mime = mime_content_type($_FILES['image']['tmp_name']);
    $ext  = $acceptedMime[$mime] ?? 'bin';
    $name = bin2hex(random_bytes(6)) . '-' . time() . '.' . $ext;
    $destPath = $uploadDir . '/' . $name;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось сохранить файл изображения.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $imagePublicPath = '/uploads/categories/' . $name;
}

/** Вставка в БД: ставим sort_order = MAX(sort_order)+1 */
try {
    // Получаем следующий порядковый номер
    $nextSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM categories")->fetchColumn();

    $sql = "INSERT INTO categories
            (coming_soon, title, subtitle, description, price, keywords, image, sort_order)
            VALUES (:coming_soon, :title, :subtitle, :description, :price, :keywords, :image, :sort_order)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':coming_soon' => $comingSoon,
        ':title'       => $title,
        ':subtitle'    => ($subtitle !== '') ? $subtitle : null,
        ':description' => ($description !== '') ? $description : null,
        ':price'       => $price, // либо число, либо NULL
        ':keywords'    => (!empty($keywords) ? json_encode($keywords, JSON_UNESCAPED_UNICODE) : null),
        ':image'       => $imagePublicPath, // либо путь, либо NULL
        ':sort_order'  => $nextSort,
    ]);

    $id = (int)$pdo->lastInsertId();

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'category' => [
            'id'           => $id,
            'coming_soon'  => (bool)$comingSoon,
            'title'        => $title,
            'subtitle'     => $subtitle !== '' ? $subtitle : null,
            'description'  => $description !== '' ? $description : null,
            'price'        => $price,
            'keywords'     => $keywords,
            'image'        => $imagePublicPath,
            'sort_order'   => $nextSort,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($destPath && is_file($destPath)) @unlink($destPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при создании категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}
