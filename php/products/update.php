<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// CORS (по желанию)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($method, ['POST','PUT','PATCH'], true)) {
  http_response_code(405);
  echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

// ===== DEBUG =====
$DEBUG = false; // ← поставь true, чтобы временно видеть текст ошибки

/** ========= Параметры изображений ========= */
const IMAGE_MAX_BYTES   = 5 * 1024 * 1024; // 5MB
const IMAGES_MAX_COUNT  = 10;              // разумный лимит на продукт
$ACCEPT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];

/** ========= ФС пути ========= */
$projectRoot = dirname(__DIR__, 2);
$uploadsDir  = $projectRoot . '/uploads/product';
if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }

/** ========= Базовый URL для абсолютных ссылок ========= */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? ($scheme . '://' . $host) : '';

/** ========= Чтение входящих данных ========= */
$payload = []; $files = [];

if ($method === 'POST') {
  $payload = $_POST;
  $files   = $_FILES;
} else { // PUT/PATCH — предполагаем JSON
  $raw = file_get_contents('php://input');
  $j = $raw ? json_decode($raw, true) : null;
  if (is_array($j)) $payload = $j;
}

/** ========= Поля продукта ========= */
$id          = isset($payload['id']) ? (int)$payload['id'] : 0;
$category_id = isset($payload['category_id']) ? (int)$payload['category_id'] : 0;
$eyebrow     = trim((string)($payload['eyebrow'] ?? ''));
$title       = trim((string)($payload['title'] ?? ''));
$tagline     = trim((string)($payload['tagline'] ?? ''));
$link_url    = trim((string)($payload['link_url'] ?? ''));

// Цены
$priceRaw     = $payload['price'] ?? null;       // со скидкой (обяз)
$priceOldRaw  = $payload['price_old'] ?? null;   // без скидки (опц., можно очистить)

/** features: JSON или массив */
$features = [];
if (array_key_exists('features', $payload)) {
  $f = $payload['features'];
  if (is_string($f)) {
    $d = json_decode($f, true);
    if (is_array($d)) $features = $d;
  } elseif (is_array($f)) {
    $features = $f;
  }
}
$features = array_values(array_filter(array_map(function($v){
  $v = trim((string)$v);
  $v = preg_replace('/\s{2,}/u', ' ', $v);
  return mb_substr($v, 0, 300);
}, $features), fn($v)=>$v!==''));
$tmp=[]; $dedup=[];
foreach ($features as $f) {
  $k = mb_strtolower($f, 'UTF-8');
  if (isset($tmp[$k])) continue;
  $tmp[$k]=1; $dedup[]=$f;
}
$features = $dedup;

/** ========= Операции по галерее =========
 * image_ids_to_delete: JSON-массив id картинок для удаления
 * images_order: JSON-массив id в итоговом порядке (первый = sort=1, и т.д.)
 * primary_id: id картинки, ставим is_primary=1
 */
$deleteIds = [];
if (isset($payload['image_ids_to_delete'])) {
  $v = $payload['image_ids_to_delete'];
  if (is_string($v)) { $v = json_decode($v, true); }
  if (is_array($v)) { $deleteIds = array_values(array_filter(array_map('intval',$v))); }
}

$orderIds = [];
if (isset($payload['images_order'])) {
  $v = $payload['images_order'];
  if (is_string($v)) { $v = json_decode($v, true); }
  if (is_array($v)) { $orderIds = array_values(array_filter(array_map('intval',$v))); }
}

$primaryId = null;
if (isset($payload['primary_id'])) {
  $primaryId = (int)$payload['primary_id'];
}

/** ========= Валидация текста/поля ========= */
$errors=[];
if ($id < 1)          $errors[]='Некорректный id.';
if ($category_id < 1) $errors[]='Выберите категорию.';
if ($eyebrow === '')  $errors[]='Заполните верхнюю подпись.';
if ($title === '')    $errors[]='Заполните заголовок.';
if ($tagline === '')  $errors[]='Заполните описание.';
if (count($features) < 1) $errors[]='Нужно минимум 1 пункт.';

