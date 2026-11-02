<?php
// RENOVA Immobilien Tirol: Formularversand über den eigenen Server (b2go/Plesk).
// Objekt-Anfragen und Suchaufträge gehen an verkauf@renovatirol.at, als Mail im RENOVA-CI (HTML) mit Textfassung.
// Empfänger steht fest und kann vom Browser aus nicht geändert werden.
// Website von archivisu.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const EMPFAENGER = 'verkauf@renovatirol.at';
const ABSENDER   = 'info@renovatirol.at';
const MAX_PRO_STUNDE = 8;
// Nur Aufrufe von der eigenen Website annehmen (renovatirol.at, renovatirol.com und die Cloudflare-Vorschau)
const ERLAUBT = '/(^|\.)(renovatirol\.at|renovatirol\.com|renova-alpenimmobilien\.pages\.dev)$/i';
const TECHNIK = ['art', 'subject', 'replyto', 'message', 'botcheck', 'access_key', 'from_name'];

function antwort(bool $ok, int $code = 200, string $msg = ''): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function zeile(string $s, int $max = 300): string {
    $s = preg_replace('/[\r\n\t]+/', ' ', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Mail im RENOVA-CI: Graphit #141414 mit neutralem Grauverlauf, Logo v2.2, Schrift #E6E4E0 / #A3A09A
function mail_html(string $art, string $titel, string $unter, array $felder, string $nachricht, string $antwortAn, string $telefon, string $betreff, string $nr): string {
    $c = ['bg' => '#141414', 'linie' => '#363636', 'text' => '#E6E4E0', 'hell' => '#F3F2EF', 'grau' => '#A3A09A'];
    $font = "'Jost','Helvetica Neue',Helvetica,Arial,sans-serif";
    $verlauf = 'background-color:#141414;background-image:radial-gradient(ellipse 120% 45% at 50% 0%,#3C3C3C 0%,rgba(60,60,60,0) 70%),linear-gradient(180deg,#2C2C2C 0%,#1C1C1C 40%,#121212 100%);';
    $knopf = function (string $href, string $text, bool $voll) use ($c, $font) {
        return '<td style="padding:0 10px 10px 0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="border:1px solid ' . $c['text'] . ';' . ($voll ? 'background:' . $c['text'] . ';' : '') . '">'
            . '<a href="' . e($href) . '" style="display:inline-block;padding:14px 24px;font-family:' . $font . ';font-size:11px;letter-spacing:2.4px;text-transform:uppercase;text-decoration:none;color:' . ($voll ? '#141414' : $c['hell']) . ';">' . e($text) . '</a></td></tr></table></td>';
    };
    $zeilen = '';
    foreach ($felder as $k => $v) {
        if ($v === '' || $v === 'k. A.') continue;
        $wert = e($v);
        if ($k === 'E-Mail') $wert = '<a href="mailto:' . e($v) . '" style="color:' . $c['text'] . ';">' . e($v) . '</a>';
        elseif ($k === 'Telefon') $wert = '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $v)) . '" style="color:' . $c['text'] . ';">' . e($v) . '</a>';
        elseif ($k === 'Objektseite') $wert = '<a href="' . e($v) . '" style="color:' . $c['text'] . ';">Seite öffnen</a>';
        $zeilen .= '<tr><td width="34%" valign="top" style="padding:11px 12px 11px 0;border-bottom:1px solid ' . $c['linie'] . ';font-family:' . $font . ';font-size:10px;letter-spacing:1.6px;text-transform:uppercase;color:' . $c['grau'] . ';">' . e($k) . '</td>'
                 . '<td valign="top" style="padding:10px 0;border-bottom:1px solid ' . $c['linie'] . ';font-family:' . $font . ';font-size:14px;line-height:1.5;color:' . $c['text'] . ';">' . $wert . '</td></tr>';
    }
    $knoepfe = $knopf('mailto:' . $antwortAn . '?subject=' . rawurlencode('Re: ' . $betreff), 'Antworten', true)
             . ($telefon !== '' ? $knopf('tel:' . preg_replace('/[^0-9+]/', '', $telefon), 'Anrufen', false) : '');
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="supported-color-schemes" content="dark"><title>' . e($betreff) . '</title></head>'
        . '<body style="margin:0;padding:0;' . $verlauf . '">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#141414" style="' . $verlauf . '"><tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">'
        . '<tr><td style="padding:24px 32px 0;"><img src="https://www.renovatirol.at/assets/logo/renova-mail-logo.png" width="170" alt="RENOVA Immobilien Tirol" style="display:block;width:170px;height:auto;border:0;"></td></tr>'
        . '<tr><td style="padding:44px 32px 0;font-family:' . $font . ';font-size:11px;letter-spacing:3px;text-transform:uppercase;color:' . $c['grau'] . ';">' . e($art === 'Suchauftrag' ? 'Neuer Suchauftrag über die Website' : 'Neue Anfrage über die Website') . '</td></tr>'
        . '<tr><td style="padding:10px 32px 0;font-family:' . $font . ';font-size:30px;font-weight:300;line-height:1.25;color:' . $c['hell'] . ';">' . e($titel) . '</td></tr>'
        . ($unter !== '' ? '<tr><td style="padding:8px 32px 0;font-family:' . $font . ';font-size:14px;line-height:1.6;color:' . $c['grau'] . ';">' . e($unter) . '</td></tr>' : '')
        . '<tr><td style="padding:28px 22px 0 32px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>' . $knoepfe . '</tr></table></td></tr>'
        . ($nachricht !== '' ? '<tr><td style="padding:30px 32px 0;font-family:' . $font . ';font-size:11px;letter-spacing:3px;text-transform:uppercase;color:' . $c['hell'] . ';">Nachricht</td></tr>'
            . '<tr><td style="padding:12px 32px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-left:1px solid ' . $c['grau'] . ';padding:4px 0 4px 18px;font-family:' . $font . ';font-size:15px;line-height:1.7;color:' . $c['text'] . ';">' . nl2br(e($nachricht)) . '</td></tr></table></td></tr>' : '')
        . '<tr><td style="padding:34px 32px 0;font-family:' . $font . ';font-size:11px;letter-spacing:3px;text-transform:uppercase;color:' . $c['hell'] . ';">Angaben</td></tr>'
        . '<tr><td style="padding:6px 32px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed;">' . $zeilen . '</table></td></tr>'
        . '<tr><td style="padding:34px 32px 36px;font-family:' . $font . ';font-size:11px;line-height:1.8;color:' . $c['grau'] . ';">' . e($nr !== '' ? 'Vorgangsnummer ' . $nr . ' · ' : '') . 'Eingegangen am ' . e(date('d.m.Y')) . ' um ' . e(date('H:i')) . ' Uhr<br>'
        . 'Gesendet über das Formular auf renovatirol.at. „Antworten“ geht direkt an ' . e($antwortAn) . '.<br>Website © archivisu</td></tr>'
        . '</table></td></tr></table></body></html>';
}

// Zustellung an den lokalen Mailserver (Postfach liegt auf demselben Server, keine Zugangsdaten nötig)
function smtp_lokal(string $an, string $von, string $daten, string &$fehler): bool {
    $fp = @stream_socket_client('tcp://127.0.0.1:25', $en, $es, 8);
    if (!$fp) { $fehler = 'SMTP verbinden: ' . $es; return false; }
    stream_set_timeout($fp, 15);
    $lies = function () use ($fp): string { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, string $erw) use ($fp, $lies, &$fehler): bool { if ($c !== '') fwrite($fp, $c . "\r\n"); $r = $lies(); if (strpos($r, $erw) !== 0) { $fehler = 'SMTP ' . (substr($c, 0, 12) ?: 'start') . ': ' . trim($r); return false; } return true; };
    $ok = $cmd('', '220') && $cmd('EHLO www.renovatirol.at', '250') && $cmd('MAIL FROM:<' . $von . '>', '250')
       && $cmd('RCPT TO:<' . $an . '>', '25') && $cmd('DATA', '354') && $cmd($daten . "\r\n.", '250');
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return $ok;
}

if (getenv('RENOVA_LOKAL')) return;   // lokal nur Funktionen laden (Vorschau)

$herkunft = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$host = parse_url($herkunft, PHP_URL_HOST) ?: '';
if (isset($_SERVER['HTTP_ORIGIN']) && preg_match(ERLAUBT, $host)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') antwort(false, 405, 'Nur POST');
if ($host !== '' && !preg_match(ERLAUBT, $host)) antwort(false, 403, 'Herkunft');

$roh = file_get_contents('php://input', false, null, 0, 60000) ?: '';
$d = json_decode($roh, true);
if (!is_array($d)) $d = $_POST;
if (!is_array($d) || !$d) antwort(false, 400, 'Leer');

// Honeypot: Bots füllen dieses Feld aus. Still "erfolgreich" antworten.
if (trim((string)($d['botcheck'] ?? '')) !== '') antwort(true);

$art = ($d['art'] ?? '') === 'suchauftrag' ? 'Suchauftrag' : 'Anfrage';
$mail = zeile((string)($d['replyto'] ?? ''), 200);
if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) antwort(false, 422, 'E-Mail');
$text = (string)($d['message'] ?? '');
$text = str_replace("\r", '', mb_substr($text, 0, 20000));
if (mb_strlen(trim($text)) < 10) antwort(false, 422, 'Nachricht');

// Einfache Bremse: höchstens MAX_PRO_STUNDE Sendungen je IP und Stunde
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$sperr = sys_get_temp_dir() . '/renova_form_' . hash('sha256', $ip . date('YmdH'));
$n = (int)@file_get_contents($sperr);
if ($n >= MAX_PRO_STUNDE) antwort(false, 429, 'Zu viele');
@file_put_contents($sperr, (string)($n + 1));

date_default_timezone_set('Europe/Vienna');
$betreff = zeile((string)($d['subject'] ?? ''), 200);
$praefix = '[Website-' . $art . ']';
if (strpos($betreff, $praefix) !== 0) $betreff = $praefix . ' ' . $betreff;

// Einzelfelder (Reihenfolge wie im Formular), alles als Text
$felder = [];
foreach ($d as $k => $v) {
    if (in_array($k, TECHNIK, true) || is_array($v)) continue;
    $felder[zeile((string)$k, 60)] = mb_substr(trim(str_replace("\r", '', (string)$v)), 0, 4000);
}
$nachricht = $felder['Nachricht'] ?? '';
unset($felder['Nachricht']);
$name = $felder['Name'] ?? '';
$telefon = $felder['Telefon'] ?? '';
if ($telefon === 'k. A.') $telefon = '';
$nr = $felder['Vorgangsnummer'] ?? '';
if ($art === 'Anfrage') {
    $titel = $felder['Objekt'] ?? 'Objekt-Anfrage';
    $unter = implode(' · ', array_filter([$name, $felder['Ort'] ?? '', $felder['Preis'] ?? '']));
} else {
    $titel = $name !== '' ? $name : 'Suchauftrag';
    $unter = implode(' · ', array_filter([$felder['Objektart'] ?? '', $felder['Region'] ?? '', $felder['Budget'] ?? '']));
}

$text .= "\n\n" . str_repeat('-', 44) . "\nGesendet über das Formular auf renovatirol.at\nAntworten geht direkt an: " . $mail . "\n";
$html = mail_html($art, $titel, $unter, $felder, $nachricht, $mail, $telefon, $betreff, $nr);

$grenze = 'renova' . bin2hex(random_bytes(8));
$daten = 'From: =?UTF-8?B?' . base64_encode('RENOVA Website') . '?= <' . ABSENDER . ">\r\n"
       . 'To: ' . EMPFAENGER . "\r\n"
       . 'Reply-To: ' . $mail . "\r\n"
       . 'Subject: =?UTF-8?B?' . base64_encode($betreff) . "?=\r\n"
       . 'Date: ' . date('r') . "\r\n"
       . 'Message-ID: <' . bin2hex(random_bytes(8)) . "@renovatirol.at>\r\n"
       . "MIME-Version: 1.0\r\n"
       . 'X-Renova-Formular: ' . $art . "\r\n"
       . "Content-Type: multipart/alternative; boundary=\"$grenze\"\r\n\r\n"
       . "--$grenze\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
       . "--$grenze\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
       . "--$grenze--";

$fehler = '';
if (!smtp_lokal(EMPFAENGER, ABSENDER, $daten, $fehler)) {
    error_log('RENOVA Formular: ' . $fehler);
    antwort(false, 500, 'Versand');
}
antwort(true);
