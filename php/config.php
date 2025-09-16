<?php
/**
 * config.php
 * Загружает настройки из .env и даёт доступ через массив $CONFIG
 */

declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue; // комментарий
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key && $value !== null) {
            putenv("$key=$value");
        }
    }
}

$CONFIG = [
  'APP_DEBUG' => (getenv('APP_DEBUG') === '1'),
    // ==== Tinkoff ====
    'TINKOFF_TERMINAL_KEY' => getenv('TINKOFF_TERMINAL_KEY'),
    'TINKOFF_PASSWORD'     => getenv('TINKOFF_PASSWORD'),

    // ==== Base URL ====
    'BASE_URL'             => rtrim(getenv('BASE_URL'), '/'),

    // ==== DB ====
    'DB_HOST'              => getenv('DB_HOST'),
    'DB_NAME'              => getenv('DB_NAME'),
    'DB_USER'              => getenv('DB_USER'),
    'DB_PASS'              => getenv('DB_PASS'),

    // ==== SMTP ====
    'SMTP_HOST'            => getenv('SMTP_HOST'),
    'SMTP_PORT'            => getenv('SMTP_PORT'),
    'SMTP_USER'            => getenv('SMTP_USER'),
    'SMTP_PASS'            => getenv('SMTP_PASS'),
    'SMTP_FROM'            => getenv('SMTP_FROM'),
    'SMTP_FROM_NAME'       => getenv('SMTP_FROM_NAME'),

    // ==== URLs для redirect и webhook ====
    'SUCCESS_URL'          => rtrim(getenv('BASE_URL'), '/') . '/success',
    'FAIL_URL'             => rtrim(getenv('BASE_URL'), '/') . '/fail',
    'WEBHOOK_URL'          => rtrim(getenv('BASE_URL'), '/') . '/php/payment/webhook.php',
];

// Можно подключить глобально:
// require_once __DIR__ . '/config.php';
// global $CONFIG;
// echo $CONFIG['DB_NAME'];
