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

function get_pgnText($pgnUrl) {
  $fileLimitBytes = 10000000; // 10Mb
  $pgnText = file_get_contents($pgnUrl, NULL, NULL, 0, $fileLimitBytes + 1);
  return $pgnText;
}

$pgnText = get_pgnText($pgnData);

// for simplicity, remove all comments from the game text 
// to avoid spurious [ in comments breaking the regular expression 
// splitting the PGN data into games
$pgnText = preg_replace("/{[^}]*}/", "", $pgnText);
$pgnText = preg_replace("/;[^\n$]*/", "", $pgnText);
$pgnText = preg_replace("/(\n|^)%[^\n$]*/", "", $pgnText);

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
$gameNumDisplay = ($gameNum ^ $numGames) . " + \"/\" + " . $numGames;
$gameNum -= 1;

$pgnGame = $games[0][$gameNum];


$lightColorHex = get_param("lightColorHex", "lch", "EFF4EC"); // FFCC99
$lightColorHexCss = "#" . $lightColorHex;
$darkColorHex = get_param("darkColorHex", "dch", "C6CEC3"); // CC9966
$darkColorHexCss = "#" . $darkColorHex;

$controlBackgroundColorHex = get_param("controlBackgroundColorHex", "cbch", "EFF4EC"); // FFCC99
$controlBackgroundColorHexCss = "#" . $controlBackgroundColorHex;
$controlTextColorHex = get_param("controlTextColorHex", "ctch", "888888"); // 663300
$controlTextColorHexCss = "#" . $controlTextColorHex;


$squareSize = get_param("squareSize", "ss", "30");
if ($squareSize < 20) { $squareSize = 20; }
$squareSizeCss = $squareSize . "px";
if ($squareSize < 30) {
  $borderSize = 0;
  $borderSizeCss = $borderSize;
} else {
  $borderSize = ceil($squareSize / 50);
  $borderSizeCss = $borderSize . "px";
}

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
if (($pieceFont == "random") || ($pieceFont == "r")) {
  $randomPiece = rand(0, 2);
  switch ($randomPiece) {
    case 1: $pieceFont = "alpha"; break;
    case 2: $pieceFont = "merida"; break;
    default: $pieceFont = "uscf"; break;
  }
}
if (($pieceFont == "hash") || ($pieceFont == "h")) {
//  $hashPiece = strlen($pgnGame) % 3;
  $hashPiece = $gameNum % 3;
  switch ($hashPiece) {
    case 1: $pieceFont = "alpha"; break;
    case 2: $pieceFont = "merida"; break;
    default: $pieceFont = "uscf"; break;
  }
}
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
if ($buttonFontSize < 10) { $buttonFontSize = 10; }
$buttonFontSizeCss = $buttonFontSize . "px";
$buttonPadding = floor($squareSize / 10);
$buttonPaddingCss = $buttonPadding . "px";


$sidetomoveBorder = floor($buttonFontSize / 18) + 1;
$sidetomoveBorderCss = $sidetomoveBorder . "px";
$sidetomoveHeight = $buttonFontSize - 2 * $sidetomoveBorder;
$sidetomoveHeightCss = $sidetomoveHeight . "px";
$sidetomoveWidth = $sidetomoveHeight;
$sidetomoveWidthCss = $sidetomoveWidth . "px";


$frameBorderColorHex = get_param("frameBorderColorHex", "fbch", "A4A4A4");
if ($frameBorderColorHex == "none") { 
  $frameBorderColorHex = "000000"; 
  $frameBorderWidth = 0;
} else {
  $frameBorderWidth = ceil($squareSize / 50);
}
$frameBorderColorHexCss = "#" . $frameBorderColorHex;
$frameBorderWidthCss = $frameBorderWidth . "px";

$frameWidth = $boardSize;
$frameWidthCss = $frameWidth . "px";
$frameHeight = $boardSize + $buttonHeight;
$frameHeightCss = $frameHeight . "px";


