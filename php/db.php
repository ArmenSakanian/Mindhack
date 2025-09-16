<?php
declare(strict_types=1);

/**
 * /php/db.php
 * Единая точка подключения к БД + безопасная сессия.
 * Подключай в других файлах:  require_once __DIR__ . '/db.php';  $pdo = db();
 */

require_once __DIR__ . '/config.php'; // загружает .env и формирует $CONFIG (см. config.php)

/* =======================
   1) Безопасная сессия (вызывай вручную там, где нужна)
   ======================= */
function start_session_secure(): void {
  if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // Lax — чтобы не ломать внешние редиректы (после оплаты и т.п.)
    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'domain'   => '',       // при необходимости укажи свой домен
      'secure'   => $secure,  // только по HTTPS в проде
      'httponly' => true,
      'samesite' => 'Lax',    // ВАЖНО: не Strict
    ]);
    session_start();
  }
}
// Не вызываем автоматически! Делай start_session_secure() в нужных скриптах.

/* =======================
   2) PDO-соединение (Singleton)
   ======================= */
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  // Берём реквизиты из $CONFIG (config.php) с фоллбеком на getenv
  global $CONFIG;
  $host = $CONFIG['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
  $name = $CONFIG['DB_NAME'] ?? getenv('DB_NAME') ?: '';
  $user = $CONFIG['DB_USER'] ?? getenv('DB_USER') ?: '';
  $pass = $CONFIG['DB_PASS'] ?? getenv('DB_PASS') ?: '';

  // Если нужен нестандартный порт — можно указать DB_HOST=127.0.0.1;port=3306 в .env
  $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // PDO::ATTR_PERSISTENT      => true, // по желанию
  ];

  try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Желательно включить корректную сортировку/сравнение
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
  } catch (Throwable $e) {
    // На проде — логируем, а не показываем детали
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'DB connect error'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  return $pdo;
}

/* =======================
   3) Утилиты JSON-ответа (по желанию)
   ======================= */
function json_ok(array $data = [], int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err(string $message = 'Ошибка', int $code = 400): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}