/** link_url обязателен и http/https */
if ($link_url === '') {
  $errors[] = 'Поле ссылки не может быть пустым.';
} else {
  if (!preg_match('~^https?://~i', $link_url)) {
    $errors[] = 'Ссылка должна начинаться с http:// или https://';
  }
}

/** price (со скидкой) — обязательна */
$price=null;
if ($priceRaw===null || $priceRaw==='' || !is_numeric($priceRaw)) {
  $errors[]='Введите корректную цену со скидкой.';
} else {
  $price=(float)$priceRaw; if ($price<=0) $errors[]='Цена со скидкой должна быть больше 0.';
}

/** price_old (без скидки) — опциональна, но если указана, то >0 и >= price */
$price_old = null;
if ($priceOldRaw !== null) {
  // Пришло поле: либо пустая строка (очистить), либо число
  if ($priceOldRaw === '') {
    $price_old = null; // очистка
  } else {
    if (!is_numeric($priceOldRaw)) {
      $errors[] = 'Старая цена указана некорректно.';
    } else {
      $price_old = (float)$priceOldRaw;
      if ($price_old <= 0) {
        $errors[] = 'Старая цена должна быть больше 0, либо пустой.';
      } elseif ($price !== null && $price > 0 && $price_old < $price) {
        $errors[] = 'Старая цена не может быть меньше цены со скидкой.';
      }
    }
  }
}

/** ========= Новые изображения из формы ========= */
$newFiles = []; // нормализованный массив файлов (images[] или legacy image)
if (isset($files['images'])) {
  $f = $files['images'];
  if (is_array($f['name'])) {
    $count = count($f['name']);
    for ($i=0; $i<$count; $i++) {
      $newFiles[] = [
        'name' => $f['name'][$i],
        'type' => $f['type'][$i],
        'tmp_name' => $f['tmp_name'][$i],
        'error' => $f['error'][$i],
        'size' => $f['size'][$i],
      ];
    }
  }
} elseif (isset($files['image'])) {
  $f = $files['image'];
  if (is_array($f['name'])) {
    $count = count($f['name']);
    for ($i=0; $i<$count; $i++) {
      $newFiles[] = [
        'name' => $f['name'][$i],
        'type' => $f['type'][$i],
        'tmp_name' => $f['tmp_name'][$i],
        'error' => $f['error'][$i],
        'size' => $f['size'][$i],
      ];
    }
  } else {
    if (!empty($f['tmp_name'])) $newFiles[] = $f; // одиночный legacy
  }
}

/** ========= Предвалидация новых файлов ========= */
if (!empty($newFiles) && count($newFiles) > IMAGES_MAX_COUNT) {
  $errors[] = 'Слишком много изображений. Максимум: ' . IMAGES_MAX_COUNT . '.';
}
$suspects = [];
if (empty($errors) && !empty($newFiles)) {
  foreach ($newFiles as $idx => $file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      $errors[] = 'Файл #' . ($idx+1) . ' не загружен.'; break;
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Ошибка загрузки файла #' . ($idx+1) . '.'; break;
    }
    $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
    if (!isset($ACCEPT[$mime])) {
      $errors[] = 'Недопустимый формат файла #' . ($idx+1) . '. Разрешены PNG/JPG/WEBP.'; break;
    }
    if ((int)$file['size'] > IMAGE_MAX_BYTES) {
      $errors[] = 'Файл #' . ($idx+1) . ' слишком большой (до 5 МБ).'; break;
    }
    $suspects[] = $mime;
  }
}

if (!empty($errors)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
  exit;
}

/** ========= Текущий продукт и категория ========= */
try {
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id=:id LIMIT 1");
  $stmt->execute([':id'=>$id]);
  $curr = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$curr) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Продукт не найден.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Ошибка БД при чтении продукта.'], JSON_UNESCAPED_UNICODE);
  exit;
}

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

/** ========= Проверка лимита на общее количество фото ========= */
try {
  $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id=:pid");
  $cntStmt->execute([':pid'=>$id]);
  $currentCount = (int)$cntStmt->fetchColumn();
  $toDeleteCount = count($deleteIds);
  $incomingCount = count($newFiles);
  $futureTotal = $currentCount - $toDeleteCount + $incomingCount;
  if ($futureTotal > IMAGES_MAX_COUNT) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Превышен лимит изображений: максимум ' . IMAGES_MAX_COUNT . '.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
} catch (Throwable $e) {
  // не критично, пропустим
}

