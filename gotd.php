<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);


function get_param($param, $shortParam, $default) {
  $out = $_REQUEST[$param];
  if ($out != "") { return $out; }
  $out = $_REQUEST[$shortParam];
  if ($out != "") { return $out; }
  return $default;
}


$pgnData = get_param("pgnData", "pd", "gotd.pgn");

function get_pgnText($pgnUrl) {
  if (strpos($pgnUrl, ":") || (strpos($pgnUrl, "%3A"))) { return "[Event \"error: invalid pgnData=$pgnUrl\"]\n"; }
  $fileLimitBytes = 10000000; // 10Mb
  $pgnText = file_get_contents($pgnUrl, NULL, NULL, 0, $fileLimitBytes + 1);
  if (!$pgnText) { return "[Event \"error: failed to get pgnData=$pgnUrl\"]\n"; }
  return $pgnText;
}

$pgnText = get_pgnText($pgnData);

$numGames = preg_match_all("/(\s*\[\s*(\w+)\s*\"([^\"]*)\"\s*\]\s*)+[^\[]*/", $pgnText, $games );


$gameNum = get_param("gameNum", "gn", "");

$expiresDate = "";
if ($gameNum == "random") { $gameNum = rand(1, $numGames); }
else if (!preg_match("/^\d+$/", $gameNum)) {
  $timeNow = time();
  $expiresDate = gmdate("D, d M Y H:i:s", (floor($timeNow / (60 * 60 * 24)) + 1) * (60 * 60 * 24)) . " GMT";
  if (!preg_match("/^[ +-]\d+$/", $gameNum)) { $gameNum = 0; } // space is needed since + is urldecoded as space
  $gameNum = floor(($gameNum + ($timeNow / (60 * 60 * 24))) % $numGames) + 1;
}
else if ($gameNum < 1) { $gameNum = 1; }
else if ($gameNum > $numGames) { $gameNum = $numGames; }
$gameNum -= 1;


if ($expiresDate) {
  header("Expires: " . $expiresDate);
}
print $games[0][$gameNum];

?>
