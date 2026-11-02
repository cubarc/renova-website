<?php
// Einmalige Einrichtung: lädt Matomo nach /statistik und löscht sich danach selbst.
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
if (($_GET['t'] ?? '') !== 'de64e7345732bda2e504e834') { http_response_code(404); exit; }
@set_time_limit(300);
$ziel = __DIR__ . '/statistik';
if (is_file($ziel . '/index.php')) { echo "Matomo ist schon da\n"; @unlink(__FILE__); exit; }
$zip = sys_get_temp_dir() . '/matomo-latest.zip';
$url = 'https://builds.matomo.org/matomo-latest.zip';
if (function_exists('curl_init')) {
  $fh = fopen($zip, 'w'); $c = curl_init($url);
  curl_setopt_array($c, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 240]);
  $ok = curl_exec($c); $err = curl_error($c); curl_close($c); fclose($fh);
  if (!$ok) { echo "Download fehlgeschlagen: $err\n"; exit; }
} else { if (!@copy($url, $zip)) { echo "Download fehlgeschlagen (copy)\n"; exit; } }
echo 'Geladen: ' . round(filesize($zip) / 1048576, 1) . " MB\n";
$z = new ZipArchive();
if ($z->open($zip) !== true) { echo "ZIP lässt sich nicht öffnen\n"; exit; }
$tmp = __DIR__ . '/_matomo_tmp';
$z->extractTo($tmp); $z->close(); @unlink($zip);
if (!rename($tmp . '/matomo', $ziel)) { echo "Verschieben fehlgeschlagen\n"; exit; }
@rmdir($tmp); @unlink(__DIR__ . '/How to install Matomo.html');
foreach (glob($tmp . '/*') ?: [] as $f) @unlink($f); @rmdir($tmp);
echo "Matomo entpackt nach /statistik\n";
@unlink(__FILE__);
echo "Einrichtungsskript gelöscht\n";
