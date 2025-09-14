<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

/** ====== Настройки загрузки изображений ====== */
const IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5MB
const IMAGES_MAX_COUNT = 10;             // максимум картинок на продукт
$ACCEPT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];

/** Папка загрузки (оставляем как у тебя было, без подпапок, чтобы не ломать пути) */
$projectRoot = dirname(__DIR__, 2);
$uploadDir   = $projectRoot . '/uploads/product';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

/** ====== Чтение и валидация полей формы ====== */
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$eyebrow     = trim((string)($_POST['eyebrow'] ?? ''));
$title       = trim((string)($_POST['title'] ?? ''));
$tagline     = trim((string)($_POST['tagline'] ?? ''));
$priceRaw    = $_POST['price'] ?? null;

/** features: JSON или массив */
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
/** нормализация и dedup */
$features = array_values(array_filter(array_map(function($v){
  $v = trim((string)$v);
  $v = preg_replace('/\s{2,}/u', ' ', $v);
  return mb_substr($v, 0, 300);
}, $features), fn($v)=>$v!==''));
$tmp = []; $dedup = [];
foreach ($features as $f) {
  $k = mb_strtolower($f,'UTF-8');
  if (isset($tmp[$k])) continue;
  $tmp[$k] = 1;
  $dedup[] = $f;
}
$features = $dedup;

/** Валидация текстовых полей */
$errors = [];
if ($category_id < 1)        $errors[] = 'Выберите категорию.';
if ($eyebrow === '')         $errors[] = 'Заполните верхнюю подпись.';
if ($title === '')           $errors[] = 'Заполните заголовок.';
if ($tagline === '')         $errors[] = 'Заполните описание.';
$price = null;
if ($priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw)) {
  $errors[] = 'Введите корректную цену.';
} else {
  $price = (float)$priceRaw;
  if ($price <= 0) $errors[] = 'Цена должна быть больше 0.';
}
if (count($features) < 1) {
  $errors[] = 'Нужно минимум 1 пункт преимущества.';
}

/** ====== Сбор изображений из формы (images[] или legacy image) ====== */
$filesPayload = [];
if (isset($_FILES['images'])) {
  // Ожидаем множественный input name="images[]"
  $f = $_FILES['images'];
  // Нормализуем структуру в список
  if (is_array($f['name'])) {
    $count = count($f['name']);
    for ($i=0; $i<$count; $i++) {
      $filesPayload[] = [
        'name' => $f['name'][$i],
        'type' => $f['type'][$i],
        'tmp_name' => $f['tmp_name'][$i],
        'error' => $f['error'][$i],
        'size' => $f['size'][$i],
      ];
    }
  }
} elseif (isset($_FILES['image'])) {
  // Совместимость со старым одиночным полем
  $f = $_FILES['image'];
  if (is_array($f['name'])) {
    $count = count($f['name']);
    for ($i=0; $i<$count; $i++) {
      $filesPayload[] = [
        'name' => $f['name'][$i],
        'type' => $f['type'][$i],
        'tmp_name' => $f['tmp_name'][$i],
        'error' => $f['error'][$i],
        'size' => $f['size'][$i],
      ];
    }
  } else {
    $filesPayload[] = $f;
  }
}

/** Проверка наличия файлов */
if (empty($filesPayload)) {
  $errors[] = 'Загрузите хотя бы одно изображение.';
}

/** Ограничение по количеству */
if (!empty($filesPayload) && count($filesPayload) > IMAGES_MAX_COUNT) {
  $errors[] = 'Слишком много изображений. Максимум: ' . IMAGES_MAX_COUNT . '.';
}

