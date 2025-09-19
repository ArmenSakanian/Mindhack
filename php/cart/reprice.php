<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../db.php';
$pdo = db();

$payload = json_decode(file_get_contents('php://input'), true);
$items = $payload['items'] ?? []; // [{id, qty}]
if (!is_array($items) || !$items) {
  echo json_encode(['ok'=>false,'message'=>'Пустая корзина']); exit;
}

$ids = array_values(array_unique(array_map(fn($i)=> (int)($i['id'] ?? 0), $items)));
$in  = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("SELECT id, title, price, available FROM products WHERE id IN ($in)");
$stmt->execute($ids);
$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $rows[(int)$r['id']] = $r; }

$result = [];
$changedCount = 0;
$subtotal = 0;

foreach ($items as $it) {
  $pid = (int)$it['id']; $qty = max(1, (int)($it['qty'] ?? 1));
  $row = $rows[$pid] ?? null;
  if (!$row || !(int)$row['available']) {
    $result[] = ['id'=>$pid, 'qty'=>$qty, 'available'=>false];
    continue;
  }
  $serverPrice = (int)$row['price']; // в копейках/центах
  $clientPrice = isset($it['price']) ? (int)$it['price'] : null;
  $changed = ($clientPrice !== null && $clientPrice !== $serverPrice);
  if ($changed) $changedCount++;

  $line = $serverPrice * $qty;
  $subtotal += $line;

  $result[] = [
    'id' => $pid,
    'qty'=> $qty,
    'available'=> true,
    'title' => $row['title'],
    'unit_price' => $serverPrice,
    'line_total' => $line,
    'changed' => $changed
  ];
}

echo json_encode([
  'ok'=>true,
  'items'=>$result,
  'changedCount'=>$changedCount,
  'subtotal'=>$subtotal
], JSON_UNESCAPED_UNICODE);
