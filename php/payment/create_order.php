<?php
/**
 * POST /php/payment/create_order.php
 * Вход: {
 *   customer: { name: string, email: string },
 *   marketing_consent: boolean,
 *   items: [{ product_id: number, qty: number }]
 * }
 * Выход: { ok:true, payment_url:string, order_uid:string } | { ok:false, message:string }
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$pdo = db();
global $CONFIG;

/** утилки */
function read_json(): array {
  $raw = file_get_contents('php://input');
  $j = $raw ? json_decode($raw, true) : null;
  return is_array($j) ? $j : [];
}
/** безопасный uid заказа */
function gen_uid(): string { return bin2hex(random_bytes(8)) . dechex(time()); }
/** сигнатура Tinkoff: все скалярные поля + Password, отсортировать и склеить значения */
function tinkoff_token(array $params, string $password): string {
  unset($params['Token']);
  $flat = [];
  foreach ($params as $k => $v) {
    if ($v === null || $v === '' || is_array($v)) continue;
    $flat[$k] = (string)$v;
  }
  $flat['Password'] = $password;
  ksort($flat, SORT_STRING);
  $concat = '';
  foreach ($flat as $v) $concat .= $v;
  return hash('sha256', $concat);
}
/** http post json */
function http_post_json(string $url, array $data): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if ($err) return ['Success'=>false,'Message'=>'cURL: '.$err, 'HttpCode'=>$code];
  $j = json_decode($resp, true);
  return is_array($j) ? $j + ['HttpCode'=>$code] : ['Success'=>false,'Message'=>'Bad JSON from gateway', 'Raw'=>$resp, 'HttpCode'=>$code];
}

/** логирование (если APP_DEBUG=1) */
function log_err(string $where, string $msg): void {
  global $CONFIG;
  if (empty($CONFIG['APP_DEBUG'])) return;
  $dir = __DIR__ . '/../logs';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  @file_put_contents($dir.'/create_order-'.date('Y-m-d').'.log',
    '['.date('H:i:s')."] $where: $msg\n", FILE_APPEND);
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('Use POST', 405);
  }

  $in = read_json();
  $itemsIn   = $in['items'] ?? [];
  $customer  = $in['customer'] ?? [];
  $marketing = !empty($in['marketing_consent']);

  $name  = trim((string)($customer['name']  ?? ''));
  $email = trim((string)($customer['email'] ?? ''));
  if ($name === '' || $email === '') json_err('Имя и email обязательны', 422);
  if (!is_array($itemsIn) || !count($itemsIn)) json_err('Корзина пуста', 422);

  // собираем product_ids -> qty
  $qtyById = [];
  foreach ($itemsIn as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $qty = max(1, (int)($it['qty'] ?? 1));
    if ($pid > 0) $qtyById[$pid] = ($qtyById[$pid] ?? 0) + $qty;
  }
  if (!count($qtyById)) json_err('Нет корректных product_id', 422);

  // загружаем товары из products (title, price[руб], link_url)
  $placeholders = implode(',', array_fill(0, count($qtyById), '?'));
  $stmt = $pdo->prepare("SELECT id, title, price, link_url FROM products WHERE id IN ($placeholders)");
  $stmt->execute(array_keys($qtyById));
  $found = $stmt->fetchAll();
  if (!count($found)) json_err('Товары не найдены', 422);
  $byId = [];
  foreach ($found as $p) $byId[(int)$p['id']] = $p;
  if (count($byId) !== count($qtyById)) json_err('Некоторые товары не найдены', 422);

  // начинаем транзакцию: создаём заказ и позиции
  $pdo->beginTransaction();

  $orderUid = gen_uid();
  $pdo->prepare("INSERT INTO orders (order_uid, customer_name, customer_email, amount, currency, status, created_at, updated_at)
                 VALUES (?, ?, ?, 0, 'RUB', 'pending', NOW(), NOW())")
      ->execute([$orderUid, $name, $email]);
  $orderId = (int)$pdo->lastInsertId();

  $insItem = $pdo->prepare("INSERT INTO order_items
     (order_id, product_id, product_name, snapshot_price, qty, snapshot_link)
     VALUES (?,?,?,?,?,?)");

  $total = 0; // копейки
  foreach ($qtyById as $pid => $qty) {
    $p = $byId[$pid];
    $priceRub = (float)$p['price'];              // decimal(10,2) в рублях
    $priceKop = (int)round($priceRub * 100);     // конвертируем в копейки
    $lineSum  = $priceKop * $qty;
    $total   += $lineSum;

    $insItem->execute([
      $orderId,
      (int)$p['id'],
      (string)$p['title'],
      $priceKop,
      (int)$qty,
      (string)($p['link_url'] ?? '')
    ]);
  }

  // сумма заказа
  $pdo->prepare("UPDATE orders SET amount=?, updated_at=NOW() WHERE id=?")
      ->execute([$total, $orderId]);

  // маркетинг (best-effort)
  if ($marketing) {
    try {
      $pdo->prepare("INSERT IGNORE INTO marketing_subscriptions (email, consent_at, source)
                     VALUES (?, NOW(), 'checkout')")
          ->execute([$email]);
    } catch (\Throwable $e) { /* ignore */ }
  }

  // --- Tinkoff Init ---
  $initParams = [
    'TerminalKey'     => $CONFIG['TINKOFF_TERMINAL_KEY'],
    'Amount'          => $total,             // копейки!
    'OrderId'         => $orderUid,
    'Description'     => 'Оплата заказа #' . $orderId,
    'NotificationURL' => $CONFIG['WEBHOOK_URL'],
    'SuccessURL'      => $CONFIG['SUCCESS_URL'],
    'FailURL'         => $CONFIG['FAIL_URL'],
    'CustomerKey'     => substr(hash('sha1', $email), 0, 32),
    // Без массивов (Receipt/DATA), чтобы не усложнять подпись на первом этапе
  ];
  $initParams['Token'] = tinkoff_token($initParams, $CONFIG['TINKOFF_PASSWORD']);

  if (!empty($CONFIG['APP_DEBUG'])) {
    log_err('INIT_PARAMS', json_encode($initParams, JSON_UNESCAPED_UNICODE));
  }

  $resp = http_post_json('https://securepay.tinkoff.ru/v2/Init', $initParams);
  if (empty($resp['Success'])) {
    $msg = 'Tinkoff Init error: ' . json_encode($resp, JSON_UNESCAPED_UNICODE);
    log_err('INIT_FAIL', $msg);
    throw new Exception($msg);
  }

  $paymentId  = (string)($resp['PaymentId'] ?? '');
  $paymentURL = (string)($resp['PaymentURL'] ?? '');

  // сохраняем payment_id и сырой ответ
  $pdo->prepare("UPDATE orders SET payment_id=?, tinkoff_payload=? , updated_at=NOW() WHERE id=?")
      ->execute([$paymentId, json_encode($resp, JSON_UNESCAPED_UNICODE), $orderId]);

  // первая запись в payments (история)
  $pdo->prepare("INSERT INTO payments (order_id, payment_id, status, amount, raw, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())")
      ->execute([$orderId, $paymentId, (string)($resp['Status'] ?? ''), $total, json_encode($resp, JSON_UNESCAPED_UNICODE)]);

  $pdo->commit();

  json_ok([
    'order_uid'   => $orderUid,
    'payment_url' => $paymentURL
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  log_err('EXCEPTION', $e->getMessage());
  json_err(!empty($CONFIG['APP_DEBUG']) ? ('Server error: '.$e->getMessage()) : 'Server error', 500);
}
