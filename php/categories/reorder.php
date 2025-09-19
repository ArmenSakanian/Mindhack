<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается']); exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

/* -------- мини-лог -------- */
function rlog(string $tag, $data): void {
  $dir = __DIR__ . '/../logs';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $path = $dir . '/categories-reorder-'.date('Y-m-d').'.log';
  $line = '['.date('H:i:s')."] $tag: ".(is_string($data)?$data:json_encode($data, JSON_UNESCAPED_UNICODE)).PHP_EOL;
  @file_put_contents($path, $line, FILE_APPEND);
}

/* -------- вход -------- */
$raw = file_get_contents('php://input') ?: '';
$ct  = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
rlog('RAW', ['ct'=>$ct, 'body'=>$raw]);

$data = json_decode($raw, true);
$order = $data['order'] ?? null;
if (!is_array($order) || !count($order)) { echo json_encode(['ok'=>false,'message'=>'Пустой порядок']); exit; }

/* нормализуем ID: только уникальные целые >0 */
$ids = [];
foreach ($order as $v) {
  $n = (int)$v;
  if ($n > 0) $ids[$n] = true;
}
$ids = array_keys($ids);
if (!count($ids)) { echo json_encode(['ok'=>false,'message'=>'Нет валидных ID']); exit; }

/* проверка существования ID */
$idList = implode(',', array_map('intval', $ids));
try {
  $cnt = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE id IN ($idList)")->fetchColumn();
  if ($cnt === 0) { echo json_encode(['ok'=>false,'message'=>'ID не найдены в БД']); exit; }
} catch (Throwable $e) {
  rlog('ERROR_CHECK', $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить порядок']); exit;
}

/* -------- двухфазное обновление без prepared -------- */
try {
  $pdo->exec("SET SQL_SAFE_UPDATES=0");
  $pdo->beginTransaction();

  // ФАЗА 1: уводим затронутые строки на большой оффсет (чтобы не ловить UNIQUE коллизии)
  $tmpOffset = 1000000;
  $sql1 = "UPDATE categories SET sort_order = sort_order + $tmpOffset WHERE id IN ($idList)";
  $pdo->exec($sql1);

  // ФАЗА 2: ставим целевые позиции через CASE
  // CASE WHEN id=5 THEN 1 WHEN id=4 THEN 2 ...
  $caseParts = [];
  foreach (array_values($ids) as $i => $id) {
    $pos = $i + 1;
    $caseParts[] = "WHEN id = ".(int)$id." THEN ".$pos;
  }
  $caseSql = implode(' ', $caseParts);
  $sql2 = "UPDATE categories
           SET sort_order = CASE $caseSql ELSE sort_order END
           WHERE id IN ($idList)";
  $pdo->exec($sql2);

  $pdo->commit();
  echo json_encode(['ok'=>true, 'updated'=>count($ids)]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  rlog('ERROR', ['message'=>$e->getMessage(), 'code'=>$e->getCode()]);
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить порядок']);
}
