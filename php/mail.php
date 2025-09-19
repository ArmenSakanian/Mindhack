<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
global $CONFIG;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

function mlog(string $tag, $data): void {
  $dir = __DIR__ . '/logs';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $line = '['.date('Y-m-d H:i:s')."] $tag: ";
  if (is_string($data)) $line .= $data; else $line .= json_encode($data, JSON_UNESCAPED_UNICODE);
  $line .= "\n";
  @file_put_contents($dir.'/mail-'.date('Y-m-d').'.log', $line, FILE_APPEND);
}

/**
 * @return array [bool ok, string error]
 */
function sendProductEmail(string $toEmail, string $toName, array $order, array $items): array {
  global $CONFIG;

  $mail = new PHPMailer(true);
  $debugBuf = '';

  try {
    $mail->isSMTP();
    $mail->Host       = $CONFIG['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $CONFIG['SMTP_USER'];
    $mail->Password   = $CONFIG['SMTP_PASS'];
    $mail->Port       = (int)$CONFIG['SMTP_PORT'];
    $mail->CharSet    = 'UTF-8';

    if ((int)$CONFIG['SMTP_PORT'] === 465) {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    // Включаем отладку в буфер, если APP_DEBUG=1
    if (!empty($CONFIG['APP_DEBUG'])) {
      $mail->SMTPDebug   = 2;
      $mail->Debugoutput = function($str, $level) use (&$debugBuf) {
        $debugBuf .= "[$level] $str\n";
      };
    }

    $mail->setFrom($CONFIG['SMTP_FROM'], $CONFIG['SMTP_FROM_NAME']);
    $mail->addAddress($toEmail, $toName);

    $orderNo = htmlspecialchars((string)($order['order_uid'] ?? $order['id']));
    $brand   = htmlspecialchars((string)($CONFIG['SMTP_FROM_NAME'] ?? 'MindHack'));
    $user    = htmlspecialchars($toName !== '' ? $toName : 'Покупатель');

    $mail->Subject = 'Ваши материалы — заказ #'.$orderNo;

    // Сборка строк таблицы
    $rowsHtml = '';
    $rowsTxt  = '';
    foreach ($items as $it) {
      $pname = htmlspecialchars((string)$it['product_name']);
      $qty   = max(1, (int)$it['qty']);
      $link  = trim((string)$it['snapshot_link']);

      $rowsHtml .= '<tr>
          <td style="padding:12px 14px;border-bottom:1px solid #2b2b2b;color:#ffffff;">'.$pname.'</td>
          <td style="padding:12px 14px;border-bottom:1px solid #2b2b2b;color:#ffffff;text-align:center;white-space:nowrap;">'.$qty.'×</td>
          <td style="padding:12px 14px;border-bottom:1px solid #2b2b2b;text-align:right;">'
            .($link
               ? '<a href="'.htmlspecialchars($link).'" target="_blank" rel="noopener" style="color:#FF9900;text-decoration:none;">Открыть</a>'
               : '<em style="color:#b7b7b7;">Ссылка будет отправлена отдельно</em>'
             ).
          '</td>
        </tr>';

      $rowsTxt .= "- {$pname} ({$qty} шт.)".($link ? " → {$link}" : "")."\n";
    }

    // ==== HTML тело письма (тёмная тема #222222, акцент #FF9900) ====
    $body = '
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Заказ #'.$orderNo.'</title>
    <style>
      /* безопасные стили для большинства клиентов: всё критичное дублируется inline */
      @media (max-width: 600px) {
        .container { width: 100% !important; padding: 0 12px !important; }
        .card { padding: 18px !important; }
        .btn { display: block !important; width: 100% !important; }
        .muted-note { font-size: 12px !important; }
      }
    </style>
  </head>
  <body style="margin:0;padding:0;background-color:#222222;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#222222;">
      <tr>
        <td align="center" style="padding:28px 16px;">
          <table class="container" role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:640px;max-width:100%;">
            <!-- Шапка -->
            <tr>
              <td style="padding:0 0 18px 0;text-align:center;">
                <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:20px;line-height:1.3;color:#ffffff;">
                  <span style="display:inline-block;padding:8px 14px;border:1px solid #2b2b2b;border-radius:10px;background:#1f1f1f;">
                    <strong style="color:#FF9900;">'.$brand.'</strong>
                  </span>
                </div>
              </td>
            </tr>

            <!-- Карточка -->
            <tr>
              <td>
                <table class="card" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                  style="background:#1c1c1c;border:1px solid #2b2b2b;border-radius:14px;padding:24px;">
                  <tr>
                    <td>
                      <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#ffffff;">
                        <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.35;color:#ffffff;">
                          Спасибо за покупку, '.$user.'!
                        </h1>
                        <p style="margin:0 0 18px 0;font-size:14px;color:#e5e5e5;">
                          Ваш заказ <strong style="color:#FF9900;">#'.$orderNo.'</strong> оплачен. Ниже — доступ к материалам.
                        </p>

                        <!-- Таблица товаров -->
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                          style="border-collapse:collapse;background:#171717;border:1px solid #2b2b2b;border-radius:10px;overflow:hidden;">
                          <thead>
                            <tr>
                              <th align="left" style="padding:12px 14px;border-bottom:1px solid #2b2b2b;font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#b7b7b7;">Товар</th>
                              <th align="center" style="padding:12px 14px;border-bottom:1px solid #2b2b2b;font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#b7b7b7;">Кол-во</th>
                              <th align="right" style="padding:12px 14px;border-bottom:1px solid #2b2b2b;font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#b7b7b7;">Доступ</th>
                            </tr>
                          </thead>
                          <tbody>'.$rowsHtml.'</tbody>
                        </table>

                        <!-- Подсказки -->
                        <div style="margin-top:16px;font-size:13px;color:#d7d7d7;">
                          <div style="margin-bottom:8px;">
                            • Материалы открываются в режиме <strong>read-only</strong>. Нажмите <em style="color:#b7b7b7;">«Файл → Сделать копию»</em>, чтобы работать в своём аккаунте.
                          </div>
                          <div class="muted-note" style="font-size:12px;color:#b7b7b7;margin-top:6px;">
                            Если письмо попало в спам — отметьте как «Не спам». Если ссылка не открывается, просто ответьте на это письмо — мы поможем.
                          </div>
                        </div>

                        <!-- Кнопка на сайт -->
                        <div style="margin-top:22px;">
                          <a href="'.htmlspecialchars((string)($CONFIG['BASE_URL'] ?? '/')).'" target="_blank" rel="noopener"
                             class="btn"
                             style="display:inline-block;padding:12px 18px;background:#FF9900;color:#111111;text-decoration:none;border-radius:10px;font-weight:600;">
                            Открыть сайт '.$brand.'
                          </a>
                        </div>

                        <!-- Подвал -->
                        <div style="margin-top:26px;padding-top:14px;border-top:1px solid #2b2b2b;font-size:12px;color:#b7b7b7;">
                          Это письмо отправлено автоматически с адреса
                          <span style="color:#e5e5e5;">'.htmlspecialchars((string)($CONFIG['SMTP_FROM'] ?? 'noreply@example.com')).'</span>.
                          По вопросам поддержки — просто ответьте на него.
                        </div>
                      </div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Низ страницы -->
            <tr>
              <td style="text-align:center;padding:16px 0 0 0;">
                <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#9a9a9a;">
                  © '.date('Y').' '.$brand.'. Все права защищены.
                </div>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';

    // Альтернативное текстовое тело (plain text)
    $alt = "Здравствуйте, {$toName}!\n"
         . "Ваш заказ #{$orderNo} оплачен.\n\n"
         . "Материалы:\n"
         . $rowsTxt
         . "\nПодсказка: доступ read-only. В своём аккаунте сделайте «Копию» файла.\n"
         . "Если ссылка не открывается — ответьте на это письмо.\n"
         . ($CONFIG['BASE_URL'] ? ("\nСайт: " . (string)$CONFIG['BASE_URL'] . "\n") : "");

    $mail->isHTML(true);
    $mail->Body    = $body;
    $mail->AltBody = $alt;

    $ok = $mail->send();
    if (!empty($CONFIG['APP_DEBUG'])) {
      mlog('SMTP_DEBUG', $debugBuf);
    }
    return [$ok ? true : false, $ok ? '' : $mail->ErrorInfo];
  } catch (\Throwable $e) {
    if (!empty($CONFIG['APP_DEBUG'])) {
      mlog('SMTP_EXC', $e->getMessage()."\n".$debugBuf);
    }
    return [false, $e->getMessage()];
  }
}
