<?php
/**
 * /php/categories/delete.php
 * Удаление категории по id (жёсткое удаление) + удаление файла изображения.
 *
 * Методы:
 *  - POST (form-data/x-www-form-urlencoded/JSON) с полем id
 *  - DELETE (JSON в теле) с полем id
 *
 * Ответы (JSON):
 *  - { ok: true, deleted: 1, id, image_removed: true|false }
 *  - { ok: false, message: '...' }
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

/* ---------- 1) Получаем id из запроса ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = null;

if ($method === 'POST') {
    // form-data / x-www-form-urlencoded
    if (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
    } else {
        // попробуем JSON-тело
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['id'])) {
                $id = (int)$json['id'];
            }
        }
    }
} elseif ($method === 'DELETE') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['id'])) {
            $id = (int)$json['id'];
        }
    }
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

/* ---------- 2) Находим запись и картинку ---------- */
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

/* ---------- 3) Удаляем запись ---------- */
try {
    $del = $pdo->prepare("DELETE FROM categories WHERE id = :id LIMIT 1");
    $del->execute([':id' => $id]);
    $deleted = $del->rowCount();
    if ($deleted < 1) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Не удалось удалить категорию.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка БД при удалении категории.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 4) Пытаемся удалить файл картинки с диска ---------- */
$imageRemoved = false;
if ($imagePathDb !== '') {
    // Определим абсолютный путь к файлу
    // $imagePathDb хранит относительный URL вида "/uploads/categories/xxx.jpg" или "uploads/categories/xxx.jpg"
    $projectRoot = dirname(__DIR__, 1);      // /php
    $projectRoot = dirname($projectRoot, 1); // корень сайта

    $rel = $imagePathDb;
    if ($rel[0] === '/') {
        $rel = substr($rel, 1); // убираем ведущий слэш
    }
    $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;

    // Минимальная защита: удаляем только внутри uploads/categories
    $uploadsDir = $projectRoot . '/uploads/categories/';
    $uploadsDirReal = realpath($uploadsDir);
    $absReal = $abs && file_exists($abs) ? realpath($abs) : null;

    if ($uploadsDirReal && $absReal && str_starts_with($absReal, $uploadsDirReal)) {
        // Удаляем файл (ошибку не считаем фатальной)
        $imageRemoved = @unlink($absReal);
    }
}

/* ---------- 5) Ответ ---------- */
echo json_encode([
    'ok' => true,
    'deleted' => 1,
    'id' => $id,
    'image_removed' => $imageRemoved,
], JSON_UNESCAPED_UNICODE);
