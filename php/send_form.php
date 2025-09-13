<?php
// /php/send_form.php
// Требуется папка PHPMailer с файлами: PHPMailer/src/{PHPMailer.php, SMTP.php, Exception.php}
// SMTP для REG.RU: mail.hosting.reg.ru (SSL 465) или TLS 587

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=UTF-8');

function json_exit($ok, $msg, $extra = []) {
  http_response_code($ok ? 200 : 400);
  echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_exit(false, 'Метод не поддерживается. Используйте POST.');
}

/* ===================== БРЕНД / ССЫЛКИ ===================== */
$BRAND_NAME     = 'MindHack';
$SUBJECT_BRAND  = 'MindHack'; // как просили в теме писем — строчными через дефис
$LOGO_URL       = 'https://mind-hack.ru/logo.png';
$SUPPORT_LINK   = 'mailto:support@mind-hack.ru';
$SITE_BASE_URL  = ''; // например 'https://mind-hack.ru' для абсолютных ссылок на /uploads

/* ===================== НАСТРОЙКИ SMTP/ПОЧТЫ ===================== */
$SMTP_HOST   = 'mail.hosting.reg.ru';
$SMTP_PORT   = 465;        // 465 для SSL, 587 для TLS
$SMTP_SECURE = 'ssl';      // 'ssl' или 'tls'
$SMTP_USER   = 'support@mind-hack.ru';
$SMTP_PASS   = 'Armen7725/1'; // <-- подставь реальный пароль

$SITE_FROM_EMAIL = 'support@mind-hack.ru';
$SITE_FROM_NAME  = 'MindHack Site';

$TO_EMAIL        = 'support@mind-hack.ru';
$TO_NAME         = 'Site Inbox';

/* ===================== ПАПКА ДЛЯ ЗАГРУЗОК ===================== */
$uploadDir = dirname(__DIR__) . '/uploads';
$maxFileBytes = 5 * 1024 * 1024; // 5 МБ
$allowedExts  = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','txt','zip','rar','7z'];
$allowedMime  = [
  'image/jpeg','image/png','image/gif',
  'application/pdf',
  'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'text/plain',
  'application/zip','application/x-rar-compressed','application/x-7z-compressed'
];

/* ===================== ВАЛИДАЦИЯ ПОЛЕЙ ===================== */
$filters = [
  'name'    => FILTER_SANITIZE_SPECIAL_CHARS,
  'email'   => FILTER_SANITIZE_EMAIL,
  'phone'   => FILTER_SANITIZE_SPECIAL_CHARS,
  'subject' => FILTER_SANITIZE_SPECIAL_CHARS,
  'message' => FILTER_SANITIZE_SPECIAL_CHARS,
  'consent' => FILTER_DEFAULT,
];
$in = filter_input_array(INPUT_POST, $filters) ?: [];

$name    = trim($in['name'] ?? '');
$email   = trim($in['email'] ?? '');
$phone   = trim($in['phone'] ?? '');
$subject = trim($in['subject'] ?? 'Сообщение с сайта');
$message = trim($in['message'] ?? '');
$consent = isset($in['consent']) && in_array($in['consent'], ['true','on','1','yes','да','Да'], true);

if (mb_strlen($name) < 2) {
  json_exit(false, 'Укажите имя (минимум 2 символа).');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_exit(false, 'Введите корректный e-mail.');
}
if ($phone === '') {
  json_exit(false, 'Укажите телефон.');
}
if (mb_strlen($subject) < 3) {
  json_exit(false, 'Тема слишком короткая (мин. 3 символа).');
}
if (mb_strlen($message) < 10) {
  json_exit(false, 'Сообщение слишком короткое (мин. 10 символов).');
}
if (!$consent) {
  json_exit(false, 'Необходимо согласие с политикой конфиденциальности.');
}

/* ===================== ПОДКЛЮЧАЕМ PHPMailer ===================== */
$base = __DIR__ . '/PHPMailer/src/';
require_once $base . 'Exception.php';
require_once $base . 'PHPMailer.php';
require_once $base . 'SMTP.php';

