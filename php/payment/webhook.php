<?php
declare(strict_types=1);

/**
 * /php/payment/webhook.php
 * Обработка уведомлений Тинькофф. Идемпотентно переводит заказ в paid и отсылает письмо.
 * Логи: /php/logs/webhook-YYYY-MM-DD.log и /php/logs/mail-YYYY-MM-DD.log (через mail.php)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mail.php';

$pdo = db();
global $CONFIG;

/* ================= LOG ================= */
function wlog(string $tag, $data): void {
  $dir = __DIR__ . '/../logs';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $line = '[' . date('Y-m-d H:i:s') . "] $tag: ";
  if (is_string($data)) $line .= $data; else $line .= json_encode($data, JSON_UNESCAPED_UNICODE);
  $line .= "\n";
  @file_put_contents($dir . '/webhook-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}

/* ============== Read JSON ============== */
$raw = file_get_contents('php://input') ?: '';
wlog('RAW', $raw ?: '(empty)');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  echo json_encode(['Success' => false, 'Message' => 'No JSON']); exit;
}

/* ========== Token verification ========= */
/**
 * Уведомления включают чувствительные поля (Pan, ExpDate, CardId), их НЕ включаем в подпись.
 * Берём только whitelisted-поля + Password, сортируем по ключу (ASCII), склеиваем значения, sha256.
 */
// ЗАМЕНИ ЭТУ ФУНКЦИЮ ЦЕЛИКОМ
function tinkoff_token_all(array $params, string $password): string {
  unset($params['Token']);                // Token не подписываем

  $flat = [];
  foreach ($params as $k => $v) {
    if ($v === null || $v === '' || is_array($v)) continue; // пропускаем пустое и массивы
    // ВАЖНО: булевы значения должны быть 'true'/'false', а не '1'/''
    if (is_bool($v)) {
      $flat[$k] = $v ? 'true' : 'false';
    } else {
      // не теряем нули и числа: всё приводим к строке как есть
      $flat[$k] = (string)$v;
    }
  }

  // добавляем секрет
  $flat['Password'] = $password;

  // сортируем по ключу (ASCII)
  ksort($flat, SORT_STRING);

  // склеиваем только значения
  $concat = '';
  foreach ($flat as $v) $concat .= $v;

  return hash('sha256', $concat);
}


$gotToken  = (string)($payload['Token'] ?? '');
$calcToken = tinkoff_token_all($payload, $CONFIG['TINKOFF_PASSWORD']);
$tokenOk   = hash_equals($calcToken, $gotToken);
wlog('TOKEN', ['got'=>$gotToken, 'calc'=>$calcToken, 'ok'=>$tokenOk]);

if (!$tokenOk) {
  // Даём шлюзу возможность ретраить (полезно при диагностике)
  echo json_encode(['Success' => false, 'Message' => 'Bad token']); exit;
}

/* ============== Extract fields ========= */
$orderUid  = (string)($payload['OrderId']   ?? '');
$paymentId = (string)($payload['PaymentId'] ?? '');
$status    = (string)($payload['Status']    ?? '');
$amount    = (int)($payload['Amount']       ?? 0);
wlog('BASIC', compact('orderUid','paymentId','status','amount'));

/* ================ Find order =========== */
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_uid = ? OR payment_id = ? LIMIT 1");
$stmt->execute([$orderUid, $paymentId]);
$order = $stmt->fetch();
if (!$order) {
  wlog('NO_ORDER', ['orderUid'=>$orderUid,'paymentId'=>$paymentId]);
  echo json_encode(['Success' => true]); // чтобы шлюз не долбил
  exit;
}

/* ======== Always log event in payments ======== */
$pdo->prepare("INSERT INTO payments (order_id, payment_id, status, amount, raw, created_at)
               VALUES (?, ?, ?, ?, ?, NOW())")
    ->execute([(int)$order['id'], $paymentId, $status, $amount, json_encode($payload, JSON_UNESCAPED_UNICODE)]);

/* ============== Process status ============== */
try {
  if (in_array($status, ['CONFIRMED','AUTHORIZED'], true)) {
    if ($order['status'] !== 'paid') {
      // помечаем оплаченным
      $pdo->prepare("UPDATE orders SET status='paid', payment_id=?, tinkoff_payload=?, updated_at=NOW() WHERE id=?")
          ->execute([$paymentId, json_encode($payload, JSON_UNESCAPED_UNICODE), (int)$order['id']]);

      // тянем позиции
      $it = $pdo->prepare("SELECT product_name, snapshot_price, qty, snapshot_link FROM order_items WHERE order_id=?");
      $it->execute([(int)$order['id']]);
      $items = $it->fetchAll();

      // письмо покупателю
      [$ok, $err] = sendProductEmail(
        (string)$order['customer_email'],
        (string)$order['customer_name'],
        $order,
        $items
      );
      wlog('MAIL', ['ok'=>$ok, 'err'=>$err]);
    } else {
      wlog('SKIP', 'already paid');
    }
  } elseif (in_array($status, ['CANCELED','REJECTED','REVERSED'], true)) {
    if ($order['status'] !== 'paid') {
      $pdo->prepare("UPDATE orders SET status='failed', tinkoff_payload=?, updated_at=NOW() WHERE id=?")
          ->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), (int)$order['id']]);
    }
  } else {
    wlog('STATUS_PASS', $status);
  }

  echo json_encode(['Success' => true]);
} catch (Throwable $e) {
  wlog('EXC', $e->getMessage());
  // Возвращаем Success:true, чтобы банк не засыпал ретраями; проблему видно в логах
  echo json_encode(['Success' => true]);
}