// undocumented features

$backgroundColorHex = get_param("backgroundColorHex", "bch", "transparent");
if (preg_match("/^[0123456789ABCDEF]{6}$/i", $backgroundColorHex)) {
  $backgroundColorHexCss = "#" . $backgroundColorHex;
} else {
  $backgroundColorHexCss = $backgroundColorHex;
}

$framePadding = get_param("framePadding", "fp", 0);
if ($framePadding != 0) {
  $framePaddingCss = $framePadding . "px";
} else {
  $framePaddingCss = $framePadding;
}

// end of  undocumented features


$outerFrameWidth = $frameWidth + 2 * $frameBorderWidth + 2 * $framePadding;
$outerFrameHeight = $frameHeight + 2 * $frameBorderWidth + 2 * $framePadding;


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


$expiresMeta = "";
if ($expiresDate) { 
  header("Expires: " . $expiresDate);
  $expiresMeta = "<meta http-equiv=\"Expires\" content=\"" . $expiresDate . "\">";
}

print <<<END
<html>

<head>

<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
$expiresMeta

<title>pgn4web puzzle of the day</title>

<style type="text/css">

html, 
body { 
  margin: 0; 
  padding: $framePadding;
  background: $backgroundColorHexCss;
}

.container {
  width: $frameWidthCss;
  height: $frameHeightCss;
  border-color: $frameBorderColorHexCss;
  border-style: outset;
  border-width: $frameBorderWidthCss;
}

.boardTable {
  width: $boardSizeCss;
  height: $boardSizeCss;
  border-width: 0;
}

.pieceImage {
  width: $pieceSizeCss;
  height: $pieceSizeCss;
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
  border-color: $lightColorHexCss;
  background: $lightColorHexCss;
}

.blackSquare,
.highlightBlackSquare {
  border-color: $darkColorHexCss;
  background: $darkColorHexCss;
}

.highlightWhiteSquare,
.highlightBlackSquare {
  border-style: inset;
}

.buttonTable {
  width: $boardSizeCss;
  height: $buttonHeightCss;
  background-color: $controlBackgroundColorHexCss;
}

.buttonCell {
  width: $buttonWidthCss;
  height: $buttonHeightCss;
  white-space: nowrap;
  overflow: hidden;
  padding: $buttonPaddingCss;
}

.buttonCellLink {
  font-family: sans-serif;
  font-size: $buttonFontSizeCss;
  font-weight: 900;
  color: $controlTextColorHexCss;
  text-decoration: none;
}

.sidetomoveBox {
  width: $sidetomoveWidthCss;
  height: $sidetomoveHeightCss;
  border-style: solid;
  border-width: $sidetomoveBorderCss;
  border-color: $controlTextColorHexCss;
}

</style>

<link rel="shortcut icon" href="pawn.ico" />

<script src="pgn4web.js" type="text/javascript"></script>

<script type="text/javascript">

SetImagePath("$pieceFont/$pieceSize");
SetShortcutKeysEnabled(false);

function setPuzzleHelpShortcutSquares(cols, rows) {
  var puzzleHelp = "pgn4web puzzle of the day" + "\\n\\n";
  puzzleHelp += "- the white or black small square below the chessboard's left side indicates the side to move" + "\\n\\n";
  puzzleHelp += "- show the puzzle's solution step by step on the chessboard by clicking the > button below the chessboard's right side" + "\\n\\n";
  puzzleHelp += "- step backwards one move by clicking the < button below the chessboard's left side" + "\\n\\n";
  puzzleHelp += "click OK to learn how to add the pgn4web puzzle of the day to your website, blog or iGoogle page";
  if ((typeof cols != "string") || (typeof rows != "string")) { return; }
  for (c=0; c<cols.length; c++) { for (r=0; r<rows.length; r++) {
      boardShortcut(cols.charAt(c).toUpperCase()+rows.charAt(r), "puzzle of the day help", function(){ if (confirm(puzzleHelp)) { window.open("http://code.google.com/p/pgn4web/wiki/Example_PuzzleOfTheDay", "_blank"); } });
  } }
}