/* ===================== УТИЛИТЫ РЕНДЕРА ПИСЕМ ===================== */
function esc($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function build_brand_background_svg_data_uri(): string {
  $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1000">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"   stop-color="#0f0b1a"/>
      <stop offset="45%"  stop-color="#121b30"/>
      <stop offset="70%"  stop-color="#153345"/>
      <stop offset="100%" stop-color="#183e5a"/>
    </linearGradient>
    <radialGradient id="r1" cx="0.1" cy="0" r="0.6">
      <stop offset="0%" stop-color="rgba(255,153,0,0.08)"/>
      <stop offset="60%" stop-color="rgba(255,153,0,0)"/>
    </radialGradient>
    <radialGradient id="r2" cx="0.9" cy="0.1" r="0.55">
      <stop offset="0%" stop-color="rgba(135,77,255,0.10)"/>
      <stop offset="55%" stop-color="rgba(135,77,255,0)"/>
    </radialGradient>
  </defs>
  <rect width="100%" height="100%" fill="url(#g)"/>
  <rect width="100%" height="100%" fill="url(#r1)"/>
  <rect width="100%" height="100%" fill="url(#r2)"/>
</svg>
SVG;
  return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function render_brand_card_html(string $subject, string $contentHtml, string $brandName = 'MindHack', string $logoUrl = '', string $supportLink = 'mailto:support@mind-hack.ru'): string {
  $bg = build_brand_background_svg_data_uri();
  $brand_bg_dark = '#0f0b1a';
  $brand_card    = '#131a2a';
  $brand_text    = '#e9eef4';
  $brand_muted   = '#cfc7de';
  $brand_accent  = '#ff9900';
  $brand_border  = 'rgba(255,255,255,0.12)';

  $rightBadge = $logoUrl
    ? '<img src="'.esc($logoUrl).'" alt="'.esc($brandName).'" style="max-height:28px;display:block;border:0;outline:none;text-decoration:none;">'
    : '<span style="display:inline-block;padding:8px 12px;border-radius:999px;background:'.$brand_accent.';color:#000;font-weight:800;font-size:12px;letter-spacing:.4px;">'.esc($brandName).'</span>';

  return <<<HTML
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{$subject}</title>
  </head>
  <body style="margin:0;padding:0;background:{$brand_bg_dark};">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
           style="background: {$brand_bg_dark}; background-image:url('{$bg}'); background-size:cover; background-repeat:no-repeat; background-position:center; padding:32px 16px;">
      <tr>
        <td align="center" style="padding:0;">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="640"
                 style="max-width:640px;background:{$brand_card};border:1px solid {$brand_border};border-radius:18px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,0.35);">
            <tr>
              <td style="padding:24px 28px;background:rgba(255,255,255,0.03);border-bottom:1px solid {$brand_border};">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                  <tr>
                    <td style="font-size:20px;line-height:1.3;color:{$brand_text};font-weight:bold;">
                      {$subject}
                    </td>
                    <td align="right" style="white-space:nowrap;">
                      {$rightBadge}
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:6px 8px 20px 8px;">
                {$contentHtml}
              </td>
            </tr>
            <tr>
              <td style="padding:18px 24px;border-top:1px solid {$brand_border};color:{$brand_muted};font-size:12px;text-align:center;">
                <div style="margin-bottom:6px;color:#fff;font-weight:700;letter-spacing:.3px;">{$brandName}</div>
                <div style="opacity:.85">Это уведомление отправлено автоматически. Нажмите «Ответить» в вашем почтовом клиенте или напишите нам: <a href="{$supportLink}" style="color:{$brand_accent};text-decoration:none;">поддержка</a>.</div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
}

/* ===================== СОХРАНЕНИЕ ФАЙЛОВ В /uploads ===================== */
$savedFiles = [];
$attachFiles = [];

if (!is_dir($uploadDir)) {
  if (!mkdir($uploadDir, 0775, true)) {
    json_exit(false, 'Не удалось создать папку для загрузок.');
  }
}

if (!empty($_FILES['files'])) {
  $f = $_FILES['files'];
  $names = is_array($f['name']) ? $f['name'] : [$f['name']];
  $tmps  = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
  $sizes = is_array($f['size']) ? $f['size'] : [$f['size']];
  $errs  = is_array($f['error']) ? $f['error'] : [$f['error']];
  $types = is_array($f['type']) ? $f['type'] : [$f['type']];

  foreach ($names as $i => $origName) {
    if ($errs[$i] === UPLOAD_ERR_NO_FILE) continue;
    if ($errs[$i] !== UPLOAD_ERR_OK) continue;

    $tmp  = $tmps[$i];
    $size = (int)$sizes[$i];
    $mime = $types[$i];

    if ($size > $maxFileBytes) continue;

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext && !in_array($ext, $allowedExts, true)) continue;

    $safeBase = preg_replace('/[^a-zA-Z0-9_\-\.]+/u', '_', pathinfo($origName, PATHINFO_FILENAME));
    $uniq = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $fileName = ($safeBase !== '' ? $safeBase : 'file') . '_' . $uniq . ($ext ? ".$ext" : '');
    $absPath = $uploadDir . '/' . $fileName;

    if (move_uploaded_file($tmp, $absPath)) {
      $rel = '/uploads/' . $fileName;
      if ($SITE_BASE_URL) {
        $rel = rtrim($SITE_BASE_URL,'/') . $rel;
      }
      $savedFiles[]  = $rel;
      $attachFiles[] = $absPath;
    }
  }
}

/* ===================== ДАННЫЕ ДЛЯ ПИСЕМ ===================== */
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$time = date('Y-m-d H:i:s');

$brand_accent = '#ff9900';
$brand_text   = '#e9eef4';
$brand_muted  = '#cfc7de';
$brand_border = 'rgba(255,255,255,0.12)';

/* ---------- Письмо в Inbox сайта ---------- */
$fields = [
  'Имя'       => $name,
  'Email'     => $email,
  'Телефон'   => $phone,
  'Тема'      => $subject,
  'Сообщение' => $message,
];

$rowsHtml = '';
foreach ($fields as $label => $value) {
  $isMessage = ($label === 'Сообщение');
  $rowsHtml .=
    '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
      <tr>
        <td style="padding:10px 12px;border-bottom:1px solid '.$brand_border.';color:'.$brand_muted.';font-size:12px;vertical-align:top;width:140px">'.esc($label).'</td>
        <td style="padding:10px 12px;border-bottom:1px solid '.$brand_border.';color:'.$brand_text.';font-size:14px;vertical-align:top;">'
          . ($isMessage ? nl2br(esc($value)) : esc($value)) .
        '</td>
      </tr>
    </table>';
}

$filesRowsHtml = '';
if (!empty($savedFiles)) {
  $filesRowsHtml .= '<div style="padding:16px 12px 6px;color:'.$brand_muted.';font-size:12px;border-bottom:1px solid '.$brand_border.'">Вложения / загруженные файлы:</div>';
  foreach ($savedFiles as $rel) {
    $fn = basename(parse_url($rel, PHP_URL_PATH));
    $filesRowsHtml .=
      '<div style="padding:6px 12px;color:'.$brand_muted.';font-size:14px;">
        <a href="'.esc($rel).'" style="color:'.$brand_accent.';text-decoration:none">'.esc($fn).'</a>
      </div>';
  }
}

$metaHtml =
  '<div style="padding:16px 12px 0;color:'.$brand_muted.';font-size:12px">'
  .'Отправлено: '.esc($time).' &nbsp;•&nbsp; IP: '.esc($ip).'<br>UA: '.esc($ua).'</div>';

$adminContent = $rowsHtml . $filesRowsHtml . $metaHtml;

/* ТЕМА ДЛЯ АДМИНА: «Обратная связь — MindHack — {тема}» */
$adminSubject = 'Обратная связь — ' . $SUBJECT_BRAND . ' — ' . ($subject !== '' ? $subject : 'без темы');

$adminHtml  = render_brand_card_html($adminSubject, $adminContent, $BRAND_NAME, $LOGO_URL, $SUPPORT_LINK);
$adminPlain = "Обратная связь — {$SUBJECT_BRAND} — ".($subject !== '' ? $subject : 'без темы')."\n\n"
            . "Имя: {$name}\nEmail: {$email}\nТелефон: {$phone}\nТема: {$subject}\n\nСообщение:\n{$message}\n\n";
if (!empty($savedFiles)) {
  $adminPlain .= "Файлы:\n" . implode("\n", $savedFiles) . "\n\n";
}
$adminPlain .= "--\nОтправлено: {$time} | IP: {$ip}\n";

/* ---------- Письмо-подтверждение пользователю ---------- */
/* ТЕМА ДЛЯ ПОЛЬЗОВАТЕЛЯ: «Обратная связь — MindHack» (без цитирования его текста) */
$userSubject = 'Обратная связь — ' . $SUBJECT_BRAND;

/* Короткое подтверждение без повторения сообщения пользователя */
$userBody = '
  <div style="padding:10px 12px;color:#e9eef4;font-size:14px;line-height:1.6">
    <p>Здравствуйте'.(mb_strlen($name) ? ', '.esc($name) : '').'!</p>
    <p>Мы получили ваше обращение в <strong style="color:'.$brand_accent.'">'.$BRAND_NAME.'</strong> и ответим в ближайшее время.</p>
    <p>Если хотите дополнить обращение — просто ответьте на это письмо или нажмите кнопку ниже.</p>
    <div style="text-align:center;margin-top:18px">
      <a href="'.esc($SUPPORT_LINK).'" style="display:inline-block;padding:12px 18px;border-radius:999px;background:'.$brand_accent.';color:#000;font-weight:800;font-size:14px;text-decoration:none;">Ответить нам</a>
    </div>
    <p style="margin-top:20px;color:'.$brand_muted.'">С уважением,<br>'.$BRAND_NAME.'.</p>
  </div>';

$userHtml  = render_brand_card_html($userSubject, $userBody, $BRAND_NAME, $LOGO_URL, $SUPPORT_LINK);
$userPlain = "Здравствуйте".(mb_strlen($name) ? ", {$name}" : "")."!\n\n"
           . "Мы получили ваше обращение в {$BRAND_NAME} и ответим в ближайшее время.\n"
           . "Чтобы дополнить обращение — просто ответьте на это письмо.\n\n"
           . "С уважением, {$BRAND_NAME}.\n";

/* ===================== ОТПРАВКА ПИСЕМ ===================== */
try {
  // 1) Письмо в Inbox сайта
  $mail = new PHPMailer(true);
  $mail->CharSet   = 'UTF-8';
  $mail->isSMTP();
  $mail->Host       = $SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = $SMTP_USER;
  $mail->Password   = $SMTP_PASS;
  $mail->SMTPSecure = $SMTP_SECURE;
  $mail->Port       = $SMTP_PORT;

  $mail->Sender = $SITE_FROM_EMAIL; // Return-Path

  $mail->setFrom($SITE_FROM_EMAIL, $SITE_FROM_NAME);
  $mail->addAddress($TO_EMAIL, $TO_NAME);
  $mail->addReplyTo($email, $name);

  $mail->Subject = $adminSubject; // <-- обновлённая тема
  $mail->isHTML(true);
  $mail->Body    = $adminHtml;
  $mail->AltBody = $adminPlain;

  foreach ($attachFiles as $path) {
    $mail->addAttachment($path);
  }

  if (!$mail->send()) {
    json_exit(false, 'Не удалось отправить письмо: ' . $mail->ErrorInfo);
  }

  // 2) Письмо-подтверждение пользователю
  try {
    $mail2 = new PHPMailer(true);
    $mail2->CharSet   = 'UTF-8';
    $mail2->isSMTP();
    $mail2->Host       = $SMTP_HOST;
    $mail2->SMTPAuth   = true;
    $mail2->Username   = $SMTP_USER;
    $mail2->Password   = $SMTP_PASS;
    $mail2->SMTPSecure = $SMTP_SECURE;
    $mail2->Port       = $SMTP_PORT;

    $mail2->Sender = $SITE_FROM_EMAIL;

    $mail2->setFrom($SITE_FROM_EMAIL, $BRAND_NAME);
    $mail2->addAddress($email, $name);
    $mail2->addReplyTo($SITE_FROM_EMAIL, $BRAND_NAME);

    $mail2->Subject = $userSubject; // <-- «Обратная связь — MindHack»
    $mail2->isHTML(true);
    $mail2->Body    = $userHtml;
    $mail2->AltBody = $userPlain;

    // Без вложений
    $mail2->send();
  } catch (Exception $e2) {
    // лог при желании: $e2->getMessage()
  }

  json_exit(true, 'Сообщение отправлено!', ['files' => $savedFiles]);
} catch (Exception $e) {
  json_exit(false, 'Ошибка отправки: ' . $e->getMessage());
}
