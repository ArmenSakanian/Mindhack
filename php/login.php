<?php
// /php/login.php
declare(strict_types=1);

// Заголовки: JSON + без кеша
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Без вывода предупреждений наружу (логируй при необходимости)
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php'; // должен вернуть PDO через функцию db()

// Разрешаем только POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Парсим вход: сначала пробуем JSON, затем form-data/x-www-form-urlencoded
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
  $data = [
    'username' => $_POST['username'] ?? $_POST['login'] ?? null,
    'password' => $_POST['password'] ?? null,
  ];
}

// Унифицируем поля
$username = trim((string)($data['username'] ?? $data['login'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Заполните логин и пароль'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = db();
  // Рекомендуется задать режим выборки в db(): ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  // На всякий случай обеспечим ассоциативный массив здесь:
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // Проверь правильные названия таблицы/полей под твою БД
  // Пример: таблица `admins` с полями `username`, `password_hash`
  $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
  $stmt->execute([$username]);
  $admin = $stmt->fetch();

  // Проверяем пользователя и пароль
  $ok = false;
  if ($admin) {
    // Основной путь: хэш
    if (!empty($admin['password_hash']) && password_verify($password, (string)$admin['password_hash'])) {
      $ok = true;
    }
    // (НЕжелательно, но на случай, если в БД пока лежит голый пароль)
    // if (!$ok && isset($admin['password']) && hash_equals((string)$admin['password'], $password)) {
    //   $ok = true;
    // }
  }

  if (!$ok) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Неверный логин или пароль'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------- СЕССИЯ ----------
  // Безопасные параметры cookie (SameSite=Lax хорош для обычного логина)
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'domain'   => '',      // оставь пустым для текущего домена
      'secure'   => $secure, // true на https
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  } else {
    // Для очень старых версий (если вдруг)
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
  }

  session_start();                   // ← обязательно!
  session_regenerate_id(true);       // предотвращает фиксацию сессии

  $_SESSION['admin_id']       = (int)$admin['id'];
  $_SESSION['admin_username'] = (string)$admin['username'];
  $_SESSION['is_admin']       = true;
  $_SESSION['login_time']     = time();

  // Закрываем сессию ДО вывода ответа, чтобы Set-Cookie ушёл сразу
  session_write_close();

  echo json_encode(['ok' => true, 'message' => 'Вход выполнен'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  // Можно логировать $e->getMessage() в файл
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Внутренняя ошибка сервера'], JSON_UNESCAPED_UNICODE);
}
