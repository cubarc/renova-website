<?php
// RENOVA Monatsbericht im CI. Holt die Zahlen des Vormonats direkt aus Matomo (/statistik)
// und schickt sie einmal pro Monat an info@renovatirol.at und statistik@renovatirol.at (Weiterleitung an info@cubarc.at).
// Aufruf am Monatsanfang durch die GitHub Action „Statistik Monatsbericht anstoßen“.
// Sicherheit: sendet nur am 1. bis 5. des Monats, pro Monat nur ein einziges Mal, gibt keine Zahlen aus.
// Website von archivisu.
declare(strict_types=1);

const BERICHT_AN   = ['info@renovatirol.at', 'statistik@renovatirol.at'];
const BERICHT_VON  = 'info@renovatirol.at';
const BERICHT_SITE = 1;
const MONATE = [1 => 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

// ---------- Darstellung (auch lokal mit Beispieldaten nutzbar) ----------
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function zahl($n): string { return number_format((float)$n, 0, ',', '.'); }
function veraenderung($jetzt, $vorher): string {
    $jetzt = (float)$jetzt; $vorher = (float)$vorher;
    if ($vorher <= 0) return $jetzt > 0 ? 'neu' : 'gleich';
    $p = round(($jetzt - $vorher) / $vorher * 100);
    if ($p == 0) return '± 0 %';
    return ($p > 0 ? '+' : '−') . abs($p) . ' %';
}
function dauer($sek): string { $sek = (int)round((float)$sek); return intdiv($sek, 60) . ' min ' . str_pad((string)($sek % 60), 2, '0', STR_PAD_LEFT) . ' s'; }

function bericht_html(array $d): string {
    $c = ['bg' => '#141414', 'karte' => '#1E1E1E', 'linie' => '#363636', 'text' => '#E6E4E0', 'hell' => '#F3F2EF', 'grau' => '#A3A09A', 'silber' => '#CFCFCF', 'balken' => '#3E3E3E'];
    // Hintergrund laut CI: Graphit #141414 mit sanftem, neutralem Grauverlauf (Outlook zeigt die einfarbige Grundfarbe)
    $verlauf = 'background-color:' . $c['bg'] . ';background-image:radial-gradient(ellipse 120% 45% at 50% 0%,#3C3C3C 0%,rgba(60,60,60,0) 70%),linear-gradient(180deg,#2C2C2C 0%,#1C1C1C 40%,#121212 100%);';
    $font = "'Jost','Helvetica Neue',Helvetica,Arial,sans-serif";
    $k = $d['kennzahlen'];
    $kachel = function (string $titel, $wert, string $sub) use ($c, $font) {
        return '<td width="33%" valign="top" style="padding:22px 12px 20px 0;border-top:1px solid ' . $c['linie'] . ';">'
            . '<div style="font-family:' . $font . ';font-size:10px;letter-spacing:1.6px;text-transform:uppercase;color:' . $c['grau'] . ';">' . e($titel) . '</div>'
            . '<div style="font-family:' . $font . ';font-size:30px;font-weight:300;color:' . $c['hell'] . ';line-height:1.2;padding-top:8px;">' . e($wert) . '</div>'
            . '<div style="font-family:' . $font . ';font-size:12px;color:' . $c['grau'] . ';padding-top:4px;">' . e($sub) . '</div></td>';
    };
    $abschnitt = function (string $titel) use ($c, $font) {
        return '<tr><td style="padding:44px 36px 14px;font-family:' . $font . ';font-size:11px;letter-spacing:3px;text-transform:uppercase;color:' . $c['hell'] . ';">' . e($titel) . '</td></tr>';
    };
    $balken = function (array $zeilen, string $einheit = '') use ($c, $font) {
        $max = max(1, ...array_map(fn($z) => (float)$z[1], $zeilen ?: [['', 1]]));
        $h = '';
        foreach ($zeilen as $z) {
            $w = max(2, (int)round((float)$z[1] / $max * 100));
            $h .= '<tr><td style="padding:0 36px 12px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td style="font-family:' . $font . ';font-size:14px;color:' . $c['text'] . ';padding-bottom:6px;">' . e($z[0]) . '</td>'
                . '<td align="right" style="font-family:' . $font . ';font-size:14px;color:' . $c['silber'] . ';padding-bottom:6px;white-space:nowrap;">' . e($z[2] ?? zahl($z[1]) . $einheit) . '</td></tr>'
                . '<tr><td colspan="2" style="background:' . $c['balken'] . ';height:2px;line-height:2px;font-size:0;"><table role="presentation" width="' . $w . '%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background:' . $c['silber'] . ';height:2px;line-height:2px;font-size:0;">&nbsp;</td></tr></table></td></tr>'
                . '</table></td></tr>';
        }
        return $h ?: '<tr><td style="padding:0 36px 12px;font-family:' . $font . ';font-size:14px;color:' . $c['grau'] . ';">Noch keine Daten in diesem Monat</td></tr>';
    };
    $liste = function (array $zeilen) use ($c, $font) {
        $h = '';
        foreach ($zeilen as $i => $z) {
            $h .= '<tr><td style="padding:0 36px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td width="34" style="font-family:' . $font . ';font-size:12px;color:' . $c['grau'] . ';padding:12px 0;border-bottom:1px solid ' . $c['linie'] . ';">' . sprintf('%02d', $i + 1) . '</td>'
                . '<td style="font-family:' . $font . ';font-size:14px;color:' . $c['text'] . ';padding:12px 0;border-bottom:1px solid ' . $c['linie'] . ';">' . e($z[0]) . '</td>'
                . '<td align="right" style="font-family:' . $font . ';font-size:14px;color:' . $c['silber'] . ';padding:12px 0;border-bottom:1px solid ' . $c['linie'] . ';white-space:nowrap;">' . e($z[1]) . '</td>'
                . '</tr></table></td></tr>';
        }
        return $h ?: '<tr><td style="padding:0 36px 12px;font-family:' . $font . ';font-size:14px;color:' . $c['grau'] . ';">Noch keine Daten in diesem Monat</td></tr>';
    };

    $h = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="supported-color-schemes" content="dark">'
       . '<title>' . e('RENOVA Monatsbericht ' . $d['monat']) . '</title></head>'
       . '<body style="margin:0;padding:0;' . $verlauf . '">'
       . '<div style="display:none;max-height:0;overflow:hidden;">' . e(zahl($k['besuche']) . ' Besuche, ' . zahl($k['anfragen']) . ' Anfragen im ' . $d['monat']) . '</div>'
       . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $c['bg'] . '" style="' . $verlauf . '"><tr><td align="center" style="padding:32px 12px;">'
       . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">'
       // Kopf
       . '<tr><td style="padding:28px 36px 0;"><img src="https://www.renovatirol.at/assets/logo/renova-mail-logo.png" width="190" alt="RENOVA Immobilien Tirol" style="display:block;width:190px;height:auto;border:0;"></td></tr>'
       . '<tr><td style="padding:56px 36px 0;font-family:' . $font . ';font-size:11px;letter-spacing:3px;text-transform:uppercase;color:' . $c['grau'] . ';">Monatsbericht Website</td></tr>'
       . '<tr><td style="padding:10px 36px 0;font-family:' . $font . ';font-size:40px;font-weight:200;color:' . $c['hell'] . ';line-height:1.15;">' . e($d['monat']) . '</td></tr>'
       . '<tr><td style="padding:14px 36px 0;font-family:' . $font . ';font-size:14px;line-height:1.7;color:' . $c['grau'] . ';">' . e($d['satz']) . '</td></tr>'
       // Kennzahlen
       . '<tr><td style="padding:36px 36px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed;"><tr>'
       . $kachel('Besuche', zahl($k['besuche']), veraenderung($k['besuche'], $k['besuche_vm']))
       . $kachel('Besucher', zahl($k['besucher']), veraenderung($k['besucher'], $k['besucher_vm']))
       . $kachel('Aufrufe', zahl($k['aufrufe']), veraenderung($k['aufrufe'], $k['aufrufe_vm']))
       . '</tr><tr>'
       . $kachel('Anfragen', zahl($k['anfragen']), veraenderung($k['anfragen'], $k['anfragen_vm']))
       . $kachel('Kontakte', zahl($k['kontakt']), veraenderung($k['kontakt'], $k['kontakt_vm']))
       . $kachel('ImmoScout', zahl($k['immoscout']), veraenderung($k['immoscout'], $k['immoscout_vm']))
       . '</tr></table></td></tr>'
       . '<tr><td style="padding:6px 36px 0;font-family:' . $font . ';font-size:12px;line-height:1.7;color:' . $c['grau'] . ';">Prozentwerte im Vergleich zum Vormonat · Kontakte sind Klicks auf Anrufen und E-Mail · ImmoScout zählt Klicks zum Inserat · Verweildauer im Schnitt ' . e(dauer($k['dauer'])) . ' · ' . e(str_replace('.', ',', (string)round((float)$k['seiten_je_besuch'], 1))) . ' Seiten je Besuch</td></tr>'
       // Objekte und Seiten
       . $abschnitt('Meistbesuchte Seiten') . $liste($d['seiten'])
       // Herkunft
       . $abschnitt('Woher die Besucher kommen') . $balken($d['kanaele'], ' %')
       . ($d['quellen'] ? '<tr><td style="padding:6px 36px 0;font-family:' . $font . ';font-size:12px;line-height:1.7;color:' . $c['grau'] . ';">Wichtigste Quellen ' . e(implode(' · ', $d['quellen'])) . '</td></tr>' : '')
       // Anfragen je Objekt
       . ($d['anfragen_objekte'] ? $abschnitt('Anfragen je Objekt') . $liste($d['anfragen_objekte']) : '')
       // Geräte und Regionen
       . $abschnitt('Geräte') . $balken($d['geraete'], ' %')
       . $abschnitt('Regionen') . $liste($d['regionen'])
       // Fuß
       . '<tr><td style="padding:52px 36px 0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="border:1px solid ' . $c['silber'] . ';">'
       . '<a href="https://www.renovatirol.at/statistik/" style="display:inline-block;padding:14px 26px;font-family:' . $font . ';font-size:11px;letter-spacing:2.6px;text-transform:uppercase;color:' . $c['hell'] . ';text-decoration:none;">Volle Statistik öffnen</a></td></tr></table></td></tr>'
       . '<tr><td style="padding:52px 36px 0;border-bottom:1px solid ' . $c['linie'] . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
       . '<tr><td style="padding:22px 36px 40px;font-family:' . $font . ';font-size:11px;line-height:1.8;color:' . $c['grau'] . ';">RENOVA Immobilien Tirol GmbH · <a href="https://www.renovatirol.at" style="color:' . $c['grau'] . ';text-decoration:none;">renovatirol.at</a><br>'
       . 'Automatischer Bericht aus der eigenen Besucherstatistik, ohne Cookies und anonymisiert.<br>Website © archivisu</td></tr>'
       . '</table></td></tr></table></body></html>';
    return $h;
}

function bericht_text(array $d): string {
    $k = $d['kennzahlen'];
    $t = "RENOVA MONATSBERICHT WEBSITE\n" . $d['monat'] . "\n\n" . $d['satz'] . "\n\n";
    $t .= 'Besuche ' . zahl($k['besuche']) . ' (' . veraenderung($k['besuche'], $k['besuche_vm']) . ")\n";
    $t .= 'Besucher ' . zahl($k['besucher']) . "\nSeitenaufrufe " . zahl($k['aufrufe']) . "\n";
    $t .= 'Anfragen ' . zahl($k['anfragen']) . "\nAnrufe und E-Mails " . zahl($k['kontakt']) . "\nZu ImmoScout " . zahl($k['immoscout']) . "\n\n";
    $t .= "MEISTBESUCHTE SEITEN\n"; foreach ($d['seiten'] as $z) $t .= '  ' . $z[0] . '  ' . $z[1] . "\n";
    $t .= "\nHERKUNFT\n"; foreach ($d['kanaele'] as $z) $t .= '  ' . $z[0] . '  ' . $z[1] . " %\n";
    $t .= "\nVolle Statistik https://www.renovatirol.at/statistik/\n";
    return $t;
}

// ---------- Daten aus Matomo ----------
function bericht_daten(callable $api, string $monatErster): array {
    $vm = date('Y-m-01', strtotime($monatErster . ' -1 month'));
    $p = fn(string $datum, array $x = []) => array_merge(['idSite' => BERICHT_SITE, 'period' => 'month', 'date' => $datum], $x);
    $vs = $api('VisitsSummary.get', $p($monatErster)); $vsv = $api('VisitsSummary.get', $p($vm));
    $ev = function (string $datum) use ($api, $p) {
        $r = ['anfragen' => 0, 'kontakt' => 0, 'immoscout' => 0, 'objekte' => []];
        foreach ($api('Events.getCategory', $p($datum, ['expanded' => 1, 'secondaryDimension' => 'eventAction'])) as $kat) {
            foreach (($kat['subtable'] ?? []) as $a) {
                $n = (int)($a['nb_events'] ?? 0);
                if ($a['label'] === 'Gesendet') $r['anfragen'] += $n;
                if ($kat['label'] === 'Kontakt' && in_array($a['label'], ['Anrufen', 'E-Mail'], true)) $r['kontakt'] += $n;
                if ($a['label'] === 'Zu ImmoScout') $r['immoscout'] += $n;
            }
        }
        foreach ($api('Events.getName', $p($datum, ['flat' => 1, 'secondaryDimension' => 'eventAction', 'filter_limit' => 50])) as $z) {
            if (strpos((string)$z['label'], 'Gesendet') !== false && strpos((string)$z['label'], 'Tirol') === false) {
                $name = trim(preg_replace('/\s*-\s*Gesendet$/', '', (string)$z['label']));
                $r['objekte'][$name] = ($r['objekte'][$name] ?? 0) + (int)$z['nb_events'];
            }
        }
        return $r;
    };
    $e = $ev($monatErster); $ev_vm = $ev($vm);
    $seiten = [];
    foreach ($api('Actions.getPageTitles', $p($monatErster, ['flat' => 1, 'filter_limit' => 7, 'filter_sort_column' => 'nb_hits'])) as $z) {
        $t = trim(explode(' | ', (string)$z['label'])[0]); if ($t === '') $t = 'Startseite';
        $seiten[] = [$t, zahl($z['nb_hits']) . ' Aufrufe'];
    }
    $namen = ['direct' => 'Direkt aufgerufen', 'search' => 'Suchmaschinen', 'website' => 'Andere Websites', 'social' => 'Soziale Netzwerke', 'campaign' => 'Kampagnen'];
    $kan = []; $summe = 0;
    foreach ($api('Referrers.getReferrerType', $p($monatErster)) as $z) {
        $typ = preg_replace('/^referrerType==/', '', (string)($z['segment'] ?? ''));
        $kan[] = [$namen[$typ] ?? (string)$z['label'], (int)$z['nb_visits']]; $summe += (int)$z['nb_visits'];
    }
    $kan = array_map(fn($z) => [$z[0], $summe ? round($z[1] / $summe * 100) : 0], $kan);
    usort($kan, fn($a, $b) => $b[1] <=> $a[1]);
    $quellen = array_slice(array_map(fn($z) => (string)$z['label'], $api('Referrers.getAll', $p($monatErster, ['filter_limit' => 5]))), 0, 5);
    $ger = []; $gs = 0; $gn = ['Desktop' => 'Computer', 'Smartphone' => 'Handy', 'Tablet' => 'Tablet', 'Phablet' => 'Handy'];
    foreach ($api('DevicesDetection.getType', $p($monatErster)) as $z) { $n = $gn[$z['label']] ?? 'Andere'; $ger[$n] = ($ger[$n] ?? 0) + (int)$z['nb_visits']; $gs += (int)$z['nb_visits']; }
    arsort($ger); $ger = array_map(fn($n, $v) => [$n, $gs ? round($v / $gs * 100) : 0], array_keys($ger), $ger);
    $reg = [];
    foreach ($api('UserCountry.getRegion', $p($monatErster, ['filter_limit' => 5])) as $z) $reg[] = [trim(explode(',', (string)$z['label'])[0]), zahl($z['nb_visits']) . ' Besuche'];
    arsort($e['objekte']); $obj = array_map(fn($n, $v) => [$n, zahl($v) . ($v == 1 ? ' Anfrage' : ' Anfragen')], array_keys($e['objekte']), $e['objekte']);
    $monat = MONATE[(int)date('n', strtotime($monatErster))] . ' ' . date('Y', strtotime($monatErster));
    $k = [
        'besuche' => $vs['nb_visits'] ?? 0, 'besuche_vm' => $vsv['nb_visits'] ?? 0,
        'besucher' => $vs['nb_uniq_visitors'] ?? ($vs['nb_users'] ?? 0), 'besucher_vm' => $vsv['nb_uniq_visitors'] ?? 0,
        'aufrufe' => $vs['nb_actions'] ?? 0, 'aufrufe_vm' => $vsv['nb_actions'] ?? 0,
        'anfragen' => $e['anfragen'], 'anfragen_vm' => $ev_vm['anfragen'],
        'kontakt' => $e['kontakt'], 'kontakt_vm' => $ev_vm['kontakt'],
        'immoscout' => $e['immoscout'], 'immoscout_vm' => $ev_vm['immoscout'],
        'dauer' => $vs['avg_time_on_site'] ?? 0, 'seiten_je_besuch' => $vs['nb_actions_per_visit'] ?? 0,
    ];
    $satz = zahl($k['besuche']) . ' Besuche und ' . zahl($k['anfragen']) . ($k['anfragen'] == 1 ? ' Anfrage' : ' Anfragen') . ' über die Website im ' . $monat . '.';
    return ['monat' => $monat, 'satz' => $satz, 'kennzahlen' => $k, 'seiten' => $seiten, 'kanaele' => $kan, 'quellen' => $quellen,
            'anfragen_objekte' => array_slice($obj, 0, 6), 'geraete' => $ger, 'regionen' => $reg];
}

// ---------- Versand über den lokalen Mailserver ----------
function bericht_senden(string $betreff, string $html, string $text, string &$fehler, array $an_liste = BERICHT_AN): bool {
    $fp = @stream_socket_client('tcp://127.0.0.1:25', $en, $es, 8);
    if (!$fp) { $fehler = 'SMTP ' . $es; return false; }
    stream_set_timeout($fp, 20);
    $lies = function () use ($fp) { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, string $erw) use ($fp, $lies, &$fehler) { if ($c !== '') fwrite($fp, $c . "\r\n"); $r = $lies(); if (strpos($r, $erw) !== 0) { $fehler = trim($r); return false; } return true; };
    $grenze = 'renova' . bin2hex(random_bytes(8));
    $kopf = 'From: =?UTF-8?B?' . base64_encode('RENOVA Statistik') . '?= <' . BERICHT_VON . ">\r\nTo: " . $an_liste[0] . "\r\nSubject: =?UTF-8?B?" . base64_encode($betreff) . "?=\r\n"
          . 'Date: ' . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@renovatirol.at>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$grenze\"\r\n\r\n";
    $teil = fn(string $typ, string $inhalt) => "--$grenze\r\nContent-Type: $typ; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($inhalt)) . "\r\n";
    $daten = $kopf . $teil('text/plain', $text) . $teil('text/html', $html) . "--$grenze--\r\n.";
    $ok = $cmd('', '220') && $cmd('EHLO www.renovatirol.at', '250') && $cmd('MAIL FROM:<' . BERICHT_VON . '>', '250');
    foreach ($an_liste as $an) $ok = $ok && $cmd('RCPT TO:<' . $an . '>', '25');
    $ok = $ok && $cmd('DATA', '354') && $cmd($daten, '250');
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return $ok;
}

// ---------- Ablauf auf dem Server ----------
if (PHP_SAPI === 'cli' && getenv('RENOVA_LOKAL')) return;   // lokal nur Funktionen laden
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Europe/Vienna');
$test = isset($_GET['test']);   // Testversand nur an statistik@ (Weiterleitung an info@cubarc.at), aktueller Monat, höchstens einmal pro Stunde
if ($test) {
    $testsperre = __DIR__ . '/statistik/tmp/renova-monatsbericht-test-' . date('Y-m-d') . '.gesendet';
    if (is_file($testsperre)) { echo "Test heute schon gesendet\n"; exit; }
}
if ((int)date('j') > 5 && !isset($_GET['pruefen']) && !$test) { echo "nichts zu tun\n"; exit; }
$monatErster = $test ? date('Y-m-01') : date('Y-m-01', strtotime('first day of last month'));
$sperre = __DIR__ . '/statistik/tmp/renova-monatsbericht-' . substr($monatErster, 0, 7) . '.gesendet';
if (is_file($sperre) && !isset($_GET['pruefen']) && !$test) { echo "schon gesendet\n"; exit; }

define('PIWIK_INCLUDE_PATH', __DIR__ . '/statistik');
define('PIWIK_USER_PATH', PIWIK_INCLUDE_PATH);
define('PIWIK_ENABLE_DISPATCH', false);
define('PIWIK_ENABLE_ERROR_HANDLER', false);
define('PIWIK_ENABLE_SESSION_START', false);
require_once PIWIK_INCLUDE_PATH . '/index.php';
require_once PIWIK_INCLUDE_PATH . '/core/API/Request.php';
$umgebung = new \Piwik\Application\Environment(null);
$umgebung->init();
\Piwik\FrontController::getInstance()->init();   // lädt die Plugins (API, Renderer)
\Piwik\Access::getInstance();
try { \Piwik\Container\StaticContainer::get('Piwik\Translation\Translator')->setCurrentLanguage('de'); } catch (\Throwable $t) {}

$api = function (string $methode, array $param) {
    return \Piwik\Access::doAsSuperUser(function () use ($methode, $param) {
        $r = \Piwik\API\Request::processRequest($methode, array_merge($param, ['format' => 'json', 'language' => 'de']));
        $j = json_decode((string)$r, true);
        return is_array($j) ? $j : [];
    });
};
try {
    $d = bericht_daten($api, $monatErster);
} catch (\Throwable $t) { error_log('RENOVA Monatsbericht Daten: ' . $t->getMessage()); http_response_code(500); echo "Fehler\n"; if (isset($_GET['pruefen'])) echo get_class($t) . ' ' . mb_substr($t->getMessage(), 0, 300) . ' @' . basename($t->getFile()) . ':' . $t->getLine() . "\n"; exit; }
if (isset($_GET['pruefen'])) { echo 'Daten ok, ' . count($d['kennzahlen']) . " Kennzahlen, nichts gesendet\n"; exit; }
if ($test) {
    @file_put_contents($testsperre, date('c')); $fehler = '';
    $ok = bericht_senden('TEST · RENOVA Website · Monatsbericht ' . $d['monat'], bericht_html($d), bericht_text($d), $fehler, ['statistik@renovatirol.at']);
    if (!$ok) { @unlink($testsperre); error_log('RENOVA Monatsbericht Test: ' . $fehler); http_response_code(500); echo "Fehler\n"; exit; }
    echo "Test gesendet\n"; exit;
}
if ((int)$d['kennzahlen']['besuche'] === 0) { @file_put_contents($sperre, 'keine Besuche ' . date('c')); echo "keine Daten, nichts gesendet\n"; exit; }
$fehler = '';
if (!@file_put_contents($sperre, date('c'))) { error_log('RENOVA Monatsbericht: Sperrdatei nicht schreibbar'); http_response_code(500); echo "Fehler\n"; exit; }
if (!bericht_senden('RENOVA Website · Monatsbericht ' . $d['monat'], bericht_html($d), bericht_text($d), $fehler)) {
    @unlink($sperre); error_log('RENOVA Monatsbericht Versand: ' . $fehler); http_response_code(500); echo "Fehler\n"; exit;
}
echo "gesendet\n";
