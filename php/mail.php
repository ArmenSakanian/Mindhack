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
      $mail->SMTPDebug  = 2;
      $mail->Debugoutput = function($str, $level) use (&$debugBuf) {
        $debugBuf .= "[$level] $str\n";
      };
    }

    $mail->setFrom($CONFIG['SMTP_FROM'], $CONFIG['SMTP_FROM_NAME']);
    $mail->addAddress($toEmail, $toName);
    $orderNo = htmlspecialchars((string)($order['order_uid'] ?? $order['id']));

    $mail->Subject = 'Ваши материалы — заказ #'.$orderNo;

    $rows = '';
    foreach ($items as $it) {
      $pname = htmlspecialchars((string)$it['product_name']);
      $qty   = (int)$it['qty'];
      $link  = trim((string)$it['snapshot_link']);
      $linkHtml = $link ? '<a href="'.htmlspecialchars($link).'" target="_blank" rel="noopener">Ссылка</a>' : '<em>Ссылка будет отправлена отдельно</em>';
      $rows .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #eee;">'.$pname.'</td>'.
               '<td style="padding:6px 10px;border-bottom:1px solid #eee;">'.$qty.'</td>'.
               '<td style="padding:6px 10px;border-bottom:1px solid #eee;">'.$linkHtml.'</td></tr>';
    }

    $body = '
      <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#111">
        <p>Здравствуйте, '.htmlspecialchars($toName).'. Спасибо за покупку!</p>
        <p>Материалы по заказу <b>#'.$orderNo.'</b>:</p>
        <table style="border-collapse:collapse;width:100%;max-width:720px">'.$rows.'</table>
        <p style="margin-top:12px">Если письмо попало в спам — отметьте «Не спам». Если ссылка не открывается — ответьте на это письмо.</p>
        <p style="color:#666">С уважением, '.htmlspecialchars($CONFIG['SMTP_FROM_NAME']).'</p>
      </div>
    ';
    $mail->isHTML(true);
    $mail->Body = $body;
    $mail->AltBody = 'Ваши материалы по заказу #'.$orderNo;

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
