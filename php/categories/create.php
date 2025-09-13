<?php
/**
 * /php/categories/create.php
 * Создание категории (админ).
 * Метод: POST (multipart/form-data)
 * Поля:
 *  - title (string, required)
 *  - subtitle (string, required)
 *  - description (string, required)
 *  - price (number, required, > 0)
 *  - keywords (JSON string) ИЛИ keywords[] (array) — от 1 до 4 шт.
 *  - image (file, required) — PNG/JPG/WEBP до 5 МБ
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подключение к БД
require_once __DIR__ . '/../db.php'; // db(): PDO
$pdo = db();

// Настройки загрузки
const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
$acceptedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

// Папка для файлов: /uploads/categories (от корня сайта)
$projectRoot = dirname(__DIR__, 1);      // /php
$projectRoot = dirname($projectRoot, 1); // /
$uploadDir   = $projectRoot . '/uploads/categories';

// Убедимся, что папка существует
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось создать папку для загрузок.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Получение и базовая очистка полей
$title       = trim($_POST['title'] ?? '');
$subtitle    = trim($_POST['subtitle'] ?? '');
$description = trim($_POST['description'] ?? '');
$priceRaw    = $_POST['price'] ?? null;

// Ключевые слова могут прийти как JSON-строка 'keywords' или массив 'keywords[]'
$keywords = [];
if (isset($_POST['keywords'])) {
    $kw = $_POST['keywords'];
    if (is_string($kw)) {
        $decoded = json_decode($kw, true);
        if (is_array($decoded)) $keywords = $decoded;
    } elseif (is_array($kw)) {
        $keywords = $kw;
    }
} elseif (isset($_POST['keywords']) || isset($_POST['keywords0'])) {
    // На всякий случай (если фронт пришлёт необычно)
}

// Нормализуем ключевые слова
$keywords = array_values(array_filter(array_map(function ($v) {
    $v = trim((string)$v);
    // уберём лишние пробелы внутри
    $v = preg_replace('/\s{2,}/u', ' ', $v);
    return $v;
}, $keywords), fn($v) => $v !== ''));

// Убираем дубликаты (регистр не учитываем)
$tmp = [];
$dedup = [];
foreach ($keywords as $kw) {
    $low = mb_strtolower($kw, 'UTF-8');
    if (isset($tmp[$low])) continue;
    $tmp[$low] = true;
    $dedup[] = $kw;
}
$keywords = $dedup;

// Валидация
$errors = [];

if ($title === '')        $errors[] = 'Заполните заголовок.';
if ($subtitle === '')     $errors[] = 'Заполните подзаголовок.';
if ($description === '')  $errors[] = 'Заполните описание.';

$price = null;
if ($priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw)) {
    $errors[] = 'Введите корректную цену.';
} else {
    $price = (float)$priceRaw;
    if ($price <= 0) $errors[] = 'Цена должна быть больше 0.';
}

// Ключевые слова: 1–4
if (count($keywords) < 1 || count($keywords) > 4) {
    $errors[] = 'Нужно от 1 до 4 ключевых слов.';
}

// Проверка файла
if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    $errors[] = 'Загрузите изображение категории.';
} else {
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

// Сохранение изображения
$ext = $acceptedMime[mime_content_type($_FILES['image']['tmp_name'])] ?? 'bin';
$base = bin2hex(random_bytes(6)) . '-' . time();
$filename = $base . '.' . $ext;
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Не удалось сохранить файл изображения.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Путь для хранения в БД и для отдачи фронту (публичный URL-путь)
$imagePublicPath = '/uploads/categories/' . $filename;

// Вставка в БД
try {
    $sql = "INSERT INTO categories
            (title, subtitle, description, price, keywords, image)
            VALUES (:title, :subtitle, :description, :price, :keywords, :image)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title'       => $title,
        ':subtitle'    => $subtitle,
        ':description' => $description,
        ':price'       => $price,
        ':keywords'    => json_encode($keywords, JSON_UNESCAPED_UNICODE),
        ':image'       => $imagePublicPath,
    ]);

    $id = (int)$pdo->lastInsertId();

    // Ответ
    echo json_encode([
        'ok' => true,
        'id' => $id,
        'category' => [
            'id'          => $id,
            'title'       => $title,
            'subtitle'    => $subtitle,
            'description' => $description,
            'price'       => $price,
            'keywords'    => $keywords,
            'image'       => $imagePublicPath,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Откат файла, если запись не создалась
    if (is_file($destPath)) @unlink($destPath);

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Ошибка БД при создании категории.',
        // 'debug' => $e->getMessage() // можно включить временно для отладки
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
