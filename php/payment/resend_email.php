<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mail.php';
$pdo = db();

function read_json(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['ok'=>false,'message'=>'Use POST'], JSON_UNESCAPED_UNICODE); exit;
  }
  $in = read_json();
  $orderUid = trim((string)($in['order_uid'] ?? ''));
  if ($orderUid === '') { echo json_encode(['ok'=>false,'message'=>'order_uid обязателен'], JSON_UNESCAPED_UNICODE); exit; }

  $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_uid = ? LIMIT 1");
  $stmt->execute([$orderUid]);
  $order = $stmt->fetch();
  if (!$order) { echo json_encode(['ok'=>false,'message'=>'Заказ не найден'], JSON_UNESCAPED_UNICODE); exit; }
  if ($order['status'] !== 'paid') { echo json_encode(['ok'=>false,'message'=>'Заказ не оплачен'], JSON_UNESCAPED_UNICODE); exit; }

  $it = $pdo->prepare("SELECT product_name, snapshot_price, qty, snapshot_link FROM order_items WHERE order_id = ?");
  $it->execute([(int)$order['id']]);
  $items = $it->fetchAll();

  $ok = sendProductEmail((string)$order['customer_email'], (string)$order['customer_name'], $order, $items);
  echo json_encode(['ok'=>$ok], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'message'=>'Server error'], JSON_UNESCAPED_UNICODE);
}
