<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2013 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ALL | E_STRICT);


$html = @file_get_contents("engine.html");


function errorExit($errorNum) {
  $html = <<<END
<!DOCTYPE HTML>
<html>
<head>
<title>web app error</title>
</head>
<body style="color: white; background: black; font-family: sans-serif;">
web app error: $errorNum
</body>
</html>
END;
  print $html;
  exit;
}


$actionNum = 0;
if (!$html) { errorExit($actionNum); }


$text = "var thisParamString = (window.location.search || window.location.hash) + '&fpis=96&pf=a&lch=FFCC99&dch=CC9966&bch=000000&hch=996633&fmch=FFEEDD&ctch=FFEEDD&fpr=0.5&els=t';";
$oldText = "var thisParamString = window.location.search || window.location.hash;";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
<meta name="viewport" content="initial-scale=1, maximum-scale=1">
END;
$oldText = "<!-- AppCheck: meta -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = "<title>Game Analysis</title>";
$oldText = "<title>pgn4web analysis board</title>";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
var lastOrientation;
var lastOrientationTimeout = null;
END;
$oldText = "<!-- AppCheck: before myOnOrientationchange -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
   if (typeof(window.orientation) != "undefined") {
      if (window.orientation === lastOrientation) { return; }
      lastOrientation = window.orientation;

      if (lastOrientationTimeout) { backToGames(); }
      else { lastOrientationTimeout = setTimeout("lastOrientationTimeout = null;", 1800); }
   }
END;
$oldText = "<!-- AppCheck: myOnOrientationchange -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
   } else {
      backToGames();
END;
$oldText = "<!-- AppCheck: clickedGameAutoUpdateFlag -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
      } else if (true) {
         theObj.innerHTML = "&crarr;";
         theObj.title = "close analysis board";
END;
$oldText = "<!-- AppCheck: updateGameAutoUpdateFlag -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
if (window.navigator.standalone) {
  window.open = function (winUrl, winTarget, winParam) {
    //
    // patch for iOS7
    //
    // if (winUrl) {
    //   var a = document.createElement("a");
    //   a.setAttribute("href", winUrl);
    //   a.setAttribute("target", winTarget ? winTarget : "_blank");
    //   var e = document.createEvent("HTMLEvents");
    //   e.initEvent("click", true, true);
    //   a.dispatchEvent(e);
    // }
    //
    // end of patch for iOS7
    //
    return null;
  };

  simpleAddEvent(document.body, "touchmove", function(e) { e.preventDefault(); });
}

function pgn4web_handleTouchEnd_body(e) {
  e.stopPropagation();
  var jj, deltaX, deltaY, theObj;
  for (var ii = 0; ii < e.changedTouches.length; ii++) {
    if ((jj = pgn4webOngoingTouchIndexById(e.changedTouches[ii].identifier)) != -1) {
      if (pgn4webOngoingTouches.length == 1) {
        deltaX = e.changedTouches[ii].clientX - pgn4webOngoingTouches[jj].clientX;
        deltaY = e.changedTouches[ii].clientY - pgn4webOngoingTouches[jj].clientY;
        if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) >= 13) {
          if (Math.abs(deltaY) > 1.5 * Math.abs(deltaX)) {
            if (deltaY > 0) { // vertical down
              if (!openerCheck()) { backToGames(); }
            } else { // vertical up
              if (theObj = document.getElementById("GameFlagToMove")) {
                clickedGameFlagToMove(theObj, { "shiftKey": false });
              }
            }
          } else if (Math.abs(deltaX) > 1.5 * Math.abs(deltaY)) {
            if (deltaX > 0) { // horizontal right
              if (theObj = document.getElementById("GameMoves")) {
                clickedGameMoves(theObj, { "shiftKey": false });
              }
            } else { // horizontal left
              if (!openerCheck()) { backToGames(); }
            }
          }
        }
        pgn4webMaxTouches = 0;
      }
      pgn4webOngoingTouches.splice(jj, 1);
    }
  }
  clearSelectedText();
}

simpleAddEvent(document.body, "touchstart", pgn4web_handleTouchStart);
simpleAddEvent(document.body, "touchmove", pgn4web_handleTouchMove);
simpleAddEvent(document.body, "touchend", pgn4web_handleTouchEnd_body);
simpleAddEvent(document.body, "touchleave", pgn4web_handleTouchEnd_body);
simpleAddEvent(document.body, "touchcancel", pgn4web_handleTouchCancel);

function backToGames() {
  //
  // patch for iOS7
  //
  if (window.navigator.standalone) {
    if (localStorage["pgn4web_live_games_app_locationHref"]) {
      window.location.href = localStorage["pgn4web_live_games_app_locationHref"];
    }
    return;
  }
  //
  // end of patch for iOS7
  //
  window.history.back();
}

END;
$oldText = "<!-- AppCheck: footer -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


print $html;


?>
