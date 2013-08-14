<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2013 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ALL | E_STRICT);

$filePrefix = 'paolo-chess-games';
$fileSuffix = '.pgn';

$allGames = '';
$lastModifiedSet = FALSE;
$lastModified;

function loadFile($fileName) {
  global $allGames, $lastModifiedSet, $lastModified;
  if ($newGames = @file_get_contents($fileName)) {
    $allGames = $newGames . $allGames;
    $newModified = @filemtime($fileName);
    if ((!$lastModifiedSet) || ($newModified > $lastModified)) {
      $lastModified = $newModified;
      $lastModifiedSet = TRUE;
    }
    return TRUE;
  } else { return FALSE; }
}

function get_param($param, $shortParam, $default) {
  if (isset($_REQUEST[$param])) { return $_REQUEST[$param]; }
  if (isset($_REQUEST[$shortParam])) { return $_REQUEST[$shortParam]; }
  return $default;
}

$fileIndexNum = ord(get_param("firstFile", "ff", "a"));
while(loadFile($filePrefix . '-' . chr($fileIndexNum) . $fileSuffix)) { $fileIndexNum++; }
loadFile($filePrefix . $fileSuffix);

if ($allGames == '') { $allGames = '% no games found for ' . $filePrefix . '-all' . $fileSuffix . "\n"; }

if (isset($headers['If-Modified-Since']) && $lastModifiedSet && (strtotime($headers['If-Modified-Since']) == $lastModified)) {
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT', true, 304);
} else {
  header('Content-Description: File Transfer');
  header('Content-Type: application/x-chess-pgn');
  header('Content-Disposition: attachment; filename=' . $filePrefix . '-all' . $fileSuffix);
  if ($lastModifiedSet) { header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT'); }
  print $allGames;
}

?>