/** Предвалидация каждого файла */
$suspects = [];
if (empty($errors)) {
  foreach ($filesPayload as $idx => $file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      $errors[] = 'Файл #' . ($idx+1) . ' не загружен.';
      break;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Ошибка загрузки файла #' . ($idx+1) . '.';
      break;
    }
    $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
    if (!isset($ACCEPT[$mime])) {
      $errors[] = 'Недопустимый формат файла #' . ($idx+1) . '. Разрешены PNG/JPG/WEBP.';
      break;
    }
    if ($file['size'] > IMAGE_MAX_BYTES) {
      $errors[] = 'Файл #' . ($idx+1) . ' слишком большой (до 5 МБ).';
      break;
    }
    $suspects[] = $mime; // пригодится для расширений
  }
}

if ($errors) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ====== Проверяем категорию ====== */
try {
  $stmt = $pdo->prepare("SELECT title FROM categories WHERE id=:id");
  $stmt->execute([':id'=>$category_id]);
  $catTitle = $stmt->fetchColumn();
  if (!$catTitle) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Категория не найдена.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при чтении категории.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ====== Готовим сохранение ====== */
$savedFiles = [];  // фактически перемещённые файлы (полные пути)
$savedUrls  = [];  // относительные URL, соответствующие product_images.url

/** Сначала физически переносим все картинки (если будет ошибка далее — удалим) */
foreach ($filesPayload as $i => $file) {
  $mime = $suspects[$i] ?? (@mime_content_type($file['tmp_name']) ?: $file['type']);
  $ext  = $ACCEPT[$mime] ?? 'bin';
  $name = bin2hex(random_bytes(6)) . '-' . time() . '-' . $i . '.' . $ext;

  $destAbs = $uploadDir . '/' . $name;
  if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
    // Откат уже перемещённых
    foreach ($savedFiles as $p) { @unlink($p); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить файл изображения #' . ($i+1) . '.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $savedFiles[] = $destAbs;
  $savedUrls[]  = '/uploads/product/' . $name;
}

/** ====== Создаём продукт + записи изображений в транзакции ====== */
try {
  $pdo->beginTransaction();

  // 1) Создаём продукт (image заполним обложкой дальше)
  $sql = "INSERT INTO products
          (category_id, category_title, eyebrow, title, tagline, features, price, image)
          VALUES (:category_id, :category_title, :eyebrow, :title, :tagline, :features, :price, :image)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':category_id'    => $category_id,
    ':category_title' => $catTitle,
    ':eyebrow'        => $eyebrow,
    ':title'          => $title,
    ':tagline'        => $tagline,
    ':features'       => json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ':price'          => $price,
    ':image'          => $savedUrls[0] ?? ''   // дублируем обложку для обратной совместимости
  ]);

  $productId = (int)$pdo->lastInsertId();

  // 2) Создаём строки галереи
  $ins = $pdo->prepare("INSERT INTO product_images (product_id, url, alt, sort, is_primary)
                        VALUES (:pid, :url, NULL, :sort, :is_primary)");
  foreach ($savedUrls as $idx => $url) {
    $ins->execute([
      ':pid' => $productId,
      ':url' => $url,
      ':sort' => $idx + 1,
      ':is_primary' => ($idx === 0 ? 1 : 0),
    ]);
  }

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'id' => $productId,
    'product' => [
      'id' => $productId,
      'category_id'    => $category_id,
      'category_title' => $catTitle,
      'eyebrow'        => $eyebrow,
      'title'          => $title,
      'tagline'        => $tagline,
      'features'       => $features,
      'price'          => $price,
      'image'          => $savedUrls[0] ?? '',
      // сразу вернём и галерею — пригодится фронту после создания
      'images' => array_map(function($url, $i){
        return [
          'url' => $url,
          'sort' => $i + 1,
          'is_primary' => $i === 0 ? 1 : 0,
        ];
      }, $savedUrls, array_keys($savedUrls)),
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  $pdo->rollBack();
  // Удалим уже записанные файлы
  foreach ($savedFiles as $p) { @unlink($p); }
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при создании продукта.'], JSON_UNESCAPED_UNICODE);
  exit;
}