/** ========= Подготовка файлов: перенос на диск ========= */
$savedFilesAbs = [];
$savedUrls     = [];
if (!empty($newFiles)) {
  foreach ($newFiles as $i => $file) {
    $mime = $suspects[$i] ?? (@mime_content_type($file['tmp_name']) ?: $file['type']);
    $ext  = $ACCEPT[$mime] ?? 'bin';
    $name = bin2hex(random_bytes(6)) . '-' . time() . '-' . $i . '.' . $ext;
    $destAbs = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $name;

    if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
      foreach ($savedFilesAbs as $p) { @unlink($p); }
      http_response_code(500);
      echo json_encode(['ok'=>false,'message'=>'Не удалось сохранить файл изображения #' . ($i+1) . '.'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $savedFilesAbs[] = $destAbs;
    $savedUrls[]     = '/uploads/product/' . $name;
  }
}

/** ========= Транзакция: обновляем продукт и галерею ========= */
try {
  $pdo->beginTransaction();

  // 1) Обновляем поля продукта (image синхронизируем после галереи)
  $sql = "UPDATE products SET
            category_id = :cid,
            category_title = :ctitle,
            eyebrow = :eyebrow,
            title = :title,
            tagline = :tagline,
            link_url = :link_url,
            features = :features,
            price_old = :price_old,
            price = :price
          WHERE id = :id LIMIT 1";
  $stmt = $pdo->prepare($sql);

  // NB: чтобы корректно писать NULL, используем bindValue с PDO::PARAM_NULL при необходимости
  if ($price_old === null) {
    $stmt->bindValue(':price_old', null, PDO::PARAM_NULL);
  } else {
    $stmt->bindValue(':price_old', $price_old);
  }

  $stmt->bindValue(':cid',      $category_id, PDO::PARAM_INT);
  $stmt->bindValue(':ctitle',   $catTitle);
  $stmt->bindValue(':eyebrow',  $eyebrow);
  $stmt->bindValue(':title',    $title);
  $stmt->bindValue(':tagline',  $tagline);
  $stmt->bindValue(':link_url', $link_url);
  $stmt->bindValue(':features', json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  $stmt->bindValue(':price',    $price);
  $stmt->bindValue(':id',       $id, PDO::PARAM_INT);
  $stmt->execute();

  // 2) Удаляем отмеченные изображения
  $deletedFilesAbs = [];
  if (!empty($deleteIds)) {
    $in = implode(',', array_fill(0, count($deleteIds), '?'));
    $sel = $pdo->prepare("SELECT id, url FROM product_images WHERE product_id=? AND id IN ($in)");
    $sel->execute(array_merge([$id], $deleteIds));
    $rowsToDelete = $sel->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rowsToDelete)) {
      $del = $pdo->prepare("DELETE FROM product_images WHERE product_id=? AND id IN ($in)");
      $del->execute(array_merge([$id], $deleteIds));

      // подготовим абсолютные пути для физического удаления после commit
      $uploadsReal = realpath($uploadsDir);
      foreach ($rowsToDelete as $r) {
        $rel = (string)$r['url'];
        $rel = ltrim($rel, '/\\');
        $abs = $projectRoot . DIRECTORY_SEPARATOR . $rel;
        $absReal = (file_exists($abs) ? realpath($abs) : null);
        if ($uploadsReal && $absReal && str_starts_with($absReal, $uploadsReal)) {
          $deletedFilesAbs[] = $absReal;
        }
      }
    }
  }

  // 3) Вставляем новые изображения (в конец)
  if (!empty($savedUrls)) {
    $q = $pdo->prepare("SELECT COALESCE(MAX(sort),0) FROM product_images WHERE product_id=:pid");
    $q->execute([':pid'=>$id]);
    $maxSort = (int)$q->fetchColumn();

    $ins = $pdo->prepare("INSERT INTO product_images (product_id, url, alt, sort, is_primary)
                          VALUES (:pid, :url, NULL, :sort, 0)");
    foreach ($savedUrls as $idx => $url) {
      $ins->execute([
        ':pid'  => $id,
        ':url'  => $url,
        ':sort' => $maxSort + $idx + 1
      ]);
    }
  }

  // 4) Если задан порядок — проставляем sort по массиву id
  if (!empty($orderIds)) {
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $sel = $pdo->prepare("SELECT id FROM product_images WHERE product_id=? AND id IN ($in)");
    $sel->execute(array_merge([$id], $orderIds));
    $existing = array_map('intval', $sel->fetchAll(PDO::FETCH_COLUMN, 0));

    $sort = 1;
    $upd = $pdo->prepare("UPDATE product_images SET sort=:s WHERE product_id=:pid AND id=:iid");
    foreach ($orderIds as $iid) {
      $iid = (int)$iid;
      if (in_array($iid, $existing, true)) {
        $upd->execute([':s'=>$sort, ':pid'=>$id, ':iid'=>$iid]);
        $sort++;
      }
    }
  }

  // 5) Если задан primary_id — переключаем обложку
  if (!is_null($primaryId) && $primaryId > 0) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id=:pid AND id=:iid");
    $chk->execute([':pid'=>$id, ':iid'=>$primaryId]);
    if ((int)$chk->fetchColumn() === 1) {
      $pdo->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=:pid")->execute([':pid'=>$id]);
      $pdo->prepare("UPDATE product_images SET is_primary=1 WHERE product_id=:pid AND id=:iid")
          ->execute([':pid'=>$id, ':iid'=>$primaryId]);
    }
  }

  // 6) Синхро products.image = текущая обложка (или пусто)
  $cover = $pdo->prepare("SELECT url FROM product_images WHERE product_id=:pid ORDER BY is_primary DESC, sort ASC, id ASC LIMIT 1");
  $cover->execute([':pid'=>$id]);
  $coverUrl = (string)$cover->fetchColumn();

  $pdo->prepare("UPDATE products SET image=:img WHERE id=:id LIMIT 1")
      ->execute([':img'=>$coverUrl, ':id'=>$id]);

  $pdo->commit();

  // Физически удалим файлы, помеченные к удалению
  foreach ($deletedFilesAbs as $abs) { @unlink($abs); }

  // 7) Отдаём галерею (+ url_full)
  $imgsStmt = $pdo->prepare("SELECT id, url, alt, sort, is_primary FROM product_images WHERE product_id=:pid ORDER BY is_primary DESC, sort ASC, id ASC");
  $imgsStmt->execute([':pid'=>$id]);
  $images = array_map(function($r) use ($baseUrl){
    $url = (string)$r['url'];
    $url_full = $url;
    if ($url && $baseUrl && strpos($url, 'http') !== 0) {
      $rel = $url[0] === '/' ? $url : ('/' . $url);
      $url_full = $baseUrl . $rel; // <-- ВАЖНО: точка, не плюс
    }
    return [
      'id'         => (int)$r['id'],
      'url'        => $url,
      'url_full'   => $url_full,
      'alt'        => $r['alt'] ?? null,
      'sort'       => (int)$r['sort'],
      'is_primary' => (int)$r['is_primary'],
    ];
  }, $imgsStmt->fetchAll(PDO::FETCH_ASSOC));

  echo json_encode([
    'ok'=>true,
    'updated'=>1,
    'id'=>$id,
    'product'=>[
      'id'             => $id,
      'category_id'    => $category_id,
      'category_title' => $catTitle,
      'eyebrow'        => $eyebrow,
      'title'          => $title,
      'tagline'        => $tagline,
      'link_url'       => $link_url,
      'features'       => $features,
      'price_old'      => $price_old,
      'price'          => $price,
      'image'          => $coverUrl,
      'images'         => $images
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // Откат новых сохранённых файлов
  foreach ($savedFilesAbs as $p) { @unlink($p); }
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'message'=>$DEBUG ? ('Ошибка при обновлении продукта: ' . $e->getMessage()) : 'Ошибка при обновлении продукта.'
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
