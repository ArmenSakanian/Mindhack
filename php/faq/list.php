<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

$page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])  : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
$offset = ($page - 1) * $limit;

$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

try {
  if ($search !== '') {
    $like = '%' . $search . '%';
   $stmt = $pdo->prepare('SELECT SQL_CALC_FOUND_ROWS id, question, answer, created_at, updated_at, sort_order
                       FROM faqs
                       WHERE question LIKE :q OR answer LIKE :q
                       ORDER BY sort_order ASC, id ASC
                       LIMIT :lim OFFSET :off');
    $stmt->bindParam(':q', $like, PDO::PARAM_STR);
  } else {
    $stmt = $pdo->prepare('SELECT SQL_CALC_FOUND_ROWS id, question, answer, created_at, updated_at, sort_order
                       FROM faqs
                       ORDER BY sort_order ASC, id ASC
                       LIMIT :lim OFFSET :off');
  }

  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $total = (int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

  echo json_encode([
    'ok' => true,
    'items' => $items,
    'page' => $page,
    'limit' => $limit,
    'total' => $total
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка загрузки: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
