<?php
// RENOVA Immobilien Tirol: Formularversand über den eigenen Server (b2go/Plesk).
// Empfänger steht fest und kann vom Browser aus nicht geändert werden.
// Website von archivisu.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const EMPFAENGER = 'info@renovatirol.at';
const ABSENDER   = 'info@renovatirol.at';
const MAX_PRO_STUNDE = 8;

function antwort(bool $ok, int $code = 200, string $msg = ''): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
// Direkte Zustellung an den lokalen Mailserver (Postfach liegt auf demselben Server, keine Zugangsdaten nötig)
function smtp_lokal(string $an, string $von, string $sub, string $text, array $kopf, string &$fehler): bool {
    $fp = @stream_socket_client('tcp://127.0.0.1:25', $en, $es, 8);
    if (!$fp) { $fehler = 'SMTP verbinden: ' . $es; return false; }
    stream_set_timeout($fp, 10);
    $lies = function () use ($fp): string { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, string $erw) use ($fp, $lies, &$fehler): bool { if ($c !== '') fwrite($fp, $c . "\r\n"); $r = $lies(); if (strpos($r, $erw) !== 0) { $fehler = 'SMTP ' . ($c ?: 'start') . ': ' . trim($r); return false; } return true; };
    $daten = 'To: ' . $an . "\r\nSubject: " . $sub . "\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@renovatirol.at>' . "\r\n" . implode("\r\n", $kopf) . "\r\n\r\n"
           . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $text)) . "\r\n.";
    $ok = $cmd('', '220') && $cmd('EHLO www.renovatirol.at', '250') && $cmd('MAIL FROM:<' . $von . '>', '250')
       && $cmd('RCPT TO:<' . $an . '>', '25') && $cmd('DATA', '354') && $cmd($daten, '250');
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return $ok;
}
function zeile(string $s, int $max = 300): string {
    $s = preg_replace('/[\r\n\t]+/', ' ', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') antwort(false, 405, 'Nur POST');

// Nur Aufrufe von der eigenen Website annehmen
$herkunft = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$host = parse_url($herkunft, PHP_URL_HOST) ?: '';
if ($host !== '' && !preg_match('/(^|\.)renovatirol\.at$/i', $host)) antwort(false, 403, 'Herkunft');

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

$betreff = zeile((string)($d['subject'] ?? ''), 200);
$praefix = '[Website-' . $art . ']';
if (strpos($betreff, $praefix) !== 0) $betreff = $praefix . ' ' . $betreff;

$text .= "\n\n" . str_repeat('-', 44) . "\nGesendet über das Formular auf renovatirol.at\nAntworten geht direkt an: " . $mail . "\n";

$kopf = [
    'From: =?UTF-8?B?' . base64_encode('RENOVA Website') . '?= <' . ABSENDER . '>',
    'Reply-To: ' . $mail,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Renova-Formular: ' . $art,
];
$sub = '=?UTF-8?B?' . base64_encode($betreff) . '?=';
$ok = @mail(EMPFAENGER, $sub, $text, implode("\r\n", $kopf), '-f' . ABSENDER);
if (!$ok) $ok = @mail(EMPFAENGER, $sub, $text, implode("\r\n", $kopf));   // manche Hosts erlauben -f nicht
if (!$ok) {
    $e = error_get_last(); $diag = $e['message'] ?? 'mail() false';
    $f2 = '';
    $ok = smtp_lokal(EMPFAENGER, ABSENDER, $sub, $text, $kopf, $f2);
    if (!$ok) antwort(false, 500, 'Versand: ' . mb_substr($diag . ' | ' . $f2, 0, 300));
}
antwort(true);
