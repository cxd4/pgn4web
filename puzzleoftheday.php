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

$squareSize = get_param("squareSize", "ss", "30");
if ($squareSize < 22) { $squareSize = 22; }
$squareSizeCss = $squareSize . "px";

$borderSize = ceil($squareSize / 50);
$borderSizeCss = $borderSize . "px";

function defaultPieceSize($ss) {
  $pieceSizeOptions = array(20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 52, 56, 60, 64, 72, 80, 88, 96, 112, 128, 144, 300);
  $targetPieceSize = floor(0.8 * $ss);
  for ($ii=count($pieceSizeOptions)-1; $ii>=0; $ii--) {
	  if ($pieceSizeOptions[$ii] <= $targetPieceSize) { return $pieceSizeOptions[$ii]; }
  }
  return $pieceSizeOptions[0];
}
$pieceSize = defaultPieceSize($squareSize - 2 * $borderSize);
$pieceSizeCss = $pieceSize . "px";

$pieceFont = get_param("pieceFont", "pf", "default");
if ($pieceFont == "a") { $pieceFont = "alpha"; }
if ($pieceFont == "m") { $pieceFont = "merida"; }
if ($pieceFont == "u") { $pieceFont = "uscf"; }
if (($pieceFont == "default") || ($pieceFont == "d")) {
  if ($pieceSize < 28) { $pieceFont = "uscf"; }
  else { 
    if ($pieceSize > 39) { $pieceFont = "merida"; }
    else { $pieceFont = "alpha"; }
  }
}

$boardSize = $squareSize * 8;
$boardSizeCss = $boardSize . "px";

$buttonHeight = $squareSize;
$buttonHeightCss = $buttonHeight . "px";
$buttonWidth = $squareSize * 4;
$buttonWidthCss = $buttonWidth . "px";
$buttonFontSize = floor($squareSize / 2.5);
$buttonFontSizeCss = $buttonFontSize . "px";

$frameBorderColorHex = get_param("frameBorderColorHex", "fbch", "A4A4A4");
if ($frameBorderColorHex == "none") { 
  $frameBorderColorHex = false; 
  $frameBorderWidth = 0;
} else {
  $frameBorderWidth = ceil($squareSize / 50);
}
$frameBorderWidthCss = $frameBorderWidth . "px";

$frameWidth = $boardSize;
$frameWidthCss = $frameWidth . "px";
$frameHeight = $boardSize + $buttonHeight;
$frameHeightCss = $frameHeight . "px";

$outerFrameWidth = $frameWidth + 2 * $frameBorderWidth;
$outerFrameHeight = $frameHeight + 2 * $frameBorderWidth;

function get_pgnText($pgnUrl) {
  $fileLimitBytes = 10000000; // 10Mb
  $pgnText = file_get_contents($pgnUrl, NULL, NULL, 0, $fileLimitBytes + 1);
  return $pgnText;
}

$pgnText = get_pgnText($pgnData);

$numGames = preg_match_all("/(\s*\[\s*(\w+)\s*\"([^\"]*)\"\s*\]\s*)+[^\[]*/", $pgnText, $games );

if ($gameNum == "random") { $gameNum = rand(1, $numGames); }
else if (! is_numeric($gameNum)) { $gameNum = ceil((time() / (60 * 60 * 24)) % $numGames); }
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

.container {
  width: $frameWidthCss;
  height: $frameHeightCss;
  border-color: #$frameBorderColorHex;
  border-style: outset;
  border-width: $frameBorderWidthCss;
}

.boardTable {
  width: $boardSizeCss;
  height: $boardSizeCss;
  border-width: 0;
}

.pieceImage {
}

.whiteSquare,
.blackSquare,
.highlightWhiteSquare,
.highlightBlackSquare {
  width: $squareSizeCss;
  height: $squareSizeCss;
  border-style: solid;
  border-width: $borderSizeCss;
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
  font-size: $buttonFontSizeCss;
  font-weight: bold;
  color: #$controlTextColorHex;
  width: $buttonWidthCss;
  height: $buttonHeightCss;
  background-color: #$controlBackgroundColorHex;
  border-style:none;
}

</style>

<link rel="shortcut icon" href="pawn.ico" />

<script src="pgn4web.js" type="text/javascript"></script>
<script type="text/javascript">
SetImagePath("$pieceFont/$pieceSize");
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

<!-- start of google analytics code -->

<!-- end of google analytics code -->

</head>

<body>

<!-- paste your PGN below and make sure you dont specify an external source with SetPgnUrl() -->
<form style="display: none;"><textarea style="display: none;" id="pgnText">

$pgnGame

{

pgn4web puzzle of the day, updated at 00:00 GMT

you can add the pgn4web puzzle of the day to your site with the following HTML code:

<iframe height='$outerFrameHeight' width='$outerFrameWidth' frameborder='0' scrolling='no' marginheight='0' marginwidth='0' src='$thisPage'>
iframe support required to display the pgn4web puzzle of the day
</iframe>

the following URL parameters allow customization of the pgn4web puzzle of the day:
- pgnData=... selects the PGN file containing the puzzles, default: tactics.pgn
- gameNum=... sets the game number for the puzzle to be shown, default: blank, showing the puzzle of the day
- squareSize=... sets the chessboard square size, default 30
- lightColorHex=... sets the light squares color, in hexadecimal format, default: EFF4EC
- darkColorHex=... sets the dark squares color, in hexadecimal format, default: C6CEC3
- pieceFont=... sets the piece font type, either alpha, merida, uscf or default, default: default
- controlBackgroundColorHex=... sets the buttons background color, in hexadecimal format, default: EFF4EC
- controlTextColorHex=... sets the buttons text color, in hexadecimal format, default: 888888
- frameBorderColorHex=... sets the frame border color, in hexadecimal format, or none, default: A4A4A4

}

</textarea></form>
<!-- paste your PGN above and make sure you dont specify an external source with SetPgnUrl() -->

<center><div class="container">
<form style="display: inline">
<table height="$frameHeight" width="$frameWidth" border="0" cellspacing="0" cellpadding="0"><tr><td colspan="2">
<div style="display: inline" id="GameBoard"></div>
</td></tr><tr><td>
<input id="leftButton" type="button" value="" title="" class="newButton" onClick="javascript:leftButtonAction();" onFocus="this.blur()">
</td><td>
<input id="rightButton" type="button" value="" title="" class="newButton" onClick="javascript:rightButtonAction();" onFocus="this.blur()">
</td></tr></table>
</form>

</div></center>

</body>

</html>
END;
?>
