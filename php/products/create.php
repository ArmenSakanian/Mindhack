<?php
/**
 * Создание продукта.
 * Метод: POST (multipart/form-data)
 * Поля:
 *  - category_id (int, required)
 *  - eyebrow (string, required)
 *  - title (string, required)
 *  - tagline (string, required)
 *  - features (JSON string) ИЛИ features[] (array строк), 1..6
 *  - price (number > 0, required)
 *  - image (file, required) PNG/JPG/WEBP <= 5MB
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE); exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
$ACCEPT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];

$projectRoot = dirname(__DIR__, 2);               // корень сайта
$uploadDir   = $projectRoot . '/uploads/product'; // /uploads/product
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$eyebrow     = trim((string)($_POST['eyebrow'] ?? ''));
$title       = trim((string)($_POST['title'] ?? ''));
$tagline     = trim((string)($_POST['tagline'] ?? ''));
$priceRaw    = $_POST['price'] ?? null;

// features: JSON или массив
$features = [];
if (isset($_POST['features'])) {
  $f = $_POST['features'];
  if (is_string($f)) {
    $d = json_decode($f, true);
    if (is_array($d)) $features = $d;
  } elseif (is_array($f)) {
    $features = $f;
  }
}

$features = array_values(array_filter(array_map(function($v){
  $v = trim((string)$v);
  return preg_replace('/\s{2,}/u', ' ', $v);
}, $features), fn($v)=>$v!==''));

// dedup (case-insensitive)
$tmp = []; $dedup = [];
foreach ($features as $f) { $k = mb_strtolower($f,'UTF-8'); if(isset($tmp[$k])) continue; $tmp[$k]=1; $dedup[]=$f; }
$features = $dedup;

// валидация
$errors = [];
if ($category_id < 1)        $errors[] = 'Выберите категорию.';
if ($eyebrow === '')         $errors[] = 'Заполните верхнюю подпись.';
if ($title === '')           $errors[] = 'Заполните заголовок.';
if ($tagline === '')         $errors[] = 'Заполните описание.';
$price = null;
if ($priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw)) $errors[] = 'Введите корректную цену.';
else { $price = (float)$priceRaw; if ($price <= 0) $errors[] = 'Цена должна быть больше 0.'; }
if (count($features) < 1 || count($features) > 6) $errors[] = 'Нужно от 1 до 6 пунктов преимуществ.';

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
  $errors[] = 'Загрузите изображение.';
} else {
  $file = $_FILES['image'];
  if ($file['error'] !== UPLOAD_ERR_OK) $errors[]='Ошибка загрузки изображения.';
  else {
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($ACCEPT[$mime])) $errors[]='Недопустимый формат изображения. Разрешены PNG/JPG/WEBP.';
    if ($file['size'] > IMAGE_MAX_BYTES) $errors[]='Файл слишком большой (до 5 МБ).';
  }
}

if ($errors) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>implode(' ',$errors)], JSON_UNESCAPED_UNICODE); exit; }

// подтянем название категории (category_title)
try {
  $stmt = $pdo->prepare("SELECT title FROM categories WHERE id=:id");
  $stmt->execute([':id'=>$category_id]);
  $catTitle = $stmt->fetchColumn();
  if (!$catTitle) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Категория не найдена.'], JSON_UNESCAPED_UNICODE); exit; }
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при чтении категории.'], JSON_UNESCAPED_UNICODE); exit;
}

// сохранить картинку
$mime = mime_content_type($_FILES['image']['tmp_name']);
$ext  = $ACCEPT[$mime] ?? 'bin';
$name = bin2hex(random_bytes(6)).'-'.time().'.'.$ext;
$dest = $uploadDir . '/' . $name;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
  http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить файл изображения.'], JSON_UNESCAPED_UNICODE); exit;
}
$imageRel = '/uploads/product/' . $name;

try {
  $sql = "INSERT INTO products
          (category_id, category_title, eyebrow, title, tagline, features, price, image)
          VALUES (:category_id, :category_title, :eyebrow, :title, :tagline, :features, :price, :image)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':category_id'   => $category_id,
    ':category_title'=> $catTitle,
    ':eyebrow'       => $eyebrow,
    ':title'         => $title,
    ':tagline'       => $tagline,
    ':features'      => json_encode($features, JSON_UNESCAPED_UNICODE),
    ':price'         => $price,
    ':image'         => $imageRel
  ]);

  $id = (int)$pdo->lastInsertId();
  echo json_encode(['ok'=>true,'id'=>$id,'product'=>[
    'id'=>$id,'category_id'=>$category_id,'category_title'=>$catTitle,'eyebrow'=>$eyebrow,'title'=>$title,
    'tagline'=>$tagline,'features'=>$features,'price'=>$price,'image'=>$imageRel
  ]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  @unlink($dest);
  http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Ошибка БД при создании продукта.'], JSON_UNESCAPED_UNICODE); exit;
}
