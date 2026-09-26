<?php

declare(strict_types=1);

namespace Nexis\Mail;

/**
 * Minimal, client-safe HTML shell for transactional notifications.
 */
final class TransactionalMailHtml
{
    public static function wrap(string $title, string $preheader, string $innerHtml, string $brand = 'Nexis'): string
    {
        $titleEsc = self::e($title);
        $preEsc = self::e($preheader);
        $brandEsc = self::e($brand !== '' ? $brand : 'Nexis');

        return <<<HTML
<!DOCTYPE html>
<html lang="und">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{$titleEsc}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f5;color:#152033;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">{$preEsc}</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:28px 12px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #d5dbe5;border-radius:12px;overflow:hidden;box-shadow:0 10px 28px rgba(21,32,51,.06);">
        <tr>
          <td style="background:#1d4f91;padding:18px 24px;">
            <p style="margin:0;font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:rgba(255,255,255,.78);font-weight:600;">{$brandEsc}</p>
            <h1 style="margin:6px 0 0;font-size:22px;line-height:1.25;color:#ffffff;font-weight:700;">{$titleEsc}</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:24px;">
            {$innerHtml}
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

    public static function badge(string $label, string $value): string
    {
        return '<p style="margin:0 0 18px;">'
            . '<span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#e8f0fa;color:#1d4f91;font-size:13px;font-weight:700;">'
            . self::e($label) . ': ' . self::e($value)
            . '</span></p>';
    }

    /**
     * @param array<string, string> $rows
     */
    public static function rows(array $rows): string
    {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr>'
                . '<td style="padding:10px 0;border-bottom:1px solid #e8ecf2;width:34%;vertical-align:top;color:#5b6577;font-size:13px;font-weight:600;">'
                . self::e($label)
                . '</td>'
                . '<td style="padding:10px 0;border-bottom:1px solid #e8ecf2;vertical-align:top;color:#152033;font-size:14px;line-height:1.45;">'
                . self::e($value)
                . '</td>'
                . '</tr>';
        }

        return $html . '</table>';
    }

    public static function message(string $label, string $text): string
    {
        $paragraphs = preg_split("/\R/u", $text) ?: [];
        $body = '';
        foreach ($paragraphs as $line) {
            $body .= '<p style="margin:0 0 8px;font-size:14px;line-height:1.55;color:#152033;">'
                . ($line === '' ? '&nbsp;' : self::e($line))
                . '</p>';
        }

        return '<p style="margin:0 0 8px;color:#5b6577;font-size:13px;font-weight:600;">' . self::e($label) . '</p>'
            . '<div style="padding:14px 16px;border-radius:10px;background:#f5f7fa;border:1px solid #e8ecf2;">'
            . $body
            . '</div>';
    }

    public static function footer(string $text): string
    {
        return '<p style="margin:20px 0 0;font-size:12px;line-height:1.45;color:#5b6577;">' . self::e($text) . '</p>';
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
