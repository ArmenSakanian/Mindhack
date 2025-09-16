<?php
declare(strict_types=1);

/**
 * GET/POST /php/payment/get_state.php
 * Вход:  order_uid | payment_id
 * Выход: { ok, status, order:{...}, items:[...] }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
$pdo = db();

function input_all(): array {
  $d = [];
  if (!empty($_GET))  $d = array_replace($d, $_GET);
  if (!empty($_POST)) $d = array_replace($d, $_POST);
  $raw = file_get_contents('php://input');
  if ($raw) { $j=json_decode($raw,true); if(is_array($j)) $d=array_replace($d,$j); }
  return $d;
}

try {
  $in = input_all();
  $orderUid  = trim((string)($in['order_uid'] ?? ''));
  $paymentId = trim((string)($in['payment_id'] ?? ''));

  if ($orderUid === '' && $paymentId === '') {
    echo json_encode(['ok'=>false,'message'=>'order_uid или payment_id обязателен'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($orderUid !== '') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_uid = ? LIMIT 1");
    $stmt->execute([$orderUid]);
  } else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE payment_id = ? LIMIT 1");
    $stmt->execute([$paymentId]);
  }
  $order = $stmt->fetch();
  if (!$order) { echo json_encode(['ok'=>false,'message'=>'Заказ не найден'], JSON_UNESCAPED_UNICODE); exit; }

  $it = $pdo->prepare("SELECT product_name, snapshot_price, qty, snapshot_link FROM order_items WHERE order_id = ?");
  $it->execute([(int)$order['id']]);
  $items = $it->fetchAll();

  echo json_encode(['ok'=>true,'status'=>$order['status'],'order'=>$order,'items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'message'=>'Server error'], JSON_UNESCAPED_UNICODE);
}
