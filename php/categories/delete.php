<?php
/**
 * /php/categories/delete.php
 * Жёсткое удаление категории по id + попытка удалить файл изображения.
 *
 * Методы:
 *  - POST (form-data/x-www-form-urlencoded/JSON) с полем id
 *  - DELETE (JSON в теле) с полем id
 *
 * Ответы (JSON):
 *  - { ok: true, deleted: 1, id, image_removed: true|false }
 *  - { ok: false, message: '...' }
 *  - 409, если к категории привязаны продукты.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

/* ---------- 1) Получаем id из запроса ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = null;

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

if ($method === 'POST') {
    if (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
    } else {
        $j = readJsonBody();
        if (isset($j['id'])) $id = (int)$j['id'];
    }
} elseif ($method === 'DELETE') {
    $j = readJsonBody();
    if (isset($j['id'])) $id = (int)$j['id'];
} else {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте POST или DELETE.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Некорректный id категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 2) Проверим связи: есть ли продукты с этой категорией ---------- */
/** Важно: если есть внешние ключи (FK) с ON DELETE RESTRICT — это всё равно упадёт.
 * Мы заранее возвращаем 409, чтобы фронт красиво показал пользователю.
 */
try {
    // Поменяй имя таблицы/поля на свои, если отличаются
    $q = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
    $q->execute([':id' => $id]);
    $linked = (int)$q->fetchColumn();
    if ($linked > 0) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'message' => 'Нельзя удалить: к категории привязаны товары (' . $linked . ' шт.). Сначала отвяжите товары или перенесите их в другую категорию.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    // Если таблицы products нет — просто идём дальше (или раскомментируй для строгого режима)
    // http_response_code(500);
    // echo json_encode(['ok' => false, 'message' => 'Ошибка БД при проверке связей.'], JSON_UNESCAPED_UNICODE);
    // exit;
}

/* ---------- 3) Находим запись и картинку ---------- */
try {
    $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Категория не найдена.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $imagePathDb = (string)($row['image'] ?? '');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при поиске категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 4) Транзакция: удаляем запись ---------- */
try {
    $pdo->beginTransaction();

    $del = $pdo->prepare("DELETE FROM categories WHERE id = :id LIMIT 1");
    $del->execute([':id' => $id]);
    $deleted = $del->rowCount();

    if ($deleted < 1) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось удалить категорию.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // Если падение из-за FK — сообщим понятнее
    $msg = $e->getMessage();
    if (stripos($msg, 'foreign key') !== false || stripos($msg, 'constraint') !== false) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'message' => 'Нельзя удалить: категория используется в других записях.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при удалении категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 5) Пытаемся удалить файл картинки с диска (не фатально) ---------- */
$imageRemoved = false;
if ($imagePathDb !== '') {
    // $imagePathDb ожидаем в виде "/uploads/categories/xxx.jpg" или "uploads/categories/xxx.jpg"
    $projectRoot = dirname(__DIR__, 2); // корень сайта
    $rel = ltrim($imagePathDb, '/');    // убираем ведущий слэш, если есть
    $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;

    // Минимальная защита: удаляем только внутри uploads/categories
    $uploadsDir     = $projectRoot . '/uploads/categories/';
    $uploadsDirReal = is_dir($uploadsDir) ? realpath($uploadsDir) : null;
    $absReal        = (is_file($abs) ? realpath($abs) : null);

    if ($uploadsDirReal && $absReal && str_starts_with($absReal, $uploadsDirReal)) {
        $imageRemoved = @unlink($absReal);
    }
}

/* ---------- 6) Ответ ---------- */
echo json_encode([
    'ok' => true,
    'deleted' => 1,
    'id' => $id,
    'image_removed' => $imageRemoved,
], JSON_UNESCAPED_UNICODE);
