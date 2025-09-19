<?php
declare(strict_types=1);

/**
 * /php/payment/webhook.php
 * Обработка уведомлений Тинькофф. Идемпотентно переводит заказ в paid и отсылает письмо.
 * Логи: /php/logs/webhook-YYYY-MM-DD.log и /php/logs/mail-YYYY-MM-DD.log (через mail.php)
 */

header('Content-Type: application/json; charset=utf-8');
/* ===== HARD DEBUG (временно) ===== */
try {
  $raw = file_get_contents('php://input');
  @file_put_contents('/tmp/webhook.raw.log', date('c')." RAW: ".$raw."\n", FILE_APPEND);
  if (function_exists('getallheaders')) {
    @file_put_contents('/tmp/webhook.raw.log', date('c')." HEADERS: ".json_encode(getallheaders(), JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
  }
  error_log('[WEBHOOK] hit at '.date('c').' len='.(is_string($raw)?strlen($raw):0));
} catch (Throwable $e) {
  error_log('[WEBHOOK] early-fail: '.$e->getMessage());
}
/* ===== /HARD DEBUG ===== */


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

/* ===== DEBUG: сырой POST + заголовки ===== */
$raw = file_get_contents('php://input') ?: '';
wlog('RAW', $raw !== '' ? $raw : '(empty)');
if (function_exists('getallheaders')) {
  wlog('HEADERS', getallheaders());
}

/* ============== Read JSON ============== */
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  wlog('ERROR', 'No JSON payload');
  echo json_encode(['Success' => false, 'Message' => 'No JSON']); exit;
}

/* ========== Token verification ========= */
/**
 * Для NOTIFY из Тинькофф берём токен строго по белому списку из док:
 * {TerminalKey, OrderId, Success, Status, PaymentId, ErrorCode, Amount, RebillId, CardId, Pan, ExpDate} + Password,
 * сортировка по ключу ASCII, конкатенация значений, sha256.
 * (Token поле всегда исключаем. Пустые и массивы пропускаем.)
 */
function tinkoff_token_notify(array $p, string $password): string {
  static $whitelist = [
    'TerminalKey','OrderId','Success','Status','PaymentId','ErrorCode',
    'Amount','RebillId','CardId','Pan','ExpDate'
  ];
  $flat = [];
  foreach ($whitelist as $k) {
    if (!array_key_exists($k, $p)) continue;
    $v = $p[$k];
    if ($v === null || $v === '' || is_array($v)) continue;
    if (is_bool($v)) $v = $v ? 'true' : 'false';
    $flat[$k] = (string)$v;
  }
  $flat['Password'] = $password;
  ksort($flat, SORT_STRING);
  $concat = '';
  foreach ($flat as $v) $concat .= $v;
  return hash('sha256', $concat);
}

$gotToken  = (string)($payload['Token'] ?? '');
$calcToken = tinkoff_token_notify($payload, (string)$CONFIG['TINKOFF_PASSWORD']);
$tokenOk   = hash_equals($calcToken, $gotToken);
wlog('TOKEN', ['got'=>$gotToken, 'calc'=>$calcToken, 'ok'=>$tokenOk]);

if (!$tokenOk) {
  // Отвечаем false, чтобы шлюз мог ретраить; ты увидишь это в логах.
  echo json_encode(['Success' => false, 'Message' => 'Bad token']); exit;
}

/* ============== Extract fields ========= */
$orderUid  = (string)($payload['OrderId']   ?? '');
$paymentId = (string)($payload['PaymentId'] ?? '');
$status    = (string)($payload['Status']    ?? '');
$amount    = (int)($payload['Amount']       ?? 0);
$success   = (bool)($payload['Success']     ?? false);
wlog('BASIC', compact('orderUid','paymentId','status','amount','success'));

/* ================ Find order =========== */
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_uid = ? OR payment_id = ? LIMIT 1");
$stmt->execute([$orderUid, $paymentId]);
$order = $stmt->fetch();

if (!$order) {
  wlog('NO_ORDER', ['orderUid'=>$orderUid,'paymentId'=>$paymentId]);
  echo json_encode(['Success' => true]); // не долбим ретраями
  exit;
}

/* ======== Always log event in payments ======== */
$pdo->prepare("
  INSERT INTO payments (order_id, payment_id, status, amount, raw, created_at)
  VALUES (?, ?, ?, ?, ?, NOW())
")->execute([(int)$order['id'], $paymentId, $status, $amount, json_encode($payload, JSON_UNESCAPED_UNICODE)]);

/* ======== Настройки статусов ======== */
/**
 * По умолчанию шлём письмо только на финальных статусах:
 *   - CONFIRMED (двухстадийная после capture)
 *   - COMPLETED (иногда присылают как финальный)
 * Если тебе нужно отправлять и на AUTHORIZED (одностадийная логика) — переключи флаг ниже.
 */
$allowAuthorizedAsPaid = false; // ← ПО НУЖДЕ: true если хочешь письмо на AUTHORIZED

$paidStatuses = ['CONFIRMED','COMPLETED'];
if ($allowAuthorizedAsPaid) $paidStatuses[] = 'AUTHORIZED';

/* ============== Process status ============== */
try {
  if (in_array($status, $paidStatuses, true) && $success) {
    if ($order['status'] !== 'paid') {
      // помечаем оплаченным
      $pdo->prepare("UPDATE orders SET status='paid', payment_id=?, tinkoff_payload=?, updated_at=NOW() WHERE id=?")
          ->execute([$paymentId, json_encode($payload, JSON_UNESCAPED_UNICODE), (int)$order['id']]);

      // тянем позиции
      $it = $pdo->prepare("
        SELECT product_name, snapshot_price, qty, snapshot_link
        FROM order_items WHERE order_id=?
      ");
      $it->execute([(int)$order['id']]);
      $items = $it->fetchAll();

      // письмо покупателю
      $to   = (string)$order['customer_email'];
      $name = (string)$order['customer_name'];

      try {
        [$ok, $err] = sendProductEmail($to, $name, $order, $items);
        wlog('MAIL', ['ok'=>$ok, 'err'=>$err, 'to'=>$to, 'order_id'=>(int)$order['id']]);
      } catch (Throwable $e) {
        wlog('MAIL_EXC', ['msg'=>$e->getMessage(), 'to'=>$to, 'order_id'=>(int)$order['id']]);
      }
    } else {
      wlog('SKIP', 'already paid (idempotent)');
    }
  } elseif (in_array($status, ['CANCELED','REJECTED','REVERSED'], true)) {
    if ($order['status'] !== 'paid') {
      $pdo->prepare("UPDATE orders SET status='failed', tinkoff_payload=?, updated_at=NOW() WHERE id=?")
          ->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), (int)$order['id']]);
      wlog('MARK_FAILED', ['order_id'=>(int)$order['id'], 'status'=>$status]);
    } else {
      wlog('PASS_FAILED', 'order already paid; not downgrading');
    }
  } else {
    wlog('STATUS_PASS', ['status'=>$status, 'success'=>$success]);
  }

  echo json_encode(['Success' => true]);
} catch (Throwable $e) {
  wlog('EXC', $e->getMessage());
  // Возвращаем Success:true, чтобы банк не долбил ретраями; проблему видно в логах
  echo json_encode(['Success' => true]);
}