setPuzzleHelpShortcutSquares("BCDEFGH", "7");
setPuzzleHelpShortcutSquares("ABCDEFGH", "23456");
setPuzzleHelpShortcutSquares("BCFG", "1");

function solutionSoFar() {
  sol = "";
  for (thisPly = StartPly; thisPly < CurrentPly; thisPly++) {
    if (thisPly % 2 == 0) {
      sol += ((thisPly / 2) + 1) + ". ";
    } else if (thisPly == StartPly) {
      sol += ((thisPly + 1) / 2) + "... ";
    }
    sol += Moves[thisPly] + " ";
  }
  return sol;
}

function customFunctionOnMove() {

  if (CurrentPly == StartPly) {
    document.getElementById("leftButtonLink").innerHTML = "<table class='sidetomoveBox' style='background-color:" + (CurrentPly % 2 ? "black" : "white" ) + ";' cellspacing='0' cellpadding='0'><tr><td></td></tr></table>";
    document.getElementById("leftButton").title = ((CurrentPly % 2) ? "Black" : "White") + " to play: find the best move";
  } else {
    document.getElementById("leftButtonLink").innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&lt;&nbsp;&nbsp;&nbsp;&nbsp;";
    document.getElementById("leftButton").title = "click > to step backwards one move";
  }

  if (CurrentPly == StartPly+PlyNumber) {
    switch (res = gameResult[currentGame]) {
      case "1-0": outcome = "white wins"; break;
      case "0-1": outcome = "black wins"; break;
      case "1/2-1/2": outcome = "draw"; break;
      default: outcome = "end"; res = "*"; break;
    }
    document.getElementById("rightButtonLink").innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;" + res + "&nbsp;&nbsp;&nbsp;&nbsp;";
    document.getElementById("rightButton").title = solutionSoFar() + " ..." + outcome;
  } else {
    document.getElementById("rightButtonLink").innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&gt;&nbsp;&nbsp;&nbsp;&nbsp;";
    if (CurrentPly == StartPly) {
      document.getElementById("rightButton").title = "click > to show the puzzle's solution step by step on the chessboard";
    } else {
      document.getElementById("rightButton").title = solutionSoFar() + " ...click > to continue showing the puzzle's solution step by step on the chessboard";
    }
  }

}

function customShortcutKey_Shift_0() {
  alert($gameNumDisplay);
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
- pieceFont=... sets the piece font type, either alpha, merida, uscf, random, hash or default, default: default
- controlBackgroundColorHex=... sets the buttons background color, in hexadecimal format, default: EFF4EC
- controlTextColorHex=... sets the buttons text color, in hexadecimal format, default: 888888
- frameBorderColorHex=... sets the frame border color, in hexadecimal format, or none, default: A4A4A4

}

</textarea></form>
<!-- paste your PGN above and make sure you dont specify an external source with SetPgnUrl() -->

<center>
<div class="container">
<div style="display: inline" id="GameBoard"></div>
<table class="buttonTable" border="0" cellspacing="0" cellpadding="0">
<tr>
<td id="leftButton" title="" class="buttonCell" onClick="javascript:MoveBackward(1);" align="center" valign="middle">
<a id="leftButtonLink" class="buttonCellLink" href="javascript:void(0);" onfocus="blur();"></a>
</td>
<td id="rightButton" title="" class="buttonCell" onClick="javascript:MoveForward(1);" align="center" valign="middle">
<a id="rightButtonLink" class="buttonCellLink" href="javascript:void(0);" onfocus="blur();"></a>
</td>
</tr>
</table>
</div>
</center>

</body>

</html>
END;
?>
