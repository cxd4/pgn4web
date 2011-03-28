<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2011 Paolo Casaschi
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

$pgnData = get_param("pgnData", "pd", "tactics.pgn");
$gameNum = get_param("gameNum", "gn", "");
$lightColorHex = get_param("lightColorHex", "lch", "EFF4EC"); // FFCC99
$darkColorHex = get_param("darkColorHex", "dch", "C6CEC3"); // CC9966
$controlBackgroundColorHex = get_param("controlBackgroundColorHex", "cbch", "EFF4EC"); // FFCC99
$controlTextColorHex = get_param("controlTextColorHex", "ctch", "888888"); // 663300

function get_pgnText($pgnUrl) {
  $fileLimitBytes = 10000000; // 10Mb
  $pgnText = file_get_contents($pgnUrl, NULL, NULL, 0, $fileLimitBytes + 1);
  return $pgnText;
}

$pgnText = get_pgnText($pgnData);

$numGames = preg_match_all("/(\s*\[\s*(\w+)\s*\"([^\"]*)\"\s*\]\s*)+[^\[]*/", $pgnText, $games );

if ($gameNum == "random") { $gameNum = rand(1, $numGames); }
else if (! is_numeric($gameNum)) { $gameNum = ceil((time() / (60 * 60 * 24)) / $numGames); }
else if ($gameNum < 1) { $gameNum = 1; }
else if ($gameNum > $numGames) { $gameNum = $numGames; }
$gameNum -= 1;

$pgnGame = $games[0][$gameNum];

function curPageURL() {
  $pageURL = 'http';
  if ($_SERVER["HTTPS"] == "on") { $pageURL .= "s"; }
  $pageURL .= "://";
  if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
  } else {
    $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
  }
  return $pageURL;
}
$thisPage = curPageURL();

print <<<END

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">

<title>pgn4web puzzle of the day</title>

<style type="text/css">

html, 
body { 
  margin: 0; 
  padding: 0; 
}

body {
  background: white;
  background: transparent;
}

.boardTable {
  width: 240px;
  height: 240px;
  border-width: 0;
}

.pieceImage {
}

.whiteSquare,
.blackSquare,
.highlightWhiteSquare,
.highlightBlackSquare {
  width: 30;
  height: 30;
  border-style: solid;
  border-width: 1;
}

.whiteSquare,
.highlightWhiteSquare {
  border-color: #$lightColorHex;
  background: #$lightColorHex;
}

.blackSquare,
.highlightBlackSquare {
  border-color: #$darkColorHex;
  background: #$darkColorHex;
}

.highlightWhiteSquare,
.highlightBlackSquare {
  border-style: inset;
}

.newButton {
  font-size: 12px;
  font-weight: bold;
  color: #$controlTextColorHex;
  width: 120px;
  height: 30px;
  background-color: #$controlBackgroundColorHex;
  border-style:none;
  display: inline;
}

</style>

<link rel="shortcut icon" href="pawn.ico" />

<script src="pgn4web.js" type="text/javascript"></script>
<script type="text/javascript">
SetImagePath("uscf/22"); // use "" path if images are in the same folder as this javascript file
SetShortcutKeysEnabled(false);

clearShortcutSquares("BCDEFGH", "7");
clearShortcutSquares("ABCDEFGH", "123456");

function customFunctionOnMove() {
  switch (CurrentPly) {
    case StartPly:
      document.getElementById("leftButton").value = (CurrentPly % 2) ? "black to move" : "white to move";
      document.getElementById("leftButton").title = ((CurrentPly % 2) ? "black to move" : "white to move") + ": find the best continuation";
      document.getElementById("rightButton").value = "solution";
      document.getElementById("rightButton").title = "show the puzzle solution on the chessboard step by step";
      break;
    case StartPly+PlyNumber:
      document.getElementById("leftButton").value = "back";
      document.getElementById("leftButton").title = "move one step backwards";
      switch (gameResult[currentGame]) {
        case "1-0":
          outcome = "white wins";
          break;
        case "0-1":
          outcome = "black wins";
          break;
        case "1/2-1/2":
          outcome = "draw game";
          break;
        default:
          outcome = "";
          break;
      }
      document.getElementById("rightButton").value = outcome;
      document.getElementById("rightButton").title = "final position" + (outcome ? ": " + outcome : "");
      break;
    default:
      document.getElementById("leftButton").value = "back";
      document.getElementById("leftButton").title = "move one step backwards";
      document.getElementById("rightButton").value = "continue";
      document.getElementById("rightButton").title = "continue showing the puzzle solution on the chessboard step by step";
      break;
  }
}

function leftButtonAction() {
  MoveBackward(1);
}

function rightButtonAction() {
  MoveForward(1);
}

</script>		  

</head>

<body>

<!-- paste your PGN below and make sure you dont specify an external source with SetPgnUrl() -->
<form style="display: none;"><textarea style="display: none;" id="pgnText">

$pgnGame

{

pgn4web puzzle of the day, updated at 00:00 GMT

you can add the pgn4web puzzle of the day to your site with the following HTML code:

<iframe height='270' width='240' frameborder='0' scrolling='no' marginheight='0' marginwidth='0' src='$thisPage'>
iframe support required to display the pgn4web puzzle of the day
</iframe>

the following URL parameters allow customization of the pgn4web puzzle of the day:
- pgnData=... selects the PGN file containing the puzzles, default: tactics.pgn
- gameNum=... sets the game number for the puzzle to be shown, default: blank, showing the puzzle of the day
- lightColorHex=... sets the light squares color, in hexadecimal format, default: EFF4EC
- darkColorHex=... sets the dark squares color, in hexadecimal format, default: C6CEC3
- controlBackgroundColorHex=... sets the buttons background color, in hexadecimal format, default: EFF4EC
- controlTextColorHex=... sets the buttons text color, in hexadecimal format, default: 888888

}

</textarea></form>
<!-- paste your PGN above and make sure you dont specify an external source with SetPgnUrl() -->

<center>

<div style="display: inline" id="GameBoard"></div>

<form style="display: inline">
<input id="leftButton" type="button" value="left button" title="" class="newButton" onClick="javascript:leftButtonAction();" onFocus="this.blur()"><input id="rightButton" type="button" value="right button" title="" class="newButton" onClick="javascript:rightButtonAction();" onFocus="this.blur()">
</form>

</table>
</center>

</body>
</html>

</html>
END;
?>
