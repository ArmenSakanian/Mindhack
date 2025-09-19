<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
$acceptedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

$projectRoot = dirname(__DIR__, 2);
$uploadsDir  = $projectRoot . '/uploads/categories';
if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = [];
$files   = [];

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

/** Извлечение полей */
$id           = isset($payload['id']) ? (int)$payload['id'] : 0;
$comingSoon   = isset($payload['coming_soon']) && (string)$payload['coming_soon'] === '1' ? 1 : 0;

$titleRaw       = $payload['title'] ?? null;
$subtitleRaw    = $payload['subtitle'] ?? null;
$descriptionRaw = $payload['description'] ?? null;
$priceRaw       = $payload['price'] ?? null;

$hasNewImage = ($method === 'POST' && isset($files['image']) && is_uploaded_file($files['image']['tmp_name']) && $files['image']['error'] === UPLOAD_ERR_OK);

/** Текущая запись */
if ($id < 1) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Некорректный id.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, coming_soon, title, subtitle, description, price, keywords, image FROM categories WHERE id = :id LIMIT 1");
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

$currentKeywords = [];
if (!empty($current['keywords'])) {
    $decoded = json_decode($current['keywords'], true);
    if (is_array($decoded)) $currentKeywords = $decoded;
}
$oldImageRelPath = (string)($current['image'] ?? '');

/** Нормализация входящих, с возможностью очистки текстовых полей */
$title       = is_null($titleRaw)       ? (string)$current['title']       : trim((string)$titleRaw);
$subtitle    = is_null($subtitleRaw)    ? $current['subtitle']            : trim((string)$subtitleRaw);
$description = is_null($descriptionRaw) ? $current['description']         : trim((string)$descriptionRaw);

/** Пустая строка => NULL */
$subtitle    = ($subtitle === '')    ? null : $subtitle;
$description = ($description === '') ? null : $description;

/** price */
if (is_null($priceRaw) || $priceRaw === '') {
    $price = null; // явная очистка
} else {
    if (!is_numeric($priceRaw)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Цена: некорректное значение.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $price = (float)$priceRaw;
    if ($price <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Цена должна быть больше 0.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** keywords */
$keywords = null;
if (array_key_exists('keywords', $payload)) {
    $kw = $payload['keywords'];
    $arr = [];
    if (is_string($kw)) {
        $decoded = json_decode($kw, true);
        if (is_array($decoded)) $arr = $decoded;
    } elseif (is_array($kw)) {
        $arr = $kw;
    }
    $arr = array_values(array_filter(array_map(function ($v) {
        $v = trim((string)$v);
        $v = preg_replace('/\s{2,}/u', ' ', $v);
        return $v;
    }, $arr), fn($v) => $v !== ''));

    // дедуп
    $tmp = []; $dedup = [];
    foreach ($arr as $k) {
        $low = mb_strtolower($k, 'UTF-8');
        if (isset($tmp[$low])) continue;
        $tmp[$low] = true; $dedup[] = $k;
    }
    if (count($dedup) > 4) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Слишком много ключевых слов (максимум 4).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $keywords = (!empty($dedup) ? $dedup : null); // пусто => NULL (очистка)
} else {
    $keywords = $currentKeywords; // поле не прислано — оставим как есть
}

/** Валидация: обязателен только title */
if ($title === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Заполните заголовок.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Новая картинка — валидация */
if ($hasNewImage) {
    $file = $files['image'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($acceptedMime[$mime])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Недопустимый формат изображения. Разрешены PNG/JPG/WEBP.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($file['size'] > IMAGE_MAX_BYTES) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Файл слишком большой (до 5 МБ).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** Если загружена новая картинка — сохраняем (старую удалим после UPDATE) */
$newImageRelPath = null;
if ($hasNewImage) {
    $file = $files['image'];
    $mime = mime_content_type($file['tmp_name']);
    $ext  = $acceptedMime[$mime] ?? 'bin';
    $name = bin2hex(random_bytes(6)) . '-' . time() . '.' . $ext;

    $dest = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось сохранить новое изображение.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $newImageRelPath = '/uploads/categories/' . $name;
}

/** Собираем целевые значения */
$targetComingSoon  = (int)$comingSoon;
$targetTitle       = $title;
$targetSubtitle    = $subtitle;
$targetDescription = $description;
$targetPrice       = $price;               // число или NULL
$targetKeywordsArr = $keywords ?? null;    // массив или NULL
$targetKeywordsJson= !empty($targetKeywordsArr) ? json_encode($targetKeywordsArr, JSON_UNESCAPED_UNICODE) : null;

/** Проверка «нет изменений» (картинку учитываем только при наличии новой) */
$noDataChange =
    $targetComingSoon  === (int)($current['coming_soon'] ?? 0) &&
    $targetTitle       === (string)$current['title'] &&
    $targetSubtitle    === ($current['subtitle'] ?? null) &&
    $targetDescription === ($current['description'] ?? null) &&
    (float)($targetPrice ?? 0) === (float)($current['price'] ?? 0) &&
    (($targetKeywordsJson ?? 'null') === (is_null($current['keywords']) ? 'null' : json_encode($currentKeywords, JSON_UNESCAPED_UNICODE)));

if ($noDataChange && !$hasNewImage) {
    echo json_encode(['ok' => false, 'message' => 'Вы ничего не меняли. Сохранять нечего.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** UPDATE */
try {
    $set = "coming_soon = :coming_soon,
            title       = :title,
            subtitle    = :subtitle,
            description = :description,
            price       = :price,
            keywords    = :keywords";

    if ($hasNewImage) {
        $set .= ", image = :image";
    }

    $sql = "UPDATE categories
            SET $set, updated_at = NOW()
            WHERE id = :id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $params = [
        ':coming_soon' => $targetComingSoon,
        ':title'       => $targetTitle,
        ':subtitle'    => $targetSubtitle,
        ':description' => $targetDescription,
        ':price'       => $targetPrice,
        ':keywords'    => $targetKeywordsJson,
        ':id'          => $id,
    ];
    if ($hasNewImage) {
        $params[':image'] = $newImageRelPath;
    }
    $stmt->execute($params);

    /** Если заменили изображение — удаляем старый файл */
    $imageReplaced = false;
    if ($hasNewImage && $oldImageRelPath) {
        $imageReplaced = true;
        $rel = ltrim($oldImageRelPath, '/');
        $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;
        $uploadsReal = realpath($uploadsDir);
        $absReal     = (is_file($abs) ? realpath($abs) : null);
        if ($uploadsReal && $absReal && str_starts_with($absReal, $uploadsReal)) {
            @unlink($absReal);
        }
    }

    echo json_encode([
        'ok' => true,
        'updated' => 1,
        'id' => $id,
        'image_replaced' => $imageReplaced,
        'category' => [
            'id'           => $id,
            'coming_soon'  => (bool)$targetComingSoon,
            'title'        => $targetTitle,
            'subtitle'     => $targetSubtitle,
            'description'  => $targetDescription,
            'price'        => $targetPrice,
            'keywords'     => $targetKeywordsArr ?? [],
            'image'        => $hasNewImage ? $newImageRelPath : $oldImageRelPath,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($newImageRelPath) {
        $rel = ltrim($newImageRelPath, '/');
        $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;
        if (is_file($abs)) @unlink($abs);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при обновлении категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}
