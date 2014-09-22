<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2014 Paolo Casaschi
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
<title>app error</title>
</head>
<body style="color: white; background: black; font-family: sans-serif;">
app error: $errorNum
</body>
</html>
END;
  print $html;
  exit;
}


$actionNum = 0;
if (!$html) { errorExit($actionNum); }


$text = "var thisParamString = (window.location.search || window.location.hash) + '&els=t&fpis=96&pf=a' + '&lch=FFCC99&dch=CC9966&bch=000000&hch=996633&fmch=FFEEDD&ctch=FFEEDD&fpr=0.5';";
$oldText = "var thisParamString = window.location.search || window.location.hash;";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = "<title>Live Games</title>";
$oldText = "<title>pgn4web analysis board</title>";
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


$text = <<<END
<link rel="icon" sizes="16x16" href="live-games-app-icon-16x16.ico">
<link rel="icon" sizes="128x128" href="live-games-app-icon-128x128.png">
<link rel="apple-touch-icon" href="live-games-app-icon-60x60.png" />
END;
$oldText = '<link rel="icon" sizes="16x16" href="pawn.ico" />';
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
var lastOrientation;
var lastOrientationTimeout = null;
simpleAddEvent(window, "orientationchange", function() {
  if (typeof(window.orientation) == "undefined") { return; }
  if (window.orientation === lastOrientation) { return; }
  var lastOrientationTimeoutString = "lastOrientationTimeout = null;";
  if (lastOrientationTimeout) {
    clearTimeout(lastOrientationTimeout);
    backToGames();
  }
  lastOrientationTimeout = setTimeout(lastOrientationTimeoutString, 1800);
  lastOrientation = window.orientation;
});
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
      } else if (localStorage["pgn4web_live_games_app_locationHref"]) {
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
    if (winUrl) {
      var a = document.createElement("a");
      a.setAttribute("href", winUrl);
      a.setAttribute("target", winTarget ? winTarget : "_blank");
      var e = document.createEvent("HTMLEvents");
      e.initEvent("click", true, true);
      a.dispatchEvent(e);
    }
    return null;
  };

  simpleAddEvent(document.body, "touchmove", function(e) { e.preventDefault(); });
}

var lsId = "pgn4web_live_games_app_";


window['defaultClickedGameEval'] = window['clickedGameEval'];
window['clickedGameEval'] = function(t,e) {
  defaultClickedGameEval(t,e);
  if (!e.shiftKey) {
    localStorage[lsId + "disableEngine"] = disableEngine;
  }
};
if (typeof(localStorage[lsId + "disableEngine"]) == "string") {
  disableEngine = (localStorage[lsId + "disableEngine"] == "true");
}


function clickedGameFlagToMove_forTouchEnd() {
  if (clickedGameFlagToMove_forTouchEnd.theObj = document.getElementById("GameFlagToMove")) {
    clickedGameFlagToMove(clickedGameFlagToMove_forTouchEnd.theObj, { "shiftKey": false });
  }
}

function clickedGameMoves_forTouchEnd() {
  if (clickedGameMoves_forTouchEnd.theObj = document.getElementById("GameMoves")) {
    clickedGameMoves(clickedGameMoves_forTouchEnd.theObj, { "shiftKey": false });
  }
}


function pgn4web_handleTouchEnd_body(e) {
  e.stopPropagation();
  var jj, deltaX, deltaY;
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
              clickedGameFlagToMove_forTouchEnd();
            }
          } else if (Math.abs(deltaX) > 1.5 * Math.abs(deltaY)) {
            if (deltaX > 0) { // horizontal right
              clickedGameMoves_forTouchEnd();
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

if (touchEventEnabled) {
  simpleAddEvent(document.body, "touchstart", pgn4web_handleTouchStart);
  simpleAddEvent(document.body, "touchmove", pgn4web_handleTouchMove);
  simpleAddEvent(document.body, "touchend", pgn4web_handleTouchEnd_body);
  simpleAddEvent(document.body, "touchleave", pgn4web_handleTouchEnd_body);
  simpleAddEvent(document.body, "touchcancel", pgn4web_handleTouchCancel);

  touchGestures_helpActions =  touchGestures_helpActions.concat([ "&nbsp;", "analysis info top-down swipe", "analysis info bottom-up swipe", "analysis info left-right swipe", "analysis info right-left swipe" ]);
  touchGestures_helpText = touchGestures_helpText.concat([ "", "close analysis board and return to game", "switch side to move", "play engine move", "close analysis board and return to game" ]);
}

function backToGames() {
  if (localStorage["pgn4web_live_games_app_locationHref"]) {
    window.location.href = localStorage["pgn4web_live_games_app_locationHref"];
  } else {
    myAlert("warning: missing app location from local storage", false, true);
  }
}

END;
$oldText = "<!-- AppCheck: footer -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


print $html;


?>
