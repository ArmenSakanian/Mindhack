<?php
/**
 * Обновление продукта.
 * Методы:
 *  - POST (multipart/form-data) — с возможной заменой изображения
 *  - PUT/PATCH (application/json) — без изображения
 * Поля:
 *  - id (int, required)
 *  - category_id (int, required)
 *  - eyebrow, title, tagline (string, required)
 *  - features (JSON / array), 1..6
 *  - price (number > 0, required)
 *  - image (file, optional) PNG/JPG/WEBP <= 5MB
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$pdo = db();

const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
$ACCEPT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];

$projectRoot = dirname(__DIR__, 2);
$uploadsDir  = $projectRoot . '/uploads/product';
if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = []; $files=[];

if ($method === 'POST') { $payload = $_POST; $files = $_FILES; }
elseif ($method==='PUT' || $method==='PATCH') {
  $raw = file_get_contents('php://input'); $j = $raw ? json_decode($raw, true) : null;
  if (is_array($j)) $payload = $j;
} else { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE); exit; }

$id          = isset($payload['id']) ? (int)$payload['id'] : 0;
$category_id = isset($payload['category_id']) ? (int)$payload['category_id'] : 0;
$eyebrow     = trim((string)($payload['eyebrow'] ?? ''));
$title       = trim((string)($payload['title'] ?? ''));
$tagline     = trim((string)($payload['tagline'] ?? ''));
$priceRaw    = $payload['price'] ?? null;

// features
$features = [];
if (array_key_exists('features', $payload)) {
  $f = $payload['features'];
  if (is_string($f)) { $d=json_decode($f, true); if (is_array($d)) $features=$d; }
  elseif (is_array($f)) { $features = $f; }
}
$features = array_values(array_filter(array_map(function($v){
  $v = trim((string)$v); return preg_replace('/\s{2,}/u',' ',$v);
}, $features), fn($v)=>$v!==''));
$tmp=[]; $dedup=[]; foreach($features as $f){ $k=mb_strtolower($f,'UTF-8'); if(isset($tmp[$k])) continue; $tmp[$k]=1; $dedup[]=$f; } $features=$dedup;

$errors=[];
if ($id<1)               $errors[]='Некорректный id.';
if ($category_id<1)      $errors[]='Выберите категорию.';
if ($eyebrow==='')       $errors[]='Заполните верхнюю подпись.';
if ($title==='')         $errors[]='Заполните заголовок.';
if ($tagline==='')       $errors[]='Заполните описание.';
$price=null;
if ($priceRaw===null || $priceRaw==='' || !is_numeric($priceRaw)) $errors[]='Введите корректную цену.';
else { $price=(float)$priceRaw; if ($price<=0) $errors[]='Цена должна быть больше 0.'; }
if (count($features)<1 || count($features)>6) $errors[]='Нужно от 1 до 6 пунктов.';

$hasNewImage=false;
if ($method==='POST' && isset($files['image']) && is_uploaded_file($files['image']['tmp_name'])) {
  $file=$files['image'];
  if ($file['error']!==UPLOAD_ERR_OK) $errors[]='Ошибка загрузки изображения.';
  else {
    $mime=mime_content_type($file['tmp_name']);
    if (!isset($ACCEPT[$mime])) $errors[]='Недопустимый формат изображения. Разрешены PNG/JPG/WEBP.';
    if ($file['size']>IMAGE_MAX_BYTES) $errors[]='Файл слишком большой (до 5 МБ).';
    $hasNewImage = empty($errors);
  }
}
if ($errors) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>implode(' ',$errors)], JSON_UNESCAPED_UNICODE); exit; }

// текущая запись
try {
  $stmt=$pdo->prepare("SELECT * FROM products WHERE id=:id LIMIT 1");
  $stmt->execute([':id'=>$id]); $curr=$stmt->fetch(PDO::FETCH_ASSOC);
  if (!$curr) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Продукт не найден.'], JSON_UNESCAPED_UNICODE); exit; }
} catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при чтении продукта.'], JSON_UNESCAPED_UNICODE); exit; }

// заголовок категории по id (на всякий случай актуализируем)
try {
  $stmt=$pdo->prepare("SELECT title FROM categories WHERE id=:id");
  $stmt->execute([':id'=>$category_id]); $catTitle = $stmt->fetchColumn();
  if (!$catTitle) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Категория не найдена.'], JSON_UNESCAPED_UNICODE); exit; }
} catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при чтении категории.'], JSON_UNESCAPED_UNICODE); exit; }

// сравнение (без картинки)
$currFeatures = [];
if (!empty($curr['features'])) { $d=json_decode($curr['features'], true); if(is_array($d)) $currFeatures=$d; }
$noDataChange =
  (int)$category_id === (int)$curr['category_id'] &&
  $catTitle === (string)$curr['category_title'] &&
  $eyebrow === (string)$curr['eyebrow'] &&
  $title === (string)$curr['title'] &&
  $tagline === (string)$curr['tagline'] &&
  (float)$price === (float)$curr['price'] &&
  json_encode(array_values($features), JSON_UNESCAPED_UNICODE) === json_encode(array_values($currFeatures), JSON_UNESCAPED_UNICODE);

if ($noDataChange && !$hasNewImage) {
  echo json_encode(['ok'=>false,'message'=>'Вы ничего не меняли. Сохранять нечего.'], JSON_UNESCAPED_UNICODE); exit;
}

// сохранить новое изображение (если есть)
$newImageRel = null; $oldImageRel = (string)($curr['image'] ?? '');
if ($hasNewImage) {
  $mime = mime_content_type($files['image']['tmp_name']);
  $ext  = $ACCEPT[$mime] ?? 'bin';
  $name = bin2hex(random_bytes(6)).'-'.time().'.'.$ext;
  $dest = rtrim($uploadsDir,'/\\').DIRECTORY_SEPARATOR.$name;
  if (!move_uploaded_file($files['image']['tmp_name'], $dest)) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить новое изображение.'], JSON_UNESCAPED_UNICODE); exit;
  }
  $newImageRel = '/uploads/product/'.$name;
}

// обновление
try {
  $sql = "UPDATE products SET
            category_id = :cid,
            category_title = :ctitle,
            eyebrow = :eyebrow,
            title = :title,
            tagline = :tagline,
            features = :features,
            price = :price".
          ($hasNewImage ? ", image = :image" : "") .
          " WHERE id = :id LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $params = [
    ':cid'=>$category_id, ':ctitle'=>$catTitle, ':eyebrow'=>$eyebrow, ':title'=>$title,
    ':tagline'=>$tagline, ':features'=>json_encode($features, JSON_UNESCAPED_UNICODE),
    ':price'=>$price, ':id'=>$id
  ];
  if ($hasNewImage) $params[':image'] = $newImageRel;

  $stmt->execute($params);

  // удалить старый файл, если был заменён
  $imageReplaced = false;
  if ($hasNewImage && $oldImageRel) {
    $rel = $oldImageRel[0]==='/' ? substr($oldImageRel,1) : $oldImageRel;
    $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;
    $uploadsReal = realpath($uploadsDir);
    $absReal = file_exists($abs) ? realpath($abs) : null;
    if ($uploadsReal && $absReal && str_starts_with($absReal, $uploadsReal)) { @unlink($absReal); }
    $imageReplaced = true;
  }

  echo json_encode([
    'ok'=>true,
    'updated'=>1,
    'id'=>$id,
    'image_replaced'=>$imageReplaced,
    'product'=>[
      'id'=>$id,'category_id'=>$category_id,'category_title'=>$catTitle,'eyebrow'=>$eyebrow,
      'title'=>$title,'tagline'=>$tagline,'features'=>$features,'price'=>$price,
      'image'=>$hasNewImage ? $newImageRel : $oldImageRel
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e){
  if (isset($dest) && file_exists($dest)) @unlink($dest);
  http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при обновлении продукта.'], JSON_UNESCAPED_UNICODE); exit;
}
