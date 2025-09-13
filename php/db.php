<?php
/**
 * /php/db.php
 * Единая точка подключения к БД + безопасная сессия.
 * Подключай в других файлах:  require_once __DIR__ . '/db.php';  $pdo = db();
 */

/* =======================
   1) Конфигурация БД
   ======================= */
const DB_HOST = 'localhost';
const DB_NAME = 'u3255026_mindhack_db';
const DB_USER = 'u3255026_mindhack_user';
const DB_PASS = 'Armen7725/1';

/* Если хочешь — можно переопределять через ENV (если среда поддерживает)
   putenv('DB_NAME=xxx'); и т.п. */
$DB_HOST = getenv('DB_HOST') ?: DB_HOST;
$DB_NAME = getenv('DB_NAME') ?: DB_NAME;
$DB_USER = getenv('DB_USER') ?: DB_USER;
$DB_PASS = getenv('DB_PASS') ?: DB_PASS;

/* =======================
   2) Безопасная сессия
   ======================= */
function start_session_secure(): void {
  if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
      'httponly' => true,
      'samesite' => 'Strict',
      'secure'   => $secure,
      'path'     => '/',
    ]);
    session_start();
  }
}
start_session_secure();


/* =======================
   3) PDO-соединение (Singleton)
   ======================= */
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = "mysql:host={$GLOBALS['DB_HOST']};dbname={$GLOBALS['DB_NAME']};charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  try {
    $pdo = new PDO($dsn, $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], $options);
  } catch (Throwable $e) {
    // На проде лучше логировать, а не показывать детали
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'DB connect error'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  return $pdo;
}

/* =======================
   4) (Необязательно) Мини-утилиты JSON-ответа
   Включил прямо здесь, чтобы не плодить файлов. Удобно для API.
   Если не нужно — можешь удалить этот блок.
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
